<?php

namespace unknown\tasks;

use pocketmine\scheduler\Task;
use unknown\punishments\Punishment;

class BanTask extends Task
{
    public function onRun(): void
    {
        Punishment::checkExpiry();
    }
}