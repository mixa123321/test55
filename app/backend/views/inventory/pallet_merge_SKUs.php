<?php

use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\helpers\Arr;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var string $search
 * @var Location $location
 * @var array $SKUs
 * @var string $SKU_info
 * @var string $SKU_qty
 * @var string $warehouse_label
 */

$location_name = $location->getLocation($warehouse_label);

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Pallet merge</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-map-marker-alt"></i>
                            Location <?= View::toHtml($location_name); ?>
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body m-md-3 p-md-1">
            <div class="row justify-content-center">
                <div class="col-md-8 p-0">
                    <form id="scan_barcode">
                        <input type="hidden" name="location_id" value="<?= $location->getId(); ?>"/>
                        <input type="hidden" id="supplier_id" name="supplier_id"/>
                        <?php (new Scan([
                            'id' => 'search',
                            'placeholder' => 'SKU barcode',
                            'type' => Scan::TYPE__SKU,
                        ], [
                            'class' => 'js-btn-scan',
                            'data' => [
                                'send' => 'scan_barcode',
                                'empty' => 'false',
                                'suppliers' => json_encode(array_values(array_unique(Arr::column($SKUs, 'id')))),
                            ],
                        ]))->render(); ?>
                        <div id="wrong_barcode" class="alert alert-danger d-none">SKU barcode is wrong</div>
                    </form>
                    <table class="table table-hover mb-0">
                        <colgroup>
                            <col width="100px">
                            <col width="*">
                            <col width="*">
                            <col width="25%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th></th>
                                <th>Qty</th>
                                <th>Merge</th>
                            </tr>
                        </thead>
                        <tbody id="lst_qties">
                            <?php foreach ($SKUs as $SKU) { ?>
                                <tr>
                                    <td class="p-2 align-middle"
                                        data-scan-barcode="<?= View::toHtml($SKU['barcode']); ?>"
                                        data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                    >
                                        <?php
                                            if (!empty($SKU['photo']))
                                                if ($link = View::toHtml(SKU::showPhotoSKU(urlencode($SKU['photo']))))
                                                    echo '<img class="img-thumbnail" style="max-width: 100px;" src="' . $link . '" /><br/>'
                                        ?>
                                    </td>
                                    <td class="p-2 align-middle"
                                        data-scan-barcode="<?= View::toHtml($SKU['barcode']); ?>"
                                        data-scan-type="<?= Scan::TYPE__SKU; ?>"
                                    >
                                        <span class="d-none d-md-block">
                                            <b><?= View::toHtml($SKU['name']); ?></b> (#<?= View::toHtml($SKU['SKU_id']); ?>)
                                        </span>
                                        <span class="scan-barcode"><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></span>
                                        <?php if (!empty($SKU['number'])) { ?>
                                            <br/><span class="text-xs"><?= View::toHtml($SKU['number']); ?> (<?= date('jS F Y', strtotime($SKU['expired'])); ?>)</span>
                                        <?php } ?>
                                    </td>
                                    <td class="p-2 align-middle"><span class="text-success font-weight-bold"><?= View::toHtml($SKU['summa']); ?></span></td>
                                    <td class="p-2 align-middle"><input type="number" class="form-control text-right" min="0" id="qty_<?= $SKU['SKU_id']; ?>"/></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/housekeeping/pallet_merge">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-chevron-left"></i>
                        </span>
                        <span class="text">Back</span>
                    </a>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split js-btn-dest">
                        <span class="text">Next</span>
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-chevron-right"></i>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    $modal_merge = new SwitchModal('mergeModal', 'Pallet merge');
echo $modal_merge->addContent('
            <div id="response" class="mb-0"></div>
            <form class="row justify-content-center d-none mt-1" id="destination">
                <div class="col">
                    ' . (new Scan([
                        'id' => 'loc_to',
                        'placeholder' => 'Destination',
                        'type' => Scan::TYPE__LOCATION,
                    ], ['class' => 'js-btn-scan-dest']))->getTemplate() .  '
                </div>
            </form>
            <div id="err_message" class="alert alert-danger mb-0 mt-2 d-none"></div>
        ')
    ->addButton('Merge', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE, ['id' => 'mergeConf', 'disabled' => 'disabled'])
    ->put();
?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_supplier.php'; ?>
<script>
    let SKUs = <?= View::toJs(json_decode($SKU_info, true)); ?>;
    const qties = new Map(<?= $SKU_qty; ?>);
    var list_SKUs = new Map();
    var loc_to = '';

    $('#scan_barcode').submit(() => {
        let barcode = $('#search').val().trim();
        let exist_SKU_id, exist_supplier_id = null;
        for (const element of SKUs) {
            if (element.barcodes.includes(barcode)) {
                exist_SKU_id = element.SKU_id;
                exist_supplier_id = element.supplier_id;
            }
        }
        if (exist_SKU_id === false) {
            if ($('#wrong_barcode').prop('classList').contains('d-none')) {
                $('#wrong_barcode').prop('classList').remove('d-none');
                setTimeout(() => {
                    if (!$('#wrong_barcode').prop('classList').contains('d-none'))
                        $('#wrong_barcode').prop('classList').add('d-none');
                }, 3000);
            }
        } else {
            let qty = $('#qty_' + exist_SKU_id);
            qty.val(+qty.val() + 1);
        }
        $('#search').val('');
        return false;
    });

    function find(items, obj)
    {
        key = Array.from(items.keys()).find(k => items.get(k) === obj);
        if (key !== undefined)
            return key.split('_')[0];
        return key;
    }

    $('.js-btn-dest').click(() => {
        $('#destination').prop('classList').add('d-none');
        $('#err_message').prop('classList').add('d-none');
        $('#mergeConf').attr('disabled', true);
        let qty = $('#lst_qties').children().children().children('input');
        let errs = [];
        let msgs = [];
        list_SKUs.clear();
        qty.each((key, element) => {
            let id = element.id.split('_');
            let barcode = '';
            for (const element of SKUs) {
                if (element.SKU_id === id[1]) {
                    barcode = element.barcodes.map(html_escape).join(', ')
                    break;
                }
            }
            let new_qty = +$('#qty_' + id[1]).val();
            if (Number.isInteger(new_qty)) {
                if (new_qty > 0 && new_qty <= qties.get(id[1])) {
                    list_SKUs.set(id[1], new_qty);
                    msgs.push(new_qty + ' x ' + barcode);
                } else if (new_qty < 0)
                    errs.push(barcode + ': quantity cannot be less than 0');
                else if (new_qty > qties.get(id[1]))
                    errs.push(barcode + ': quantity cannot be more than ' + qties.get(id[1]));
            } else
                errs.push(barcode + ': quantity must have int value');
        });
        if (errs.length === 0 && msgs.length === 0)
            errs.push('You must select one or more SKU');
        if (errs.length !== 0)
            $('#response').html('<div class="alert alert-danger row m-0">' + errs.join('<br/>') + '</div>');
        else {
            $('#response').html('You want merge pallet from location ' + html_escape(<?= View::toJs($location_name) ?>) + ' (' + msgs.join(', ') + ') to location');
            $('#destination').prop('classList').remove('d-none');
            $('#loc_to').val('');
        }
        <?= $modal_merge->show(); ?>
    });

    $('.modal').on('shown.bs.modal', function() {
        $(this).find('[autofocus]').focus();
    });

    $('.js-btn-scan-dest').click(() => {
        $('#mergeConf').attr('disabled', true);
        if (!$('#err_message').prop('classList').contains('d-none'))
            $('#err_message').prop('classList').add('d-none');
        loc_to = '';
        $.ajax({
            url: "/housekeeping/check_destination",
            method: "POST",
            data: {loc_from_id: <?= $location->getId(); ?>, loc_to: $('#loc_to').val(), SKUs: Array.from(list_SKUs, ([SKU_id, quantity]) => ({SKU_id, quantity}))},
            dataType: "json",
            success: function (json) {
                if ('error' in json) {
                    showErrorMerge(json.error, 'success', 'danger');
                    $('#loc_to').val('');
                } else {
                    $('#mergeConf').attr('disabled', false);
                    loc_to = $('#loc_to').val();
                    showErrorMerge('You can complete pallet merge to location ' + loc_to, 'danger', 'success');
                    $('#loc_to').val('');
                }
            },
            error: function (json) {
                showErrorMerge('Connection error', 'success', 'danger');
                $('#loc_to').val('');
            }
        });
        return false;
    });

    $('#loc_to').keyup((e) => {
        if (e.keyCode === 13)
            return;
        if (loc_to !== $('#loc_to').val().trim() && loc_to !== '') {
            $('#mergeConf').attr('disabled', true);
            if (!$('#err_message').prop('classList').contains('d-none'))
                $('#err_message').prop('classList').add('d-none');
        } else if (loc_to === $('#loc_to').val().trim() && loc_to !== '') {
            $('#mergeConf').attr('disabled', false);
            if ($('#err_message').prop('classList').contains('d-none'))
                $('#err_message').prop('classList').remove('d-none');
        }
    });

    function showErrorMerge(msg, on, off)
    {
        showError('err_message', msg, 0)
        $('#err_message').prop('classList').remove('alert-' + on);
        $('#err_message').prop('classList').add('alert-' + off);
    }

    $('#mergeConf').click(() => {
        $.ajax({
            url: "/housekeeping/complete_destination",
            method: "POST",
            data: {loc_from_id: <?= $location->getId(); ?>, loc_to: loc_to, SKUs: Array.from(list_SKUs, ([SKU_id, quantity]) => ({SKU_id, quantity}))},
            dataType: "json",
            success: function (json) {
                if ('error' in json)
                    showErrorMerge(json.error, 'success', 'danger');
                else
                    location = '/housekeeping/completed_destination';
            },
            error: function (json) {
                showErrorMerge('Connection error', 'success', 'danger');
            }
        });
    });
</script>