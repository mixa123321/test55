<?php

use app\backend\models\Suppliers;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\entities\SuppliersConfig;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\statical\Account;

/**
 * @var User $authUser
 * @var Supplier $supplier
 * @var string $admin
 * @var bool $user_page
 * @var array $warehouse_list
 * @var int $warehouse_id
 * @var bool $warehouse_virtual
 * @var array $settings
 * @var string $back
 */

if (!isset($user_page))
    $user_page = false;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Company settings</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-user-cog"></i> <?= View::toHtml($supplier->getName()) ?>
            </h6>
        </div>
        <form method="post" action="/suppliers/adding_supplier">
            <div class="card-body">
                <input type="hidden" name="supplier[id]" value="<?= $supplier->getId(); ?>">
                <div class="row">
                    <?php if (!empty($supplier->getLogo())) { ?>
                        <div class="col-auto">
                            <img class="img-thumbnail" style="max-height: 70px; max-width: 70px;" title="logo" src="<?= Suppliers::showLogoSrc($supplier->getLogo()); ?>" alt="logo" />
                        </div>
                    <?php } ?>
                    <div class="col-3">
                        <div class="form-group">
                            <h5>Company information</h5>
                            <hr class="my-2">
                            <label>Name:</label> <b class="text-success"><?= View::toHtml($supplier->getName()) ?></b><br/>
                            <label>Email:</label> <b class="text-success"><?= View::toHtml($supplier->getEmail()) ?></b><br/>
                            <label>Phone:</label> <b class="text-success"><?= View::toHtml($supplier->getContact()) ?></b><br/>
                        </div>
                    </div>
                    <div class="col">
                        <?php
                            $hr = Html::tag('hr', null, ['class' => 'my-2']);
                            if (!empty($warehouse_list)) {
                                echo Html::tag('h5', 'Company addres' . (count($warehouse_list) > 1 ? 'ses' : 's')) . $hr;
                                foreach ($warehouse_list as $warehouse_item) {
                        ?>
                                    <div class="row">
                                        <div class="col-6">
                                            <b class="text-success"><?= View::toHtml($warehouse_item['name']) ?></b><br/>
                                            <p><?= $warehouse_item['address'] ?></p>
                                        </div>
                                        <div class="col-6">
                                            <i class="text-success">Billing address</i>
                                            <p><?= $warehouse_item['billing_address'] ?></p>
                                        </div>
                                    </div>
                        <?php
                                }
                            } else
                                echo Html::tag('h5', 'No warehouses available') . $hr;
                        ?>
                    </div>
                    <div class="col-3">
                        <?php if (!$user_page) { ?>
                            <div class="form-group">
                                <h5>Addition</h5>
                                <hr class="my-2">
                                <label>Type of account:</label> <b class="text-success"><?= View::toHtml(Account::getNameAccount($supplier->getTypeAccount())) ?></b><br/>
                                <label>Account manager:</label> <b class="text-success"><?= View::toHtml($admin) ?></b><br/>
                                <label><i>Packing Slip Block</i></label><br/>
                                <div class="border p-2 mb-2">
                                    <label>Offer returns:</label> <?= $supplier->isOfferReturns() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                                    <?php if ($supplier->isOfferReturns()) { ?>
                                        <label>Type of return:</label> <b class="text-success"><?= View::toHtml(!$supplier->isCustomReturn() ? 'Regular' : 'Custom') ?></b><br/>
                                        <?php if (!$supplier->isCustomReturn()) { ?>
                                            <label>Offer exchanges:</label> <?= $supplier->isOfferExchanges() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                                        <?php } else { ?>
                                            <label>Custom return:</label> <?= $supplier->isCustomReturn() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                                            <label>Custom customer return doc:</label> <?= $supplier->existCustomReturnDocFile() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                                        <?php } ?>
                                    <?php } ?>
                                    <label>Batch no./Expiry detail:</label> <?= $supplier->isPackingSlipBatchExpired() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                                    <label>Additional Information:</label> <b class="text-success"><?= View::toHtml($supplier->getPackingSlipAdditionalInformation()) ?></b><br/>
                                </div>
                                <label><i>SKU Block</i></label><br/>
                                <div class="border p-2 mb-2">
                                    <label>Weeks before expiry:</label>
                                    <?php if ($supplier->hasWeeksBeforeExpiry()) { ?>
                                        <b class="text-success"><?= $supplier->getWeeksBeforeExpiry() ?></b>
                                    <?php } ?><br/>
                                    <label>WMS product price:</label> <?= $supplier->getProductPrice() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                                    <label>Default description of goods:</label> <b class="text-success"><?= View::toHtml($supplier->getDefaultDescriptionOfGoods()) ?></b><br/>
                                </div>
                                <label><i>EU PID Block</i></label><br/>
                                <div class="border p-2 mb-2">
                                    <label>Shopify NS-PID:</label> <b class="text-success"><?= View::toHtml($settings[SuppliersConfig::TYPE_SHOPIFY_NS_PID_ATTRIBUTE] ?? '') ?: 'SKU (default)' ?></b><br/>
                                    <label>WooCommerce NS-PID:</label> <b class="text-success"><?= View::toHtml($settings[SuppliersConfig::TYPE_WOOCOMMERCE_NS_PID_ATTRIBUTE] ?? '') ?: 'SKU (default)' ?></b><br/>
                                    <label>Magento NS-PID:</label> <b class="text-success"><?= View::toHtml($settings[SuppliersConfig::TYPE_MAGENTO_NS_PID_ATTRIBUTE] ?? '') ?: 'SKU (default)' ?></b><br/>
                                    <label>Magento S-PID:</label> <b class="text-success"><?= View::toHtml($settings[SuppliersConfig::TYPE_MAGENTO_S_PID_ATTRIBUTE] ?? '') ?></b><br/>
                                </div>
                                <label><i>Order Block</i></label><br/>
                                <div class="border p-2 mb-2">
                                    <label>Utilise despatch date:</label>
                                    <?php if ($supplier->getUtiliseDespatchDate()) { ?>
                                        <b class="text-success"><?= $supplier->getDaysBeforeDespatch(); ?></b>
                                    <?php } ?><br/>
                                    <label>Use order inserts:</label> <?= $supplier->getOrderInserts() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                                </div>
                                <label><i>Shopify block</i></label><br/>
                                <div class="border p-2 mb-2">
                                    <label>Utilise Shopify Fulfilment locations:</label> <?= $supplier->getShopifyLocations() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                                    <?php if ($supplier->getShopifyLocations()) { ?>
                                        <label>Split orders:</label> <?= ($settings[SuppliersConfig::TYPE_SHOPIFY_SPLIT_ORDER] ?? false) ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                                    <?php } ?>
                                </div>
                                <label>No Action Required (Returns):</label> <?= $supplier->getNoActionReturns() ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br/>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="col-auto">
                            <div class="form-group">
                                <a class="btn btn-primary btn-icon-split btn-block" href="/<?= $user_page ? 'account' : 'suppliers'; ?>/save_supplier/?id=<?= $supplier->getId(); ?>">
                                    <span class="icon text-white-50 text-left">
                                        <i class="fas fa-fw fa-user-edit"></i>
                                    </span>
                                    <span class="text flex-fill">Edit</span>
                                </a>
                            </div>
                        <?php if (!$user_page) { ?>
                            <div class="form-group">
                                <a class="btn btn-primary btn-icon-split btn-block" href="/suppliers/warehouses/?id=<?= $supplier->getId(); ?>">
                                    <span class="icon text-white-50 text-left">
                                        <i class="fas fa-warehouse"></i>
                                    </span>
                                    <span class="text flex-fill">Warehouses</span>
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <?php if (!$user_page) { ?>
                            <a class="btn btn-outline-orange pl-3 btn-sm" href="/suppliers?<?= View::toHtml($back); ?>">
                                <i class="fas fa-fw fa-chevron-left mr-2"></i>
                                <span class="ml-1">Back</span>
                            </a>
                        <?php } ?>
                    </div>
                    <div class="col-auto ml-3">
                        <?php if (!$user_page && empty($supplier->getWarehouses())) { ?>
                                <div class="no_warehouses">
                                    <button class="btn btn-success btn-icon-split" disabled>
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-users"></i>
                                        </span>
                                        <span class="text">Users</span>
                                    </button>
                                </div>
                        <?php } else  { ?>
                            <a class="btn btn-success btn-icon-split"
                               href="/<?= $user_page ? 'account/edit_user' : ('users/supplier_users?supplier_id=' . $supplier->getId()); ?>"
                            >
                                <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-user<?= $user_page ? '' : 's'; ?>"></i>
                                </span>
                                <span class="text"><?= $user_page ? 'Edit user' : 'Users'; ?></span>
                            </a>
                        <?php } ?>
                    </div>
                    <div class="col-auto ml-3">
                        <?php if (empty($supplier->getWarehouses())) { ?>
                            <div class="no_warehouses">
                                <button class="btn btn-success btn-icon-split" disabled>
                                    <span class="icon text-white-50">
                                        <i class="fas fa-mail-bulk"></i>
                                    </span>
                                    <span class="text">Notifications</span>
                                </button>
                            </div>
                        <?php } else  { ?>
                            <a class="btn btn-success btn-icon-split" href="/account/notifications/?id=<?= $supplier->getId(); ?><?= $user_page ? '&account=1' : ''; ?>">
                                <span class="icon text-white-50">
                                    <i class="fas fa-mail-bulk"></i>
                                </span>
                                <span class="text">Notifications</span>
                            </a>
                        <?php } ?>
                    </div>
                    <?php if (!$user_page && !$warehouse_virtual) { ?>
                        <div class="col-auto ml-3">
                            <?php if (empty($supplier->getWarehouses())) { ?>
                                <div class="no_warehouses">
                                    <button class="btn btn-success btn-icon-split" disabled>
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-box-open"></i>
                                        </span>
                                        <span class="text">Packaging</span>
                                    </button>
                                </div>
                            <?php } else  { ?>
                                <a class="btn btn-success btn-icon-split" href="/packages/?id=<?= $supplier->getId()?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-box-open"></i>
                                    </span>
                                    <span class="text">Packages</span>
                                </a>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (!$user_page) { ?>
                        <div class="col-auto ml-3">
                            <?php if (!empty($supplier->getWarehouses()) || $authUser->isRoleSA()) { ?>
                                <a class="btn btn-success btn-icon-split" href="/SKUs/?supplier_id=<?= $supplier->getId(); ?>&back=/suppliers/settings?id=<?=$supplier->getId();?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-industry"></i>
                                    </span>
                                    <span class="text">SKUs</span>
                                </a>
                            <?php } else { ?>
                                <div class="no_warehouses">
                                    <button class="btn btn-success btn-icon-split" disabled>
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-industry"></i>
                                        </span>
                                        <span class="text">SKUs</span>
                                    </button>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    $('.no_warehouses').mouseover(function() {
        $(this).tooltip({
            "html": true,
            "title": "<b>No warehouses available</b>"
        });
    });
</script>
