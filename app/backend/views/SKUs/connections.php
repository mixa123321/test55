<?php

use app\core\channels\magento\MagentoConnector;
use app\core\channels\shopify\ShopifyConnector;
use app\core\channels\woocommerce\WooCommerceConnector;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\models\Channels;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;

/**
 * @var $authUser User
 * @var $SKU SKU
 * @var $back string
 * @var $connected_channels array
 * @var $supplier_channels array
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Connections for <?= View::toHtml($SKU->getName()); ?>
        </h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-plug"></i> Connected channels
            </h6>
            <div class="col-auto p-0">
                <button class="btn btn-success btn-icon-split js-btn-connect">
                    <span class="icon text-white-50">
                        <i class="fas fa-fw fa-plus"></i>
                    </span>
                    <span class="text">Connect new SKU</span>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($connected_channels)) { ?>
                <?php foreach ($connected_channels as $channel => $connected_channel) { ?>
                    <h3><?= $channel; ?></h3>
                    <?php
                        if (empty(Channels::$allowed_channels[$channel])) {
                            echo "Coming soon";
                            continue;
                        }
                    ?>
                    <table class="table mb-0">
                        <colgroup>
                            <col width="*"/>
                            <col width="17%"/>
                            <col width="17%"/>
                            <col width="17%"/>
                            <col width="270px"/>
                        </colgroup>
                        <tr>
                            <th>Shop name</th>
                            <th>Product ID <span class="text-danger">*</span></th>
                            <th><?php switch ($channel) {
                                case ShopifyConnector::INTEGRATION_NAME:
                                    echo "Variant ID <i class=\"fas fa-exclamation-circle\" data-toggle=\"tooltip\" data-placement=\"top\"
                                                      title=\"In case you don\'t have any variants for your $channel item, please leave this field blank\"></i>";
                                    break;
                                case WooCommerceConnector::INTEGRATION_NAME:
                                    echo "Variation ID <i class=\"fas fa-exclamation-circle\" data-toggle=\"tooltip\" data-placement=\"top\"
                                                      title=\"In case you don\'t have any variants for your $channel item, please leave this field blank\"></i>";
                                    break;
                                case MagentoConnector::INTEGRATION_NAME:
                                    break;
                            } ?></th>
                            <th><?= Channels::isShopify($channel) ? 'Inventory ID' : ''; ?></th>
                            <th>Actions</th>
                        </tr>
                        <?php $back_for_channel_link = View::toHtml(str_replace('&', '^', $back));
                        foreach ($connected_channel as $shop_url => $connected_SKUs) { ?>
                            <tr>
                                <td><?= View::toHtml($shop_url)?></td>
                                <?php
                                    $is_many = count($connected_SKUs) > 1;
                                    foreach ($connected_SKUs as $key => $connected_SKU) {
                                ?>
                                        <form method="POST" action="/SKUs/change_connected_skus">
                                            <input type="hidden" name="back" value="<?= View::toHtml($back); ?>">
                                            <input type="hidden" name="id" value="<?= (int) $SKU->getId(); ?>">
                                            <input type="hidden" name="shop_id" value="<?= (int) $connected_SKU['shop_id']; ?>">
                                            <input type="hidden" name="sku_channel_id" value="<?= (int) $connected_SKU['sku_channel_id']; ?>">
                                            <td><input type="text" name="product_id" class="form-control" value="<?= View::toHtml($connected_SKU['product_id']); ?>"></td>
                                            <td><?php switch ($channel) {
                                                case ShopifyConnector::INTEGRATION_NAME:
                                                case WooCommerceConnector::INTEGRATION_NAME:
                                                    echo "<input type=\"text\" name=\"variant_id\" class=\"form-control\" value=\"" . View::toHtml($connected_SKU['variant_id']) . "\">";
                                                    break;
                                                case MagentoConnector::INTEGRATION_NAME:
                                                    break;
                                            } ?></td>
                                            <td><?= Channels::isShopify($channel) ? View::toHtml($connected_SKU['inventory_item_id']) : ''; ?></td>
                                            <td>
                                                <a class="btn btn-primary" target="_blank"
                                                   href="/SKUs/external_link?channel=<?= $channel; ?>&SKU_id=<?= $SKU->getId()?>&shop_id=<?= $connected_SKU['shop_id']?>&variant_id=<?= urlencode((string)($connected_SKU['variant_id'] ?? '')); ?>&product_id=<?= urlencode((string)$connected_SKU['product_id']) ?>&back=<?= $back_for_channel_link; ?>"
                                                >View in store</a>
                                                <button class="btn btn-orange js-swap-connected-sku p-2"
                                                        id="SCID_<?= (int) $connected_SKU['sku_channel_id']; ?>">
                                                    <i class="fas fa-fw fa-check"></i>
                                                </button>
                                                <button class="btn btn-danger js-delete-connected-sku p-2"
                                                        id="SCID_<?= (int) $connected_SKU['sku_channel_id']; ?>">
                                                    <i class="fas fa-fw fa-times"></i>
                                                </button>
                                            </td>
                                            <?php
                                                if ($key + 1 != count($connected_SKUs))
                                                    echo "</tr><tr><td></td>";
                                            ?>
                                        </form>
                                <?php } ?>
                            </tr>
                        <?php } ?>
                    </table>
                <?php } ?>
                <?= View::include_js(BACKEND_JS, "channels_skus_edit.js")?>
            <?php } else { ?>
                <div class="alert alert-warning m-0">
                    No records found
                </div>
            <?php } ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-outline-orange pl-3" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                        <i class="fas fa-fw fa-chevron-left mr-2"></i>
                        <span class="ml-1">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$modal_delete_SKU_channel = new ConfirmModal('deleteSKUChannel', 'Delete SKU channel');
echo $modal_delete_SKU_channel->addContent(Html::error("Are you sure?"))
    ->addForm('/SKUs/delete_connected_skus', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="SKU_channel_id">' .
        '<input type="hidden" id="SKU_id" name="SKU_id" value="' . $SKU->getId(). '">'  .
        '<input type="hidden" name="back" value="' . View::toHtml($back) . '">' .
        $modal_delete_SKU_channel->getButtons())->put();
?>

<div class="modal" tabindex="-1" role="dialog" id="connectSKU">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Connect new SKU</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post">
                    <div class="row justify-content-end">
                        <div class="col-12">
                            <div class="form-group-element mb-1">
                                <select id="channel_id" class="form-control form-control-lg custom-select">
                                    <?php foreach ($supplier_channels as $channel => $shop) { ?>
                                        <option value="<?= View::toHtml($channel); ?>"><?= View::toHtml($channel); ?></option>
                                    <?php } ?>
                                </select>
                                <label for="supplier_id">Channel</label>
                            </div>
                            <div class="form-group-element mb-1">
                                <select id="shop_id" class="form-control form-control-lg custom-select">
                                    <?php foreach ($supplier_channels[array_keys($supplier_channels)[0]] as $shop_id => $shop) { ?>
                                        <option value="<?= $shop_id; ?>"><?= View::toHtml($shop['shop_url']); ?></option>
                                    <?php } ?>
                                </select>
                                <label for="supplier_id">Shop</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-1">
                                <div class="form-group-element">
                                    <input id="product_id" type="text" class="form-control form-control-lg" placeholder=" " />
                                    <label id="product_name" for="product_id"></label>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-1" id="variant_group">
                                <div class="form-group-element">
                                    <input id="variant_id" type="text" class="form-control form-control-lg" placeholder=" " />
                                    <label id="variant_name" for="variant_id"></label>
                                </div>
                            </div>
                        </div>
                        <div id="getError" class="col-12 text-white text-center mb-1 d-none">
                            <p class="form-control-static bg-danger p-1 m-0" id="msgError"></p>
                        </div>
                        <div class="col" id="product_info"></div>
                        <input id="inventory_id" type="hidden" />
                        <div class="col-auto">
                            <button class="btn btn-primary" id="get_product" type="submit">Get</button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-danger js-connect-confirm" disabled>Add</button>
            </div>
        </div>
    </div>
</div>
<script>
    var supplier_shops = <?= View::toJs($supplier_channels); ?>;
    var shopify = '<?= ShopifyConnector::INTEGRATION_NAME; ?>';
    var woocommerce = '<?= WooCommerceConnector::INTEGRATION_NAME; ?>';
    var magento = '<?= MagentoConnector::INTEGRATION_NAME; ?>';
</script>
<?= View::include_js(BACKEND_JS, "show_error.js"); ?>
<?= View::include_js(BACKEND_JS, "connect_sku.js"); ?>