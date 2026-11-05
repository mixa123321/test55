<?php

use app\core\entities\Location;
use app\core\entities\User;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;
use app\core\entities\SKU;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;

/**
 * @var User $authUser
 * @var array $SKUs
 * @var array $suppliers
 * @var array $batches
 * @var array $is_batches
 * @var int $sid
 * @var string $search
 * @var int $current
 * @var int $measure
 * @var int $unassigned
 * @var int $status
 * @var int $size
 * @var int $pages
 * @var int $present_in_orders
 * @var array $warehouses_virtual
 * @var bool $warehouse_virtual
 */

$merge_modal = new ConfirmModal('merge', 'Merge SKU', ConfirmModal::SIZE_LG);
$merge_modal->includeFile('SKUs/modals/merge.php');

$barcode_modal = new ConfirmModal('barcode', 'Add barcode', ConfirmModal::SIZE_NORMAL);
$barcode_modal->includeFile('SKUs/modals/barcode.php');

$back_params = 's=' . urlencode($search) . "&c=$current&sid=$sid&measure=$measure&status=$status&unassigned=$unassigned&present_in_orders=$present_in_orders";

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Inventory</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        SKUs list (<?= $size; ?> item<?= $size > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="text" id="s" name="s" placeholder=" "
                                   value="<?= View::toHtml(trim($search) !== '' ? $search : '') ?>"/>
                            <label for="s">SKU barcode</label>
                        </div>
                    </div>
                    <div class="form-group col-md-3">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'sid',
                                ];
                                if (count($suppliers) == 1) {
                                    $options['disabled'] = 'disabled';
                                }
                                echo Html::dropDownList('sid', $suppliers, (string)$sid, $options);
                            ?>
                            <label for="sid">Account</label>
                        </div>
                    </div>
                    <?php if (!$warehouse_virtual) { ?>
                        <div class="form-group col-md-3">
                            <div class="form-group-element">
                                <?php
                                echo Html::dropDownList('measure', [0 => 'all', 1 => 'Y', 2 => 'N'], (string)$measure, [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'measure',
                                ]);
                                ?>
                                <label for="measure">Measure</label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-md-3">
                        <div class="form-group-element">
                            <?php
                            echo Html::dropDownList('status', [0 => 'all'] + SKU::getStateNames(1), (string)$status, [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'status',
                            ]);
                            ?>
                            <label for="status">Status</label>
                        </div>
                    </div>
                    <?php if (!$warehouse_virtual) { ?>
                        <div class="form-group col-md-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="unassigned" name="unassigned"<?= !empty($unassigned) ? ' checked' : '';?> onclick="submit();">
                                <label class="form-check-label" for="unassigned">Unassigned SKUs</label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-md-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="present_in_orders" name="present_in_orders"<?= !empty($present_in_orders) ? ' checked' : '';?> onclick="submit();">
                            <label class="form-check-label" for="present_in_orders">Present in orders</label>
                        </div>
                    </div>
                    <div class="col-auto ml-auto mb-3">
                        <a href="/housekeeping/inventory/?c=<?= $current; ?>" class="btn btn-lg btn-outline-orange">Clear</a>
                        <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                    </div>
                </div>
            </form>
            <?php if (count($SKUs)) { ?>
                <?php if ($size) View::pagination("/housekeeping/inventory/?$back_params&", 1, $pages, $current); ?>
                <div class="row">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <colgroup>
                                <col width="20%">
                                <col width="15%">
                                <col width="15%">
                                <col width="20%">
                                <?php if (!$warehouse_virtual) { ?>
                                    <col>
                                    <col>
                                <?php } ?>
                                <col width="8%">
                                <col width="10%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>SKU (id)<br/>Barcode</th>
                                <th>Account (id)</th>
                                <th>Batches</th>
                                <th>Available</th>
                                <?php if (!$warehouse_virtual) { ?>
                                    <th>Allocated</th>
                                    <th>Measure<br/>Capacity</th>
                                <?php } ?>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($SKUs as $SKU) {
                                $back = '?id=' . View::toHtml($SKU['id']) . "&back=/housekeeping/inventory&$back_params";
                                ?>
                                <tr>
                                    <td><?= View::toHtml($SKU['name']) ?> (#<?= View::toHtml($SKU['id']) ?>)<br/><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']) ?></td>
                                    <td><?= View::toHtml($SKU['sname']) ?> (#<?= View::toHtml($SKU['sid']) ?>)</td>
                                    <td>
                                        <?php if(!empty($batches[$SKU['id']])) { ?>
                                            <?php foreach($batches[$SKU['id']] as $batch) { ?>
                                                <b>Batch#:</b> <?= View::toHtml($batch['batch_number'])?>
                                                (<?= View::toHtml($batch['quantity'])?> units)<br/>
                                                <b>Exp#:</b> <?= View::toHtml(date('jS M Y', strtotime($batch['expired'])))?>
                                                <br/><br/>
                                            <?php } ?>
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if ($SKU['state'] == SKU::BUNDLE_NUM) { ?>
                                            <?= $SKU['bundle_available'] ?? 0; ?>
                                            <?php if (!$warehouse_virtual) { ?>
                                            </td>
                                            <td><td>
                                            <?php } ?>
                                        <?php } else { ?>
                                            Total: <b><?= View::toHtml($SKU['available']) ?></b>
                                            <?php if (!$warehouse_virtual) { ?>
                                            <br/>
                                            <?= Location::PF; ?> <b><?= View::toHtml($SKU[Location::PF]) ?> / <?= View::toHtml($SKU[Location::PF . '_last']) ?></b><br/>
                                            <?= Location::ST; ?> <b><?= View::toHtml($SKU[Location::ST]) ?> / <?= View::toHtml($SKU[Location::ST . '_last']) ?></b><br/>
                                            <?= Location::PA; ?> <b><?= View::toHtml($SKU[Location::PA]) ?></b><br/>
                                            <?= Location::CA; ?> <b><?= View::toHtml($SKU[Location::CA]) ?></b><br/>
                                            <?= Location::DH; ?> <b><?= View::toHtml($SKU[Location::DH]) ?></b>
                                        </td>
                                        <td><br/><?= Location::OB; ?> <b><?= View::toHtml($SKU[Location::OB]) ?></b><br/>
                                            <?= Location::TP; ?> <b><?= View::toHtml($SKU[Location::TP]) ?></b><br/>
                                            <?= Location::LP; ?> <b><?= View::toHtml($SKU[Location::LP]) ?></b></td>
                                        <td>
                                            <span class="badge badge-<?= !empty($SKU['measure']) ? "success" : "danger"; ?>">
                                                <?= empty($SKU['measure']) ? 'N' : 'Y'; ?>
                                            </span><br/>
                                            <span class="badge badge-<?= $SKU['capacity'] ? "success" : "danger"; ?>">
                                                <?= $SKU['capacity'] ? 'Y' : 'N'; ?>
                                            </span>
                                            <?php } elseif (!empty($warehouse_virtual)) {
                                                echo '<br/>';
                                                foreach ($warehouses_virtual as $warehouse_name)
                                                    echo View::toHtml($warehouse_name) . ' <b>' . View::toHtml($SKU[$warehouse_name]) . '</b><br/>';
                                            } ?>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php $status_label = $SKU['state'] == 1 ? "success" : "danger"; ?>
                                        <span class="badge badge-<?= $status_label?>">
                                        <?= View::toHtml(SKU::getStateName($SKU['state'])); ?>
                                    </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-primary btn-block justify-content-start btn-icon-split mt-0 mb-1" href="/SKUs/view_sku/<?= View::toHtml($back); ?>">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-info"></i>
                                        </span>
                                            <span class="text flex-fill">Detail</span>
                                        </a>
                                        <?php if (!$warehouse_virtual) { ?>
                                            <a class="btn btn-primary btn-block justify-content-start btn-icon-split mt-0 mb-1" href="/SKUs/measure/<?= View::toHtml($back); ?>">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-ruler"></i>
                                                </span>
                                                <span class="text flex-fill">Measure</span>
                                            </a>
                                            <a class="btn btn-primary btn-block justify-content-start btn-icon-split mt-0 mb-1" href="/housekeeping/sku_locations/<?= View::toHtml($back); ?>">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-map-marker-alt"></i>
                                            </span>
                                                <span class="text flex-fill">Locations</span>
                                            </a>
                                        <?php } ?>
                                        <?php if ($authUser->isRoleWorker()) : ?>
                                            <button class="btn btn-primary justify-content-start btn-block btn-icon-split mt-0 mb-1"
                                                    type="button"
                                                    onclick="<?= View::toHtml($barcode_modal->showWithParameteres([
                                                        'id' => $SKU['id'],
                                                        'supplier_id' => $SKU['sid'],
                                                        'name' => View::toJs($SKU['name']),
                                                    ])); ?>"
                                            >
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-barcode"></i>
                                                    </span>
                                                <span class="text flex-fill" style="padding-left: 0; "><nobr>Add barcode</nobr></span>
                                            </button>
                                        <?php endif; ?>

                                        <button class="btn btn-primary justify-content-start btn-block btn-icon-split mt-0 mb-3" type="button"
                                                onclick="return LabelPrint(<?= View::toHtml($SKU['id']); ?>,
                                                        <?= View::toHtml(json_encode((string)$SKU['name'])); ?>,
                                                        <?= View::toHtml(json_encode(array_values((array)$SKU['barcodes']))); ?>);">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-print"></i>
                                        </span>
                                            <span class="text flex-fill">Label</span>
                                        </button>
                                        <?php if (!empty($is_batches[$SKU['id']]) && $authUser->hasWorkersAccess()) { ?>
                                            <a class="btn btn-success btn-block justify-content-start btn-icon-split mt-0 mb-1" href="/housekeeping/batches/<?= View::toHtml($back); ?>">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-clock"></i>
                                            </span>
                                                <span class="text flex-fill">Batches</span>
                                            </a>
                                        <?php } ?>
                                        <?php if (SKU::isUnexpected($SKU['state'])): ?>
                                            <button class="btn btn-danger justify-content-start btn-block btn-icon-split mt-0 mb-1"
                                                    type="button"
                                                    onclick="<?= $merge_modal->showWithParameteres([
                                                        'id' => $SKU['id'],
                                                        'supplier_id' => $SKU['sid'],
                                                    ]); ?>"
                                            >
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-exchange-alt"></i>
                                                </span>
                                                <span class="text flex-fill">Merge</span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($warehouse_virtual && SKU::isActive($SKU['state'])) { ?>
                                            <button class="btn btn-success justify-content-start btn-block btn-icon-split mt-0 mb-1 js-inventory"
                                                    type="button"
                                                    id="sku_id_<?= $SKU['id']; ?>"
                                                    data-sku_obj='<?= json_encode($SKU, JSON_HEX_APOS); ?>'>
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-warehouse"></i>
                                            </span>
                                                <span class="text flex-fill">In reality</span>
                                            </button>
                                        <?php } ?>

                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php
                if ($size) View::pagination("/housekeeping/inventory/?$back_params&", 1, $pages, $current);
            } else
                echo Html::warning('No records found');
            ?>
        </div>
    </div>
</div>
<?php
    $modal_label = new SwitchModal('labelPrint', 'Generate label');
    echo $modal_label->addContent('
            <input id="SKU_id" type="hidden"/>
            <div class="row">
                <div class="col text-lg text-center" id="nameSKU"></div>
            </div>
            <div class="row justify-content-center">
                <div class="col col-md-4">
                    <select class="form-control custom-select" id="barcode" name="barcode"></select>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col col-md-4">
                    <div class="form-group mb-1">
                        <label for="size">Labels in row</label>
                        <input id="size" class="form-control text-center" type="number" min="1" max="8" value="5"/>
                    </div>
                    <div id="sizeError" class="col bg-danger text-white p-1 rounded d-none text-center">
                        Maximum is 8
                    </div>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col col-md-4">
                    <div class="form-group mt-2 mb-1">
                        <label for="qty">Quantity</label>
                        <input id="qty" class="form-control text-center" type="number" min="1" value="50"/>
                    </div>
                    <div id="qtyError" class="col bg-danger text-white p-1 rounded d-none text-center">
                        Minimum is 1
                    </div>
                </div>
            </div>')
        ->addButton('Generate', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE | SwitchModal::BUTTON_CONFIRM, ['class' => 'js-btn-conf'])
        ->put();
?>
<script>
    $('.js-btn-conf').click(function() {
        var size = $('#size').val();
        var qty = $('#qty').val();
        var barcode = $('#barcode').val();
        if (size > 0 && size <= 8 && qty > 0) {
            <?= $modal_label->hide(); ?>
            window.open('/housekeeping/label_print/?id=' + $('#SKU_id').val() + '&size=' + size + '&qty=' + qty + '&barcode=' + barcode);
        } else {
            if (size <= 0 || size > 8) {
                $('#sizeError').prop("classList").remove('d-none');
                setTimeout(function () {
                    $('#sizeError').prop("classList").add('d-none');
                }, 3000);
            }
            if (qty <= 0) {
                $('#qtyError').prop("classList").remove('d-none');
                setTimeout(function () {
                    $('#qtyError').prop("classList").add('d-none');
                }, 3000);
            }
        }
    });
    function LabelPrint(id, name, barcodes)
    {
        $('#SKU_id').val(id);
        $('#nameSKU').text(name + ' (#' + id + ')');
        $('#barcode').html('');
        for (let barcode of barcodes) {
            let opt = document.createElement('option');
            opt.value = barcode;
            opt.textContent = barcode;
            $('#barcode').append(opt);
        }
        <?= $modal_label->show(); ?>
        return false;
    }
</script>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'inventory_in_reality.php'; ?>
<?= $merge_modal->put(); ?>
<?= $barcode_modal->put(); ?>
