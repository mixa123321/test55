<?php
/**
 * @var $authUser User
 * @var $supplier Supplier
 * @var $admins array
 * @var $user_page bool
 * @var $warehouses array
 * @var $settings array
 * @var $back string
 */

use app\backend\models\Suppliers;
use app\core\entities\Supplier;
use app\core\entities\SuppliersConfig;
use app\core\entities\User;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\View;
use app\core\helpers\Html;
use app\core\statical\Account;

if (!isset($user_page))
    $user_page = false;
$defaultLogoSrc = addslashes(COMMON_IMG . '\photo.png');

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= View::toHtml($supplier->getName() ? "Edit " : "Create ") ?>
            company</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-user-circle"></i> <?= View::toHtml($supplier->getName() ?: "New company") ?>
            </h6>
        </div>
        <form method="post" action="/<?= $user_page ? 'account' : 'suppliers'; ?>/adding_supplier"
              enctype="multipart/form-data" id="form">
            <div class="card-body">
                <input type="hidden" name="supplier_data[id]" value="<?= $supplier->getId(); ?>">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <h4>Company information</h4>
                        <hr class="mt-3">
                        <div class="form-row">
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input name="supplier_data[name]" id="name" type="text"
                                                       class="form-control form-control-lg" placeholder=" "
                                                       value="<?= View::toHtml($supplier->getName()); ?>">
                                                <label for="name">Company Name<span
                                                            class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input name="supplier_data[contact]" id="contact" type="text"
                                                       class="form-control form-control-lg" placeholder=" "
                                                       value="<?= View::toHtml($supplier->getContact()); ?>">
                                                <label for="contact">Phone</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input name="supplier_data[email]" id="email" type="text"
                                                       class="form-control form-control-lg" placeholder=" "
                                                       value="<?= View::toHtml($supplier->getEmail()); ?>">
                                                <label for="email">Email <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="row border ml-1 mr-0 py-3">
                                    <div class="col-md-6 align-self-center">
                                        <div class="form-group m-0">
                                            <label for="logo">Select Logo</label>
                                            <input type="hidden" name="supplier_data[logo]"
                                                   value="<?= View::toHtml($supplier->getLogo()); ?>">
                                            <img class="img-thumbnail"
                                                 style="max-height: 70px;"
                                                 title="logo"
                                                 src="<?= !empty($supplier->getLogo()) ? Suppliers::showLogoSrc($supplier->getLogo()) : ''; ?>"
                                                 id="imgLogo"/>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
                                        <input name="null_logo" id="null_logo" type="hidden">
                                        <input name="logo" id="logo" type="file" class="d-none">
                                        <button type="button" id="cancel_img"
                                                class="btn btn-sm btn-outline-orange h-100 w-100">
                                            Undo Logo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php if (!$user_page) { ?>
                            <h4 class="mt-4">Addition</h4>
                            <hr class="mt-3">
                            <div class="row">
                                <div class="col-6">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="type_account1">Type of account <span
                                                            class="text-danger">*</span></label><br/>
                                                <?php foreach (Account::getTypeAccount() as $key) { ?>
                                                    <div class="form-check form-check-inline">
                                                        <input type="radio" class="form-check-input"
                                                               id="type_account<?= $key; ?>"
                                                               name="supplier_data[type_account]"
                                                               value="<?= $key; ?>"<?= $key == $supplier->getTypeAccount() ? " checked" : "" ?>>
                                                        <label class="form-check-label"
                                                               for="type_account<?= $key; ?>"><?= Account::getNameAccount($key) ?></label>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <div class="form-group-element">
                                                    <?php if ($authUser->isRoleSA())
                                                        echo Html::dropDownList(
                                                            'supplier_data[user_id]',
                                                            $admins,
                                                            (string)$supplier->getUserId(),
                                                            [
                                                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                                                'id' => 'user_id',
                                                            ]
                                                        );
                                                    else { ?>
                                                        <input name="supplier_data[user_id]" type="hidden"
                                                               value="<?= $authUser->getId(); ?>">
                                                        <p class="form-control form-control-lg form-control-static">
                                                            <b><?= View::toHtml($authUser->getLogin()) ?></b></p>
                                                    <?php } ?>
                                                    <label for="user_id">Account manager <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label>Packing slip block</label><br/>
                                            <div class="row border m-0 mb-3 py-2">
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="offer_returns">Offer Returns</label>
                                                        <div class="form-check">
                                                            <input type="checkbox"
                                                                   class="form-check-input position-static"
                                                                   id="offer_returns"
                                                                   name="supplier_data[offer_returns]"
                                                                <?= $supplier->isOfferReturns() ? ' checked' : ''; ?>>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="return_block_regular">Type of return</label><br>
                                                        <div class="form-check form-check-inline">
                                                            <input type="radio"
                                                                   class="form-check-input"
                                                                   id="return_block_regular"
                                                                <?= $supplier->isCustomReturn() ? '' : 'checked' ?>
                                                                   name="supplier_data[custom_return]"
                                                                   value="0"
                                                                <?= !$supplier->isOfferReturns() ? 'disabled' : ''; ?>
                                                            >
                                                            <label class="form-check-label" for="return_block_regular">Regular</label>
                                                        </div>
                                                        <div class="form-check form-check-inline">
                                                            <input type="radio"
                                                                   class="form-check-input"
                                                                   id="return_block_custom"
                                                                <?= $supplier->isCustomReturn() ? 'checked' : '' ?>
                                                                   name="supplier_data[custom_return]"
                                                                   value="1"
                                                                <?= !$supplier->isOfferReturns() ? 'disabled' : ''; ?>
                                                            >
                                                            <label class="form-check-label" for="return_block_custom">Custom</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group">
                                                        <label for="offer_returns">Batch no./Expiry detail</label>
                                                        <div class="form-check">
                                                            <input type="checkbox"
                                                                   class="form-check-input position-static"
                                                                   id="offer_returns"
                                                                   name="supplier_data[packing_slip_batch_expired]"
                                                                <?= $supplier->isPackingSlipBatchExpired() ? ' checked' : ''; ?>>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-12 p-2 <?= !$supplier->isOfferReturns() ? 'd-none' : ''; ?>"
                                                     id="return_block_settings">
                                                    <div class="row border p-2 m-1 <?= !$supplier->isOfferReturns() || $supplier->isCustomReturn() ? 'd-none' : ''; ?>"
                                                         id="regular_ps">
                                                        <div class="col-md-12">
                                                            <div class="form-group mb-0">
                                                                <div class="form-check">
                                                                    <input type="checkbox"
                                                                           class="form-check-input"
                                                                           id="offer_exchanges"
                                                                           name="supplier_data[offer_exchanges]"
                                                                        <?= $supplier->isOfferExchanges() ? ' checked' : ''; ?>>
                                                                    <label for="offer_exchanges">Offer Exchanges</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row border p-2 m-1 <?= !$supplier->isOfferReturns() || !$supplier->isCustomReturn() ? ' d-none' : ''; ?>"
                                                         id="custom_ps">
                                                        <div class="col-md-12">
                                                            <div class="row">
                                                                <div class="col-md-12">
                                                                    <p class="form-control-static">This file will be
                                                                        attached instead of the standard return
                                                                        block</p>
                                                                    <p class="form-control-static"><span
                                                                                class="text-success">Must be in PDF format &lt; 10Mb</span>
                                                                    </p>
                                                                    <input type="hidden" name="MAX_FILE_SIZE"
                                                                           value="10485760">
                                                                    <input type="file" class="form-control-file"
                                                                           name="custom-return-doc"
                                                                           id="custom_return_doc" value="">
                                                                    <?php if ($supplier->existCustomReturnDocFile()) { ?>
                                                                        <input type="hidden"
                                                                               name="clear_custom_return_doc" value="0">
                                                                        <div id="custom_return_doc_block">
                                                                            <div class="row mt-2">
                                                                                <div class="col">
                                                                                    <button
                                                                                            id="print_custom_return_doc"
                                                                                            class="btn btn-primary float-left"
                                                                                            type="button"
                                                                                            data-toggle="tooltip"
                                                                                            data-placement="top"
                                                                                            title="<?= View::toHtml($supplier->getCustomReturnDoc()) ?>"
                                                                                            onmouseover="$(event.target).tooltip('show')"
                                                                                    >
                                                                                        Print loaded file
                                                                                    </button>
                                                                                </div>
                                                                                <div class="col">
                                                                                    <button
                                                                                            id="clear_custom_return_doc"
                                                                                            class="btn btn-danger float-right"
                                                                                            type="button"
                                                                                            data-toggle="tooltip"
                                                                                            data-placement="top"
                                                                                            title="<?= View::toHtml($supplier->getCustomReturnDoc()) ?>"
                                                                                            onmouseover="$(event.target).tooltip('show')"
                                                                                    >
                                                                                        Clear
                                                                                    </button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php } ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($authUser->hasAdminAccess()) { ?>
                                                    <div class="col-md-12">
                                                        <div class="form-group my-2">
                                                            <div class="form-group-element">
                                                                <textarea class="form-control form-control-lg"
                                                                          name="supplier_data[packing_slip_additional_information]"
                                                                          id="packing_slip_additional_information"
                                                                          style="resize: none"
                                                                          oninput="auto_grow(this)"><?= View::toHtml($supplier->getPackingSlipAdditionalInformation()) ?></textarea>
                                                                <label for="packing_slip_additional_information">Additional Information</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label>SKU block</label><br/>
                                            <div class="row border m-0 mb-3 py-2">
                                                <div class="col-md-7">
                                                    <div class="form-group mb-0">
                                                        <div class="row">
                                                            <div class="col-auto">
                                                                <label for="has_weeks_before_expiry">Weeks before expiry</label>
                                                                <div class="form-check">
                                                                    <input type="checkbox"
                                                                           class="form-check-input position-static"
                                                                           id="has_weeks_before_expiry"
                                                                           name="supplier_data[has_weeks_before_expiry]"
                                                                           onchange="$('#weeks_before_expiry_block').toggleClass('d-none', !$(this).is(':checked'))"
                                                                        <?= $supplier->hasWeeksBeforeExpiry() ? ' checked' : ''; ?>
                                                                    >
                                                                </div>
                                                            </div>
                                                            <div class="col <?= $supplier->hasWeeksBeforeExpiry() ? '' : 'd-none'; ?>"
                                                                 id="weeks_before_expiry_block">
                                                                <div class="form-group-element">
                                                                    <input name="supplier_data[weeks_before_expiry]"
                                                                           id="weeks_before_expiry" type="number"
                                                                           class="form-control form-control-lg" placeholder=" "
                                                                           value="<?= $supplier->getWeeksBeforeExpiry(); ?>"
                                                                           min="0" step="1">
                                                                    <label for="weeks_before_expiry_number">Count</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="form-group mb-0">
                                                        <label for="product_price">WMS Product Price</label><br/>
                                                        <div class="form-check">
                                                            <input type="checkbox"
                                                                   class="form-check-input position-static"
                                                                   id="product_price"
                                                                   name="supplier_data[product_price]"
                                                                <?= $supplier->getProductPrice() ? ' checked' : ''; ?>>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($authUser->hasAdminAccess()) { ?>
                                                    <div class="col-md-12 mt-2">
                                                        <div class="form-group mb-0">
                                                            <div class="form-group-element">
                                                                <textarea class="form-control form-control-lg"
                                                                          name="supplier_data[default_description_of_goods]"
                                                                          id="default_description_of_goods"
                                                                          style="resize: none"
                                                                          oninput="auto_grow(this)"
                                                                ><?= View::toHtml($supplier->getDefaultDescriptionOfGoods()) ?></textarea>
                                                                <label for="default_description_of_goods">Default description of goods</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <label>EU PID Block</label><br/>
                                            <div class="row border m-0 mb-3 py-2">
                                                <div class="col-md-12">
                                                    <table class="table table-borderless mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th></th>
                                                                <th>NS-PID</th>
                                                                <th>S-PID</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td class="align-middle">Shopify</td>
                                                                <td>
                                                                    <input type="text" class="form-control" name="shopify_ns_pid_attribute" placeholder="SKU (default)" title="Use the metafield's Name as shown on the product page in Shopify - not its internal key" value="<?= View::toHtml($settings[SuppliersConfig::TYPE_SHOPIFY_NS_PID_ATTRIBUTE] ?? ''); ?>">
                                                                </td>
                                                                <td class="align-middle text-success"><b>Barcode</b></td>
                                                            </tr>
                                                            <tr>
                                                                <td class="align-middle">WooCommerce</td>
                                                                <td>
                                                                    <input type="text" class="form-control" name="woocommerce_ns_pid_attribute" placeholder="SKU (default)" value="<?= View::toHtml($settings[SuppliersConfig::TYPE_WOOCOMMERCE_NS_PID_ATTRIBUTE] ?? ''); ?>">
                                                                </td>
                                                                <td class="align-middle text-success"><b>GTIN, UPC, EAN, or ISBN</b></td>
                                                            </tr>
                                                            <tr>
                                                                <td class="align-middle">Magento 2</td>
                                                                <td>
                                                                    <input type="text" class="form-control" name="magento_ns_pid_attribute" placeholder="SKU (default)" value="<?= View::toHtml($settings[SuppliersConfig::TYPE_MAGENTO_NS_PID_ATTRIBUTE] ?? ''); ?>">
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control" name="magento_s_pid_attribute" placeholder="Name" value="<?= View::toHtml($settings[SuppliersConfig::TYPE_MAGENTO_S_PID_ATTRIBUTE] ?? ''); ?>">
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <p class="text-muted small mb-0">Leave NS-PID blank to use the SKU by default, or enter the name of the attribute to map it from instead.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label>Order block</label><br/>
                                            <div class="row border m-0 mb-4 py-2">
                                                <div class="col-md-7">
                                                    <div class="form-group mb-0">
                                                        <div class="row">
                                                            <div class="col-auto">
                                                                <label for="utilise_despatch_date">Utilise despatch date</label>
                                                                <div class="form-check">
                                                                    <input type="checkbox"
                                                                           class="form-check-input position-static"
                                                                           id="utilise_despatch_date"
                                                                           name="supplier_data[utilise_despatch_date]"
                                                                           onchange="$('#days_before_despatch').toggleClass('d-none', !$(this).is(':checked'))"
                                                                        <?= $supplier->getUtiliseDespatchDate() ? ' checked' : ''; ?>
                                                                    >
                                                                </div>
                                                            </div>
                                                            <div class="col <?= $supplier->getUtiliseDespatchDate() ? '' : 'd-none'; ?>" id="days_before_despatch">
                                                                <div class="form-group-element">
                                                                    <input name="supplier_data[days_before_despatch]"
                                                                           id="days_before_despatch" type="number"
                                                                           class="form-control form-control-lg" placeholder=" "
                                                                           value="<?= $supplier->getDaysBeforeDespatch(); ?>"
                                                                           min="0" step="1">
                                                                    <label for="days_before_despatch">Days</label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="form-group mb-0">
                                                        <label for="order_inserts">Use order inserts</label><br/>
                                                        <div class="form-check">
                                                            <input type="checkbox"
                                                                   class="form-check-input position-static"
                                                                   id="order_inserts"
                                                                   name="supplier_data[order_inserts]"
                                                                <?= $supplier->getOrderInserts() ? ' checked' : ''; ?>>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="no_action_returns">No Action Required (Returns)</label><br/>
                                                <div class="form-check">
                                                    <input type="checkbox"
                                                           class="form-check-input position-static"
                                                           id="no_action_returns"
                                                           name="supplier_data[no_action_returns]"
                                                        <?= $supplier->getNoActionReturns() ? ' checked' : ''; ?>>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <label>Shopify block</label><br/>
                                            <div class="row border m-0 mb-4 py-2">
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="shopify_locations">Utilise Shopify Fulfilment
                                                            Locations</label><br/>
                                                        <div id="msq_shopify_locations"
                                                             class="bg-danger text-white mx-4 px-2<?= $supplier->getShopifyLocations() ? ' d-none' : ''; ?>"
                                                             style="position: absolute; z-index: 1;font-size: 0.85rem;">You can select one active
                                                            shop (<a class="text-white"
                                                                     href="/channels?supplier_id=<?= $supplier->getId(); ?>"
                                                                     target="_blank">Accounts/Channels</a>)
                                                        </div>
                                                        <div class="form-check">
                                                            <input type="checkbox"
                                                                   class="form-check-input position-static"
                                                                   id="shopify_locations"
                                                                   name="supplier_data[shopify_locations]"
                                                                <?= $supplier->getShopifyLocations() ? ' checked' : ''; ?>>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div id="shopify_split_orders_element" class="form-group mb-0 <?= $supplier->getShopifyLocations() ? '' : 'd-none'; ?>">
                                                        <label for="shopify_split_orders">Split orders</label><br/>
                                                        <div class="form-check">
                                                            <input type="checkbox" class="form-check-input position-static" id="shopify_split_orders" name="shopify_split_orders" <?= ($settings[SuppliersConfig::TYPE_SHOPIFY_SPLIT_ORDER] ?? false) ? ' checked' : ''; ?>>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3"
                           href="/<?= $user_page ? 'account' : ('suppliers' . ($supplier->getId() !== null ? ('/settings/?id=' . $supplier->getId()) : '?' . View::toHtml($back))); ?>">
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

<?php
$modal_conf_clear_custom_return_doc = new ConfirmModal('clearCustomReturnDoc', 'Clear custom return doc', ConfirmModal::SIZE_NORMAL, true);
echo $modal_conf_clear_custom_return_doc->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'clearCustomReturnDocBtn'])->put();
?>

<script>
    const submit = $('#form :submit');
    $(function () {
        $("#form :input").change(() => submit.removeAttr("disabled"));
        submit.attr("disabled", "disabled")
    });

    $('#shopify_locations').click(() => {
        if ($('#shopify_locations').is(':checked')) {
            $('#msq_shopify_locations').prop('classList').add('d-none');
            $('#shopify_split_orders_element').prop('classList').remove('d-none');
        } else {
            $('#msq_shopify_locations').prop('classList').remove('d-none');
            $('#shopify_split_orders_element').prop('classList').add('d-none');
        }
    });

    const $return_block_regular = $('#return_block_regular');
    const $return_block_custom = $('#return_block_custom');

    $return_block_regular.click(function () {
        $('#regular_ps').removeClass('d-none');
        $('#custom_ps').addClass('d-none');
    });

    $return_block_custom.click(function () {
        $('#regular_ps').addClass('d-none');
        $('#custom_ps').removeClass('d-none');
    });

    $('#offer_returns').change(function () {
        const val = $(this).is(':checked');
        $('#return_block_settings').toggleClass('d-none', !val);
        $return_block_regular.attr("disabled", !val);
        $return_block_custom.attr("disabled", !val);

        $('#regular_ps').toggleClass('d-none', !$return_block_regular.is(':checked'));
        $('#custom_ps').toggleClass('d-none', !!$return_block_regular.is(':checked'));
    });

    <?php if (!empty($supplier->getCustomReturnDoc()) && $supplier->existCustomReturnDocFile()) { ?>

    $('#print_custom_return_doc').click(function () {
        printPdfDocument("<?= $supplier->getUploadCustomReturnDocUrl(); ?>");
    });

    function clearCustomReturnDoc() {
        $('input[name=clear_custom_return_doc]').val(1);
        $('#custom_return_doc_block').remove();
        submit.trigger('change');
    }

    $('#custom_return_doc').change(function () {
        clearCustomReturnDoc();
    });

    $('#clear_custom_return_doc').click(function () {
        <?= $modal_conf_clear_custom_return_doc->show(); ?>
    });

    $('#clearCustomReturnDocBtn').click(function () {
        clearCustomReturnDoc();
    });

    <?php } ?>

    ((() => {
        auto_grow(document.getElementById('default_description_of_goods'));
        auto_grow(document.getElementById('packing_slip_additional_information'));
    })());

    function auto_grow(element) {
        if (!element.value) {
            element.rows = 1;
            return;
        }
        let element_jq = $('#' + element.id);
        const div = document.createElement('div');
        div.style.width = (element.scrollWidth -
                           parseInt(element_jq.css('padding-right')) -
                           parseInt(element_jq.css('padding-left'))) + 'px';
        div.style.height = '20px';
        div.style.overflow = 'hidden';
        div.style.position = 'absolute';
        div.style.top = '0px';
        div.style.left = '0px';
        div.style.fontSize = element_jq.css('font-size');
        div.style.fontFamily = element_jq.css('font-family');
        document.body.appendChild(div);
        const arr = element.value.split('\n');
        let rows = 0;
        arr.forEach(item => {
            const span = document.createElement('span');
            span.textContent = item;
            div.replaceChildren(span);
            rows += span.getClientRects().length;
        });
        document.body.removeChild(div);
        element.rows = rows;
    }

</script>
<?= View::include_js_with_params(BACKEND_JS, "handle_image_form.js", [
    'imgFileInputId' => 'logo',
    'imgId' => 'imgLogo',
    'cancelImgId' => 'cancel_img',
    'setNullId' => 'null_logo',
    'defaultCurrentSrc' => addslashes(COMMON_IMG . '\photo.png'),
    'currentSrc' => empty($supplier->getLogo()) ? null : Suppliers::showLogoSrc($supplier->getLogo())
]) ?>


