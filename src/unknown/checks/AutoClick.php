<?php

namespace unknown\checks;

use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\network\mcpe\protocol\ServerboundPacket;
use pocketmine\network\mcpe\protocol\PlayerActionPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use unknown\Loader;
use unknown\punishments\Punishment;

class AutoClick
{
    private array $clicks = [];
    private array $patterns = [];
    private array $vios = [];
    private array $sequences = [];
    private array $lastClickTime = [];
    private array $suspicious = [];
    private Config $config;

    public function __construct()
    {
        $this->config = Loader::getInstance()->getConfig();
    }

    public function run(ServerboundPacket $packet, Player $player): void
    {
        if (!$this->config->getNested("checks.autoclick.enabled", true)) {
            return;
        }
        
        $name = $player->getName();
        $time = microtime(true);

        if ($this->isClick($packet)) {
            if (!isset($this->clicks[$name])) {
                $this->clicks[$name] = [];
                $this->patterns[$name] = [];
                $this->sequences[$name] = [];
                $this->suspicious[$name] = 0;
                $this->vios[$name] = 0;
                $this->lastClickTime[$name] = $time;
            }

            $timeDiff = $time - $this->lastClickTime[$name];
            if ($timeDiff < 0.01) {
                $this->suspicious[$name]++;
            }
            
            $this->lastClickTime[$name] = $time;
            $this->clicks[$name][] = $time;

            $this->clean($name, $time);
            $this->analyze($name);

            $cps = count($this->clicks[$name]);
            $maxClicks = $this->config->getNested("checks.autoclick.max_autoclick_alerts", 20);
            $maxBan = $this->config->getNested("checks.autoclick.max_autoclick_ban", 25);
            $pingMax = $this->config->getNested("checks.autoclick.ping_threshold", 100);
            $vioMax = $this->config->getNested("checks.autoclick.violation_threshold", 3);

            $ping = $player->getNetworkSession()->getPing();

            if ($ping <= $pingMax) {
                if ($this->isBot($name, $cps, $maxBan) || ($this->suspicious[$name] >= 5 && $cps > $maxClicks)) {
                    $this->vios[$name]++;
                    Loader::getInstance()->debug("AutoClick violation for $name: {$this->vios[$name]}/$vioMax, CPS: $cps", 1);

                    if ($this->vios[$name] >= $vioMax) {
                        Punishment::ban($player, "AutoClick", "30d");
                        $this->reset($name);
                    } else {
                        Loader::getInstance()->getAntiCheatManager()->alert($player, "AutoClick", $cps);
                    }
                } elseif ($cps > $maxClicks) {
                    Loader::getInstance()->getAntiCheatManager()->alert($player, "AutoClick", $cps);
                }
            } else {
                Loader::getInstance()->debug("Player $name ignored due to high ping: {$ping}ms > {$pingMax}ms", 2);
            }
        }
    }

    private function isClick(ServerboundPacket $packet): bool
    {
        return ($packet instanceof PlayerActionPacket &&
                ($packet->action === PlayerActionPacket::ACTION_START_BREAK || 
                 $packet->action === PlayerActionPacket::ACTION_ABORT_BREAK)) ||
            ($packet instanceof InventoryTransactionPacket &&
                $packet->trData instanceof UseItemOnEntityTransactionData) ||
            ($packet instanceof LevelSoundEventPacket &&
                ($packet->sound === LevelSoundEventPacket::SOUND_ATTACK_NODAMAGE || 
                 $packet->sound === LevelSoundEventPacket::SOUND_ATTACK_STRONG)) ||
            ($packet instanceof AnimatePacket && 
                $packet->action === AnimatePacket::ACTION_SWING_ARM);
    }

    private function clean(string $name, float $time): void
    {
        // Mantener solo los clics dentro de una ventana de tiempo
        $timeWindow = $this->config->getNested("checks.autoclick.time_window", 1.0);
        $this->clicks[$name] = array_filter($this->clicks[$name], function ($stamp) use ($time, $timeWindow) {
            return ($time - $stamp) <= $timeWindow;
        });
    }

    private function analyze(string $name): void
    {
        if (count($this->clicks[$name]) < 3) {
            return;
        }

        $gaps = [];
        $stamps = $this->clicks[$name];
        sort($stamps);

        // Analizar gaps entre clics
        for ($i = 1; $i < count($stamps); $i++) {
            $gaps[] = round(($stamps[$i] - $stamps[$i - 1]) * 1000, 2); // en milisegundos
        }

        $this->patterns[$name] = $gaps;
        
        // Detección de secuencias repetitivas
        if (count($gaps) >= 5) {
            $this->analyzeSequences($name, $gaps);
        }
    }
    
    private function analyzeSequences(string $name, array $gaps): void
    {
        // Buscar patrones repetitivos en las secuencias de tiempo
        $sequence = "";
        foreach ($gaps as $gap) {
            // Redondeamos y categorizamos tiempos para detectar patrones
            if ($gap < 20) {
                $sequence .= "A"; // muy rápido
            } elseif ($gap < 50) {
                $sequence .= "B"; // rápido
            } elseif ($gap < 100) {
                $sequence .= "C"; // medio
            } else {
                $sequence .= "D"; // lento
            }
        }
        
        $this->sequences[$name] = $sequence;
        
        // Buscar patrones repetitivos
        for ($len = 2; $len <= 5; $len++) {
            $patterns = [];
            for ($i = 0; $i <= strlen($sequence) - $len; $i++) {
                $pat = substr($sequence, $i, $len);
                if (!isset($patterns[$pat])) {
                    $patterns[$pat] = 0;
                }
                $patterns[$pat]++;
            }
            
            // Si un patrón se repite muchas veces, es sospechoso
            foreach ($patterns as $pat => $count) {
                if ($count >= 3 && strlen($pat) >= 3) {
                    $this->suspicious[$name] += 2;
                    Loader::getInstance()->debug("Found suspicious click pattern for $name: '$pat' repeated $count times", 2);
                    break;
                }
            }
        }
    }

    private function isBot(string $name, int $cps, int $maxBan): bool
    {
        if ($cps <= $maxBan) {
            return false;
        }

        if (count($this->patterns[$name]) < 5) {
            return false;
        }

        $gaps = $this->patterns[$name];
        
        // Varias métricas para detectar patrones de bot
        $var = $this->calcVar($gaps);
        $stdDev = sqrt($var);
        $mean = array_sum($gaps) / count($gaps);
        $cv = ($mean > 0) ? $stdDev / $mean : 0; // Coeficiente de variación
        
        // Calcular consecutivos similares (detección de consistencia sospechosa)
        $similarConsecutive = 0;
        for ($i = 1; $i < count($gaps); $i++) {
            if (abs($gaps[$i] - $gaps[$i-1]) < 5) { // menos de 5ms de diferencia
                $similarConsecutive++;
            }
        }
        
        $varThreshold = $this->config->getNested("checks.autoclick.variance_threshold", 2.0);
        $cvThreshold = $this->config->getNested("checks.autoclick.cv_threshold", 0.3);
        
        Loader::getInstance()->debug("Player $name: CPS=$cps, Variance=$var, CV=$cv, Similar=$similarConsecutive", 2);
        
        // Un auto-clicker mostrará patrones muy regulares (baja varianza)
        // o patrones muy precisos (muchos consecutivos similares)
        return ($var < $varThreshold || $cv < $cvThreshold || $similarConsecutive >= 3);
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

    public function getPotentialCPS(string $name): int
    {
        return isset($this->clicks[$name]) ? count($this->clicks[$name]) : 0;
    }

    private function reset(string $name): void
    {
        unset($this->clicks[$name]);
        unset($this->patterns[$name]);
        unset($this->sequences[$name]);
        unset($this->suspicious[$name]);
        unset($this->vios[$name]);
        unset($this->lastClickTime[$name]);
    }
}