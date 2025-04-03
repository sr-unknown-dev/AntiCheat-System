<?php

namespace unknown\managers;

use CortexPE\DiscordWebhookAPI\Embed;
use CortexPE\DiscordWebhookAPI\Message;
use CortexPE\DiscordWebhookAPI\Webhook;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as TF;
use unknown\Loader;
use unknown\punishments\Punishment;

class AntiCheatManager
{
    private Config $config;
    private Config $exemptCfg;
    private array $exempt = [];
    private array $alerts = [];
    private array $vioCount = [];
    private array $lastAlert = [];
    private array $alertCooldown = [];

    public function __construct()
    {
        $loader = Loader::getInstance();
        $this->config = $loader->getConfig();
        $this->exemptCfg = new Config($loader->getDataFolder() . "exempt.yml", Config::YAML);
        $this->loadExempt();
    }

    private function loadExempt(): void
    {
        $this->exempt = $this->exemptCfg->get("players", []);
    }

    public function alert(Player $player, string $check, $value): void
    {
        if (!$player->isOnline()) {
            return;
        }
        
        $name = $player->getName();
        $time = microtime(true);
        
        if (isset($this->lastAlert[$name][$check])) {
            $cooldown = $this->config->getNested("alerts.cooldown." . strtolower($check), 1.0);
            if ($time - $this->lastAlert[$name][$check] < $cooldown) {
                return;
            }
        }
        
        $this->lastAlert[$name][$check] = $time;
        
        if (!isset($this->vioCount[$name])) {
            $this->vioCount[$name] = 1;
        } else {
            $this->vioCount[$name]++;
        }

        $vioCount = $this->vioCount[$name];
        
        $valueStr = is_float($value) ? number_format($value, 2) : $value;
        
        $msg = TF::colorize("§8[§cAntiCheat§8]: §7Player: " . $name . " §7type: §f" .
            $check ." §7value: §f" . $valueStr . " §7vios: §7(x" . $vioCount . ")");

        foreach (Server::getInstance()->getOnlinePlayers() as $staff) {
            if ($this->hasAlerts($staff)) {
                $staff->sendMessage($msg);
            }
        }

        $this->sendWebhook($check, $player, $value, $this->vioCount[$name]);
    }

    private function sendWebhook(string $check, Player $player, $value, int $vioCount): void
    {
        $url = $this->config->getNested("discord.webhook");
        if (empty($url)) {
            return;
        }
        
        try {
            $hook = new Webhook($url);
            $msg = new Message();
            $embed = new Embed();

            $embed->setTitle($check . " Alert");
            $embed->setColor(0xf9ff1a);
            
            $valueStr = is_float($value) ? number_format($value, 2) : $value;
            
            $embed->setDescription(
                "Player: " . $player->getName() .
                "\nPing: " . $player->getNetworkSession()->getPing() . "ms" .
                "\nValue: " . $valueStr . 
                "\nViolations: " . $vioCount
            );
            $embed->setFooter("Server Network");

            $msg->addEmbed($embed);
            $hook->send($msg);
        } catch (\Exception $e) {
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
        return isset($this->alerts[$player->getName()]) && $player->hasPermission("anticheat.alerts") || Server::getInstance()->isOp($player->getName());
    }

    public function toggleExempt(Player $player): void
    {
        $name = $player->getName();

        if ($this->isExempt($player)) {
            unset($this->exempt[$name]);
            $player->sendMessage(TF::colorize("§8[§gAntiCheat§8] §fLa exepción ha sido removida."));
        } else {
            $this->exempt[$name] = true;
            $player->sendMessage(TF::colorize("§8[§gAntiCheat§8] §fLa exepcion ha sido añadida."));
        }

        $this->exemptCfg->set("players", array_keys($this->exempt));
        $this->exemptCfg->save();
    }

    public function isExempt(Player $player): bool
    {
        return isset($this->exempt[$player->getName()]);
    }
    
    public function resetVios(string $name): void
    {
        unset($this->vioCount[$name]);
        unset($this->lastAlert[$name]);
    }

    public function punish(Player $player, string $check): void
    {
        if (!$player->isOnline()) {
            return;
        }

        $checkLower = strtolower($check);
        $banReasons = ["speed", "autoclick", "reach", "fly", "killaura", "aimbot"];

        if (in_array($checkLower, $banReasons, true)) {
            Punishment::ban($player, ucfirst($checkLower), "30d");
        }
    }
}