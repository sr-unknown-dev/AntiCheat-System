<?php

namespace unknown\events;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\player\Player;
use pocketmine\Server;
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

        if (Punishment::isBanned($name)) {
            $reason = Punishment::getReason($name);
            $timeLeft = Punishment::getTimeLeft($name);
            $discordLink = Loader::getInstance()->getConfig()->getNested("discord.link", "");

            $player->kick(TextFormat::colorize(
                "&7Estás baneado\n" .
                    "Razón: &6{$reason}\n" .
                    "&7Expira en: {$timeLeft}\n" .
                    "&7Si deseas apelar el ban: &6{$discordLink}"
            ));
        }

        if ($player->hasPermission("anticheat.alerts") || Server::getInstance()->isOp($player->getName())) {
            Loader::getInstance()->getAntiCheatManager()->toggleAlerts($player);
        }
    }

    public function onJoin(PlayerLoginEvent $event): void
    {
        $player = $event->getPlayer();
        if (!$player instanceof Player) return;

        if ($player->hasPermission("anticheat.alerts") || Server::getInstance()->isOp($player->getName())) {
            Loader::getInstance()->getAntiCheatManager()->toggleAlerts($player);
        }
    }
}
