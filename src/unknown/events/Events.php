<?php

namespace unknown\events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use unknown\Loader;
use unknown\punishments\Punishment;

class Events implements Listener
{
    public function onLogin(PlayerLoginEvent $event): void
    {
        $player = $event->getPlayer();
        if (!$player instanceof Player) return;

        $name = $player->getName();
        $bans = Punishment::$config->getAll();
        $currentTime = time();

        if (isset($bans[$name]) && ($bans[$name]['expires'] > $currentTime || $bans[$name]['expires'] == 0)) {
            $banData = $bans[$name];
            $player->kick(TextFormat::colorize(
                "&7Estás baneado\n" .
                "Razón: &6{$banData['reason']}\n" .
                "&7Expira en: " . Punishment::formatTime($banData['expires'] - $currentTime) . "\n" .
                "&7Si deseas apelar el ban: &6" . Loader::getInstance()->getConfig()->get("discord-link")
            ));
        }
    }
}