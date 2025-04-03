<?php

namespace unknown\commands;

use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use CortexPE\Commando\BaseCommand;
use unknown\Loader;
use unknown\commands\subcommands\AlertsSubCommand;
use unknown\commands\subcommands\ExempSubCommand;
use unknown\commands\subcommands\UnBanSubCommand;

class AntiCheatCommand extends BaseCommand {

    public function __construct(Loader $load) {
        parent::__construct($load, "ac", "AntiCheat system commands");
    }

    protected function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerSubCommand(new ExempSubCommand());
        $this->registerSubCommand(new AlertsSubCommand());
        $this->registerSubCommand(new UnBanSubCommand());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if(!$sender instanceof Player) {
            $sender->sendMessage("§cThis command can only be used in-game!");
            return;
        }

        $sender->sendMessage("§e=== AntiCheat Commands ===");
        $sender->sendMessage("§7/ac exemp <player> §f- Exempt a player");
        $sender->sendMessage("§7/ac alerts §f- View player alerts");
        $sender->sendMessage("§7/ac unban <player> §f- Unban a player");
        $sender->sendMessage("§e=========================");
    }

    public function getPermission(): ?string
    {
        return "anticheat.cmds";
    }
}
