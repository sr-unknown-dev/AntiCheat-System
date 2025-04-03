<?php

namespace unknown\commands\subcommands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use unknown\AntiCheat;
use unknown\Loader;

class AlertsSubCommand extends BaseSubCommand
{

    public function __construct()
    {
        parent::__construct("alerts", "Toggle anticheat alerts", ["al"]);
    }

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("on|off", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cThis command can only be used in-game!");
            return;
        }

        $playerName = $sender->getName();
        $manager = Loader::getInstance()->getAntiCheatManager();
        $selection = $args["on|off"];

        if ($sender instanceof Player) {
            switch ($selection) {
                case 'on':
                    if (!$manager->hasAlerts($sender)) {
                        $manager->toggleAlerts($sender);
                        $sender->sendMessage("§cYou have disabled anticheat alerts!");
                    } else {
                        $sender->sendMessage("§cYou have already enabled anticheat alerts!");
                    }
                    break;

                case 'off':
                    if ($manager->hasAlerts($sender)) {
                        $manager->toggleAlerts($sender);
                        $sender->sendMessage("§cYou have disabled anticheat alerts!");
                    } else {
                        $sender->sendMessage("§cYou have already disabled anticheat alerts!");
                    }
                    break;

                default:
                    $sender->sendMessage("§cUsage: /ac alerts <on|off>");
                    break;
            }
        }
    }

    public function getPermission(): ?string
    {
        return "anticheat.alerts";
    }
}
