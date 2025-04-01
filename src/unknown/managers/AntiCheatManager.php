<?php

namespace hcf\module\anticheat;

use CortexPE\DiscordWebhookAPI\Embed;
use CortexPE\DiscordWebhookAPI\Message;
use CortexPE\DiscordWebhookAPI\Webhook;
use pocketmine\player\Player;
use pocketmine\player\PlayerInfo;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use unknown\Loader;

class AntiCheatManager
{
    private Config $config;
    private Config $exemptConfig;
    private array $exemptPlayers = [];
    private array $alerts = [];

    public function __construct()
    {
        $loader = Loader::getInstance();
        $this->config = $loader->getConfig();
        $this->exemptConfig = new Config($loader->getDataFolder() . "exempt.yml", Config::YAML);
        $this->loadExemptPlayers();
    }

    private function loadExemptPlayers(): void
    {
        $this->exemptPlayers = $this->exemptConfig->get("players", []);
    }

    public function alertStaff(Player $player, string $checkName, int $violations): void
    {
        if (!$player->isOnline()) {
            return;
        }

        $message = TF::colorize("§8[§cAntiCheat§8] §f" . $player->getName() . " §7ha fallado §f" .
            $checkName . " §7(x" . $violations . ")");

        $onlinePlayers = Server::getInstance()->getOnlinePlayers();
        foreach ($onlinePlayers as $staff) {
            if ($staff->hasPermission("anticheat.alerts") && $this->hasAlerts($staff)) {
                $staff->sendMessage($message);
            }
        }

        $webhookUrl = $this->config->getNested("alerts.webhook");
        if (!empty($webhookUrl)) {
            try {
                $webhook = new Webhook($webhookUrl);
                $msg = new Message();
                $embed = new Embed();

                $embed->setTitle($checkName . " Alert");
                $embed->setColor(0xf9ff1a);
                $embed->setDescription(
                    "Player: " . $player->getName() .
                    "\nPing: " . $player->getNetworkSession()->getPing() .
                    "\nViolations: " . $violations
                );
                $embed->setFooter("Server Network");

                $msg->addEmbed($embed);
                $webhook->send($msg);
            } catch (\Exception $e) {
                Server::getInstance()->getLogger()->error("Failed to send webhook: " . $e->getMessage());
            }
        }
    }

    public function toggleAlerts(Player $player): void
    {
        $name = $player->getName();

        if ($this->hasAlerts($player)) {
            unset($this->alerts[$name]);
            $player->sendMessage(TF::colorize("§8[§gAntiCheat§8] §fLas alertas han sido desactivadas."));
        } else {
            $this->alerts[$name] = true;
            $player->sendMessage(TF::colorize("§8[§gAntiCheat§8] §fLas alertas han sido activadas."));
        }
    }

    public function hasAlerts(Player $player): bool
    {
        return isset($this->alerts[$player->getName()]) && $player->hasPermission("anticheat.alerts");
    }

    public function toggleExemption(Player $player): void
    {
        $name = $player->getName();

        if ($this->hasExemption($player)) {
            unset($this->exemptPlayers[$name]);
            $player->sendMessage(TF::colorize("§8[§gAntiCheat§8] §fLa exención ha sido removida."));
        } else {
            $this->exemptPlayers[$name] = true;
            $player->sendMessage(TF::colorize("§8[§gAntiCheat§8] §fLa exención ha sido añadida."));
        }

        $this->exemptConfig->set("players", array_keys($this->exemptPlayers));
        $this->exemptConfig->save();
    }

    public function hasExemption(Player $player): bool
    {
        return isset($this->exemptPlayers[$player->getName()]);
    }

    public function punishments(Player $player, string $checkName): void
    {
        if (!$player->isOnline()) {
            return;
        }

        $staffMode = Loader::getInstance()->getStaffModeManager();
        if ($staffMode === null) {
            return;
        }

        $checkNameLower = strtolower($checkName);
        $banReasons = ["speed", "autoclick", "reach"];

        if (in_array($checkNameLower, $banReasons, true)) {
            $staffMode->addBanAntiCheat($player, ucfirst($checkNameLower), "30d");
        }
    }
}