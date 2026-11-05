<?php

use app\backend\models\Warehouses;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\billet\PickingBillet;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\Filter;
use app\core\entities\Location;

/**
 * @var User $authUser
 * @var array $SKUs
 * @var float $weight
 * @var Location $cart
 * @var string $warehouse_label
 * @var bool $is_mezzanine
 * @var array $floors
 * @var array $filters
 * @var array $sort_services
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dropping (multi)</h1>
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
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/cart/dropping/?back=/drop/dropping_m<?= !empty($filters['floor']) && $filters['floor'] != Filter::NOT_SELECTED ? ('?filter[floor]=' . urlencode($filters['floor'])) : '' ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-shopping-cart"></i>
                        </span>
                        <span class="text d-none d-md-block">Change trolley</span>
                    </a>
                </div>
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
                <form action="/drop/dropping_m" method="GET" name="filter">
                    <input type="hidden" name="clear" value="1" />
                    <?php if ($is_mezzanine) { ?>
                        <div class="form-row justify-content-center">
                            <div class="form-group col-lg-3 mb-1">
                                <div class="form-group-element">
                                    <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
                                        'id' => 'floor',
                                        'onchange' => 'filter.clear.value = 0; filter.submit()'
                                    ];
                                    echo Html::dropDownList('filter[floor]',
                                        $floors,
                                        $filters['floor'] ?? null, $options);
                                    ?>
                                    <label for="floor">Floor</label>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
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
            <?php if (!empty($SKUs)) { ?>
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <form method="post" action="/drop/dropping_get" id="frm_dropping">
                            <input type="hidden" id="mode" name="mode" value="multi"/>
                            <input type="hidden" id="batch_id" name="batch_id"/>
                            <input type="hidden" id="SKU_id" name="SKU_id"/>
                            <?php if ($filters['floor'] != Filter::NOT_SELECTED) { ?>
                                <input type="hidden" name="filter[floor]" value="<?= View::toHtml($filters['floor']); ?>">
                            <?php } ?>
                            <?php if (is_array($filters['services']) && $filters['services'] != Filter::NOT_SELECTED) { ?>
                                <?php foreach ($filters['services'] as $service) { ?>
                                    <input type="hidden" name="filter[services][]" value="<?= View::toHtml($service); ?>">
                                <?php } ?>
                            <?php } ?>
                            <div class="form-group row mb-2 pr-0">
                                <div class="col">
                                    <?php (new Scan([
                                        'id' => 'search',
                                        'name' => 'search',
                                        'placeholder' => 'Location or SKU barcode',
                                        'style' => 'min-width: 150px;',
                                        'type' => Scan::TYPE__SKU | Scan::TYPE__LOCATION,
                                    ], [
                                        'class' => 'js-btn-scan',
                                        'title' => 'Location/SKU',
                                        'icon' => 'fas fa-fw fa-box-open',
                                        'data' => [
                                            'send' => 'frm_dropping',
                                            'locations' => '["' . $cart->getId() . '"]',
                                            'suppliers' => json_encode(array_values(array_unique(Arr::column($SKUs, 'supplier_id')))),
                                            'batches' => json_encode(array_values(array_unique(Arr::column($SKUs, 'batch_id')))),
                                            'type' => '22',
                                        ],
                                    ]))->render(); ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <?php foreach ($SKUs as $SKU)
                        echo (new PickingBillet($SKU))->put();
                    ?>
                </div>
            <?php } else
                echo Html::warning("There are no SKUs to drop");
            ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_SKU_batch.php'; ?>
<?= View::include_js(BACKEND_JS, "multi_select.js"); ?>