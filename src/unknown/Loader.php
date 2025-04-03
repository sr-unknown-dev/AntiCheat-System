<?php

namespace unknown;

use unknown\checks\AutoClick;
use unknown\checks\Speed;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use unknown\checks\Aimbot;
use unknown\checks\Fly;
use unknown\checks\KillAura;
use unknown\checks\Reach;
use unknown\commands\AntiCheatCommand;
use unknown\events\Events;
use unknown\events\PacketListener;
use unknown\managers\AntiCheatManager;
use unknown\punishments\Punishment;
use unknown\tasks\BanTask;

class Loader extends PluginBase
{
    use SingletonTrait;

    public AntiCheatManager $antiCheatManager;
    private AutoClick $autoClickCheck;
    private Reach $reachCheck;
    private Speed $speedCheck;
    private KillAura $killAuraCheck;
    private Fly $flyCheck;
    private Aimbot $aimbotCheck;


    protected function onLoad(): void
    {
        self::setInstance($this);
    }

    protected function onEnable(): void
    {
        $this->saveDefaultConfig();
        
        new Punishment();
        
        $this->antiCheatManager = new AntiCheatManager();
        $this->autoClickCheck = new AutoClick();
        $this->reachCheck = new Reach();
        $this->speedCheck = new Speed();
        $this->killAuraCheck = new KillAura();
        $this->flyCheck = new Fly();
        $this->aimbotCheck = new Aimbot();
        
        $this->getServer()->getCommandMap()->register("anticheat", new AntiCheatCommand());
        $this->getServer()->getPluginManager()->registerEvents(new PacketListener($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new Events($this), $this);
        $this->getScheduler()->scheduleRepeatingTask(new BanTask(), 1200);
    }

    public function getAntiCheatManager(): AntiCheatManager
    {
        return $this->antiCheatManager;
    }

    public function getAutoClickCheck(): AutoClick
    {
        return $this->autoClickCheck;
    }

    public function getReachCheck(): Reach
    {
        return $this->reachCheck;
    }

    public function getSpeedCheck(): Speed
    {
        return $this->speedCheck;
    }

    public function getKillAuraCheck(): KillAura
    {
        return $this->killAuraCheck;
    }

    public function getFlyCheck(): Fly
    {
        return $this->flyCheck;
    }

    public function getAimbotCheck(): Aimbot
    {
        return $this->aimbotCheck;
    }

    public function debug(string $message, int $level = 1): void
    {
        if (!$this->getConfig()->getNested("debug.enabled", false)) {
            return;
        }
        
        $configLevel = $this->getConfig()->getNested("debug.log_level", 1);
        
        if ($level <= $configLevel) {
            $this->getLogger()->debug("[AntiCheat] " . $message);
        }
    }
}
