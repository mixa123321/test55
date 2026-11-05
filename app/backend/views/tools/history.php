<?php
/**
 * @var int $current_page
 * @var array $shops
 * @var array $types
 * @var bool $next_page
 * @var int $next_page
 * @var array $inventory_records
 * @var array $channels
 */

use app\backend\models\InventorySync;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\View;
use app\core\statical\WithDate;

$min_date = Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id];
$max_date = time() - InventorySync::HISTORY_MIN_DAYS*86400;

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">History of inventory</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-history"></i>
                            History
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <button class="btn btn-danger btn-block justify-content-start btn-icon-split js-btn-clear">
                        <span class="icon text-white-50 align-self-center">
                            <i class="fas fa-fw fa-trash-alt"></i>
                        </span>
                        <span class="text flex-fill">Clear before...</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'id' => 'shop_id',
                                    'class' => 'form-control form-control-lg custom-select',
                                ];
                                if (count($shops) < 2)
                                    $options['disabled'] = true;
                                echo Html::dropDownList('shop_id', $shops,!empty($_GET['shop_id']) ? $_GET['shop_id'] : null, $options);
                            ?>
                            <label for="shop_id">Shop</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'id' => 'channel_id',
                                    'class' => 'form-control form-control-lg custom-select',
                                ];
                                if (count($channels) < 2)
                                    $options['disabled'] = true;
                                echo Html::dropDownList('channel_id', $channels,!empty($_GET['channel_id']) ? $_GET['channel_id'] : null, $options);
                            ?>
                            <label for="channel_id">Channel</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input type="text" name="object_id" id="object_id" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['object_id']) ? $_GET['object_id'] : '') ?>">
                            <label for="object_id">ID</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <?= Html::dropDownList(
                                'type',
                                $types,
                                !empty($_GET['type']) ? $_GET['type'] : null,
                                [
                                    'id' => 'type',
                                    'class' => 'form-control form-control-lg custom-select',
                                ],
                                ) ?>
                            <label for="shop_id">Type</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input type="text" name="SKU_id" id="SKU_id" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['SKU_id']) ? $_GET['SKU_id'] : '') ?>">
                            <label for="SKU_id">SKU ID</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="create_date_from" name="create_date_from"
                                   value="<?= View::toHtml(!empty($_GET['create_date_from']) ? $_GET['create_date_from'] : '') ?>"
                                   min="<?= $min_date; ?>"/>
                            <label for="create_date_from">Date from</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="create_date_to" name="create_date_to"
                                   min="<?= View::toHtml(!empty($_GET['create_date_from']) ? $_GET['create_date_from'] : $min_date) ?>"
                                   value="<?= View::toHtml(!empty($_GET['create_date_to']) ? $_GET['create_date_to'] : '') ?>"/>
                            <label for="date_to">Date to</label>
                        </div>
                    </div>
                </div>
                <div class="text-right mb-3">
                    <a href="/inventory_sync/history" class="btn btn-lg btn-outline-orange">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                </div>
            </form>
            <?php if (count($inventory_records)) { ?>
                <?php View::simplePagination("/inventory_sync/history?" . http_build_query($_GET) . (empty($_GET) ? '' : '&'), $current_page, $next_page); ?>
                <div class="row mt-3">
                    <div class="col table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>Shop</th>
                                <th>Event</th>
                                <th>Create</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php
                                foreach ($inventory_records as $inventory_record) {
                                    $additional = json_decode($inventory_record['additional'] ?: '[]', true);
                                    if (($field = InventorySync::getExternalFieldName($inventory_record['cname'])) !== false)
                                        $integration_item_id = "{$inventory_record['cname']} item ID: {$additional[$field]}";
                                    else
                                        $integration_item_id = 'Integration is not available';
                            ?>
                                <tr<?= !$inventory_record['is_active'] ? ' style="background-color: lightpink; color: black;"' : ''; ?>>
                                    <td><?= View::toHtml($shops[$inventory_record['shop_id']]) . '<br/>' . View::toHtml($inventory_record['sname']) . ' (' . $inventory_record['sid'] . ')'; ?></td>
                                    <td><?= InventorySync::showType($inventory_record['type'], $inventory_record['object_id']); ?></td>
                                    <td><?= '<b>' . ($inventory_record['user_id'] > 0 ? View::toHtml($inventory_record['login']) : 'System') . '</b><br/>' . date(WithDate::DATE_FRONT_FORMAT, strtotime($inventory_record['create_date'])); ?></td>
                                    <td><?= '<b>' . View::toHtml($inventory_record['name']) . "</b> (#{$inventory_record['SKU_id']})<br/>" . View::toHtmlConvertWithPrimaryBarcode($inventory_record['barcode']) . ' (' . View::toHtml($integration_item_id) . ')'; ?></td>
                                    <td class="text-right"><?= InventorySync::showQuantity($inventory_record['quantity'], $inventory_record['cname']); ?></td>
                                    <td style="width: 125px">
                                        <button class="btn btn-<?= empty($additional['error']) ? 'primary' : 'danger'; ?> btn-block justify-content-start btn-icon-split mt-0 mb-1 js-btn-detail"
                                                data-additional="<?= View::toHtml($inventory_record['additional']); ?>">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-info"></i>
                                            </span>
                                            <span class="text flex-fill">Detail</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php }?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php View::simplePagination("/inventory_sync/history?" . http_build_query($_GET) . (empty($_GET) ? '' : '&'), $current_page, $next_page); ?>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="detailInventory">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Inventory details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="container-fluid p-sm-4 p-2">
                    <div class="col table-responsive">
                        <table class="table table-hover m-0">
                            <thead>
                            <tr>
                                <th>Parameter</th>
                                <th>Value</th>
                            </tr>
                            </thead>
                            <tbody id="tableInfo"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    $modal_clear = new ConfirmModal('clearBefore', 'Clear history of inventory');
    echo $modal_clear->addContent('
            <div class="form-group form-inline m-0 p-0">
                    <p class="form-control-static form-control-lg text-lg align-self-center pl-0 mb-0">Before</p>
                    <input class="col form-control form-control-lg" type="date" id="clear_date" name="clear_date"
                           value="' . date('Y-m-d', $max_date) . '"
                           max="' . date('Y-m-d', $max_date) . '"/>
            </div>')
        ->addForm('/inventory_sync/clear_history', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
        ->put();
?>
<script>
    $('#create_date_from').change(function () {
        var dateTo, dateFrom = new Date(create_date_from.value);
        create_date_to.min = create_date_from.value;
        if (create_date_to.value.trim() != '') {
            dateTo = new Date(create_date_to.value);
            if (dateFrom > dateTo)
                create_date_to.value = '';
        }
    });

    $('.js-btn-detail').click(function () {
        $('#detailInventory').modal('show');
        var info = $(this).data('additional');
        $('#tableInfo').children().remove().end();
        $.each(info, function (key, val) {
            if (key != 'error')
                $('#tableInfo').append($('<tr>').append(
                    $('<td>').text(key),
                    $('<td>').text(val !== null && typeof val === 'object' ? JSON.stringify(val) : val)
                ));
        });
        if (info.error != null)
            $('#tableInfo').append($('<tr class="bg-danger text-white">').append(
                $('<td>').text('Code: ' + info.error.code),
                $('<td>').text(typeof info.error.msg != 'undefined' ? ('Msg: ' + (info.error.base64 ? atob(info.error.msg) : info.error.msg)) : '')
            ));
        $('#modal-info').html();
    });

    $('.js-btn-clear').click(function () {
        <?= $modal_clear->show(); ?>
    });
</script>
