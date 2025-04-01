<?php

namespace hcf\module\anticheat\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use unknown\Loader;

class Speed {

    private array $lastPositions = [];
    private $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function run(ServerboundPacket $packet, Player $player): void {
        if ($packet instanceof MovePlayerPacket) {
            $name = $player->getName();
            $currentPosition = $player->getPosition();

            if (!isset($this->lastPositions[$name])) {
                $this->lastPositions[$name] = $currentPosition;
                return;
            }

            $lastPosition = $this->lastPositions[$name];
            $distance = $currentPosition->distance($lastPosition);
            $this->lastPositions[$name] = $currentPosition;

            $maxSpeed = $this->config->get("checks")["speed"]["max_speed"];
            if ($distance > $maxSpeed && $player->getNetworkSession()->getPing() <= 100) {
                $this->alertStaff($player, $distance);
            }
        }
    }

    private function alertStaff(Player $player, float $distance): void {
        $message = "El jugador " . $player->getName() . " se está moviendo a una velocidad de " . $distance . " bloques por segundo.";
        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $onlinePlayer) {
            if ($onlinePlayer->hasPermission("anticheat.alerts")) {
                $onlinePlayer->sendMessage($message);
            }
        }
    }
}