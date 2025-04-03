<?php

namespace unknown\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use unknown\Loader;
use unknown\punishments\Punishment;

class Speed
{

    private array $lastPositions = [];
    private array $lastMoveTime = [];
    private array $violations = [];
    private Config $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function run(ServerboundPacket $packet, Player $player): void
    {

        $isMovementPacket = ($packet instanceof MovePlayerPacket || $packet instanceof PlayerAuthInputPacket);

        if ($isMovementPacket) {
            $name = $player->getName();
            $currentPosition = $player->getPosition();
            $currentTime = microtime(true);

            if (!isset($this->lastPositions[$name])) {
                $this->lastPositions[$name] = $currentPosition;
                $this->lastMoveTime[$name] = $currentTime;
                $this->violations[$name] = 0;
                return;
            }

            $lastPosition = $this->lastPositions[$name];
            $lastTime = $this->lastMoveTime[$name];
            $timeDelta = $currentTime - $lastTime;

            if ($timeDelta < 0.001) {
                return;
            }

            $horizontalDistance = sqrt(
                pow($currentPosition->x - $lastPosition->x, 2) +
                pow($currentPosition->z - $lastPosition->z, 2)
            );

            $verticalDistance = $currentPosition->y - $lastPosition->y;
            $speed = $horizontalDistance / $timeDelta;

            $this->lastPositions[$name] = $currentPosition;
            $this->lastMoveTime[$name] = $currentTime;

            $maxSpeedBase = $this->config->getNested("checks.speed.max_speed", 10);
            $maxSpeedBanBase = $this->config->getNested("checks.speed.max_speed_ban", 15);
            $pingThreshold = $this->config->getNested("checks.speed.ping_threshold", 100);
            $violationThreshold = $this->config->getNested("checks.speed.violation_threshold", 3);

            $speedMultiplier = $this->getSpeedMultiplier($player);
            $maxSpeed = $maxSpeedBase * $speedMultiplier;
            $maxSpeedBan = $maxSpeedBanBase * $speedMultiplier;

            $ping = $player->getNetworkSession()->getPing();

            if ($ping <= $pingThreshold && !$this->isLegitimateMovement($player, $horizontalDistance, $verticalDistance)) {
                if ($speed > $maxSpeedBan) {
                    $this->violations[$name]++;

                    if ($this->violations[$name] >= $violationThreshold) {
                        Punishment::ban($player, "SpeedHack", "30d");
                        $this->resetPlayerData($name);
                    } else {
                        Loader::getInstance()->getAntiCheatManager()->alert($player, "Speed", $speed);
                    }
                } elseif ($speed > $maxSpeed) {
                    Loader::getInstance()->getAntiCheatManager()->alert($player, "Speed", $speed);
                    $this->violations[$name] += 0.5;
                } else {
                    $this->violations[$name] = max(0, $this->violations[$name] - 0.25); // Decrease violations over time
                }
            }
        }
    }

    private function getSpeedMultiplier(Player $player): float
    {
        $multiplier = 1.0;

        $effect = $player->getEffects()->get(VanillaEffects::SPEED());
        if ($effect instanceof EffectInstance) {
            $multiplier += 0.2 * ($effect->getAmplifier() + 1);
        }

        if ($player->isSprinting()) {
            $multiplier += 0.3;
        }

        if ($this->isPlayerInWater($player)) {
            $multiplier -= 0.4;
        }
        if ($this->isPlayerOnIce($player)) {
            $multiplier += 0.5;
        }

        return max(0.1, $multiplier);
    }

    private function isPlayerInWater(Player $player): bool
    {
        $block = $player->getWorld()->getBlock($player->getPosition());
        return $block->getName() === "Water";
    }

    private function isPlayerOnIce(Player $player): bool
    {
        $position = $player->getPosition()->subtract(0, 0.2, 0);
        $block = $player->getWorld()->getBlock($position);
        return $block->getName() === "Ice" || $block->getName() === "Packed Ice" || $block->getName() === "Blue Ice";
    }

    private function isLegitimateMovement(Player $player, float $horizontalDistance, float $verticalDistance): bool
    {
        if ($player->isFlying()) {
            return true;
        }

        if ($player->isGliding()) {
            return true;
        }

        if ($horizontalDistance > 50) {
            return true;
        }

        if ($horizontalDistance > 20 && $this->violations[$player->getName()] < 1) {
            return true;
        }

        return false;
    }


    private function resetPlayerData(string $name): void
    {
        unset($this->lastPositions[$name]);
        unset($this->lastMoveTime[$name]);
        unset($this->violations[$name]);
    }
}