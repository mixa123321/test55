<?php

use app\core\entities\User;
use app\core\entities\SKU;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\entities\Supplier;
use app\core\models\Warehouses;
use app\core\statical\Currencies;
use app\core\helpers\Barcode128Rule;

/**
 * @var User $authUser
 * @var Supplier $supplier
 * @var SKU $SKU
 * @var int $supplier_id
 * @var string $back
 * @var array $currencies
 * @var bool $supplier_read_only
 * @var array $min_stock_level_data
 * @var int $warehouse_id
 * @var array $countries
 * @var int $shop_id
 * @var array $inventory_ids
 * @var array $bundles_names
 * @var array $barcodes
 * @var bool $warehouse_virtual
 * @var string $cost_center
 * @var string $ns_pid
 * @var string $s_pid
 */

$warehouse = Warehouses::getById($warehouse_id);
$currency_warehouse = Currencies::getCurrency($warehouse->getCurrencyId());

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?= View::toHtml(!empty($SKU->getId()) ? "Edit " : "Create ") ?>SKU <?= !empty($SKU->getId()) ? '#'.$SKU->getId() : ''?>
        </h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <div class="col pl-0">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-fw fa-archive"></i> <?= View::toHtml($SKU->getName() ?: "New SKU") ?>
                </h6>
            </div>
            <?php if (!empty($shop_id) && !empty($inventory_ids)) { ?>
                <div class="col-auto pr-0">
                    <button class="btn btn-success btn-icon-split js-update-connected-sku" onclick="updateSKU(<?= "$shop_id, {$SKU->getId()}, " . View::toHtml(View::toJs($inventory_ids)); ?>);">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-sync"></i>
                            </span>
                        <span class="text">Shopify</span>
                    </button>
                </div>
            <?php } ?>
            <?php if ($SKU->getName() && !$authUser->isClientSide() && $warehouse_virtual) { ?>
                <div class="col-auto pr-0">
                    <button class="btn btn-success btn-icon-split js-in-reality">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-warehouse"></i>
                        </span>
                        <span class="text">In reality</span>
                    </button>
                </div>
            <?php } ?>
        </div>
        <div id="btn-used-SKU" class="col alert alert-warning d-none">
            SKU used in bundle<?= (count($bundles_names) === 1 ? ' ' : 's ') . View::toHtml(implode(', ', $bundles_names)) ?>
        </div>
        <form method="post" action="/SKUs/adding_sku" enctype="multipart/form-data" class="m-0">
            <div class="card-body">
                <input type="hidden" name="supplier_id" value="<?= $supplier_id; ?>">
                <input type="hidden" id="SKU_id" name="SKU[id]" value="<?= (int)$SKU->getId(); ?>">
                <input type="hidden" name="SKU[cost_value]" value="0">
                <input type="hidden" name="SKU[cost_currency]" value="GBP">
                <input type="hidden" name="SKU[state]" value="<?= ((int)$SKU->getId() !== 0) ? $SKU->getState() : 1; ?>">
                <input type="hidden" name="SKU[photo]" value="<?= !empty($SKU->getPhoto()) ? View::toHtml($SKU->getPhoto()) : ''; ?>">
                <input type="hidden" name="back" value="<?= View::toHtml($back); ?>">
                <input type="hidden" id="changePhoto" name="changePhoto" value="0">
                <div class="row justify-content-center">
                    <div class="col-md-11">
                        <div class="row bg-light mr-2">
                            <div class="col bg-white">
                                <?php if(!empty($suppliers)) { ?>
                                    <?php if (!$supplier_read_only) { ?>
                                        <div class="form-row">
                                            <div class="form-group col-12">
                                                <div class="form-group-element">
                                                    <select id="supplier_id" name="supplier_id" class="form-control form-control-lg custom-select">
                                                        <?php foreach ($suppliers as $row) { ?>
                                                            <option value="<?= $row['id'] ?>"<?= (!empty($supplier_id) && $row['id'] == $supplier_id) ? " selected" : "" ?>><?= View::toHtml($row['name']) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                    <label for="supplier_id">Company</label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } else { ?>
                                        <div class="form-row">
                                            <div class="form-group col-12">
                                                <p class="form-control-static font-weight-bold text-primary pl-2">
                                                    <i class="fa fa-user"></i> <?= View::toHtml($suppliers[$supplier_id]['name']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                                <?php if (!empty($supplier)) {  ?>
                                    <div class="form-row">
                                        <div class="form-group col-12">
                                            <p class="form-control-static font-weight-bold text-primary pl-2">
                                                <i class="fa fa-user"></i> <?= View::toHtml($supplier->getName())?>
                                            </p>
                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="form-row">
                                    <div class="col-4 align-self-center">
                                        <div class="form-group mb-0">
                                            <div class="row border m-0 p-3">
                                                <div class="col-6 p-0">
                                                    <label for="imgPhoto">Select photo</label><br/>
                                                    <img class="img-thumbnail m-0" style="max-height: 70px;" title="photo" src="" id="imgPhoto"/>
                                                </div>
                                                <div class="col-6 p-0">
                                                    <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
                                                    <input name="null_photo" id="null_photo" type="hidden">
                                                    <input name="photo" id="photo" type="file" class="d-none">
                                                    <button type="button" id="cancel_img" class="btn btn-sm btn-outline-orange h-100">
                                                        Undo photo
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-8">
                                        <div class="form-row">
                                            <div class="col-lg-12">
                                                <div class="form-group">
                                                    <div class="form-group-element">
                                                        <input name="SKU[name]" id="name" type="text" class="form-control form-control-lg" placeholder=" "
                                                               value="<?= View::toHtml($SKU->getName()); ?>">
                                                        <label for="name">Name <span class="text-danger">*</span></label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <div class="form-group-element">
                                                        <input name="SKU[cost_value]" id="cost_value" type="number" step="any" min="0" class="form-control form-control-lg" placeholder=" "
                                                               value="<?= View::toHtml($SKU->getCostValue()); ?>">
                                                        <label for="cost_value">Cost value</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-5">
                                                <div class="form-group">
                                                    <div class="form-group-element">
                                                        <select id="cost_currency" name="SKU[cost_currency]" class="form-control form-control-lg custom-select">
                                                            <?php foreach ($currencies as $id => $currency) { ?>
                                                                <option value="<?= View::toHtml($id) ?>"<?= $id === ($SKU->getCostCurrency() ?: $currency_warehouse) ? " selected" : "" ?>><?= View::toHtml($currency) ?></option>
                                                            <?php } ?>
                                                        </select>
                                                        <label for="cost_currency">Cost currency</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="col-lg-6">
                                                <div class="form-group">
                                                    <div class="form-group-element">
                                                        <input name="SKU[market_value]" id="market_value" type="number" step="any" min="0" class="form-control form-control-lg" placeholder=" "
                                                               value="<?= View::toHtml($SKU->getMarketValue()); ?>">
                                                        <label for="market_value">Market value</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-5">
                                                <div class="form-group">
                                                    <div class="form-group-element">
                                                        <select id="market_currency" name="SKU[market_currency]" class="form-control form-control-lg custom-select">
                                                            <?php foreach ($currencies as $id => $currency) { ?>
                                                                <option value="<?= View::toHtml($id) ?>"<?= $id === ($SKU->getMarketCurrency() ?: $currency_warehouse) ? " selected" : "" ?>><?= View::toHtml($currency) ?></option>
                                                            <?php } ?>
                                                        </select>
                                                        <label for="market_currency">Market currency</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-1">
                                                <div class="form-group">
                                                    <label for="gs1">GS1</label>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input position-static" id="gs1" name="SKU[gs1]"<?= $SKU->getGs1() ? ' checked="checked"' : ''; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4 bg-white">
                                <div class="col-12">
                                    <div class="form-row">
                                        <div class="col-12 alert alert-danger mt-1 mb-1 d-none" id="error-barcode"></div>
                                    </div>
                                    <div class="form-group-element">
                                        <input type="text" id="add-barcode" class="form-control form-control-lg" placeholder=" " value="" maxlength="255">
                                        <label for="add-barcode">Add barcode <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-12" id="barcodes"></div>
                                <div class="barcodes-content d-none"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input name="SKU[DDS]" id="DDS" type="text" class="form-control form-control-lg" placeholder=" "
                                               value="<?= View::toHtml($SKU->getDDS()); ?>">
                                        <label for="DDS">DDS</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input name="SKU[exemption_TARIC]" id="exemption_TARIC" type="text" class="form-control form-control-lg" placeholder=" "
                                               value="<?= View::toHtml($SKU->getExemptionTARIC()); ?>">
                                        <label for="exemption_TARIC">Exemption TARIC</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input name="SKU[ns_pid]" id="ns_pid" type="text" maxlength="255" class="form-control form-control-lg" placeholder=" " value="<?=View::toHtml($ns_pid ?? '')?>">
                                        <label for="ns_pid">NS-PID</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input name="SKU[s_pid]" id="s_pid" type="text" maxlength="255" class="form-control form-control-lg" placeholder=" " value="<?=View::toHtml($s_pid ?? '')?>">
                                        <label for="s_pid">S-PID</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input name="SKU[customer_ref]" id="customer_ref" type="text" class="form-control form-control-lg" placeholder=" "
                                               value="<?= View::toHtml($SKU->getCustomerRef()); ?>">
                                        <label for="customer_ref">Customer Ref<?= empty($SKU->getId()) ? ' <span class="text-danger">*</span>' : ''; ?></label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input name="SKU[unique_customer_ref]" id="unique_customer_ref" type="text" class="form-control form-control-lg" placeholder=" "
                                               value="<?= View::toHtml($SKU->getUniqueCustomerRef()); ?>">
                                        <label for="unique_customer_ref">Unique Customer Ref</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-10">
                                <div class="form-group">
                                    <label>Customer Ref 2 (Product description)</label>
                                    <textarea rows="4" class="form-control" name="SKU[customer_ref2]" id="customer_ref2"><?= View::toHtml($SKU->getCustomerRef2()); ?></textarea>
                                </div>
                            </div>
                            <div class="col-lg-2 mt-4">
                                <div class="form-group mb-0">
                                    <label for="ignore_WBE">Ignore Weeks Before Expiry</label>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input position-static" id="ignore_WBE" name="SKU[ignore_WBE]"<?= $SKU->getIgnoreWBE() ? ' checked="checked"' : ''; ?>">
                                    </div>
                                </div>
                                <?php if ($authUser->isRoleSA()) { ?>
                                    <div class="form-group">
                                        <label for="serial_number">Serial Number</label>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input position-static" id="serial_number" name="SKU[serial_number]"<?= $SKU->getSerialNumber() ? ' checked="checked"' : ''; ?>">
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input name="SKU[season]" id="season" type="text" class="form-control form-control-lg" placeholder=" "
                                               value="<?= View::toHtml($SKU->getSeason()); ?>">
                                        <label for="hs_code">Season</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input name="SKU[hs_code]" id="hs_code" type="text" class="form-control form-control-lg" placeholder=" "
                                               value="<?= View::toHtml($SKU->getHsCode()); ?>">
                                        <label for="hs_code">HS Code <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-2">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <select id="country_origin" name="SKU[country_id]" class="form-control form-control-lg custom-select">
                                            <?php foreach ($countries as $id => $country) { ?>
                                                <option value="<?= View::toHtml($id) ?>"<?= $id === ($SKU->getCountryId() ?: 'UK') ? " selected" : "" ?>><?= View::toHtml($country) ?></option>
                                            <?php } ?>
                                        </select>
                                        <label for="country_origin">Country of Origin <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group-element">
                                    <input name="SKU[cost_centre]" id="cost_centre" type="text" class="form-control form-control-lg" placeholder=" " value="<?=View::toHtml($cost_center ?? '')?>">
                                    <label for="cost_centre">Cost Centre</label>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($min_stock_level_data)) { ?>
                            <h4 class="mt-4">Inventory</h4>
                            <div class="row">
                                <div class="col-lg-6">
                                    <table id="min_stock_level_data" class="table table-hover mt-2 mb-0">
                                        <colgroup>
                                            <col width="30%">
                                            <col width="70%">
                                        </colgroup>
                                        <thead>
                                        <th>Warehouse</th>
                                        <th>Min Stock Level</th>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                    <script>
                                        const supplier_id = parseInt('<?= $supplier_id ?>');
                                        const min_stock_level_data = <?= View::toJs(json_decode($min_stock_level_data, true)) ?>;

                                        const supplier_id_el = document.getElementById('supplier_id');

                                        if (supplier_id > 0) {
                                            if (min_stock_level_data[supplier_id] !== undefined)
                                                setMinStockLevelList(min_stock_level_data[supplier_id]);
                                        } else if (supplier_id_el) {
                                            let s_id = +supplier_id_el.value;
                                            if (s_id > 0 && min_stock_level_data[s_id] !== undefined)
                                                setMinStockLevelList(min_stock_level_data[s_id]);
                                        }
                                        if (supplier_id_el)
                                            supplier_id_el.addEventListener('change', ({target}) => {
                                                if (+target.value > 0 && min_stock_level_data[+target.value] !== undefined)
                                                    setMinStockLevelList(min_stock_level_data[+target.value]);
                                            })
                                        function setMinStockLevelList(min_stock_level_Arr) {
                                            $('#min_stock_level_data').find('tbody').html(min_stock_level_Arr.reduce((t, stock_level_data) => {
                                                t += ` <tr>
                                                        <td>${html_escape(stock_level_data.warehouse_name)}</td>
                                                        <td>
                                                            <input type="number"
                                                                   min="0"
                                                                   name="min_stock_level[${+stock_level_data.warehouse_id}]"
                                                                   value="${stock_level_data.value ? html_escape(stock_level_data.value) : ''}"
                                                                   step="1"
                                                                   class="form-control"
                                                            >
                                                        </td>
                                                    </tr>`;
                                                return t;
                                            }, ''));
                                        }
                                    </script>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-orange pr-3" type="submit">
                            <span class="mr-1">Done</span>
                            <i class="fas fa-fw fa-chevron-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="inventorySKU">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Inventory</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <div class="row">
                        <div class="col-12">
                            <b>WMS</b>
                        </div>
                        <div class="col-6 form-group mb-1">
                            <div class="form-group-element">
                                <input id="old_country_id" type="text" class="form-control form-control-lg" placeholder=" " disabled />
                                <label for="old_country_id">Country of Origin</label>
                            </div>
                        </div>
                        <div class="col-6 form-group mb-1">
                            <div class="form-group-element">
                                <input id="old_hs_code" type="text" class="form-control form-control-lg" placeholder=" " disabled />
                                <label for="old_hs_code">HS Code</label>
                            </div>
                        </div>
                        <div class="col-6 form-group mb-1">
                            <div class="form-group-element">
                                <input id="old_cost" type="text" class="form-control form-control-lg" placeholder=" " disabled />
                                <label for="old_cost">Cost</label>
                            </div>
                        </div>
                        <div class="col-12 mt-2">
                            <b>Shopify</b>
                        </div>
                        <div class="col-12 form-group mb-1">
                            <div class="form-group-element">
                                <select id="inventory_id" name="inventory_id" class="form-control form-control-lg custom-select">
                                </select>
                                <label for="inventory_id">Inventory Id</label>
                            </div>
                        </div>
                        <div class="col-6 form-group mb-1">
                            <div class="form-group-element">
                                <input id="new_country_id" type="text" class="form-control form-control-lg" placeholder=" " disabled />
                                <label for="new_country_id">Country of Origin</label>
                            </div>
                        </div>
                        <div class="col-6 form-group mb-1">
                            <div class="form-group-element">
                                <input id="new_hs_code" type="text" class="form-control form-control-lg" placeholder=" " disabled />
                                <label for="new_hs_code">HS Code</label>
                            </div>
                        </div>
                        <div class="col-6 form-group mb-1">
                            <div class="form-group-element">
                                <input id="new_cost" type="text" class="form-control form-control-lg" placeholder=" " disabled />
                                <label for="new_cost">Cost</label>
                            </div>
                        </div>
                        <div id="getInventoryError" class="col-12 text-white text-center mb-1 d-none">
                            <p class="form-control-static bg-danger p-1 m-0" id="msgInventoryError"></p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-danger js-update-confirm" disabled>Update</button>
            </div>
        </div>
    </div>
</div>
<?= View::include_js_with_params(BACKEND_JS, "handle_image_form.js", [
    'imgFileInputId' => 'photo',
    'imgId' => 'imgPhoto',
    'cancelImgId' => 'cancel_img',
    'setNullId' => 'null_photo',
    'defaultCurrentSrc' => addslashes(COMMON_IMG . '\photo.png'),
    'currentSrc' => empty($SKU->getPhoto()) ? null : SKU::showPhotoSKU(urlencode($SKU->getPhoto()))
]); ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'SKU_in_reality.php'; ?>
<script>

    $(document).ready(function () {
        class Barcodes {
            constructor() {
                this.reg_exp = new RegExp('<?= addslashes(Barcode128Rule::REGEXP_BARCODE_128); ?>');
                this.barcodes = <?= View::toJs($barcodes); ?>;
            }
            addEvents() {
                let self = this;
                let add_barcode = $('#add-barcode');
                function addBarcode(input) {
                    let barcode = input.value;
                    barcode = $.trim(barcode);
                    if (!barcode) {
                        $(input).val('');
                        $(input).focus();
                        showError('error-barcode', 'Empty value', 3000);
                        return false;
                    }
                    if (!self.reg_exp.test(barcode)) {
                        $(input).val('');
                        $(input).focus();
                        showError('error-barcode', 'Not allowed symbols', 3000);
                        return false;
                    }
                    if (self.checkExistBarcode(barcode)) {
                        showError('error-barcode', 'This value already exists', 3000);
                        return false;
                    }
                    self.appendButton(barcode);
                    $(input).val('');
                    $(input).focus();
                }
                add_barcode.change(function () {
                    addBarcode(this);
                });
                add_barcode.keydown(function (e) {
                    if (e.keyCode === 13) {
                        e.preventDefault();
                        addBarcode(this);
                        return false;
                    }
                });
                $(document).on("click", '.active-barcodes .icon', function () {
                    $(this).parent('.active-barcodes').remove();
                    self.removeHidden(this.value);
                });
                return this;
            }
            checkExistBarcode(barcode) {
                let exist_value = false;
                $('.barcodes-values').each(function () {
                    if (this.value.toUpperCase() === barcode.toUpperCase())
                        exist_value = true;
                });
                return exist_value;
            }
            removeHidden(barcode) {
                $('.barcodes-values[value="' + barcode + '"]').remove();
            }
            reFill() {
                let self = this;
                $('.barcodes-values-common').remove();
                $('.active-barcodes').remove();
                for (const barcode of this.barcodes) {
                    this.appendButton(barcode);
                }
                $( "#barcodes" ).sortable();
            }
            getButton(barcode) {
                const b = html_escape(barcode);
                return '<li class="btn btn-success btn-icon-split m-0 active-barcodes ml-1 mt-1">' +
                    '<span class="text text-white">' + b + '</span>' +
                    '<span class="icon text-white"><i class="fas fa-fw fa-times"></i></span>' +
                    '<input type="hidden" class="barcodes-values-common barcodes-values" name="barcodes[]" value="' + b + '">' +
                    '</li>';
            }
            appendButton(barcode) {
                $('#barcodes').append(this.getButton(barcode));
            }
        }
        let barcodes = (new Barcodes()).addEvents();
        barcodes.reFill();
    });

    var new_params = new Map();

    $('#market_currency').change(function () {
        let current_currency = <?= View::toJs($SKU->getMarketCurrency() ?: $currency_warehouse); ?>;
        let bundles_names = <?= View::toJs($bundles_names); ?>;
        if (bundles_names.length !== 0 && this.value !== current_currency) {
            $('#btn-used-SKU').prop('classList').remove('d-none');
        } else {
            $('#btn-used-SKU').prop('classList').add('d-none');
        }
    });

    $('.js-update-connected-sku').mouseover(() => {
        $('.js-update-connected-sku').tooltip({
            "html": true,
            "title": "Country of Origin<br/>HS code<br/>Cost"
        });
    });

    $('#inventory_id').change(() => {
        setNewParams(new_params.get($('#inventory_id').val()))
    });

    function updateSKU(shop_id, SKU_id, inventory_ids) {
        $('.js-update-connected-sku').tooltip('hide');
        $('#getInventoryError').prop('classList').add('d-none');
        $('.js-update-confirm').prop('disabled', true);
        $('#inventory_id').prop('disabled', true);
        $('#old_hs_code').val('');
        $('#old_cost').val('');
        $('#old_country_id').val('');
        $('#new_hs_code').val('');
        $('#new_cost').val('');
        $('#new_country_id').val('');
        $.ajax({
            url: "/SKUs/get_inventory",
            method: "POST",
            dataType: 'json',
            data: { shop_id : shop_id, SKU_id : SKU_id, inventory_ids : inventory_ids },
            success: function (json) {
                if ('error' in json) {
                    $('#getInventoryError').prop('classList').remove('d-none');
                    $('#msgInventoryError').html(json.error);
                } else {
                    let data = JSON.parse(json.success);
                    $('#old_hs_code').val(data.old_hs_code);
                    $('#old_cost').val(data.old_cost);
                    $('#old_country_id').val(data.old_country_id);
                    new_params.clear();
                    $('#inventory_id option').remove();
                    $.each(data.new_data, function (key, item) {
                        $('#inventory_id').append($('<option>', { value : key }).text(key));
                        new_params.set(key, [item.new_hs_code, item.new_cost, item.new_country_id]);
                    });
                    setNewParams(new_params.get($('#inventory_id').val()));
                    $('#inventory_id').prop('disabled', false);
                    $('.js-update-confirm').prop('disabled', false);
                }
            },
            error: function (json) {
                $('#getInventoryError').prop('classList').remove('d-none');
                $('#msgInventoryError').html('Connection error');
            }
        });
        $('#inventorySKU').modal('show');
    }

    function setNewParams(params)
    {
        $('#new_hs_code').val(params[0]);
        $('#new_cost').val(params[1]);
        $('#new_country_id').val(params[2]);
    }

    <?php if (!empty($SKU->getId())) { ?>
    $('.js-update-confirm').click(() => {
        $.ajax({
            url: "/SKUs/set_inventory",
            method: "POST",
            dataType: 'json',
            data: { SKU_id : <?= $SKU->getId(); ?>, hs_code : $('#new_hs_code').val(), cost : $('#new_cost').val(), country_id : $('#new_country_id').val() },
            success: function (json) {
                if ('error' in json) {
                    $('#getInventoryError').prop('classList').remove('d-none');
                    $('#msgInventoryError').html(json.error);
                } else {
                    $('#inventorySKU').modal('hide');
                    location.reload();
                }
            },
            error: function (json) {
                $('#getInventoryError').prop('classList').remove('d-none');
                $('#msgInventoryError').html('Connection error');
            }
        });
        $('.js-update-confirm').prop('disabled', true);
    });
    <?php } ?>
</script>

