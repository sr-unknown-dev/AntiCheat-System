<?php

namespace unknown\commands\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use unknown\Loader;
use unknown\punishments\Punishment;

class UnBanSubCommand extends BaseSubCommand {

    public function __construct() {
        parent::__construct("unban", "Unban a player from the anti-cheat system", ["unb"]);

    }

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if(!$sender instanceof Player) return;

        if(!isset($args["player"])) {
            $sender->sendMessage("§cUsage: /ac unban <player>");
            return;
        }

        $playerName = $args["player"];
        
        if(!Punishment::isBanned($playerName)) {
            $sender->sendMessage("§cPlayer ". $playerName . " is not banned");
            return;
        }
        Punishment::unban($playerName);
        $sender->sendMessage("§aSuccessfully unbanned player " . $playerName);
    }

    public function getPermission(): ?string
    {
        return "anticheat.unban";
    }
}
