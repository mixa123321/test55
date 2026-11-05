<?php

namespace app\core\interfaces;

interface GroupLock
{
    public function getLockFileName(): string;
}