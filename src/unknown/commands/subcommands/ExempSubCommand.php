<?php

namespace unknown\commands\subcommands;

use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use unknown\Loader;

class ExempSubCommand extends BaseSubCommand {

    public function __construct() {
        parent::__construct("exempt", "Exempt a player from the anticheat alerts", ["ex"]); 
    }

    /**
     * Configure the command structure
     */
    protected function prepare(): void {
        $this->setPermission("anticheat.command.exempt");
    }

    /**
     * Execute the command
     */
    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if(!$sender instanceof Player) {
            $sender->sendMessage("This command can only be used in-game");
            return;
        }

        $manager = Loader::getInstance()->getAntiCheatManager();
        
        if ($manager->isExempt($sender)) {
            $sender->sendMessage("You are already exempt from the anticheat");
        }else {
            $manager->toggleExempt($sender);
            $sender->sendMessage("You are now exempt from the anticheat");
        }
    }

    public function getPermission(): ?string
    {
        return "anticheat.exempt";
    }
}
