<?php

namespace hcf\module\anticheat\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use unknown\Loader;

class AutoClick {

    private array $clickTimestamps = [];
    private $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function run(ServerboundPacket $packet, Player $player): void {
        if ($packet instanceof PlayerActionPacket) {
            $name = $player->getName();
            $currentTime = microtime(true);

            if (!isset($this->clickTimestamps[$name])) {
                $this->clickTimestamps[$name] = [];
            }

            $this->clickTimestamps[$name][] = $currentTime;

            $this->clickTimestamps[$name] = array_filter($this->clickTimestamps[$name], function($timestamp) use ($currentTime) {
                return ($currentTime - $timestamp) <= 1;
            });

            $clicksPerSecond = count($this->clickTimestamps[$name]);
            $maxClicks = $this->config->get("checks")["autoclick"]["max_autoclick_alerts"];

            if ($clicksPerSecond > $maxClicks) {
                $this->alertStaff($player, $clicksPerSecond);
            }
        }
    }

    private function alertStaff(Player $player, int $clicksPerSecond): void {
        $message = "El jugador " . $player->getName() . " está haciendo " . $clicksPerSecond . " clics por segundo.";
        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $onlinePlayer) {
            if ($onlinePlayer->hasPermission("anticheat.alerts")) {
                $onlinePlayer->sendMessage($message);
            }
        }
    }
}