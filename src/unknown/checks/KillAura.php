<?php

namespace unknown\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\math\Vector3;
use unknown\Loader;
use unknown\punishments\Punishment;

class KillAura {

    private array $attacks = [];
    private array $targets = [];
    private array $rotations = [];
    private array $vios = [];
    private array $exempt = [];
    private Config $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function handle(ServerboundPacket $packet, Player $player): void
    {
        if ($this->isExempt($player)) {
            return;
        }
        
        $name = $player->getName();
        
        if ($packet instanceof InventoryTransactionPacket && 
            $packet->trData instanceof UseItemOnEntityTransactionData) {
            
            $time = microtime(true);
            $targetId = $packet->trData->getActorRuntimeId();
            
            if (!isset($this->attacks[$name])) {
                $this->attacks[$name] = [];
                $this->targets[$name] = [];
                $this->rotations[$name] = [];
                $this->vios[$name] = 0;
            }
            
            $this->attacks[$name][] = $time;
            
            $this->attacks[$name] = array_filter($this->attacks[$name], function($t) use ($time) {
                return ($time - $t) <= 2;
            });
            
            $this->targets[$name][$targetId] = $time;
            
            $this->rotations[$name][] = [
                'yaw' => $player->getLocation()->getYaw(),
                'pitch' => $player->getLocation()->getPitch(),
                'time' => $time
            ];
            
            $this->rotations[$name] = array_filter($this->rotations[$name], function($r) use ($time) {
                return ($time - $r['time']) <= 2;
            });
            
            $this->checkMultiAura($player);
            
            $this->checkRotations($player);
        }
    }
    
    private function checkMultiAura(Player $player): void
    {
        $name = $player->getName();
        $time = microtime(true);
        
        foreach ($this->targets[$name] as $id => $t) {
            if ($time - $t > 1) {
                unset($this->targets[$name][$id]);
            }
        }
        
        $targetCount = count($this->targets[$name]);
        $maxTargets = $this->config->getNested("checks.killaura.max_targets", 3);
        $vioThreshold = $this->config->getNested("checks.killaura.violation_threshold", 3);
        
        if ($targetCount >= $maxTargets) {
            $this->vios[$name]++;
            
            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "KillAura", "30d");
                $this->reset($name);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "KillAura", $targetCount);
            }
        }
    }
    
    private function checkRotations(Player $player): void
    {
        $name = $player->getName();
        
        if (count($this->rotations[$name]) < 3) {
            return;
        }
        
        $rotations = $this->rotations[$name];
        $changes = [];
        
        for ($i = 1; $i < count($rotations); $i++) {
            $prev = $rotations[$i-1];
            $curr = $rotations[$i];
            
            $yawChange = abs($curr['yaw'] - $prev['yaw']);
            if ($yawChange > 180) $yawChange = 360 - $yawChange;
            
            $pitchChange = abs($curr['pitch'] - $prev['pitch']);
            $timeChange = $curr['time'] - $prev['time'];
            
            if ($timeChange > 0) {
                $changes[] = [
                    'yaw' => $yawChange,
                    'pitch' => $pitchChange,
                    'time' => $timeChange,
                    'speed' => $yawChange / $timeChange
                ];
            }
        }
        
        if (count($changes) < 2) {
            return;
        }
        
        $maxSpeed = $this->config->getNested("checks.killaura.max_rotation_speed", 40);
        $vioThreshold = $this->config->getNested("checks.killaura.violation_threshold", 3);
        
        $impossibleCount = 0;
        $maxDetectedSpeed = 0;
        
        foreach ($changes as $change) {
            if ($change['speed'] > $maxSpeed) {
                $impossibleCount++;
                $maxDetectedSpeed = max($maxDetectedSpeed, $change['speed']);
            }
        }
        
        if ($impossibleCount >= 2) {
            $this->vios[$name]++;
            
            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "KillAura", "30d");
                $this->reset($name);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "KillAura", round($maxDetectedSpeed, 2));
            }
        }
    }
    
    public function exempt(Player $player, int $secs = 30): void
    {
        $this->exempt[$player->getName()] = time() + $secs;
    }
    
    private function isExempt(Player $player): bool
    {
        $name = $player->getName();
        if (!isset($this->exempt[$name])) {
            return false;
        }
        
        if ($this->exempt[$name] < time()) {
            unset($this->exempt[$name]);
            return false;
        }
        
        return true;
    }
    
    private function reset(string $name): void
    {
        unset($this->attacks[$name]);
        unset($this->targets[$name]);
        unset($this->rotations[$name]);
        unset($this->vios[$name]);
    }
}