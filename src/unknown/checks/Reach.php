<?php

namespace unknown\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\entity\Entity;
use pocketmine\world\World;
use unknown\Loader;
use unknown\punishments\Punishment;

class Reach
{
    private array $violationCounts = [];
    private array $lastAttackTimes = [];
    private Config $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function handle(ServerboundPacket $packet, Player $player): void
    {
        if ($packet instanceof InventoryTransactionPacket &&
            $packet->trData instanceof UseItemOnEntityTransactionData) {
            $targetId = $packet->trData->getActorRuntimeId();
            $target = $this->getEntityByRuntimeId($player->getWorld(), $targetId);

            if ($target !== null) {
                $distance = $player->getPosition()->distance($target->getPosition());
                $this->evaluateReach($player, $distance);
            }
        }
    }

    public function run(Player $player, float $reachDistance): void
    {
        $this->evaluateReach($player, $reachDistance);
    }

    private function evaluateReach(Player $player, float $reachDistance): void
    {
        $playerName = $player->getName();
        $pingCompensation = $this->calculatePingCompensation($player->getNetworkSession()->getPing());
        $currentTime = microtime(true);

        $maxAlertDistance = $this->config->getNested("checks.reach.max_reach_alerts", 3.5) + $pingCompensation;
        $maxBanDistance = $this->config->getNested("checks.reach.max_reach_ban", 4.5) + $pingCompensation;
        $violationThreshold = $this->config->getNested("checks.reach.violation_threshold", 3);

        $this->violationCounts[$playerName] = $this->violationCounts[$playerName] ?? 0;
        $timeSinceLastAttack = $this->getTimeSinceLastAttack($playerName, $currentTime);

        if ($reachDistance >= $maxBanDistance) {
            $this->violationCounts[$playerName]++;
            if ($this->violationCounts[$playerName] >= $violationThreshold) {
                Punishment::ban($player, "Reach", "30d");
                $this->resetViolations($playerName);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "Reach", $reachDistance);
            }
        } elseif ($reachDistance > $maxAlertDistance) {
            Loader::getInstance()->getAntiCheatManager()->alert($player, "Reach", $reachDistance);
            if ($timeSinceLastAttack < 0.5) {
                $this->violationCounts[$playerName] += 0.5;
            }
        } else {
            $this->violationCounts[$playerName] = max(0, $this->violationCounts[$playerName] - 0.25);
        }
    }

    private function calculatePingCompensation(int $ping): float
    {
        return match (true) {
            $ping < 50 => 0.0,
            $ping < 100 => 0.2,
            $ping < 150 => 0.4,
            $ping < 200 => 0.6,
            $ping < 300 => 0.8,
            default => 1.0,
        };
    }

    private function getEntityByRuntimeId(World $world, int $runtimeId): ?Entity
    {
        return $world->getEntity($runtimeId);
    }

    private function getTimeSinceLastAttack(string $playerName, float $currentTime): float
    {
        $timeSinceLastAttack = $currentTime - ($this->lastAttackTimes[$playerName] ?? 0);
        $this->lastAttackTimes[$playerName] = $currentTime;
        return $timeSinceLastAttack;
    }

    private function resetViolations(string $playerName): void
    {
        unset($this->violationCounts[$playerName], $this->lastAttackTimes[$playerName]);
    }
}