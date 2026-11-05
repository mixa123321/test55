<?php

use app\backend\models\Shippings;
use app\core\helpers\scan\Scan;
use app\core\models\Warehouses;
use app\core\statical\Countries;
use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\Html;
use app\core\models\OrderCategories;
use app\core\models\config\OrdersConfig;

/**
 * @var User $authUser
 * @var Countries $countries
 * @var array $delivery
 * @var int $carrier_id
 * @var array $carriers
 * @var array $quantities
 * @var array $order_bundles
 * @var int $in_date
 * @var int $supplier_id
 * @var array $order
 * @var string $back
 * @var int $shipping_service_id
 * @var array $shipping_services
 * @var string $available_skus JSON
 * @var string $skus_info JSON
 * @var int $warehouse_id
 * @var string $bundles_info JSON
 * @var array<int, string> $suppliers
 * @var array<int, string> $order_categories
 * @var int $printed_matter
 * @var string $eori_number
 * @var string $vat_number
 * @var string $ioss_number
 * @var string $tax_id
 */

$new_del = empty($delivery['id']);
$country_id = Warehouses::getCountryId($warehouse_id);

?>
<div class="scrolling-panel">
    <a class="scrolling-but rounded" href="#special" title="Special requirements">
        <i class="fas fa-tools"></i>
    </a>
    <a class="scrolling-but rounded" href="#shipping" title="Shipping">
        <i class="fas fa-truck"></i>
    </a>
    <a class="scrolling-but rounded" href="#skus" title="SKU(s)">
        <i class="fas fa-industry"></i>
    </a>
</div>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Create order</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-fw fa-paste"></i>Manually create a new order
                    </h6>
                </div>
                <?php if (!empty($suppliers) && !$authUser->isRoleSupplierUser()) { ?>
                    <div class="col-auto">
                        <h6 class="m-0 font-weight-bold text-primary">Select Company
                            <?php
                            echo Html::dropDownList('supplier_id', $suppliers, (string)$supplier_id,
                                [
                                    'class' => 'selectpicker form-control',
                                    'id' => 'suppliers',
                                ]);
                            ?>
                        </h6>
                    </div>
                <?php } ?>
                <?php if (!empty($users)) { ?>
                    <div class="col-auto">
                        <h6 class="m-0 font-weight-bold text-primary">Select User
                            <?php
                            echo Html::dropDownList('user_id', $users, (string)$user_id,
                                [
                                    'class' => 'selectpicker form-control',
                                    'id' => 'users',
                                ]);
                            ?>
                        </h6>
                    </div>
                <?php } ?>
            </div>
        </div>
        <form method="post" action="/orders/adding_order/" enctype="multipart/form-data" name="frmCreate">
            <input type="hidden" name="mode" value="1">
            <input type="hidden" name="order[user_id]" value="<?= $user_id ?: $authUser->getId(); ?>">
            <input type="hidden" name="order[supplier_id]" value="<?= $supplier_id; ?>">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="alert alert-danger" id="add_sku_error" style="display: none"></div>
                        <p class="form-control-static text-success"><b><a name="skus">Add SKUs using the search field below</a></b></p>
                        <div class="input-group col-sm-6">
                            <?php (new Scan(['id' => 'search_skus', 'type' => Scan::TYPE__SKU], [
                                'id' => 'btn_add_sku',
                                'title' => 'Add',
                                'icon' => 'fas fa-fw fa-plus',
                                'type' => 'button',
                            ]))->render(); ?>
                        </div>
                        <table class="table table-hover mt-4">
                            <colgroup>
                                <col width="*">
                                <col width="*">
                                <col width="*">
                                <col width="15%">
                                <col width="15%">
                                <col width="*">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Barcode</th>
                                <th>Product Name</th>
                                <th>Available in stock</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="skus_tbody">
                            </tbody>
                        </table>
                        <div class="row justify-content-end">
                            <div class="col-auto">
                                <button class="btn btn-primary btn-icon-split" type="button" onclick="
                                                event.preventDefault();
                                                frmCreate.mode.value = 2;
                                                frmCreate.submit();
                                                ">
                                    <span class="text text-white">Upload (CSV)</span>
                                    <span class="icon text-white">
                                        <i class="fas fa-fw fa-chevron-up"></i>
                                    </span>
                                </button>
                            </div>
                        </div>
                        <input type="hidden" name="new_del" value="<?= (int)$new_del; ?>">
                        <p class="form-control-static text-success"><b><a name="shipping">Shipping</a></b></p>
                        <p class="form-control-static">Choose your destination, carrier & despatch date. To avoid delays to your shipment please select an appropriate carrier for the size/destination of your item.</p>
                        <div class="row">
                            <input type="hidden" name="delivery[id]" value="<?= !$new_del ? $delivery['id'] : 0; ?>">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-md-4 col-form-label"><b>Delivery address</b></label>
                                </div>
                                <div class="form-group row">
                                    <label for="countries" class="col-md-4 col-form-label">Country <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <?= Html::dropDownListSelectpicker(
                                            'delivery[country_id]',
                                            $countries,
                                            isset($delivery['country_id']) ? $delivery['country_id'] : $country_id,
                                            [
                                                'class' => 'selectpicker form-control',
                                                'id' => 'countries',
                                                'data-actions-box' => 'true',
                                                'data-selected-text-format' => 'count == 1',
                                                'onchange' => 'new_del.value = 1;',
                                            ]
                                        ) ?>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[postcode]" class="col-md-4 col-form-label">Postcode <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <input name="delivery[postcode]" id="delivery[postcode]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['postcode']) ? View::toHtml($delivery['postcode']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[name]" class="col-md-4 col-form-label">Company Name</label>
                                    <div class="col-md-8">
                                        <input name="delivery[name]" id="delivery[name]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['name']) ? View::toHtml($delivery['name']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[contact]" class="col-md-4 col-form-label">Contact Name <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <input name="delivery[contact]" id="delivery[contact]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['contact']) ? View::toHtml($delivery['contact']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[phone]" class="col-md-4 col-form-label">Contact Tel <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <input name="delivery[phone]" id="delivery[phone]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['phone']) ? View::toHtml($delivery['phone']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[email]" class="col-md-4 col-form-label">Contact Email <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <input name="delivery[email]" id="delivery[email]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['email']) ? View::toHtml($delivery['email']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[address1]" class="col-md-4 col-form-label">Address 1 <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <input name="delivery[address1]" id="delivery[address1]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['address1']) ? View::toHtml($delivery['address1']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[address2]" class="col-md-4 col-form-label">Address 2</label>
                                    <div class="col-md-8">
                                        <input name="delivery[address2]" id="delivery[address2]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['address2']) ? View::toHtml($delivery['address2']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[address3]" class="col-md-4 col-form-label">Address 3</label>
                                    <div class="col-md-8">
                                        <input name="delivery[address3]" id="delivery[address3]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['address3']) ? View::toHtml($delivery['address3']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[town]" class="col-md-4 col-form-label">Town <span class="text-danger">*</span></label>
                                    <div class="col-md-8">
                                        <input name="delivery[town]" id="delivery[town]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['town']) ? View::toHtml($delivery['town']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label for="delivery[county]" class="col-md-4 col-form-label"><?= $country_id == 'US' ? 'State' : 'County'; ?> <span class="text-danger" id="county_span">*</span></label>
                                    <div class="col-md-8">
                                        <input name="delivery[county]" id="delivery[county]" onchange="new_del.value = 1;" type="text" class="form-control" value="<?= isset($delivery['county']) ? View::toHtml($delivery['county']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="row mt-5">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <?php
                                                $options = [
                                                    'id' => 'carrier_id',
                                                    'class' => 'form-control form-control-lg custom-select',
                                                    'onchange' => 'changeServiceOption();',
                                                ];
                                                echo Html::dropDownList('order[carrier_id]', $carriers, $carrier_id, $options);
                                                ?>
                                                <label for="carrier_id">Carrier Service</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <?php
                                        $options = [
                                            'class' => 'form-control form-control-lg custom-select',
                                            'id' => 'shipping_services_id',
                                        ];
                                        echo Html::dropDownList('order[shipping_services_id]', $shipping_services[$carrier_id], $shipping_service_id, $options);
                                        ?>
                                        <label for="shipping_services_id">Service Option</label>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="DDP">Terms <span class="text-danger">*</span></label>
                                            <?= Html::dropDownListSelectpicker(
                                                'order[DDP]',
                                                Shippings::TERMS,
                                                $order['DDP'] ?? Shippings::TYPE_TERM_DDU,
                                                [
                                                    'class' => 'selectpicker form-control',
                                                    'id' => 'DDP'
                                                ]
                                            ) ?>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="order[despatch_date]"><b>Despatch date <span class="text-danger">*</span></b></label>
                                            <input name="order[despatch_date]" id="order[despatch_date]" type="date" class="form-control" value="<?= date('Y-m-d',isset($order['despatch_date']) ? (strtotime($order['despatch_date']) > $in_date ? strtotime($order['despatch_date']) : $in_date) : $in_date); ?>" min="<?= date('Y-m-d',$in_date) ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="order[ref]"><b>Reference <span class="text-danger">*</span></b></label>
                                    <input name="order[ref]" id="order[ref]" type="text" class="form-control order-ref" value="<?= View::toHtml($order['ref'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="order[ref2]"><b>Customer Reference 2</b></label>
                                    <input name="order[ref2]" id="order[ref2]" type="text" class="form-control" value="<?= View::toHtml($order['ref2'] ?? ''); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <?php
                                                $options = [
                                                    'class' => 'form-control form-control-lg custom-select',
                                                    'id' => 'order_category',
                                                ];
                                                echo Html::dropDownList('order[category]', $order_categories, $order['category'] ?? OrderCategories::DEFAULT, $options);
                                                ?>
                                                <label for="order_category">Order Category</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <div class="form-group">
                                            <label for="printed_matter">Printed Matter</label>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input position-static" id="printed_matter" <?= $printed_matter ? 'checked' : '' ?> value="1" name="order[config][<?= OrdersConfig::TYPE_ORDER_PRINTED_MATTER?>]"/>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="eori_number">EORI Number</label>
                                            <input name="order[config][<?= OrdersConfig::TYPE_ORDER_EORI_NUMBER ?>]" id="eori_number" type="text" class="form-control" value="<?= View::toHtml($eori_number); ?>">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="vat_number">VAT Number</label>
                                            <input name="order[config][<?= OrdersConfig::TYPE_ORDER_VAT_NUMBER ?>]" id="vat_number" type="text" class="form-control" value="<?= View::toHtml($vat_number); ?>">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="ioss_number">IOSS Number</label>
                                            <input name="order[config][<?= OrdersConfig::TYPE_ORDER_IOSS_NUMBER ?>]" id="ioss_number" type="text" class="form-control" value="<?= View::toHtml($ioss_number); ?>">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="tax_id">TAX ID</label>
                                            <input name="order[config][<?= OrdersConfig::TYPE_ORDER_TAX_ID ?>]" id="tax_id" type="text" class="form-control" value="<?= View::toHtml($tax_id); ?>">
                                        </div>
                                    </div>
                                </div>
                               <div class="row mt-n3">
                                    <div class="col-6">
                                        <div class="form-group">
                                            <label for="purchase_order_number">Purchase Order Number</label>
                                            <input
                                                name="order[purchase_order_number]"
                                                id="purchase_order_number"
                                                type="text"
                                                class="form-control"
                                                maxlength="20"
                                                value="<?= View::toHtml($order['purchase_order_number'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p class="form-control-static text-success"><br/><b><a name="special">Special requirements</a></b></p>
                        <p class="form-control-static">If you have any special requirements for this order or wish to add some notes to the order for your own internal use, you can specify them below.</p>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="order[picking]"><b>Special picking instructions</b></label>
                                    <textarea class="form-control" id="order[picking]" name="order[picking]" rows="3"><?= isset($order['picking']) ? View::toHtml($order['picking']) : ''; ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="order[packing]"><b>Special packing instructions</b></label>
                                    <textarea class="form-control" id="order[packing]" name="order[packing]" rows="3"><?= isset($order['packing']) ? View::toHtml($order['packing']) : ''; ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="order[notes]"><b>Order Notes</b> - add any notes to this order for your own internal use only</label>
                                    <textarea class="form-control" id="order[notes]" name="order[notes]" rows="3"><?= isset($order['notes']) ? View::toHtml($order['notes']) : ''; ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <p class="form-control-static"><b>Additional Documentation</b></p>
                                <p class="form-control-static">All orders are despatched with a despatch note and customs documentation if required.</p>
                                <p class="form-control-static">However, if you have any specific additional documentation for this order you can upload it here (<span class="text-success">Must be in PDF format < 10Mb</span>) and it will be included too.</p>
                                <p class="form-control-static">Please do not upload promotional material.</p>
                                <input type="hidden" name="MAX_FILE_SIZE" value="10485760" />
                                <input type="file" class="form-control-file" name="docs" value="<?= isset($order['docs']) ? View::toHtml($order['docs']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back)); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-chevron-left"></i>
                            </span>
                            <span class="text">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" type="submit">
                            <span class="text text-white">Done</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script type="text/template" id="sku_tr_template">
    <tr class="{{from-bundle-id}} {{active}}">
        <td>{{barcode_html}}</td>
        <td>{{name}}</td>
        <td>
            <span>{{left}}</span>
            <input name="left[{{id}}]" type="hidden" class="form-control" value="{{left}}">
        </td>
        <td><input name="quantity[{{id}}]" type="number" class="sku-quantity form-control" {{from-bundle-qty}} min="0" step="1" value="{{qty}}"></td>
        <td><input name="price[{{id}}]" type="number" class="sku-price form-control" {{from-bundle-price}} min="0" step="any" value="{{price}}"></td>
        <td><button type="button" class="btn btn-danger js-btn-remove-sku" data-sku="{{barcode}}">Remove</button></td>
    </tr>
</script>
<script>
    var global_skus_to_search = <?= $available_skus; ?>;
    var global_skus_info = <?= $skus_info; ?>;
    var order_skus = <?= empty($quantities) ? '[]' : $quantities; ?>;
    var shipping_services = <?= View::toJs($shipping_services) ?>;
    var bundles_info = <?= $bundles_info; ?>;
    var order_bundles = <?= empty($order_bundles) ? '[]' : $order_bundles; ?>;

    function changeServiceOption()
    {
        $('#shipping_services_id option').remove();
        $.each(shipping_services[$('#carrier_id').val()], function (key, item) {
            $('#shipping_services_id').append($('<option>', { value : key }).text(item));
        });
    }
</script>
<style>
    .ui-autocomplete {
        max-height: 300px;
        overflow-y: auto;
        overflow-x: hidden;
    }
</style>
<?= View::include_js(BACKEND_JS, "add_sku.js")?>
<script>
    renderOrderSKUs();

    function addBundle(bundle_id, bundle_name, quantity, price) {
        $('#skus_tbody').append(`<tr id="bundle_${+bundle_id}">
                                        <td colspan="3">Bundle: ${html_escape(bundle_name)}</td>
                                        <td>
                                            <input name="bundle_quantity[${+bundle_id}]" type="number" class="form-control bundle_counter" min="0" step="1" value="${+quantity}">
                                        </td>
                                        <td>
                                            <input name="bundle_price[${+bundle_id}]" type="number" class="form-control" min="0" step="any" value="${html_escape(price)}">
                                        </td>
                                        <td><button type="button" class="btn btn-danger js-btn-remove-bundle">Remove</button></td>
                                    </tr>`);
        order_skus = order_bundles[bundle_id].SKUs;
        renderOrderSKUs({
            id: bundle_id,
            qty: order_bundles[bundle_id].quantity
        });
    }

    function addNewBundle(bundle_id, bundle_name, quantity, price, SKUs) {
        $('#skus_tbody').append(`<tr id="bundle_${+bundle_id}">
                                        <td colspan="3">Bundle: ${html_escape(bundle_name)}</td>
                                        <td>
                                            <input name="bundle_quantity[${+bundle_id}]" type="number" class="form-control bundle_counter_new" min="0" step="1" value="${+quantity}">
                                        </td>
                                        <td>
                                            <input name="bundle_price[${+bundle_id}]" type="number" class="form-control" min="0" step="any" value="${html_escape(price)}">
                                        </td>
                                        <td><button type="button" class="btn btn-danger js-btn-remove-bundle">Remove</button></td>
                                    </tr>`);
        order_skus = SKUs;
        renderOrderSKUs({
            id: bundle_id,
            qty: quantity
        });
    }

    if (order_bundles !== undefined) {
        for(let bundle_id in order_bundles) {
            addBundle(bundle_id, order_bundles[bundle_id].name, order_bundles[bundle_id].quantity, order_bundles[bundle_id].price)
        }
    }

    $(document).on('click', '.js-btn-remove-bundle', function () {
        let bundle_tr = $(this).closest('tr');
        let bundle_id = +bundle_tr.attr('id').replace('bundle_', '');
        $('.bundle_' + bundle_id).remove();
        bundle_tr.remove();
        let bundles_SKUs = Object.keys(bundles_info[bundle_id].SKUs);
        delete added_bundles[bundles_info[bundle_id].name];
        // for (const bundles_SKU of bundles_SKUs) {
        //     delete added_skus[bundles_SKU];
        // }
    });

    $(document).on('change', '.bundle_counter', function () {
        let bundle_tr = $(this).closest('tr');
        const bundle_id = +bundle_tr.attr('id').replace('bundle_', '');
        const count = +$(this).val();
        bundle_tr.siblings('.bundle_' + bundle_id).each(function () {
            const SKU_id = +$(this).find('.sku-quantity').attr('name').replace('quantity[', '').replace(']', '');
            if (SKU_id <= 0)
                return;
            for (let barcode in order_bundles[bundle_id].SKUs)
                if (+order_bundles[bundle_id].SKUs[barcode].id === SKU_id) {
                    $(this).find('.sku-quantity').val(+order_bundles[bundle_id].SKUs[barcode].quantity * count);
                    break;
                }
        });
    });

    $(document).on('change', '.bundle_counter_new', function () {
        let bundle_tr = $(this).closest('tr');
        const bundle_id = +bundle_tr.attr('id').replace('bundle_', '');
        const count = +$(this).val();
        bundle_tr.siblings('.bundle_' + bundle_id).each(function () {
            const SKU_id = +$(this).find('.sku-quantity').attr('name').replace('quantity[', '').replace(']', '');
            if (SKU_id <= 0)
                return;
            for (let barcode in bundles_info[bundle_id]['SKUs'])
                if (+bundles_info[bundle_id].SKUs[barcode].id === SKU_id) {
                    $(this).find('.sku-quantity').val(+bundles_info[bundle_id].SKUs[barcode].quantity * count);
                    break;
                }
        });
    });

    $('#suppliers').change(function() {
        frmCreate['order[supplier_id]'].value = this.value;
        frmCreate['order[user_id]'].value = '';
        frmCreate.mode.value = 3;
        frmCreate.submit();
    });

    $('#users').change(function() {
        frmCreate['order[user_id]'].value = this.value;
    });

    $('.order-ref').change(function () {
        this.value = this.value.replace(/\s/g, '');
    });

    function countryValue(countries) {
        if (Array.isArray(countries) && countries.includes($('#countries').val()))
            $('#county_span').prop('classList').remove('d-none');
        else
            $('#county_span').prop('classList').add('d-none');
    }

    $(document).ready(function () {
        countryValue(['US']);
        $('#countries').change(function () {
            countryValue(['US']);
        })
    });

</script>