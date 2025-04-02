<?php

namespace unknown\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\math\Vector3;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use unknown\Loader;
use unknown\punishments\Punishment;

class Fly {

    private array $positions = [];
    private array $lastY = [];
    private array $airTime = [];
    private array $vios = [];
    private array $exempt = [];
    private Config $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function run(ServerboundPacket $packet, Player $player): void {
        if (!Loader::getInstance()->getConfig()->getNested("checks.fly.enabled", true)) {
            return;
        }
        
        if ($player->getAllowFlight()) {
            return;
        }
        
        $isMove = ($packet instanceof MovePlayerPacket || $packet instanceof PlayerAuthInputPacket);
        
        if ($isMove) {
            $name = $player->getName();
            $pos = $player->getPosition();
            $time = microtime(true);
            
            if (!isset($this->positions[$name])) {
                $this->positions[$name] = [];
                $this->lastY[$name] = $pos->y;
                $this->airTime[$name] = 0;
                $this->vios[$name] = 0;
            }
            
            // Store position history
            $this->positions[$name][] = [
                'x' => $pos->x,
                'y' => $pos->y,
                'z' => $pos->z,
                'time' => $time
            ];
            
            // Limit history size
            if (count($this->positions[$name]) > 20) {
                array_shift($this->positions[$name]);
            }
            
            // Check if player is on ground
            $onGround = $this->isOnGround($player);
            
            // Check for fly
            $this->checkFly($player, $onGround);
            
            $this->lastY[$name] = $pos->y;
        }
    }
    
    private function checkFly(Player $player, bool $onGround): void
    {
        $name = $player->getName();
        $pos = $player->getPosition();
        
        // Reset air time if on ground
        if ($onGround) {
            $this->airTime[$name] = 0;
            return;
        }
        
        // Increment air time
        $this->airTime[$name]++;
        
        // Get config values
        $maxAirTime = $this->config->getNested("checks.fly.max_air_time", 40);
        $vioThreshold = $this->config->getNested("checks.fly.violation_threshold", 3);
        
        // Check for vertical movement
        $yDiff = $pos->y - $this->lastY[$name];
        
        // If player is not falling and has been in air too long
        if ($this->airTime[$name] > $maxAirTime && $yDiff >= 0) {
            $this->vios[$name]++;
            
            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "Fly", "30d");
                $this->reset($name);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "Fly", $this->airTime[$name]);
            }
        }
        
        // Check for hovering (minimal vertical movement)
        if ($this->airTime[$name] > 10 && abs($yDiff) < 0.05) {
            $this->vios[$name] += 0.5;
            
            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "Fly", "30d");
                $this->reset($name);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "Fly", "Hovering");
            }
        }
    }
    
    private function isOnGround(Player $player): bool
    {
        $pos = $player->getPosition();
        $world = $player->getWorld();
        
        // Check block below player
        $blockPos = $pos->subtract(0, 0.3, 0)->floor();
        $block = $world->getBlock($blockPos);
        
        // Check if block is solid
        if ($block->isSolid()) {
            return true;
        }
        
        // Check surrounding blocks (for edge cases)
        $offsets = [
            [0.3, 0, 0],
            [-0.3, 0, 0],
            [0, 0, 0.3],
            [0, 0, -0.3],
            [0.3, 0, 0.3],
            [-0.3, 0, -0.3],
            [0.3, 0, -0.3],
            [-0.3, 0, 0.3]
        ];
        
        foreach ($offsets as $offset) {
            $checkPos = $pos->subtract($offset[0], 0.3, $offset[2])->floor();
            $checkBlock = $world->getBlock($checkPos);
            
            if ($checkBlock->isSolid()) {
                return true;
            }
        }
        
        return false;
    }
    
    private function reset(string $name): void
    {
        unset($this->positions[$name]);
        unset($this->lastY[$name]);
        unset($this->airTime[$name]);
        unset($this->vios[$name]);
    }
}