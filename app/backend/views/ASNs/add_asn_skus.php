<?php

use app\backend\models\ASNs;
use app\backend\models\Suppliers;
use app\core\entities\ASN;
use app\core\entities\User;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\statical\Services;
use app\core\helpers\Html;

/**
 * @var User $authUser
 * @var array $ASN
 * @var array $SKUs
 * @var array $users
 * @var string $date_from
 * @var array $skus_order
 * @var array $containers_list
 * @var string $in_date
 * @var string $available_skus
 * @var string $skus_info
 * @var bool $warehouse_virtual
 * @var string $available_skus
 * @var string $skus_info
 * @var string $back
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Create<?= !empty($ASN['order_id']) ? ' Return' : ''; ?> ASN (<?= View::toHtml(Suppliers::getById($ASN['supplier_id'])->getName() . " - " . date('d.m.Y', $ASN['arrival_date'])) ?>)</h1>
    </div>
    <form method="post" action="/goods_in/add_asn_save" name="frmSave">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-fw fa-sign-in-alt"></i> Add SKUs</h6>
                </div>
                <div class="col-auto">
                    <?php if (!is_null($users) && empty($ASN['order_id'])) { ?>
                        <div class="form-inline">
                            <label for="user_id">User account:&nbsp;</label>
                            <select id="user_id" name="ASN[user_id]" class="form-control">
                                <?php foreach ($users as $user) { ?>
                                    <option value="<?= $user['user_id'] ?>"<?= $user['user_id'] == $ASN['user_id'] ? ' selected' : ''; ?>><?= View::toHtml($user['login']) ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <input type="hidden" name="mode">
        <?php if (is_null($users) ||  !empty($ASN['order_id'])) { ?>
            <input type="hidden" name="ASN[user_id]" value="<?= View::toHtml($ASN['user_id'] ?? '') ?>">
        <?php } ?>
        <input type="hidden" name="ASN[supplier_id]" value="<?= View::toHtml($ASN['supplier_id']) ?>">
        <input type="hidden" name="ASN[arrival_date]" value="<?= View::toHtml($ASN['arrival_date']) ?>">
        <input type="hidden" name="ASN[is_return]" value="<?= (int)!empty($ASN['is_return']); ?>">
        <input type="hidden" name="ASN[unscheduled]" value="<?= View::toHtml($ASN['unscheduled']) ?>">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-6">
                                <?php if (!empty($ASN['order_id'])) { ?>
                                    <input type="hidden" name="ASN[order_id]" value="<?= View::toHtml($ASN['order_id']) ?>">
                                    <input type="hidden" name="ASN[order_ref]" value="<?= View::toHtml($ASN['order_ref']) ?>">
                                    <p class="form-control-static">
                                        <b>Order</b> #<?= View::toHtml($ASN['order_id']); ?> (<b>Ref</b>: <?= View::toHtml($ASN['order_ref'] ?: '-'); ?>)
                                        <?php if ($ASN['unscheduled']) { ?>
                                            <span class="badge badge-danger">Unassigned</span>
                                        <?php } ?>
                                    </p>
                                <?php } ?>
                                <div class="alert alert-danger" id="add_sku_error" style="display: none"></div>
                                <p class="form-control-static text-success"><b><a name="skus">Add SKUs using the search field below</a></b></p>
                                <div class="input-group">
                                    <?php (new Scan(['id' => 'search_skus', 'type' => Scan::TYPE__SKU], [
                                        'id' => 'btn_add_sku',
                                        'title' => 'Add',
                                        'icon' => 'fas fa-fw fa-plus',
                                        'type' => 'button',
                                    ]))->render(); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <p class="form-control-static"><b>Comment:</b></p>
                                <textarea class="form-control col" name="ASN[comment]" rows="3"><?= !empty($ASN['comment']) ? View::toHtml($ASN['comment']) : ''; ?></textarea>
                            </div>
                            <div class="col-md-12">
                                <table class="table table-hover mt-4">
                                    <thead>
                                    <tr>
                                        <th>Barcode</th>
                                        <th>Product Name</th>
                                        <th>Quantity</th>
                                        <th>Batch No.</th>
                                        <th>Expiry date</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody id="skus_tbody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <hr/>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group mb-0">
                                    <label for="arrival_date"><b>ASN Reference:</b></label>
                                    <input type="text" name="ASN[ref]" id="ref" value="<?= !empty($ASN['ref']) ? View::toHtml($ASN['ref']) : ''; ?>" class="form-control">
                                </div>
                            </div>
                            <?php if ($authUser->isRoleSupplierUser()) { ?>
                                <div class="col-md-3">
                                    <div class="form-group mb-0">
                                        <label for="arrival_date"><b>Carrier:</b></label>
                                        <input type="text" name="ASN[carrier]" id="carrier" value="<?= !empty($ASN['carrier']) ? View::toHtml($ASN['carrier']) : ''; ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-0">
                                        <label for="arrival_date"><b>Tracking Number:</b></label>
                                        <input type="text" name="ASN[tracking_number]" id="tracking_number" value="<?= !empty($ASN['tracking_number']) ? View::toHtml($ASN['tracking_number']) : ''; ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group mb-0">
                                        <label for="container"><b>Container:</b></label>
                                        <?= Html::dropDownList("ASN[container]", $containers_list, ASN::PALLET, ['class' => 'form-control'])?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <hr/>
                        <div class="row">
                            <?php if ($warehouse_virtual && !empty($locations_list)) { ?>
                                <div class="col-md-3">
                                    <div class="form-group mb-0">
                                        <label for="container"><b>Ship To Location:</b></label>
                                        <?= Html::dropDownList("ASN[ship_to_location]", $locations_list, null, ['class' => 'form-control'])?>
                                    </div>
                                </div>
                            <?php } ?>
                            <?php if ($authUser->isRoleSupplierUser()) { ?>
                                <div class="col-md-6">
                                    <b>Please tell us what additional services you would like us to provide if your ASN is non-conforming on arrival. You can select more than one service if you wish.</b>
                                    <?php
                                        foreach (Services::getAll() as $key => $service) {
                                            if (!$key)
                                                continue;
                                            echo "<div class=\"form-check\"><input type=\"checkbox\" class=\"form-check-input\" id=\"ASN[services][$key]\" name=\"ASN[services][$key]\"" . (!empty($ASN['services'][$key]) && $ASN['services'][$key] == 'on' ? ' checked' : '') . ">";
                                            echo "<label class='form-check-label' for='ASN[services][$key]'>" . View::toHtml($service) . "</label></div>\n";
                                        }
                                    ?>
                                    <label for="ASN[services][0]">Other (please specify here)</label>
                                    <input type="text" name="ASN[service0]" id="ASN[service0]" value="<?= !empty($ASN['service0']) ? View::toHtml($ASN['service0']) : ''; ?>" class="form-control">
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-chevron-left"></i>
                        </span>
                            <span class="text">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" id="btnUpload" type="button" onclick="frmSave.mode.value = 2;">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-file-upload"></i>
                            </span>
                            <span class="text">Upload (CSV)</span>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" type="submit" onclick="frmSave.mode.value = 1;">
                            <span class="text text-white">Done</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<script type="text/template" id="sku_tr_template">
    <tr>
        <td>{{barcode_html}}<input name="barcode[{{id}}]" type="hidden" value="{{barcode}}"/></td>
        <td>{{name}}</td>
        <td><input name="quantity[{{id}}]" type="text" class="form-control" value="{{qty}}"></td>
        <td><input name="batch_number[{{id}}]" type="text" class="form-control" value="{{batch_number}}"></td>
        <td><input name="batch_expired[{{id}}]" class="form-control"  type="date" id="date_from"
                   value="{{batch_expired}}"
                   min="<?= date('Y-m-d', $in_date) ?>"/></td>
        <td><button type="button" class="btn btn-danger js-btn-remove-sku" data-sku="{{barcode}}">Remove</button></td>
    </tr>
</script>
<script>
    var global_skus_to_search = <?= $available_skus ?>;
    var global_skus_info = <?= $skus_info ?>;
    var order_skus = <?= $skus_order ?>;

    $('#btnUpload').click(function () {
        frmSave.submit();
    });
</script>

<?= View::include_js(BACKEND_JS, "add_sku.js")?>

<script>
    renderOrderSKUs();
</script>
