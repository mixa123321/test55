<?php

use app\core\entities\User;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var array $SKU
 * @var string $mode
 * @var int $size
 * @var int $cart_id
 * @var string $back
 * @var int $actual
 */

if (empty($size))
    $size = 1;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Picking (<?= View::toHtml($mode); ?>)</h1>
    </div>
    <?= View::include('/utility/individual_SKU_scan.php', [
        'action' => '/pick/picking_conf',
        'title' => 'PICK',
        'data' => [
            'location_id' => $SKU['location_id'],
            'SKU_id' => $SKU['id'],
            'batch_id' => empty($SKU['batch_id']) ? '' : $SKU['batch_id'],
            'mode' => $mode,
            'size' => $size,
            'cart_id' => $cart_id,
            'back' => $back,
        ],
        'SKU' => $SKU,
        'instruction' => $SKU['picking'],
        'back' => '/pick/picking_' . substr($mode,0,1) . '/?' . $back,
        'actual' => $actual,
    ]); ?>
</div>