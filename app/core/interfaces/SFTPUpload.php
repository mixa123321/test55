<?php

namespace app\core\interfaces;

use app\core\entities\Warehouse;
use app\core\entities\User;

interface SFTPUpload
{
    public function execute($head, $data, $form, Warehouse $warehouse, User $user): array;
}