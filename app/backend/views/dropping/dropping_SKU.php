<?php

use app\core\entities\User;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var array $SKU
 * @var string $mode
 * @var string $back
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dropping</h1>
    </div>
    <?= View::include('/utility/individual_SKU_scan.php', [
        'action' => '/drop/dropping_conf',
        'title' => 'DROP',
        'data' => [
            'location_id' => $SKU['location_id'],
            'SKU_id' => $SKU['id'],
            'batch_id' => $SKU['batch_id'],
            'mode' => $mode,
            'back' => $back,
        ],
        'SKU' => $SKU,
        'instruction' => $SKU['picking'],
        'back' => $back,
    ]); ?>
</div>