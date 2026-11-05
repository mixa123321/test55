<?php

use app\core\entities\User;
use app\core\helpers\billet\PickingBillet;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\Filter;
use app\core\entities\Location;
use app\core\helpers\modal\ConfirmModal;

/**
 * @var User $authUser
 * @var array $SKUs
 * @var float $weight
 * @var Location $cart
 * @var string $warehouse_label
 * @var array $filters
 * @var array $sort_services
 */

$cancel_pallet_modal = ConfirmModal::areYouSure('cancel_pallet', 'Return pallet')
    ->addContent('<div class="alert alert-danger d-none js-error" style="position: relative"></div>')
    ->addContent('<div class="js-loader text-center d-none"><i class="fas fa-spinner fa-spin"></i></div>');
echo $cancel_pallet_modal->put();

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dropping (large)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        SKUs list (<?= count($SKUs); ?> item<?= count($SKUs) > 1 ? 's' : '' ?>).
                        <?= View::include('utility/cart_widget.php', [
                            'cart' => $cart,
                            'warehouse_label' => $warehouse_label,
                            'weight' => $weight,
                        ]); ?>
                    </span>
                    </h6>
                </div>
                <?php if ($cart->isDetached()): ?>
                    <div class="col-auto">
                        <a class="btn btn-danger btn-icon-split" href="#" onclick="<?= $cancel_pallet_modal->show(); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-arrow-left"></i>
                            </span>
                            <span class="text">Return pallet</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="col-auto">
                        <a class="btn btn-primary btn-icon-split" href="/cart/dropping/?back=/drop/dropping_l">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-shopping-cart"></i>
                            </span>
                            <span class="text d-none d-md-block">Change trolley</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-end d-md-none">
                <div class="col-auto mb-1">
                    <button class="btn btn-primary btn-icon-split text-nowrap mr-0" data-toggle='collapse' data-target="#filter_show" id="filter_click">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-filter"></i>
                        </span>
                    </button>
                </div>
            </div>
            <div class="d-md-block collapse hide" id="filter_show">
                <form action="/drop/dropping_l" method="GET" name="filter">
                    <input type="hidden" name="clear" value="1" />
                    <div class="form-row justify-content-center">
                        <div class="form-group col-md-6">
                            <div class="form-group-element">
                                <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
                                        'id' => 'services',
                                        'onchange' => 'filter.clear.value = 0; filter.submit();',
                                        'data-empty-value' => Filter::NOT_SELECTED,
                                    ];
                                    if (count($sort_services) == 1)
                                        $options += ['disabled' => 'disabled'];
                                    echo Html::multiSelect('filter[services][]', 
                                        $sort_services,
                                        $filters['services'] ?? [], 
                                        $options);
                                ?>
                                <label for="services">Services</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php if(!empty($SKUs)) { ?>
                <div class="row justify-content-center mb-2">
                    <div class="col-md-10">
                        <form method="post" action="/drop/dropping_table">
                            <input type="hidden" name="mode" value="large"/>
                            <?php (new Scan([
                                'id' => 'search',
                                'name' => 'search',
                                'placeholder' => 'Packing large table',
                                'type' => Scan::TYPE__LOCATION,
                            ], [
                                'id' => 'btnLoc',
                                'icon' => 'fas fa-fw fa-box-open',
                                'title' => 'Location',
                            ]))->render(); ?>
                        </form>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <?php foreach ($SKUs as $SKU)
                        echo (new PickingBillet($SKU, false))
                            ->setColor('info')
                            ->put();
                    ?>
                </div>
            <?php } else
                echo Html::warning("There are no SKUs to drop");
            ?>
        </div>
    </div>
</div>
<script>
    $('#btnLoc').click(function () {
        $('#search').focus();
        return $('#search').val().trim() != '';
    });
    $('<?= $cancel_pallet_modal->getSelector(); ?>').on('confirm', function (e) {
        e.preventDefault();
        var $error = $('.js-error', $(this)),
            $loader = $('.js-loader', $(this));
        $error.addClass('d-none');
        $loader.removeClass('d-none');
        $.post('/cart/return_pallet', {
            back: '/pick/picking_l',
            scan: <?= View::toJs((string)$cart->getLocation($warehouse_label)); ?>,
        }).done(function (data) {
            if (data.success) {
                window.location.href = '/pick/picking_l';
            } else {
                $error.text(data.message || 'Error').removeClass('d-none');
            }
        }).always(function () {
            $loader.addClass('d-none');
        });
    });
</script>
<?= View::include_js(BACKEND_JS, "multi_select.js"); ?>