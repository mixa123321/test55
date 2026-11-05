<?php

namespace app\core\interfaces;

interface Command
{
    const PATH = ROBOT_PATH . DIRECTORY_SEPARATOR . 'lockers' . DIRECTORY_SEPARATOR . 'queue' . DIRECTORY_SEPARATOR;

    public function handle();
}