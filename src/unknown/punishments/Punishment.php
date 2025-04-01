<?php

namespace unknown\punishments;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use unknown\Loader;

class Punishment
{
    private static Config $config;
    public function __construct()
    {
        self::$config = new Config(Loader::getInstance()->getDataFolder() . "bans.json", Config::JSON);
    }
    
    public static function addBan(Player $player, string $reason, int $time = 0) : void
    {
        if (self::$config->exists($player->getName())) {
            return;
        }else{
            $banData = [
                "name" => $player->getName(),
                "reason" => $reason,
                "time" => time()
            ];
            self::$config->set($player->getName(), $banData);
            self::$config->save();
            $player->kick("You have been banned for: " . $reason);
        }
    }

    public static function removeBan(Player $player) : void
    {
        if (self::$config->exists($player->getName())) {
            self::$config->remove($player->getName());
            self::$config->save();
        }else{
            return;
        }
    }

    public static function isBanned(Player $player) : bool
    {
        return self::$config->exists($player->getName());
    }

    public static function getBanReason(Player $player) : string
    {
        if (self::$config->exists($player->getName())) {
            return self::$config->get($player->getName())["reason"];
        }else{
            return "No reason";
        }
    }
}