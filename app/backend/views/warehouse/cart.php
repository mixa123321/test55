<?php

use app\backend\models\Cart;
use app\core\entities\Location;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var User $authUser
 * @var Location $cart
 * @var array $carts
 * @var array $virtuals
 * @var array $pallets
 * @var User $user
 * @var int $virt_id
 * @var string $mode
 * @var string $back
 * @var string $warehouse_label
 */

$take_title = 'Take';
if ($user->isRoleWorker() && $authUser != $user) {
    $take_title = 'Give';
}

$a_param = "id={$user->getId()}&back=" . str_replace('&','^', $back) . "&mode=$mode";
$safe_back = View::toHtml($back);

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Trolley <b><?= !$user->hasManagerAccess() ? View::toHtml($cart->getLocation($warehouse_label)) : ''; ?></b></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-<?= !$user->hasManagerAccess() ? 'shopping-cart' : 'user'; ?>"></i>
                        <?= View::toHtml($user->getLogin() . ' (#' . $user->getId() . ')'); ?>
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <form method="post" action="/cart/change" name="changeCart">
            <input type="hidden" name="mode" value="<?= View::toHtml($mode); ?>">
            <input type="hidden" name="back" value="<?= View::toHtml($back); ?>">
            <input type="hidden" name="user_id" value="<?= $user->getId(); ?>">
            <div class="card-body">
                <?php if ($back != "/") {
                    echo Html::warning("Please take or change a trolley first");
                } ?>
                <?php
                    if ($user->hasManagerAccess()) {
                        echo Html::info('You can unhook a trolley which at the "Table" or "Large"');
                    }
                    if (!empty($_GET['unhook_cart_id'])) {
                        $toUnhookCartId = (int)$_GET['unhook_cart_id'];
                        $unhook_cart_name = View::toHtml($carts[$toUnhookCartId]['name']);
                        $unhook_cart_user = View::toHtml($carts[$toUnhookCartId]['user_id']);
                        echo Html::warning(
                                "<span>Trolley \"{$unhook_cart_name}\" is at the packing station \"{$unhook_cart_user}\".
                                        If you would like to unhook this trolley, press <b>\"Confirm\"</b></span>
                                <a class=\"btn btn-warning btn-icon-split btn-sm\" 
                                    href='/cart/unhook_trolley/?cart_id={$toUnhookCartId}&back={$safe_back}'>
                                    <span class=\"text d-none d-sm-block\" style=\"width:150px;\">Confirm</span>
                                </a>", false);

                    }
                    if (!$authUser->isRoleWorker() && $user->isRoleWorker()) 
                        foreach ($pallets as $pallet) {
                            $pallet_name = View::toHtml($pallet['name']);
                            $pallet_alias = View::toHtml($pallet['alias']);
                            $pallet_user = View::toHtml($pallet['user_id']);
                            if (empty($pallet['user_id'])) {
                                $scan_type = Scan::TYPE__LOCATION;
                                echo Html::warning(
                                    "<span>Pallet \"{$pallet_name}\" is free, it needs to be processed!
                                            If you would like to give this pallet to worker, press <b>\"Give\"</b></span>
                                    <span class=\"btn btn-warning btn-icon-split btn-sm\"
                                        data-scan-type='{$scan_type}'
                                        data-scan-barcode='{$pallet_alias}'
                                        <span class=\"text d-none d-sm-block\" style=\"width:150px;\">Give</span>
                                    </span>", false);
                            } elseif (!Cart::isAtWorker($pallet['user_id'], $user) && empty($pallet['orders_count'])) {
                                echo Html::warning(<<<HTML
                                    <span>Pallet <b>{$pallet_name}</b> is at the packing station "{$pallet_user}" and is ready to be returned.
                                            If you would like to return this pallet by worker, press <b>"Return"</b></span>
                                    <a class="btn btn-primary btn-icon-split btn-sm" 
                                        href='/cart/unhook_trolley/?cart_id={$pallet['id']}&back={$safe_back}'
                                    >
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-arrow-left"></i>
                                        </span>
                                        <span class="text d-none d-sm-block" style="width:150px;">Return</span>
                                    </a>
HTML, false);
                            }
                        }
                    ?>
                    <div class="form-group row my-2 js-scan-barcode" data-scan-type="<?= Scan::TYPE__LOCATION; ?>">
                        <div class='col'>
                            <input class="form-control" type="text" id="cart_new" style="min-width: 150px;" name="cart_new" placeholder="Trolley Id/Alias" autofocus/>
                        </div>
                        <div class='col-auto'>
                            <button class="btn btn-success btn-icon-split btn-lg" id="search" type="button">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-shopping-cart"></i>
                                    </span>
                                <span class="text d-none d-md-block btn-success" style="width:180px;" onclick="takeTrolley()"><?= $take_title; ?></span>
                            </button>
                        </div>
                    </div>
                    <?php if ($cart->CA() || $cart->ST()) {
                        $user_cart = empty($carts[$cart->getId()]) ? null : $carts[$cart->getId()];
                        $user_virt = empty($virtuals[$virt_id]) ? null : $virtuals[$virt_id];
                        ?>
                        <div class="form-group row justify-content-center">
                            <div class='col text-center text-md-left'>
                                <input type="hidden" name="cart" value="<?= $cart->getId(); ?>">
                                <p class="form-control-static text-lg" style="min-width: 150px;"><b><?= View::toHtml($cart->getLocation($warehouse_label)); ?></b></p>
                            </div>
                            <div class='col-auto'>
                                <a class="btn btn-danger btn-icon-split btn-lg" href="/cart/delete/?<?= View::toHtml($a_param); ?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-times"></i>
                                    </span>
                                    <span class="text" style="width:180px;">Release</span>
                                </a>
                                <?php if (!empty($user_cart) && !Cart::isNone($user_cart['mode']) && !$cart->isDetached()) { ?>
                                    <br/>
                                    <button class="btn btn-primary btn-icon-split btn-lg mt-2 js-hands-to"
                                            data-loc_from="<?= $cart->getId(); ?>"
                                            data-loc_to="<?= $virt_id; ?>"
                                            data-mode='<?= View::toHtml(json_encode($user_cart['mode'])) ?>'
                                            type="button">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-hands"></i>
                                        </span>
                                        <span class="text" style="width:180px;">To Virtual</span>
                                    </button>
                                <?php } ?>
                                <?php if (!empty($user_virt) && !Cart::isNone($user_virt['mode']) && !$cart->isDetached()) { ?>
                                    <br/>
                                    <button class="btn btn-primary btn-icon-split btn-lg mt-2 js-hands-from"
                                            data-loc_from="<?= $virt_id; ?>"
                                            data-loc_to="<?= $cart->getId(); ?>"
                                            data-mode='<?= View::toHtml(json_encode($user_virt['mode'])) ?>'
                                            type="button">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-hands"></i>
                                        </span>
                                        <span class="text" style="width:180px;">From Virtual</span>
                                    </button>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                <div class="row">
                    <?php foreach ($carts as $_cart) { ?>
                        <div class="col-sm-6 col-md-4 col-lg-3 col-xl-2 d-flex my-2<?= !empty($_cart['consist']) ? " js-loc-SKU\" id=\"{$_cart['id']}" : ''; ?>">
                            <div class="flex-fill text-center border border-info p-2 text-info
                                <?= $_cart['state'] == 0 ? "order-secondary bg-light text-muted" : ''; ?>
                                <?= !empty($_cart['consist']) ? "block_hover " : ''; ?>
                                <?= empty($_cart['mode']) ? 'alert-warning' : ($_cart['id'] == $cart->getId() ? 'alert-success ' : ''); ?>"
                                <?= !empty($_cart['consist']) ? ' style="border-width: 3px !important"' : ''; ?>>
                                <span class="text-lg"><b><?= View::toHtml($_cart['name']); ?></b></span><br/>
                                <span class="badge badge-secondary"><?= View::toHtml($_cart['alias']); ?></span><br/>
                                <span class="text-xs">SKUs/Units: <b><?= empty($_cart['consist']) ? '-' : $_cart['consist']; ?></b><hr class="m-0"/>
                                    Weight: <b><?= empty($_cart['weight']) ? '-' : $_cart['weight']; ?></b></span>
                                <?php if ($_cart['id'] != $cart->getId()) { ?>
                                    <input type="hidden" name="carts[<?= $_cart['id']; ?>]" value="<?= View::toHtml($_cart['name']); ?>">
                                    <?php if($_cart['user_id']): ?>
                                        <input type="hidden"
                                               name="busy_carts[]"
                                               value="<?= View::toHtml($_cart['name']); ?>"
                                               data-alias="<?= View::toHtml($_cart['alias']) ?>"
                                               data-name-user="<?= View::toHtml($_cart['user_id']) ?>"
                                        >
                                    <?php endif; ?>
                                    <hr class="m-0"/>
                                        <?php if ($_cart['state'] == 0): ?>
                                            <span class="text-success"><b>is locked</b></span>
                                        <?php else: ?>
                                            <span class="text-<?= !$_cart['user_id'] ? 'success' : 'danger'; ?>"><b><?= !$_cart['user_id'] ? 'is free' : 'at the ' . View::toHtml($_cart['user_id']); ?></b></span>
                                        <?php endif; ?>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <?php if (!empty($virtuals)) { ?>
                    <div class="row">
                        <div class="col-12 mt-2 mb-0">
                            <h2>Virtuals</h2>
                        </div>
                        <?php foreach ($virtuals as $_virt) { ?>
                            <div class="col-sm-6 col-md-4 col-lg-3 col-xl-2 d-flex my-2<?= !empty($_virt['consist']) ? " js-loc-SKU\" id=\"{$_virt['id']}" : ''; ?>">
                                <div class="flex-fill text-center border border-info p-2 text-info <?= !empty($_virt['consist']) ? "block_hover " : ''; ?> <?= empty($_virt['mode']) ? 'alert-warning' : ($_virt['id'] == $cart->getId() ? 'alert-success ' : ''); ?>"<?= !empty($_virt['consist']) ? ' style="border-width: 3px !important"' : ''; ?>>
                                    <?php if (!$user->isRoleWorker()) { ?>
                                        <span class="text-lg"><b><?= View::toHtml($_virt['user_id']); ?></b></span><br/>
                                    <?php } ?>
                                    <span class="text-xs">SKUs/Units: <b><?= empty($_virt['consist']) ? '-' : $_virt['consist']; ?></b><hr class="m-0"/>
                                        Weight: <b><?= empty($_virt['weight']) ? '-' : $_virt['weight']; ?></b><br/></span>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
                <?php if (!empty($pallets)) { ?>
                    <div class="row">
                        <div class="col-12 mt-2 mb-0">
                            <h2>Pallets</h2>
                        </div>
                        <?php foreach ($pallets as $pallet) { ?>
                            <div class="col-sm-6 col-md-4 col-lg-3 col-xl-2 d-flex my-2<?= !empty($pallet['consist']) ? " js-loc-SKU\" id=\"{$pallet['id']}" : ''; ?>">
                                <div class="flex-fill text-center border border-info p-2 text-info <?= !empty($pallet['consist']) ? "block_hover " : ''; ?> <?= empty($pallet['mode']) ? 'alert-warning' : ($pallet['id'] == $cart->getId() ? 'alert-success ' : ''); ?>"<?= !empty($pallet['consist']) ? ' style="border-width: 3px !important"' : ''; ?>>
                                    <span class="text-lg"><b><?= View::toHtml($pallet['name']); ?></b></span><br/>
                                    <span class="badge badge-secondary"><?= View::toHtml($pallet['alias']); ?></span><br/>
                                    <span class="text-xs">SKUs/Units: <b><?= empty($pallet['consist']) ? '-' : $pallet['consist']; ?></b><hr class="m-0"/>
                                        Weight: <b><?= empty($pallet['weight']) ? '-' : $pallet['weight']; ?></b></span>
                                    <?php if ($pallet['id'] != $cart->getId()) { ?>
                                        <input type="hidden" name="carts[<?= $pallet['id']; ?>]" value="<?= View::toHtml($pallet['name']); ?>">
                                        <?php if($pallet['user_id']): ?>
                                            <input type="hidden"
                                                   name="busy_carts[]"
                                                   value="<?= View::toHtml($pallet['name']); ?>"
                                                   data-alias="<?= View::toHtml($pallet['alias']) ?>"
                                                   data-name-user="<?= View::toHtml($pallet['user_id']) ?>"
                                            >
                                        <?php endif; ?>
                                        <hr class="m-0"/><span class="text-<?= !$pallet['user_id'] ? 'success' : 'danger'; ?>"><b><?= !$pallet['user_id'] ? 'is free' : 'at the ' . View::toHtml($pallet['user_id']); ?></b></span>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </form>
        <?php if ($back != "/") { ?>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl(str_replace('^', '?', $back))); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-chevron-left"></i>
                            </span>
                            <span class="text">Back</span>
                        </a>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="selectMode">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select type of SKU for moving</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <select id="mode_id" size="4" class="form-control col" multiple>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-success" id="resultMode" type="button" disabled>Select</button>
            </div>
        </div>
    </div>
</div>

<!--<div class="modal fade" id="take_trolley" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-body">Are you sure? This Trolley is already being used by <span id="user_name_of_trolley"></span></div>
            <div class="modal-footer">
                <button class="btn btn-outline-orange" type="button" data-dismiss="modal">Cancel</button>
                <button class="btn btn-orange" onclick="$('[name=changeCart]').submit();">Take</button>
            </div>
        </div>
    </div>
</div>-->

<?php
$modal_take_trolley = new ConfirmModal('takeTrolley', 'Take trolley');
echo $modal_take_trolley->addContent('Are you sure? This Trolley is already being used by <span id="user_name_of_trolley"></span>')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['id' => 'confirmTakeTrolley'])
    ->put();
?>

<script>
    var a_param = <?= View::toJs($a_param); ?>;
    var loc_from;
    var loc_to;
    var mode;

    $('.js-loc-SKU').click(function (e) {
        location = '/housekeeping/locations/?loc=' + e.currentTarget.id + '&back=/cart';
    });

    function hands(hands_data) {
        loc_from = hands_data.data('loc_from');
        loc_to = hands_data.data('loc_to');
        mode = hands_data.data('mode');
        if (mode.length == 1) {
            location = '/cart/hands/?loc_from_id=' + loc_from + '_' + mode[0] + '&loc_to_id=' + loc_to + '&' + a_param;
            return;
        }
        $('#resultMode').prop('disabled', true);
        $('#mode_id').children().remove().end();
        mode.forEach(function (item) {
            $('#mode_id').append($('<option>', { value : item }).text(item.toUpperCase()));
        });
        $('#selectMode').modal('show');
    }

    $('.js-hands-to').click(function () {
        hands($('.js-hands-to'));
    });

    $('.js-hands-from').click(function () {
        hands($('.js-hands-from'));
    });

    $('#resultMode').click(function () {
        location = '/cart/hands/?loc_from_id=' + loc_from + '_' + $('#mode_id').val() + '&loc_to_id=' + loc_to + '&' + a_param;
    });

    $('#mode_id').change(function () {
        if ($('#resultMode').prop('disabled'))
            $('#resultMode').prop('disabled', false);
    });

    $('#confirmTakeTrolley').click(function () {
        $('[name=changeCart]').submit();
    });

    function takeTrolley() {
        // var cartNew = $.trim($("#cart_new").val());
        // var userNameBusyTrolley = '';

        // $('input[name="busy_carts[]"]').map(function () {
        //     if ($(this).val() === cartNew || $(this).attr('data-alias') === cartNew) {
        //         userNameBusyTrolley = $(this).attr('data-name-user');
        //     }
        // });

        //if (userNameBusyTrolley) {
        //    $('#user_name_of_trolley').html(userNameBusyTrolley);
        //    <?php //= $modal_take_trolley->show(); ?>
        //} else {
            $('[name=changeCart]').submit();
        // }
    }

</script>
