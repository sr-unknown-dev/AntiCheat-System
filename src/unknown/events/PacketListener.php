<?php

namespace unknown\events;

use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use unknown\Loader;

class PacketListener implements Listener
{

    private Loader $loader;

    public function __construct(Loader $loader)
    {
        $this->loader = $loader;
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void {
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();
        
        if ($player === null) return;
        
        if ($packet instanceof ServerboundPacket) {
            $this->loader->getAutoClickCheck()->run($packet, $player);
            $this->loader->getSpeedCheck()->run($packet, $player);
            $this->loader->getFlyCheck()->run($packet, $player);
            
            if ($packet instanceof InventoryTransactionPacket && 
                $packet->trData instanceof UseItemOnEntityTransactionData) {
                $this->loader->getReachCheck()->handle($packet, $player);
                $this->loader->getKillAuraCheck()->handle($packet, $player);
                $this->loader->getAimbotCheck()->handle($packet, $player);
            }
        }
    }
}
