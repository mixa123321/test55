<?php

use app\backend\models\Cart;
use app\core\entities\Location;
use app\core\helpers\View;

/**
 * @var Location $cart
 * @var string $warehouse_label
 * @var float|string|null $weight
 * @var float|string|null $weight_all
 */

$weight_badge = $weight < Cart::CART_WEIGHT ? 'success' : 'danger';
$label = $cart->US() ? 'Virtual' : $cart->getLocation($warehouse_label);
?>
My <?= $cart->getTypeTitle(); ?> (<?= View::toHtml($label); ?>)
<?php if (isset($weight)): ?>
    <span class="text-<?= $weight < Cart::CART_WEIGHT ? 'success' : 'danger'; ?>">
        <?= sprintf("%.3f", $weight); ?>
    </span>
    <?php if (isset($weight_all)): ?>
        /<span class="text-<?= $weight_all < Cart::CART_WEIGHT ? 'success' : 'danger'; ?>">
            <?= sprintf("%.3f", $weight_all); ?>
        </span>
    <?php endif; ?>
    Kg
<?php endif; ?>