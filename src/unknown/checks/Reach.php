<?php

namespace unknown\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use unknown\Loader;

class Reach{

    private array $clicks = [];
    private $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function run(Player $p, float $distanciaAlcance) : void {
        $ping = $p->getNetworkSession()->getPing();
        $nombre = $p->getName();
        $checks = $this->config->get("checks");

        if ($distanciaAlcance >= $checks["reach"]["max_reach_ban"]) {
            Loader::getInstance()->getStaffModeManager()->addBanAntiCheat($p, "Reach", "30d");
        } elseif ($distanciaAlcance > $checks["reach"]["max_reach_alerts"]) {
            Loader::getInstance()->getAntiCheatManager()->AlertStaff($p, "Reach" ,$distanciaAlcance);
        }
    }
}