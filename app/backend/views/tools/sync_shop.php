<?php

use app\backend\models\SKUs;
use app\core\entities\SKU;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\modal\ConfirmModal;
use app\backend\models\InventorySync;

/**
 * @var int $shop_id
 * @var string $shop_name
 * @var string $supplier_name
 * @var array $SKUs
 * @var string $external_name
 * @var int $total
 * @var int $page
 * @var int $total_pages
 * @var string $back
 * @var string $now_update
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Inventory of shop <?= View::toHtml("$shop_name ($supplier_name)"); ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-list"></i>
                            SKUs (<?= $total; ?> item<?= $total > 1 ? 's' : ''; ?>)
                        </span>
                    </h6>
                </div>
                <?php if (count($SKUs)) { ?>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" id="btn_update_shop">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-sync"></i>
                            </span>
                            <span class="text">All SKUs</span>
                        </button>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <form method="get">
                <input type="hidden" name="id" value="<?= $shop_id ?>">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER ?>"/>
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="text" id="SKU_id"
                                   name="SKU_id"
                                   value="<?= View::toHtml(!empty($_GET['SKU_id']) ? $_GET['SKU_id'] : '') ?>"
                                   placeholder=" "
                            />
                            <label for="SKU_id">SKU ID</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="text" id="barcode"
                                   name="barcode"
                                   value="<?= View::toHtml(!empty($_GET['barcode']) ? $_GET['barcode'] : '') ?>"
                                   placeholder=" "
                            />
                            <label for="barcode">Barcode</label>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-end mr-3">
                    <a href="/inventory_sync/sync_shop?id=<?= $shop_id ?>" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                </div>
            </form>
            <?php if (count($SKUs)) { ?>
                <div class="alert alert-danger d-none fixed-top" id="errUpdate">
                </div>
                <?php View::pagination("/inventory_sync/sync_shop?" . http_build_query($_GET) . '&', 1, $total_pages, $page, InventorySync::LIMIT_PER_PAGE); ?>
                <div class="row mt-3">
                    <div class="col table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>SKU ID</th>
                                <th>Name / Barcode</th>
                                <th><?= $external_name; ?> / Customer Ref</th>
                                <th>Receiving</th>
                                <th>Inventory</th>
                                <th>Stock</th>
                                <th>Sync</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($SKUs as $SKU) {
                                $SKU_info = [0];
                                if ($SKU['available_shop'] === '-')
                                    $SKU_info = [3, 'danger', 'Not found in shop'];
                                elseif ($SKU['state'] != 1)
                                    $SKU_info = [2, SKUs::colorStatus($SKU['state']), View::toHtml(SKU::getStateName($SKU['state']))];
                            ?>
                                <tr>
                                    <td><?= View::toHtml($SKU['id']); ?></td>
                                    <td><?= View::toHtml($SKU['name']) . '<br/>' . View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></td>
                                    <td><?= View::toHtml($SKU['external_id']) . '<br/>' . View::toHtml($SKU['customer_ref']); ?></td>
                                    <td><?= $now_update; ?></td>
                                    <td<?= $SKU_info[0] ? " colspan=\"{$SKU_info[0]}\" class=\"text-center\"" : ''; ?> id="available_<?= View::toHtml($SKU['inventory']); ?>">
                                        <?php
                                            if (empty($SKU_info[0]))
                                                echo View::toHtml($SKU['available_shop']) . '</td><td>' . View::toHtml($SKU['available']);
                                            else
                                                echo "<span class=\"badge badge-{$SKU_info[1]}\">{$SKU_info[2]}</span>";
                                        ?>
                                    </td>
                                    <?php if ($SKU_info[0] != 3) { ?>
                                        <td>
                                            <button id="sync_<?= View::toHtml($SKU['inventory']); ?>" class="btn btn-primary rounded rounded-circle p-2 js-btn-sync"<?= ($SKU['state'] != 1 || $SKU['available_shop'] == $SKU['available']) ? ' disabled' : ''; ?>
                                                    data-name="<?= View::toHtml($SKU['name']); ?>"
                                                    data-SKU_id="<?= View::toHtml($SKU['id']); ?>"
                                                    data-available_shop="<?= View::toHtml($SKU['available_shop']); ?>"
                                                    data-available="<?= View::toHtml($SKU['available']); ?>"
                                                    data-inventory_id="<?= View::toHtml($SKU['inventory']); ?>">
                                                <i class="fas fa-fw fa-sync"></i>
                                            </button>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php }?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php View::pagination("/inventory_sync/sync_shop?" . http_build_query($_GET) . '&', 1, $total_pages, $page, InventorySync::LIMIT_PER_PAGE); ?>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-outline-orange pl-3" href="/inventory_sync/sync?<?= View::toHtml($back); ?>">
                        <i class="fas fa-fw fa-chevron-left mr-2"></i>
                        <span class="ml-1">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$modal_update_SKU = new ConfirmModal('updateSKU', '<span id="name_SKU"></span>');
echo $modal_update_SKU->addContent('')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['id' => 'btn_sync_update'])
    ->addContent('<input type="hidden" id="SKU_id" /><input type="hidden" id="inventory_id" />' .
        '<input type="hidden" id="available_shop" /><input type="hidden" id="available" />' .
        '<p class="form-control-static text-lg mb-0">Last update was at ' . $now_update . '. Continue?</p>')
    ->put();

$modal_sync_shop = new ConfirmModal('syncShop', 'Shop synchronisation');
echo $modal_sync_shop->addContent('<div class="container-fluid pl-0">
                                               <p class="form-control-static text-lg mb-0">It can take some time. Are you sure?</p>
                                          </div>
                                          <div class="text-center d-none" id="icon_update">
                                              <img src="' . COMMON_IMG . DIRECTORY_SEPARATOR . 'tenor.gif' . '" style="width: 150px"/>
                                          </div>
                                          <div class="alert d-none mt-3 mb-0" id="errUpdateShop"></div>')
    ->changeNo(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_CANCEL | ConfirmModal::BUTTON_CONFIRM, ['id' => 'btn_update_cancel', 'aria-label' => 'Close'])
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['id' => 'btn_update_conf'])
    ->addCustomButtons('<input type="hidden" id="shop_id" value="' . $shop_id . '">' .
        '<input type="hidden" id="shop_name" value="' . View::toHtml($shop_name) . '">' .
        $modal_sync_shop->getButtons())
    ->put();
?>
<script>
    $('.js-btn-sync').click(function () {
        $('#name_SKU').text('Synchronisation of ' + $(this).data('name'));
        $('#SKU_id').val($(this).data('sku_id'));
        $('#inventory_id').val($(this).data('inventory_id'));
        $('#available_shop').val($(this).data('available_shop'));
        $('#available').val($(this).data('available'));
        <?= $modal_update_SKU->show(); ?>
    });

    $('#btn_sync_update').click(function () {
        <?= $modal_update_SKU->hide(); ?>
        let inventory_id = $('#inventory_id').val();
        let available = $('#available').val();
        $.ajax({
            url: "/inventory_sync/update_inventory",
            method: "POST",
            dataType: 'json',
            data: {
                shop_id: <?= (int)$_GET['id']; ?>,
                SKU_id: $('#SKU_id').val(),
                inventory_id: inventory_id,
                available_shop: $('#available_shop').val(),
                available: available
            },
            success: function (json) {
                if ('error' in json)
                    showError('errUpdate', json.error, 3000);
                else {
                    $('#available_' + inventory_id).html(available);
                    $('#sync_' + inventory_id).prop('disabled', true);
                }
            },
            error: function (json) {
                showError('errUpdate', $('#name_SKU').text() + ' is a fault!', 3000);
            }
        });
    });

    $('#btn_update_shop').click(function () {
        <?= $modal_sync_shop->show(); ?>
    });

    $('#btn_update_conf').click(function () {
        blockUpdate(true);
        $('#icon_update').prop('classList').remove('d-none');
        $.ajax({
            url: "/inventory_sync/update_shop",
            method: "POST",
            dataType: 'json',
            data: { shop_id: $('#shop_id').val() },
            success: function (json) {
                if ('error' in json)
                    showErrorShop(json.error, 'danger');
                else
                    showErrorShop('Update success!', 'success')
                $('#icon_update').prop('classList').add('d-none');
            },
            error: function (json) {
                showErrorShop('Shop ' + $('#shop_name').val() + ' connection error', 'danger');
                $('#icon_update').prop('classList').add('d-none');
            }
        });
    });

    function blockUpdate(disabled)
    {
        $('#btn_update_close').prop('disabled', disabled);
        $('#btn_update_cancel').prop('disabled', disabled);
        $('#btn_update_conf').prop('disabled', disabled);
    }

    function showErrorShop(msg, style)
    {
        $('#errUpdateShop').text(msg);
        $('#errUpdateShop').prop('classList').remove('alert-danger');
        $('#errUpdateShop').prop('classList').remove('alert-success');
        $('#errUpdateShop').prop('classList').add('alert-' + style);
        $('#errUpdateShop').prop('classList').remove('d-none');
        setTimeout(() => {
            $('#errUpdateShop').prop('classList').add('d-none');
            blockUpdate(false);
            if (style == 'success') {
                <?= $modal_sync_shop->hide(); ?>
                location = '/inventory_sync/sync_shop?id=' + $('#shop_id').val() + '&<?= urlencode($back); ?>';
            }
        }, 3000);
    }
</script>
