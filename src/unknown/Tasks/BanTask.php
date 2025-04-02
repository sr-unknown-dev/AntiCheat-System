<?php

namespace unknown;

use pocketmine\scheduler\Task;
use unknown\punishments\Punishment;

class BanTask extends Task
{

    /**
     * @inheritDoc
     */
    public function onRun(): void
    {
        Punishment::checkExpiration();
    }
}