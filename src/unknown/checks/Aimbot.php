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

class Aimbot {

    private array $rotations = [];
    private array $attacks = [];
    private array $patterns = [];
    private array $vios = [];
    private array $exempt = [];
    private Config $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function handle(ServerboundPacket $packet, Player $player): void
    {
        if (!Loader::getInstance()->getConfig()->getNested("checks.aimbot.enabled", true)) {
            return;
        }
        
        $name = $player->getName();
        $time = microtime(true);
        
        $this->recordRotation($player, $time);
        
        if ($packet instanceof InventoryTransactionPacket && 
            $packet->trData instanceof UseItemOnEntityTransactionData) {
            
            if (!isset($this->attacks[$name])) {
                $this->attacks[$name] = [];
                $this->patterns[$name] = [];
                $this->vios[$name] = 0;
            }
            
            $this->attacks[$name][] = $time;
            
            $this->attacks[$name] = array_filter($this->attacks[$name], function($t) use ($time) {
                return ($time - $t) <= 3;
            });
            
            $this->checkAimbot($player);
        }
    }
    
    private function recordRotation(Player $player, float $time): void
    {
        $name = $player->getName();
        $loc = $player->getLocation();
        
        if (!isset($this->rotations[$name])) {
            $this->rotations[$name] = [];
        }
        
        $this->rotations[$name][] = [
            'yaw' => $loc->getYaw(),
            'pitch' => $loc->getPitch(),
            'time' => $time
        ];
        
        if (count($this->rotations[$name]) > 40) {
            array_shift($this->rotations[$name]);
        }
    }
    
    private function checkAimbot(Player $player): void
    {
        $name = $player->getName();
        
        if (count($this->rotations[$name]) < 10 || count($this->attacks[$name]) < 2) {
            return;
        }
        
        $attackRotations = [];
        foreach ($this->attacks[$name] as $attackTime) {
            $closest = null;
            $minDiff = PHP_FLOAT_MAX;
            
            foreach ($this->rotations[$name] as $rotation) {
                $timeDiff = $attackTime - $rotation['time'];
                if ($timeDiff >= 0 && $timeDiff < $minDiff) {
                    $minDiff = $timeDiff;
                    $closest = $rotation;
                }
            }
            
            if ($closest !== null) {
                $attackRotations[] = $closest;
            }
        }
        
        if (count($attackRotations) < 2) {
            return;
        }
        
        $this->analyzePatterns($player, $attackRotations);
    }
    
    private function analyzePatterns(Player $player, array $attackRotations): void
    {
        $name = $player->getName();
        
        $changes = [];
        for ($i = 1; $i < count($attackRotations); $i++) {
            $prev = $attackRotations[$i-1];
            $curr = $attackRotations[$i];
            
            $yawChange = abs($curr['yaw'] - $prev['yaw']);
            if ($yawChange > 180) $yawChange = 360 - $yawChange;
            
            $pitchChange = abs($curr['pitch'] - $prev['pitch']);
            $timeChange = $curr['time'] - $prev['time'];
            
            $changes[] = [
                'yaw' => $yawChange,
                'pitch' => $pitchChange,
                'time' => $timeChange
            ];
        }
        
        $yawChanges = array_column($changes, 'yaw');
        $pitchChanges = array_column($changes, 'pitch');
        
        $yawVar = $this->calcVar($yawChanges);
        $pitchVar = $this->calcVar($pitchChanges);
        
        $this->patterns[$name][] = [
            'yawVar' => $yawVar,
            'pitchVar' => $pitchVar
        ];
        
        if (count($this->patterns[$name]) > 5) {
            array_shift($this->patterns[$name]);
        }

        $maxVar = $this->config->getNested("checks.aimbot.max_variance", 5.0);
        $vioThreshold = $this->config->getNested("checks.aimbot.violation_threshold", 3);
        
        $suspiciousPatterns = 0;
        foreach ($this->patterns[$name] as $pattern) {
            if ($pattern['yawVar'] < $maxVar && $pattern['pitchVar'] < $maxVar) {
                $suspiciousPatterns++;
            }
        }
        
        if ($suspiciousPatterns >= 3) {
            $this->vios[$name]++;
            
            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "Aimbot", "30d");
                $this->reset($name);
            } else {
                $value = round(($yawVar + $pitchVar) / 2, 2);
                Loader::getInstance()->getAntiCheatManager()->alert($player, "Aimbot", $value);
            }
        }
    }
    
    private function calcVar(array $vals): float
    {
        $count = count($vals);
        if ($count === 0) return 0;
        
        $mean = array_sum($vals) / $count;
        $var = 0;
        
        foreach ($vals as $val) {
            $var += pow($val - $mean, 2);
        }
        
        return $var / $count;
    }
    
    private function reset(string $name): void
    {
        unset($this->rotations[$name]);
        unset($this->attacks[$name]);
        unset($this->patterns[$name]);
        unset($this->vios[$name]);
    }
}