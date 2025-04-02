<?php

namespace unknown;

use hcf\module\anticheat\AntiCheatManager;
use hcf\module\anticheat\checks\AutoClick;
use hcf\module\anticheat\checks\Speed;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use unknown\checks\Aimbot;
use unknown\checks\Fly;
use unknown\checks\KillAura;
use unknown\checks\Reach;
use unknown\events\PacketListener;

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
        $this->antiCheatManager = new AntiCheatManager();
        $this->autoClickCheck = new AutoClick();
        $this->reachCheck = new Reach();
        $this->speedCheck = new Speed();
        $this->killAuraCheck = new KillAura();
        $this->flyCheck = new Fly();
        $this->aimbotCheck = new Aimbot();
        $this->getServer()->getPluginManager()->registerEvents(new PacketListener($this), $this);
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
}
