<?php

namespace unknown\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use unknown\Loader;
use unknown\punishments\Punishment;

class AutoClick
{
    private array $clicks = [];
    private array $patterns = [];
    private array $vios = [];
    private Config $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function run(ServerboundPacket $packet, Player $player): void
    {
        $name = $player->getName();
        $time = microtime(true);

        if ($this->isClick($packet)) {
            if (!isset($this->clicks[$name])) {
                $this->clicks[$name] = [];
                $this->patterns[$name] = [];
                $this->vios[$name] = 0;
            }

            $this->clicks[$name][] = $time;

            $this->clean($name, $time);
            $this->analyze($name);

            $cps = count($this->clicks[$name]);
            $maxClicks = $this->config->getNested("checks.autoclick.max_autoclick_alerts", 20);
            $maxBan = $this->config->getNested("checks.autoclick.max_autoclick_ban", 25);
            $pingMax = $this->config->getNested("checks.autoclick.ping_threshold", 100);
            $vioMax = $this->config->getNested("checks.autoclick.violation_threshold", 3);

            $ping = $player->getNetworkSession()->getPing();

            if ($ping <= $pingMax) {
                if ($this->isBot($name, $cps, $maxBan)) {
                    $this->vios[$name]++;

                    if ($this->vios[$name] >= $vioMax) {
                        Punishment::ban($player, "AutoClick", "30d");
                        $this->reset($name);
                    } else {
                        Loader::getInstance()->getAntiCheatManager()->alert($player, "AutoClick", $cps);
                    }
                } elseif ($cps > $maxClicks) {
                    Loader::getInstance()->getAntiCheatManager()->alert($player, "AutoClick", $cps);
                }
            }
        }
    }

    private function isClick(ServerboundPacket $packet): bool
    {
        return ($packet instanceof PlayerActionPacket &&
                ($packet->action === 7 ||
                    $packet->action === 8)) ||
            ($packet instanceof InventoryTransactionPacket &&
                $packet->trData instanceof UseItemOnEntityTransactionData) ||
            ($packet instanceof LevelSoundEventPacket &&
                $packet->sound === 42);
    }

    private function clean(string $name, float $time): void
    {
        $this->clicks[$name] = array_filter($this->clicks[$name], function ($stamp) use ($time) {
            return ($time - $stamp) <= 1;
        });
    }

    private function analyze(string $name): void
    {
        if (count($this->clicks[$name]) < 2) {
            return;
        }

        $gaps = [];
        $stamps = $this->clicks[$name];
        sort($stamps);

        for ($i = 1; $i < count($stamps); $i++) {
            $gaps[] = round(($stamps[$i] - $stamps[$i - 1]) * 1000, 2);
        }

        $this->patterns[$name] = $gaps;
    }

    private function isBot(string $name, int $cps, int $maxBan): bool
    {
        if ($cps <= $maxBan) {
            return false;
        }

        if (count($this->patterns[$name]) < 5) {
            return false;
        }

        $gaps = $this->patterns[$name];
        $var = $this->calcVar($gaps);

        return $var < 2.0;
    }

    private function calcVar(array $vals): float
    {
        $count = count($vals);
        if ($count === 0) return 0;

        $mean = array_sum($vals) / $count;
        $var = 0;

        foreach ($vals as $val) {
            $var += pow($val - $mean, 2);
        }

        return $var / $count;
    }

    private function reset(string $name): void
    {
        unset($this->clicks[$name]);
        unset($this->patterns[$name]);
        unset($this->vios[$name]);
    }
}