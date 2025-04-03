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

    private array $vios = [];
    private array $lastAttacks = [];
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
            $target = $this->getEntity($player->getWorld(), $targetId);

            if ($target !== null) {
                $distance = $player->getPosition()->distance($target->getPosition());
                $this->check($player, $distance);
            }
        }
    }

    public function run(Player $player, float $reachDistance): void
    {
        $this->check($player, $reachDistance);
    }

    private function check(Player $player, float $reachDistance): void
    {
        $name = $player->getName();
        $ping = $player->getNetworkSession()->getPing();
        $currentTime = microtime(true);

        if (!isset($this->vios[$name])) {
            $this->vios[$name] = 0;
        }

        if (!isset($this->lastAttacks[$name])) {
            $this->lastAttacks[$name] = 0;
        }

        $timeSince = $currentTime - $this->lastAttacks[$name];
        $this->lastAttacks[$name] = $currentTime;

        $maxAlerts = $this->config->getNested("checks.reach.max_reach_alerts", 3.5);
        $maxBan = $this->config->getNested("checks.reach.max_reach_ban", 4.5);
        $pingComp = $this->calculationPing($ping);
        $vioThreshold = $this->config->getNested("checks.reach.violation_threshold", 3);

        $adjReach = $maxAlerts + $pingComp;
        $adjReachBan = $maxBan + $pingComp;

        if ($reachDistance >= $adjReachBan) {
            $this->vios[$name]++;

            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "Reach", "30d");
                $this->reset($name);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "Reach", $reachDistance);
            }
        } elseif ($reachDistance > $adjReach) {
            Loader::getInstance()->getAntiCheatManager()->alert($player, "Reach", $reachDistance);

            if ($timeSince < 0.5) {
                $this->vios[$name] += 0.5;
            }
        } else {
            $this->vios[$name] = max(0, $this->vios[$name] - 0.25);
        }
    }

    private function calculationPing(int $ping): float
    {
        if ($ping < 50) return 0;
        if ($ping < 100) return 0.2;
        if ($ping < 150) return 0.4;
        if ($ping < 200) return 0.6;
        if ($ping < 300) return 0.8;
        return 1.0;
    }

    private function getEntity(World $world, int $runtimeId): ?Entity
    {
        foreach ($world->getEntities() as $entity) {
            if ($entity->getId() === $runtimeId) {
                return $entity;
            }
        }
        return null;
    }

    private function reset(string $name): void
    {
        unset($this->vios[$name]);
        unset($this->lastAttacks[$name]);
    }
}