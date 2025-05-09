<?php

namespace unknown\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\world\World;
use unknown\Loader;
use unknown\punishments\Punishment;

class KillAura {

    private array $attacks = [];
    private array $targetPositions = [];
    private array $targets = [];
    private array $rotations = [];
    private array $vios = [];
    private array $angleDistribution = [];
    private array $lastAttackAngles = [];
    private array $switchCounter = [];
    private Config $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function handle(ServerboundPacket $packet, Player $player): void
    {
        if (!$this->config->getNested("checks.killaura.enabled", true)) {
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
                $this->targetPositions[$name] = [];
                $this->rotations[$name] = [];
                $this->angleDistribution[$name] = [];
                $this->lastAttackAngles[$name] = null;
                $this->switchCounter[$name] = 0;
                $this->vios[$name] = 0;
            }
            
            $this->attacks[$name][] = $time;
            
            // Limpiar ataques antiguos
            $this->attacks[$name] = array_filter($this->attacks[$name], function($t) use ($time) {
                return ($time - $t) <= 2;
            });
            
            // Registrar información del objetivo
            $target = $this->getEntity($player->getWorld(), $targetId);
            if ($target !== null) {
                $targetPos = $target->getPosition();
                $this->targetPositions[$name][$targetId] = [
                    'x' => $targetPos->x,
                    'y' => $targetPos->y,
                    'z' => $targetPos->z,
                    'time' => $time
                ];
                
                // Calcular ángulos de ataque desde el jugador al objetivo
                $playerPos = $player->getPosition();
                $dx = $targetPos->x - $playerPos->x;
                $dz = $targetPos->z - $playerPos->z;
                $requiredYaw = atan2($dz, $dx) * (180 / M_PI) - 90;
                if ($requiredYaw < 0) $requiredYaw += 360;
                
                $dy = $targetPos->y + $target->getEyeHeight() - ($playerPos->y + $player->getEyeHeight());
                $horizontalDistance = sqrt($dx * $dx + $dz * $dz);
                $requiredPitch = -atan2($dy, $horizontalDistance) * (180 / M_PI);
                
                // Comprobar cambio brusco de objetivo
                if ($this->lastAttackAngles[$name] !== null) {
                    $yawDiff = abs($requiredYaw - $this->lastAttackAngles[$name]['yaw']);
                    if ($yawDiff > 180) $yawDiff = 360 - $yawDiff;
                    
                    if ($yawDiff > 45) {
                        $this->switchCounter[$name]++;
                        
                        if ($this->switchCounter[$name] >= 3) {
                            $this->vios[$name] += 0.5;
                            $this->switchCounter[$name] = 0;
                            Loader::getInstance()->debug("KillAura rapid target switch detected for $name", 1);
                        }
                    }
                }
                
                $this->lastAttackAngles[$name] = [
                    'yaw' => $requiredYaw,
                    'pitch' => $requiredPitch
                ];
                
                // Registrar distribución angular
                $yawKey = floor($player->getLocation()->getYaw() / 10) * 10;
                if (!isset($this->angleDistribution[$name][$yawKey])) {
                    $this->angleDistribution[$name][$yawKey] = 0;
                }
                $this->angleDistribution[$name][$yawKey]++;
                
                // Verificar FOV (si el jugador está mirando aproximadamente hacia el objetivo)
                $this->checkFov($player, $requiredYaw, $requiredPitch);
            }
            
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
            $this->checkAngleDistribution($player);
        }
    }
    
    private function checkFov(Player $player, float $requiredYaw, float $requiredPitch): void
    {
        $name = $player->getName();
        $actualYaw = $player->getLocation()->getYaw();
        $actualPitch = $player->getLocation()->getPitch();
        
        // Calcular diferencia en ángulo de visión
        $yawDiff = abs($actualYaw - $requiredYaw);
        if ($yawDiff > 180) $yawDiff = 360 - $yawDiff;
        
        $pitchDiff = abs($actualPitch - $requiredPitch);
        
        $maxFov = $this->config->getNested("checks.killaura.max_fov_difference", 60);
        
        // Si el jugador no está mirando aproximadamente hacia el objetivo
        if ($yawDiff > $maxFov) {
            Loader::getInstance()->debug("Player $name attacked outside FOV: YawDiff=$yawDiff°", 2);
            $this->vios[$name] += 0.75;
            
            $vioThreshold = $this->config->getNested("checks.killaura.violation_threshold", 3);
            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "KillAura", "30d");
                $this->reset($name);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "KillAura", "FOV:" . round($yawDiff, 2));
            }
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
        
        Loader::getInstance()->debug("Player $name attacked $targetCount targets in 1 second (max: $maxTargets)", 2);
        
        if ($targetCount >= $maxTargets) {
            $this->vios[$name]++;
            
            Loader::getInstance()->debug("KillAura violation for $name: " . $this->vios[$name] . "/" . $vioThreshold, 1);
            
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
                    'speed' => $yawChange / $timeChange,
                    'combinedSpeed' => ($yawChange + $pitchChange) / $timeChange
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
        $consistentRotations = 0;
        $lastSpeed = null;
        
        foreach ($changes as $change) {
            // Detectar velocidades de rotación imposibles
            if ($change['speed'] > $maxSpeed) {
                $impossibleCount++;
                $maxDetectedSpeed = max($maxDetectedSpeed, $change['speed']);
            }
            
            // Detectar consistencia sospechosa (velocidades idénticas entre rotaciones)
            if ($lastSpeed !== null && abs($change['speed'] - $lastSpeed) < 1.0) {
                $consistentRotations++;
            }
            $lastSpeed = $change['speed'];
        }
        
        // Detectar movimientos precisos imposibles
        if ($impossibleCount >= 2) {
            $this->vios[$name]++;
            
            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "KillAura", "30d");
                $this->reset($name);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "KillAura", round($maxDetectedSpeed, 2));
            }
        }
        
        // Detectar rotaciones sospechosamente consistentes
        if ($consistentRotations >= 3) {
            $this->vios[$name] += 0.5;
            Loader::getInstance()->debug("KillAura consistent rotation pattern detected for $name", 1);
            
            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "KillAura", "30d");
                $this->reset($name);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "KillAura", "ConsistentPattern");
            }
        }
    }
    
    private function checkAngleDistribution(Player $player): void
    {
        $name = $player->getName();
        
        // Necesitamos suficientes datos para verificar la distribución
        if (count($this->angleDistribution[$name]) < 5) {
            return;
        }
        
        // Los humanos normalmente atacan desde un rango pequeño de ángulos
        // Los KillAura suelen distribuir ataques uniformemente en 360 grados
        $distributions = array_values($this->angleDistribution[$name]);
        $attackCount = array_sum($distributions);
        $maxAngle = max($distributions);
        
        // Calcular distribución esperada uniforme
        $expectedPerAngle = $attackCount / count($this->angleDistribution[$name]);
        
        // Calcular chi-cuadrado (medida de uniformidad)
        $chiSquare = 0;
        foreach ($distributions as $count) {
            $chiSquare += pow($count - $expectedPerAngle, 2) / $expectedPerAngle;
        }
        
        // Un chi-cuadrado bajo indica distribución uniforme (sospechoso)
        $chiThreshold = $this->config->getNested("checks.killaura.chi_square_threshold", 3.0);
        
        if ($chiSquare < $chiThreshold && count($this->angleDistribution[$name]) >= 8) {
            $this->vios[$name] += 0.75;
            Loader::getInstance()->debug("KillAura angle distribution anomaly for $name: chi=$chiSquare", 1);
            
            $vioThreshold = $this->config->getNested("checks.killaura.violation_threshold", 3);
            if ($this->vios[$name] >= $vioThreshold) {
                Punishment::ban($player, "KillAura", "30d");
                $this->reset($name);
            } else {
                Loader::getInstance()->getAntiCheatManager()->alert($player, "KillAura", "Angle:" . round($chiSquare, 2));
            }
        }
        
        // Evitar que crezca indefinidamente
        if (count($this->angleDistribution[$name]) > 18) {
            // Remover el ángulo menos frecuente
            $minCount = min($distributions);
            foreach ($this->angleDistribution[$name] as $angle => $count) {
                if ($count === $minCount) {
                    unset($this->angleDistribution[$name][$angle]);
                    break;
                }
            }
        }
    }
    
    private function getEntity(World $world, int $runtimeId): ?Entity
    {
        foreach ($world->getEntities() as $entity) {
            if ($entity->getId() === $runtimeId) {
                return $entity;
            }
        }
        return null;
    }
    
    private function reset(string $name): void
    {
        unset($this->attacks[$name]);
        unset($this->targets[$name]);
        unset($this->targetPositions[$name]);
        unset($this->rotations[$name]);
        unset($this->angleDistribution[$name]);
        unset($this->lastAttackAngles[$name]);
        unset($this->switchCounter[$name]);
        unset($this->vios[$name]);
    }
}