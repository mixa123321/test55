<?php

use app\core\entities\Location;
use app\core\helpers\modal\Modal;
use app\core\helpers\View;

/**
 * @var Modal $modal
 * @var array $carts
 * @var bool $can_unhookcart
 * @var int $table_id
 */
?>

<div>
<?php foreach ($carts as $cart) : ?>
    <div class="d-flex">
        <div class="flex-fill text-center border-info border p-2 text-info">
            <span class="text-lg"><b><?= View::toHtml($cart['name']); ?></b></span>
            <hr class="m-0"/>
            <?php if ($can_unhook_cart && $cart['type_id'] == Location::CA): ?>
                <a class="btn btn-danger btn-icon-split mt-2"
                    href="/cart/unhook_trolley?cart_id=<?= $cart['id']; ?>&back=/pack/packing_l?table_id=<?= $table_id; ?>">
                    <span class="icon text-white-50">
                        <i class="fas fa-fw fa-shopping-cart"></i>
                    </span>
                    <span class="text text-white">Unhook</span>
                </a>
            <?php endif; ?>
            <?php if ($cart['orders_count'] ?? 0 > 0) { ?>
                <a class="btn btn-success btn-icon-split mt-2 ml-3"
                    href="/pack/packing_unload?cart_id=<?= $cart['id']; ?>&mode=large&table_id=<?= $table_id; ?>&back=/pack/packing_l?table_id=<?= $table_id; ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-arrow-down"></i>
                        </span>
                    <span class="text text-white">Unload</span>
                </a>
            <?php } ?>
        </div>
    </div>
<?php endforeach; ?>
</div>