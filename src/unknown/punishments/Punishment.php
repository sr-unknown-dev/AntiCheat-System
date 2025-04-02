<?php

namespace unknown\punishments;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use unknown\Loader;
use pocketmine\utils\TextFormat;

class Punishment
{
    public static Config $config;
    private static array $timeUnits = ['m' => 60, 'h' => 3600, 'd' => 86400, 'w' => 604800];
    private static array $formatUnits = [86400 => 'd', 3600 => 'h', 60 => 'm', 1 => 's'];

    public function __construct()
    {
        self::$config = new Config(Loader::getInstance()->getDataFolder() . "bans.json", Config::JSON);
    }

    public static function ban(Player $player, string $reason, string $duration = "15d"): void
    {
        if (self::$config->exists($player->getName())) {
            return;
        }

        $secs = self::parseTime($duration);
        $expires = time() + $secs;

        $data = [
            "name" => $player->getName(),
            "reason" => $reason,
            "time" => time(),
            "expires" => $expires
        ];

        self::$config->set($player->getName(), $data);
        self::$config->save();

        $player->kick(TextFormat::colorize(
            "&7Has sido BANEADO\n" .
            "Razón: &6{$reason}\n" .
            "&7Expira en: " . self::formatTime($secs) . "\n" .
            "&7Si deseas apelar el ban: &6" . Loader::getInstance()->getConfig()->get("discord-link")
        ));
    }

    public static function unban(string $name): void
    {
        if (self::$config->exists($name)) {
            self::$config->remove($name);
            self::$config->save();
        }
    }

    public static function isBanned(string $name): bool
    {
        if (!self::$config->exists($name)) {
            return false;
        }
        
        $ban = self::$config->get($name);
        if ($ban['expires'] > 0 && $ban['expires'] <= time()) {
            self::unban($name);
            return false;
        }
        
        return true;
    }

    public static function getReason(string $name): string
    {
        if (self::$config->exists($name)) {
            return self::$config->get($name)["reason"];
        }
        return "No reason";
    }

    public static function getExpiry(string $name): int
    {
        if (self::$config->exists($name)) {
            return self::$config->get($name)["expires"];
        }
        return 0;
    }
    
    public static function getTimeLeft(string $name): string
    {
        if (!self::$config->exists($name)) {
            return "Not banned";
        }
        
        $ban = self::$config->get($name);
        $timeLeft = $ban['expires'] - time();
        
        if ($timeLeft <= 0) {
            self::unban($name);
            return "Expired";
        }
        
        return self::formatTime($timeLeft);
    }

    public static function checkExpiry(): void
    {
        $now = time();
        $bans = self::$config->getAll();
        $changed = false;

        foreach ($bans as $name => $ban) {
            if ($ban['expires'] > 0 && $ban['expires'] <= $now) {
                unset($bans[$name]);
                $changed = true;
            }
        }

        if ($changed) {
            self::$config->setAll($bans);
            self::$config->save();
        }
    }

    public static function parseTime(string $duration): int
    {
        if (!preg_match_all('/(\d+)([mhwd])/', $duration, $matches, PREG_SET_ORDER)) {
            return 86400;
        }

        return array_reduce($matches, function ($total, $match) {
            $unit = $match[2];
            $value = (int)$match[1];
            return $total + ($value * self::$timeUnits[$unit]);
        }, 0);
    }

    public static function formatTime(int $secs): string
    {
        if ($secs <= 0) return "0s";
        
        $result = [];
        foreach (self::$formatUnits as $unit => $symbol) {
            if ($count = floor($secs / $unit)) {
                $result[] = $count . $symbol;
                $secs %= $unit;
            }
        }
        return implode(' ', $result);
    }
}