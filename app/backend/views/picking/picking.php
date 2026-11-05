<?php

use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\billet\PickingBillet;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\entities\Location;
use app\core\helpers\Filter;

/**
 * @var User $authUser
 * @var string $mode
 * @var array $SKUs
 * @var array $suppliers_list
 * @var array $weight
 * @var int $weight_all
 * @var Location $cart
 * @var bool $is_mezzanine
 * @var array $zones
 * @var array $type_ids
 * @var string $warehouse_label
 * @var array $sort_services
 * @var array $filters
 */

if (empty($cart))
    $cart = null;
$W = $weight['weight'] ?? 0;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Picking (<?= View::toHtml($mode); ?>)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header p-3">
            <div class="row">
                <div class="col" style="min-width: 150px;">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        SKUs list (<?= count($SKUs); ?> item<?= count($SKUs) > 1 ? 's' : '' ?>).
                        <?= View::include('utility/cart_widget.php', [
                            'cart' => $cart,
                            'warehouse_label' => $warehouse_label,
                            'weight' => $W,
                            'weight_all' => $weight_all,
                        ]); ?>
                    </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/cart/picking/?back=/pick/picking_<?= substr($mode, 0, 1); ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-shopping-cart"></i>
                        </span>
                        <span class="text d-none d-md-block">Change trolley</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body p-3">
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
                <form method="get" name="frmFilter" action="/pick/picking_<?= substr($mode, 0, 1); ?>">
                    <input type="hidden" name="clear" value="1" />
                    <div class="form-row justify-content-center">
                        <div class="form-group col-lg-2 mb-1">
                            <div class="form-group-element">
                                <?php if (count($type_ids) == 1) {
                                    $index = array_keys($type_ids)[0];
                                ?>
                                    <input type="text" id="type_id" class="form-control form-control-lg" value="<?= $type_ids[$index]; ?>" disabled/>
                                <?php } else { ?>
                                    <select id="type_id" name="type_id" class="form-control form-control-lg custom-select" <?= count($type_ids) == 1 ? 'disabled' : 'onchange="$(\'#client\').val(-1); submit();"'; ?>>
                                        <?php foreach ($type_ids as $type_id => $type_name) { ?>
                                            <option value="<?= $type_id; ?>"<?= (isset($_GET['type_id']) && $type_id == $_GET['type_id']) ? ' selected' : ''; ?>><?= View::toHtml($type_name); ?></option>
                                        <?php } ?>
                                    </select>
                                <?php } ?>
                                <label for="type_id">Locations</label>
                            </div>
                        </div>
                        <?php if ($is_mezzanine) { ?>
                            <div class="form-group col-auto mr-3 align-self-center mb-1">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="mezzanine" name="filter[mezzanine]"<?= !empty($filters['mezzanine']) ? ' checked' : ''; ?> onclick="submit();">
                                    <label class="form-check-label" for="mezzanine">Mezzanine</label>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="form-row justify-content-center">
                        <div class="form-group col-md-6 mb-1">
                            <div class="form-group-element">
                                <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
                                        'id' => 'services',
                                        'onchange' => 'frmFilter.clear.value = 0; submit();',
                                        'data-empty-value' => Filter::NOT_SELECTED,
                                    ];
                                    if (count($sort_services) == 1)
                                        $options += ['disabled' => 'disabled'];
                                    echo Html::multiSelect('filter[services][]', 
                                        $sort_services,
                                        $filters['services'] ?? [],
                                        $options
                                    );
                                ?>
                                <label for="services">Services</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row justify-content-center">
                        <div class="form-group col-md-6 mb-1">
                            <div class="form-group-element">
                                <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
                                        'id' => 'client',
                                        'onchange' => 'frmFilter.clear.value = 0; submit();',
                                        'data-empty-value' => Filter::NOT_SELECTED,
                                    ];
                                    
                                    $supplier_options = [];
                                    foreach ($suppliers_list as $key => $supplier) {
                                        $supplier_options[$key] = is_array($supplier) && isset($supplier['name']) ? 
                                            $supplier['name'] : (is_string($supplier) ? $supplier : 'Unknown');
                                    }
                                    
                                    echo Html::multiSelect('filter[client][]', 
                                        $supplier_options,
                                        $filters['client'] ?? [],
                                        $options
                                    );
                                ?>
                                <label for="client">Client</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row justify-content-center">
                        <div class="form-group col-md-6">
                            <div class="form-group-element">
                                <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
                                        'id' => 'zones',
                                        'onchange' => 'frmFilter.clear.value = 0; submit();',
                                        'data-empty-value' => Filter::NOT_SELECTED,
                                    ];
                                    echo Html::multiSelect('filter[zones][]', 
                                        $zones,
                                        $filters['zones'] ?? [],
                                        $options
                                    );
                                ?>
                                <label for="zones">Aisles</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <?php if ($cart !== null && !empty($SKUs)) { ?>
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <form method="post" action="/pick/picking_get" id="formPick" name="frmPick" class="p-0">
                            <input type="hidden" id="mode" name="mode" value="<?= View::toHtml($mode); ?>"/>
                            <input type="hidden" id="cart_id" name="cart_id" value="<?= $cart->getId(); ?>"/>
                            <input type="hidden" id="SKU_id" name="SKU_id"/>
                            <input type="hidden" id="batch_id" name="batch_id"/>
                            <input type="hidden" name="back" value="<?= http_build_query($_GET); ?>"/>
                            <?php (new Scan([
                                'id' => 'search',
                                'name' => 'search',
                                'placeholder' => 'Location or SKU barcode',
                                'style' => 'min-width: 150px;',
                                'type' => Scan::TYPE__SKU | Scan::TYPE__LOCATION,
                            ], [
                                'title' => 'Location/SKU',
                                'icon' => 'fas fa-fw fa-box-open',
                                'class' => 'js-btn-scan',
                                'data' => [
                                    'send' => 'formPick',
                                    'locations' => json_encode(array_values(array_unique(Arr::column($SKUs, 'location_id')))),
                                    'suppliers' => json_encode(array_values(array_unique(Arr::column($SKUs, 'supplier_id')))),
                                    'batches' => json_encode(array_values(array_unique(Arr::column($SKUs, 'batch_id')))),
                                    'type' => '1',
                                    'reason' => '1',
                                ]
                            ]))->render(); ?>
                        </form>
                    </div>
                </div>
                <div class="row justify-content-center p-3">
                    <?php
                        foreach ($SKUs as $SKU)
                            echo (new PickingBillet($SKU))->put();
                    ?>
                </div>
            <?php } else { echo Html::warning("There are no SKUs waiting to be picked"); }?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_SKU_batch.php'; ?>
<script>
    $('#filter_click').click(function () {
        $('#search').prop('readonly', 'readonly');
        $('#search').focus();
        $('#search').prop('readonly', '');
    });
</script>
<?= View::include_js(BACKEND_JS, "multi_select.js"); ?>