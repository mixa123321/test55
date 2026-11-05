<?php

use app\backend\models\OrderDocs;
use app\backend\models\Shippings;
use app\core\entities\Address;
use app\core\entities\Carrier;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\OrderDoc;
use app\core\entities\ShippingService;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\HTTP;
use app\core\helpers\status\Status;
use app\core\helpers\View;
use app\core\models\PackingSlips;
use app\core\models\Warehouses;
use app\core\statical\Countries;
use app\core\statical\Sources;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\modal\ConfirmModal;
use app\core\models\OrderCategories;
use app\core\statical\WithDate;

/**
 * @var User $authUser
 * @var array $SKUs
 * @var Address $delivery_address
 * @var array $carriers
 * @var Carrier $carrier
 * @var array $errors
 * @var Order $order
 * @var array $supplier
 * @var Countries $countries
 * @var Countries $currencies
 * @var array $packages
 * @var array $SKU_locations
 * @var array $package_types
 * @var array $locations_users
 * @var array $users
 * @var string $back
 * @var array $shipping_data
 * @var array $shipping_errors
 * @var array $returns
 * @var array $history
 * @var array $shipping_services
 * @var string $shipping_service_name
 * @var int $special
 * @var int $printed_matter
 * @var string $eori_number
 * @var string $vat_number
 * @var string $ioss_number
 * @var string $tax_id
 * @var Delivery $delivery
 * @var bool $is_short_ship
 * @var array $printers
 * @var string $printer_def
 * @var OrderDoc $doc
 * @var string $risk_messages
 * @var bool $has_order_bundles
 * @var bool $has_order_substitutions
 * @var string $percent
 * @var string $packing_slip_type
 * @var array $warehouse
 * @var array $short_ship
 * @var int $warehouse_id
 * @var array $serial_numbers
 * @var bool $warehouse_virtual
 * @var bool $can_create_similar
 */

$error_virtual = $order->errorVirtual();
$country_id = Warehouses::getCountryId($warehouse_id);

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">View order #<?= $order->getId(); ?>
            <?= $special ? Status::getStatus(ShippingService::SPECIAL_NAMES[$special], 'success') : ''; ?>
            <?php if ($authUser->isRoleSupplierUser() && !empty($short_ship)) { ?>
                <p class="bg-primary text-white p-2 mb-0" style="line-height: 1.1; font-size: 0.6em"><?= implode('<br/>', array_map([View::class, 'toHtmlHistory'], $short_ship)); ?></p>
            <?php } ?>
            <?= $printed_matter ? Status::getStatus('Printed Matter', 'success') : ''; ?>
        </h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col-lg-3 text-lg">
                    Current status: <span class="text-primary"><?= ucwords($order->getStatusName()); ?></span>
                </div>
                <div class="col text-lg text-center">
                    <?php
                        $num_stage = $order->getStage();
                        foreach (Order::getStages() as $key => $stage)
                            echo "<span class='text-" . ($num_stage >= $key ? 'success' : 'dark') . "'>$stage" . ($num_stage == $key && !empty($percent) && !$warehouse_virtual ? " ($percent)" : '') . "</span>" . ($key < 5 ? ' > ' : '');
                        if (!empty($returns)) {
                            echo '<br/><div class="row text-left justify-content-end"><div class="col-auto bg-primary text-white">Return ASNs:</div><div class="col-auto bg-primary text-white">';
                            foreach ($returns as $return)
                                echo "<b>{$return['id']} / " . (!empty($return['ref']) ? View::toHtml($return['ref']) : '-') . '</b> (' . View::toHtml($return['comment']) . ')<br/>';
                            echo '</div></div>';
                        }
                    ?>
                </div>
                <div class="mr-2">
                    <?php if ($can_create_similar) { ?>
                        <button type="button"
                                class="btn btn-block btn-success mt-0 mb-1 js-similar-btn"
                                data-order-id="<?= (int)$order->getId() ?>">Duplicate
                        </button>
                    <?php } ?>
                </div>
                <div class="mr-2">
                    <?php if (Order::canQuery($order->getStatus()) && $authUser->hasManagerAccess()) { ?>
                        <button type="button"
                                class="btn btn-block btn-success mt-0 mb-1 js-query-btn"
                                data-order-id="<?= (int)$order->getId() ?>">Query
                        </button>
                    <?php } elseif (Order::isQuery($order->getStatus()) && $authUser->hasManagerAccess()) { ?>
                        <button type="button"
                                class="btn btn-block btn-success mt-0 mb-1 js-reinstate-btn"
                                data-order-id="<?= (int)$order->getId() ?>">Reinstate
                        </button>
                    <?php } ?>
                </div>
                <div>
                    <?php if (Order::canCancelOrder($order->getStatus(), $warehouse_virtual, $authUser->isRoleSupplierUser())) { ?>
                        <button type="button"
                                class="btn btn-block btn-danger mt-0 mb-1 js-cancel-btn"
                                data-order-id="<?= (int)$order->getId() ?>">Cancel
                        </button>
                    <?php } ?>
                </div>
                <?php if ($authUser->isRoleSA() && $warehouse_virtual) { ?>
                    <div class="col-auto pr-0">
                        <button class="btn btn-success btn-icon-split js-webhook" data-order_id="<?= $order->getId(); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-globe"></i>
                            </span>
                            <span class="text">Webhooks</span>
                        </button>
                    </div>
                <?php } ?>
                <?php if (!$authUser->isClientSide() && $warehouse_virtual && !$order->getIsHash()) { ?>
                    <input type="hidden" id="order_id" value="<?= $order->getId(); ?>">
                    <div class="col-auto">
                        <button class="btn btn-<?= $error_virtual ? 'danger' : 'success'; ?> btn-icon-split js-<?= $error_virtual ? 'retry' : 'in-reality'; ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-<?= $error_virtual ? 'sync' : 'warehouse'; ?>"></i>
                            </span>
                            <span class="text"><?= $error_virtual ? 'Retry' : 'In reality'; ?></span>
                        </button>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col h3 mt-2">SKUs</div>
                        <?php if ($order->canEdit()) { ?>
                            <div class="col-auto">
                                <button class="col-auto btn btn-success btn-icon-split js-btn-edit-skus"
                                        data-order_id="<?= (int)$order->getId() ?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </span>
                                    <span class="text text-white flex-fill">Edit</span>
                                </button>
                            </div>
                        <?php } ?>
                        <?php if ($has_order_substitutions) { ?>
                            <div class="col-auto">
                                <button class="col-auto btn btn-primary btn-icon-split js-btn-order-substitutions"
                                        data-order_id="<?= (int)$order->getId() ?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-exchange-alt"></i>
                                    </span>
                                    <span class="text text-white flex-fill">Substitutions</span>
                                </button>
                            </div>
                        <?php } ?>
                        <?php if ($has_order_bundles) { ?>
                            <div class="col-auto">
                                <button class="col-auto btn btn-primary btn-icon-split js-btn-order-bundles"
                                        data-order_id="<?= (int)$order->getId() ?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-box-open"></i>
                                    </span>
                                    <span class="text text-white flex-fill">Bundles</span>
                                </button>
                            </div>
                        <?php } ?>
                        <?php if ($order->backorder() && $is_short_ship && !$order->isHashed()) { ?>
                            <div class="col-auto">
                                <button class="col-auto btn btn-primary btn-icon-split js-short-ship"
                                        data-order_id="<?= (int)$order->getId() ?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-unlink"></i>
                                    </span>
                                    <span class="text text-white flex-fill">Short Ship</span>
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                    <table class="table table-hover">
                        <colgroup>
                            <col width="10%">
                            <col width="*">
                            <col width="*">
                            <col width="15%">
                            <col width="7%">
                            <col width="7%">
                            <col width="15%">
                            <col width="10%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>SKU ID</th>
                                <th>Barcode</th>
                                <th>Name</th>
                                <th>Batches</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <?php if (!$warehouse_virtual) { ?>
                                    <?php if ($authUser->hasManagerAccess()) { ?>
                                        <th>Current location</th>
                                    <?php } else { ?>
                                        <th>Status</th>
                                    <?php } ?>
                                <?php } ?>
                                <th>Active orders</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($SKUs as $SKU) { ?>
                            <tr>
                                <td>
                                    <a class="btn-link" href="/SKUs/<?= $authUser->isRoleManager() ? 'view_sku' : 'edit_sku' ?>/?id=<?= View::toHtml($SKU['id']); ?>&back=/orders/view_order^id=<?= $order->getId(); ?>">
                                        <?= View::toHtml($SKU['id']); ?>
                                    </a>
                                </td>
                                <?php if (!empty($SKU['barcode'])) { ?>
                                    <td><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></td>
                                    <td>
                                        <?php
                                            echo View::toHtml($SKU['name']);
                                            if (!empty($SKU['DDS']))
                                                echo '<br/><span class="badge badge-primary">DDS</span> ' . View::toHtml($SKU['DDS']);
                                            if (!empty($SKU['exemption_TARIC']))
                                                echo '<br/><span class="badge badge-primary">Exemption TARIC</span> ' . View::toHtml($SKU['exemption_TARIC']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($SKU_locations[$SKU['id']])) { ?>
                                            <?php foreach ($SKU_locations[$SKU['id']] as $sku_location) { ?>
                                                <?= !empty($sku_location['batch_number']) ? ($sku_location['batch_number'] . '<br>') : ''; ?>
                                            <?php } ?>
                                        <?php } ?>
                                    </td>
                                <?php } else { ?>
                                    <td colspan="3">
                                        <div class="row justify-content-center bg-danger text-white">
                                            <span class="alert m-0 p-2">Not found</span>
                                        </div>
                                    </td>
                                <?php } ?>
                                <td><?= View::toHtml($SKU['quantity']); ?></td>
                                <td><?= View::toHtml($SKU['price'] ?? ''); ?></td>
                                <?php if (!$warehouse_virtual) { ?>
                                    <td>
                                        <?php if ($authUser->hasManagerAccess()) { ?>
                                            <?php if ($order->despatched()) { ?>
                                                <b>Already despatched</b>
                                            <?php } elseif (!empty($SKU_locations[$SKU['id']])) { ?>
                                                <?php foreach ($SKU_locations[$SKU['id']] as $sku_location) { ?>
                                                    <b><?= View::toHtml($sku_location['quantity']) ?></b> item(s)
                                                                                                          in
                                                    <b><?= View::toHtml($sku_location['location_name']) ?></b>
                                                    <?php if (!empty($locations_users[$sku_location['location_id']])) { ?>
                                                        held by <i class="fas fa-user"></i>
                                                        <b><?= View::toHtml($locations_users[$sku_location['location_id']]) ?></b>
                                                    <?php } ?>
                                                    <br/>
                                                <?php } ?>
                                            <?php } else {
                                                echo Html::warning("Not yet allocated");
                                            } ?>
                                        <?php } else {
                                            $status = $order->despatched() ? ['Despatched', 'success']
                                                                           : (!empty($SKU_locations[$SKU['id']]) ? ['Allocated', 'primary'] : ['Backorder', 'danger']);
                                            ?>
                                            <span class="badge badge-<?= $status[1]; ?>"><?= $status[0]; ?></span>
                                        <?php } ?>
                                    </td>
                                <?php } ?>
                                <td>
                                    <button class="btn btn-primary js-btn-orders" type="button"
                                            id="<?= $SKU['id']; ?>"
                                            data-order_id="<?= $order->getId(); ?>">
                                        View
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <hr/>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="row">
                                <div class="col">
                                    <h3>General info</h3>
                                </div>
                                <?php if ($authUser->hasAdminAccess() && !in_array($order->getTypeCreate(), Sources::$not_show_requests) && !$order->getIsHash()) { ?>
                                    <div class="col">
                                        <button class="btn btn-lg btn-icon-split float-md-right m-r-lg-4 p-1 btn-primary"
                                                onclick="showOriginalRequest()">
                                            Logs
                                        </button>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="row">
                                <div class="col border p-2 mx-1 mb-3">
                                    <div class="row">
                                        <div class="col">
                                            <p class="form-control-static mb-0"><b>Customer Reference:</b>
                                                <?= View::toHtml(empty($order->getRef()) ? '-' : $order->getRef()); ?>
                                            </p>
                                            <p class="form-control-static mb-0"><b>Customer Reference 2:</b>
                                                <?= View::toHtml(empty($order->getRef2()) ? '-' : $order->getRef2()); ?>
                                            </p>
                                            <p class="form-control-static mb-0"><b>Order Category:</b>
                                                <?= View::toHtml(OrderCategories::getCategoryName($order->getCategory())); ?>
                                            </p>
                                            <div class="row">
                                                <div class="col">
                                                    <p class="form-control-static mb-0">
                                                        <b>Purchase Order Number:</b>
                                                        <?= View::toHtml(empty($order->getPurchaseOrderNumber()) ? '-' : $order->getPurchaseOrderNumber()); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($order->canEdit() || $order->canEditPurchaseOrderNumber()) { ?>
                                            <div class="col-auto">
                                                <button class="btn btn-success btn-icon-split js-btn-edit-ref"
                                                        data-order_id="<?= View::toHtml($order->getId()); ?>"
                                                        data-ref='<?= View::toHtml($order->getRef()); ?>'
                                                        data-ref2='<?= View::toHtml($order->getRef2()); ?>'
                                                        data-purchase_order_number='<?= View::toHtml($order->getPurchaseOrderNumber()); ?>'>
                                                        <span class="icon text-white-50">
                                                            <i class="fas fa-fw fa-edit"></i>
                                                        </span>
                                                    <span class="text text-white flex-fill">Edit</span>
                                                </button>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <p class="form-control-static"><b>Account Name:</b>
                                        <?= View::toHtml($supplier['name']); ?>
                                    </p>
                                    <p class="form-control-static">
                                        <b>Created:</b> <?= View::toHtml(date(WithDate::DATE_FRONT_FORMAT,
                                            strtotime($order->getCreateDate()))) ?>
                                        <br/>
                                        <b>Despatch:</b> <?= (!empty($order->getDespatchDate()) && strtotime($order->getDespatchDate()) > 0) ? View::toHtml(date('jS M Y',
                                            $order->getDespatchDateInt())) : "-" ?>
                                        <br/>
                                        <b>Despatched:</b> <?= (!empty($order->getDespatchedDate()) && strtotime($order->getDespatchedDate()) > 0) ? View::toHtml(date(WithDate::DATE_FRONT_FORMAT,
                                            $order->getDespatchedDateInt())) : "-" ?>
                                        <?php if (!empty($subwarehouse[$order->getId()])) { ?>
                                            <br/>
                                            <b>Shipped from:</b> <?= View::toHtml($subwarehouse[$order->getId()]); ?>
                                        <?php } ?>
                                    </p>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <p class="form-control-static mb-2"><b>Delivery</b></p>
                                    <?php if (!empty($eori_number)) { ?>
                                        <p class="form-control-static mb-1"><b>EORI Number:</b>
                                            <?= View::toHtml($eori_number); ?>
                                        </p>
                                    <?php } ?>
                                    <?php if (!empty($vat_number)) { ?>
                                        <p class="form-control-static mb-1"><b>VAT Number:</b>
                                            <?= View::toHtml($vat_number); ?>
                                        </p>
                                    <?php } ?>
                                    <?php if (!empty($ioss_number)) { ?>
                                        <p class="form-control-static mb-1"><b>IOSS Number:</b>
                                            <?= View::toHtml($ioss_number); ?>
                                        </p>
                                    <?php } ?>
                                    <?php if (!empty($tax_id)) { ?>
                                        <p class="form-control-static mb-1"><b>TAX ID:</b>
                                            <?= View::toHtml($tax_id); ?>
                                        </p>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col border p-2 mx-1 mb-0">
                                    <div class="row">
                                        <div class="col">
                                            <p class="form-control-static mb-0"><b>Address</b></p>
                                        </div>
                                        <?php if ($order->canEdit() || ($authUser->hasManagerAccess() && $order->canEditTaxNumbers())) { ?>
                                            <div class="col-auto">
                                                <button class="btn btn-success btn-block btn-icon-split js-btn-edit"
                                                        data-order_id="<?= View::toHtml($order->getId()); ?>"
                                                        data-countries='<?= View::toHtml(json_encode($countries)); ?>'
                                                        data-delivery='<?= View::toHtml(json_encode($delivery->toArray() + ['eori_number' => $eori_number, 'vat_number' => $vat_number, 'ioss_number' => $ioss_number, 'tax_id' => $tax_id])); ?>'
                                                        data-can_edit_address="<?= (int)($order->canEdit() || ($authUser->hasManagerAccess() && $order->canEditDeliveryAtPacking())); ?>">
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-edit"></i>
                                                    </span>
                                                    <span class="text text-white flex-fill">Edit</span>
                                                </button>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <?= $delivery_address; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col">
                                    <p class="form-control-static">
                                        <b>Additional documentation:</b>
                                    </p>
                                </div>
                                <?php if ($order->canEdit() || $order->awaitingUploadDocuments()) { ?>
                                    <div class="col-auto">
                                        <button class="btn btn-success btn-block btn-icon-split js-btn-edit-doc"
                                                data-order_id="<?= View::toHtml($order->getId()); ?>"
                                                data-document_url='<?= View::toHtml(!empty($doc) ? $doc->getDocumentUrl() : ''); ?>'>
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-edit"></i>
                                            </span>
                                            <span class="text text-white flex-fill">Edit</span>
                                        </button>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if (empty($doc)) { ?>
                                <div class="row mt-0">
                                    <div class="col"><p class="m-0">No files</p></div>
                                </div>
                            <?php } elseif ($doc instanceof OrderDoc) { ?>
                                <div class="row mt-1">
                                    <div class="col">
                                        <p class="form-control-static m-0">
                                            <b>Status:</b>
                                            <span class="badge badge-<?= OrderDocs::colorStatus($doc->getStatus()); ?>">
                                                <?= $doc->getStatus(); ?>
                                            </span>
                                            <?php if ($doc->getStatus() === OrderDoc::STATUS_FAIL) { ?>
                                                <span class="text text-danger mx-3"><?= View::toHtml($doc->getComment()) ?></span>
                                            <?php } ?>
                                        </p>
                                    </div>
                                    <div class="col-auto">
                                        <?php if (!empty($doc->getName()) && $doc->existFile()) { ?>
                                            <button
                                                    id="btn_print_doc"
                                                    class="btn btn-primary"
                                                    type="button"
                                                    data-toggle="tooltip" data-placement="top" title="<?= View::toHtml($doc->getName()) ?>"
                                                    onmouseover="$(event.target).tooltip('show')"
                                            >
                                                Print Doc File
                                            </button>
                                        <?php } ?>
                                        <?php if ($order->canEdit() || $order->awaitingUploadDocuments()) {
                                            if ($doc->isFail() && empty($doc->getName()) && !empty($doc->getDocumentUrl())) { ?>
                                                    <button id="docReset"
                                                            class="btn btn-primary"
                                                            type="button"
                                                    >Reset</button>
                                        <?php }} ?>
                                    </div>
                                </div>
                                <?php if (!empty($doc->getDocumentUrl()) && !$doc->existFile()) { ?>
                                <div class="row">
                                    <div class="col mt-3">
                                        <p class="form-control-static">
                                            <a href="<?= View::toHref($doc->getDocumentUrl()) ?>" target="_blank" rel="noopener noreferrer">
                                                <?= View::toHtml($doc->getDocumentUrl()) ?>
                                            </a>
                                        </p>
                                    </div>
                                </div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col">
                                    <h3>Shipping</h3>
                                </div>
                                <div class="col-auto">
                                    <?php if ($authUser->hasManagerAccess() && !empty($shipping_data)) { ?>
                                        <?php  if (!$warehouse_virtual && !$order->getIsHash()) { ?>
                                            <button id="shippingButton" class="btn btn-lg btn-outline-<?= $order->printingDocumentsIssue() ? 'primary' : 'primary'; ?> p-1">
                                                <?= $carrier->getCode(); ?> Requests
                                            </button>
                                        <?php } ?>
                                        <?php if ($authUser->isRoleSA() && !$order->getIsHash()) { ?>
                                            <?php if ((!$warehouse_virtual && ($order->printingDocumentsIssue() || $order->printingDocuments())) ||
                                                      ($warehouse_virtual && ($order->packing() || $order->packed()))) { ?>
                                                <button id="markDespatchedButton" class="btn btn-lg btn-outline-primary p-1">
                                                    Mark as Despatched
                                                </button>
                                            <?php } ?>
                                        <?php } ?>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row mb-1">
                                <div class="col-7 border p-2 ml-2 mr-2 mb-2">
                                    <div class="row">
                                        <div class="col">
                                            <p class="form-control-static mb-0"><b>Shipping service:</b>
                                                <?= View::toHtml($shipping_service_name); ?>
                                            </p>
                                            <p class="form-control-static mb-0"><b>Terms:</b>
                                                <?= View::toHtml(Shippings::TERMS[(int)$order->getDDP()]); ?>
                                            </p>
                                        </div>
                                        <?php if ($order->canEdit()) { ?>
                                            <div class="col-auto">
                                                <button class="btn btn-success btn-icon-split js-btn-edit-shipping-service"
                                                        data-order_id="<?= (int)$order->getId() ?>"
                                                        data-shipping-services='<?= View::toHtml(json_encode($shipping_services)) ?>'
                                                        data-selected-service='<?= $order->getShippingServicesId(); ?>'
                                                        data-terms='<?= (int)$order->getDDP(); ?>'>
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-edit"></i>
                                                    </span>
                                                    <span class="text text-white flex-fill">Edit</span>
                                                </button>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="col mx-1 p-0 pr-2">
                                    <?php if (!$authUser->isRoleWorker() && !empty($shipping_errors) && !$warehouse_virtual) { ?>
                                        <div class="alert alert-danger mb-0">
                                            <?php
                                                for ($i = 0; $i < count($shipping_errors) - 1; $i++)
                                                    echo View::toHtml($shipping_errors[$i]) . '<hr class="my-1"/>';
                                                echo View::toHtml($shipping_errors[$i]);
                                            ?>
                                        </div>
                                    <?php } ?>
                                    <?php if (!empty($risk_messages)) { ?>
                                        <div class="alert alert-danger mb-0">
                                            <?= View::toHtmlHistory($risk_messages); ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php if ($authUser->hasManagerAccess() && !$warehouse_virtual && !$order->isHashed()) { ?>
                                <div class="row mb-1">
                                    <div class="col-7 px-3 mx-2 mb-0">
                                        <div class="row">
                                            <?php if ($packing_slip_type === PackingSlips::TYPE_RETAIN_ME) { ?>
                                                <button class="col my-3 btn btn-secondary js-packing-slip" id="prndoc_<?= $order->getId(); ?>" type="button">
                                                    Request Retain.me packing slip
                                                </button>
                                                <h6 class="col-auto font-weight-bold text-primary pr-0">Select Retain Me printer
                                                    <?php
                                                    echo Html::dropDownList('printer_def', $printers, $printer_def . "",
                                                        [
                                                            'class' => 'selectpicker form-control',
                                                            'id' => 'printer_def',
                                                        ]);
                                                    ?>
                                                </h6>
                                            <?php } elseif($packing_slip_type !== PackingSlips::TYPE_NONE) { ?>
                                                <a target="_blank" class="col btn btn-primary mr-2"
                                                   href="/orders/get_packing_slip?id=<?= (int)$order->getId() ?>">
                                                    View Packing slip
                                                </a>
                                                <button class="col btn btn-primary ml-2" id="btn_print_packing_slip">
                                                    Print Packing slip
                                                </button>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <?php if (!empty($packages)) {
                                foreach ($packages as $package) { ?>
                                    <div class="order-box p-3 mb-2 mt-2">
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="order-box-header p-2">
                                                    <div class="row">
                                                        <div class="col-sm-4">
                                                            <h4 class="title"><?= View::toHtml($package['name']) ?></h4>
                                                            <?php if (!$authUser->isClientSide()) { ?>
                                                            <div class="form-check-inline">
                                                                <input type="checkbox" class="form-check-input"
                                                                    <?= (int)$package['order_inserts'] ? ' checked' : ''; ?> disabled>
                                                                <label for="order_inserts" class="mt-2">Order insert used</label>
                                                            </div>
                                                            <?php } ?>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <span><?= empty($package_types[$package['package_type_id']]) ? '-' : View::toHtml($package_types[$package['package_type_id']]); ?></span>
                                                        </div>
                                                        <div class="col-md-5">
                                                            <span class="alert-info w-100 float-right mt-1 pl-1">
                                                                Length: <span class="float-right pr-2"><?= ((double)$package['length'] > 0) ? (number_format($package['length'], 2) . ' cm') : '-' ?></span>
                                                            </span>
                                                            <span class="alert-info w-100 float-right pl-1">
                                                                Width: <span class="float-right pr-2"><?= ((double)$package['width'] > 0) ? (number_format($package['width'], 2) . ' cm') : '-' ?></span>
                                                            </span>
                                                            <span class="alert-info w-100 float-right pl-1">
                                                                Height: <span class="float-right pr-2"><?= ((double)$package['height'] > 0) ? (number_format($package['height'], 2) . ' cm') : '-' ?></span>
                                                            </span>
                                                            <span class="alert-info w-100 float-right pl-1">
                                                                Weight: <span class="float-right pr-2"><?= ((double)$package['weight'] > 0) ? (number_format($package['weight'], 2) . ' kg') : '-' ?></span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="skus-area mt-3">
                                                    <?php foreach ($package['skus'] as $sku) { ?>
                                                        <div class="row<?= $sku != end($package['skus']) ? ' border-bottom mb-2 pb-2': ' mb-0 pb-0'; ?>">
                                                            <div class="col-<?= empty($sku['serial_number']) ? 10 : 8 ?>">
                                                                <span><?= View::toHtml($sku['name']) ?></span>
                                                                <br/>
                                                                <span><strong><?= View::toHtmlConvertWithPrimaryBarcode($sku['barcode']) ?></strong></span>
                                                            </div>
                                                            <?php if ($sku['serial_number']) { ?>
                                                                <div class="col-2 text-right">
                                                                    <button type="button" class="btn btn-primary btn-icon-split mt-2 show_serial_numbers" id="show_serial_number_<?= $sku['id'] ?>" data-package-id="<?= $package['id'] ?>" data-sku-id="<?= $sku['id'] ?>">
                                                                        <span class="icon text-white-50">
                                                                            <i class="fas fa-fw fa-cube"></i>
                                                                        </span>
                                                                        <span class="text d-none d-md-block pl-1 pr-1"><span id="quantity_serial_numbers_<?= $sku['id']; ?>"><?= count($serial_numbers[$sku['id']][$package['id']]); ?></span></span>
                                                                    </button>
                                                                </div>
                                                            <?php } ?>
                                                            <div class="col-2">
                                                                <input type="text" class="form-control-plaintext mt-1"
                                                                       readonly
                                                                       value="<?= View::toHtml($sku['quantity']) ?>">
                                                            </div>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <?php if ($order->getShippingServicesId()) {
                                                    if (!empty($package['shipping']['tracking_number'])) { ?>
                                                    <h4>Assigned Tracking Number:
                                                        <?php if (!empty($package['shipping']['url'])) { ?>
                                                            <span class="badge badge-light text-white">
                                                                <a target="_blank"
                                                                   href="<?= View::toHtml($package['shipping']['url']) ?>">
                                                                    <?= View::toHtml($package['shipping']['tracking_number']) ?>
                                                                </a>
                                                            </span>
                                                        <?php } else { ?>
                                                            <span class="badge badge-secondary">
                                                                <?= View::toHtml($package['shipping']['tracking_number']) ?>
                                                            </span>
                                                        <?php } ?>
                                                    </h4>
                                                    <?php if (!$warehouse_virtual && !$order->isHashed()) { ?>
                                                        <?php if (!empty($package['shipping']['url']) || $carrier->getIsInternal()) { ?>
                                                            <?php if ($authUser->hasManagerAccess()) { ?>
                                                                <a class="btn btn-primary w-100 mb-3" target="_blank"
                                                                   href="<?= HTTP::getZprnUrl("/main/print_label?shipment_id={$package['shipping']['id']}&hash=" . md5($package['shipping']['tracking_number'])) ?>">
                                                                    Print Shipping Label
                                                                </a>
                                                            <?php } ?>
<!--                                                            <a class="btn btn-primary w-100" target="_blank"-->
<!--                                                               href="--><?php //= HTTP::getDomain(); ?><!--/main/print_label?shipment_id=--><?php //= $package['shipping']['id']; ?><!--&hash=--><?php //= md5($package['shipping']['tracking_number']) ?><!--&viewMode=1">-->
<!--                                                                View Shipping Label-->
<!--                                                            </a>-->
                                                        <?php } ?>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <h4 class="alert-warning p-2">Label not found </h4>
                                                <?php }  }  ?>
                                                <?php if (!empty($package['returns'])) { ?>
                                                    <h4 class="mt-3">Tracking Number (for return):
                                                    <?php if (!empty($package['returns']['tracking_number'])) { ?>
                                                            <span class="badge badge-light text-primary">
                                                                <?= View::toHtml($package['returns']['tracking_number']) ?>
                                                            </span>
                                                        </h4>
                                                        <?php if (!$order->isHashed()) { ?>
                                                            <?php if ($authUser->hasManagerAccess()) { ?>
                                                                <a class="btn btn-primary w-100 mb-3" target="_blank"
                                                                   href="<?= HTTP::getZprnUrl("/main/print_label?return_id={$package['returns']['id']}&hash=" . md5($package['returns']['tracking_number'])) ?>">
                                                                    Print Return Label
                                                                </a>
                                                            <?php } ?>
                                                            <a class="btn btn-primary w-100" target="_blank"
                                                               href="<?= HTTP::getDomain(); ?>/main/print_label?return_id=<?= $package['returns']['id']; ?>&hash=<?= md5($package['returns']['tracking_number']) ?>&viewMode=1">
                                                                View Return Label
                                                            </a>
                                                    <?php }} else { ?>
                                                        </h4>
                                                        <div class="alert alert-danger">
                                                            <?= View::toHtml($package['returns']['secondary_barcode']) ?>
                                                        </div>
                                                    <?php } ?>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php }
                            } else { ?>
                                <?= Html::warning("Shipping is not created yet") ?>
                            <?php } ?>
                        </div>
                    </div>
                    <hr/>
                    <h3>Special requirements</h3>
                    <div class="row">
                        <div class="col-lg-3 col-12 border py-2 mx-1"><b>Special picking instructions</b><hr class="m-0 mb-2"/><?= View::toHtml($order->getPicking() ?: '-'); ?></div>
                        <div class="col-lg-3 col-12 border py-2 mx-1"><b>Special packing instructions</b><hr class="m-0 mb-2"/><?= View::toHtml($order->getPacking() ?: '-'); ?></div>
                        <div class="col-lg-3 col-12 border py-2 mx-1"><b>Order notes</b><hr class="m-0 mb-2"/><?= View::toHtml($order->getNotes() ?: '-'); ?></div>
                    </div>
                    <?php if ($authUser->hasManagerAccess()) { ?>
                        <hr class="mt-4">
                        <h3>History</h3>
                        <div class="table-responsive" style="max-height: 400px">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Comment</th>
                                </thead>
                                <tbody>
                                <?php foreach ($history as $item) { ?>
                                    <tr>
                                        <td><?= View::toHtml(date(WithDate::DATE_FRONT_FORMAT, strtotime($item['create_date']))) ?></td>
                                        <td><?= View::toHtml(!empty($users[$item['user_id']]) ? $users[$item['user_id']] : 'System') ?></td>
                                        <td><?= View::toHtmlHistory($item['comment']); ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                    <?php if (!$warehouse_virtual && !$order->getIsHash()) { ?>
                        <hr/>
                        <h3>Additional charge</h3>
                        <div class="row">
                            <?php
                            $charge = 0;
                            if ($authUser->hasManagerAccess() || $order->getBillingChargePreparation()) {
                                $charge++;
                                ?>
                                <div class="col-lg-3 col-12 border pb-2 mx-1">
                                    <p class="form-control-static mt-1 mb-1"><b>Custom additional charge for
                                            preparation:</b></p>
                                    <?php if (!$authUser->hasManagerAccess()) { ?>
                                        <p class="form-control-static mt-1 mb-1"><?= View::toHtml($order->getBillingDescriptionPreparation()); ?></p>
                                        <p class="form-control-static mt-1 mb-1 mr-4 text-right"><?= sprintf("%5.3f %s",
                                                $order->getBillingChargePreparation(),
                                                $order->getBillingChargeCurrency()); ?></p>
                                    <?php } else { ?>
                                        <textarea class="form-control mt-1 mb-1" rows="3"
                                                  id="description_preparation"><?= View::toHtml($order->getBillingDescriptionPreparation()); ?></textarea>
                                        <div class="input-group mt-1 mb-1">
                                            <input class="form-control" type="number" id="charge_preparation"
                                                   value="<?= sprintf("%5.3f", $order->getBillingChargePreparation()); ?>"/>
                                            <div class="input-group-append">
                                                <span class="input-group-text"><?= View::toHtml($order->getBillingChargeCurrency()); ?></span>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <?php
                            if ($authUser->hasManagerAccess() || $order->getBillingChargeShipping()) {
                                $charge++;
                                ?>
                                <div class="col-lg-3 col-12 border pb-2 mx-1">
                                    <p class="form-control-static mt-1 mb-1"><b>Custom additional charge for shipping:</b>
                                    </p>
                                    <?php if (!$authUser->hasManagerAccess()) { ?>
                                        <p class="form-control-static mt-1 mb-1"><?= View::toHtml($order->getBillingDescriptionShipping()); ?></p>
                                        <p class="form-control-static mt-1 mb-1 mr-4 text-right"><?= sprintf("%5.3f %s",
                                                $order->getBillingChargeShipping(),
                                                $order->getBillingChargeCurrency()); ?></p>
                                    <?php } else { ?>
                                        <textarea class="form-control mt-1 mb-1" rows="3"
                                                  id="description_shipping"><?= View::toHtml($order->getBillingDescriptionShipping()); ?></textarea>
                                        <div class="input-group mt-1 mb-1">
                                            <input class="form-control" type="number" id="charge_shipping"
                                                   value="<?= sprintf("%5.3f", $order->getBillingChargeShipping()); ?>"/>
                                            <div class="input-group-append">
                                                <span class="input-group-text"><?= View::toHtml($order->getBillingChargeCurrency()); ?></span>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <?php
                            if ($authUser->hasManagerAccess() || $order->getBillingChargeAdditional()) {
                                $charge++;
                                ?>
                                <div class="col-lg-3 col-12 border pb-2 mx-1">
                                    <p class="form-control-static mt-1 mb-1"><b>Custom additional charge for additional shipping:</b></p>
                                    <?php if (!$authUser->hasManagerAccess()) { ?>
                                        <p class="form-control-static mt-1 mb-1"><?= View::toHtml($order->getBillingDescriptionAdditional()); ?></p>
                                        <p class="form-control-static mt-1 mb-1 mr-4 text-right"><?= sprintf("%5.3f %s",
                                                $order->getBillingChargeAdditional(),
                                                $order->getBillingChargeCurrency()); ?></p>
                                    <?php } else { ?>
                                        <textarea class="form-control mt-1 mb-1" rows="3"
                                                  id="description_additional"><?= View::toHtml($order->getBillingDescriptionAdditional()); ?></textarea>
                                        <div class="input-group mt-1 mb-1">
                                            <input class="form-control" type="number" id="charge_additional"
                                                   value="<?= sprintf("%5.3f", $order->getBillingChargeAdditional()); ?>"/>
                                            <div class="input-group-append">
                                                <span class="input-group-text"><?= View::toHtml($order->getBillingChargeCurrency()); ?></span>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if (!$charge) { ?>
                            <p class="form-control-static mt-1">No additional charge</p>
                        <?php } elseif ($authUser->hasManagerAccess()) { ?>
                            <div class="col-lg-<?= 3*$charge; ?> col-12 text-right">
                                <div class="alert alert-danger mt-1 mb-1 d-none" id="charge_err"></div>
                                <div class="alert alert-success mt-1 mb-1 d-none" id="charge_ok"></div>
                                <button class="btn btn-primary mt-2" type="button" id="charge_update">Update</button>
                            </div>
                        <?php } ?>
                    <?php } ?>
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
            </div>
        </div>
    </div>
</div>

<?php
$show_serial_numbers = new InfoModal('serialNumbers', 'Serial numbers');
echo $show_serial_numbers->addContent('
<table class="table table-hover mb-0">
    <thead><tr><th>Serial number</th><th>Created</th></tr></thead>
    <tbody id="tbody_serial_numbers"></tbody>
</table>')->put();

$modal_mark_despatched = new ConfirmModal('markDespatched', 'Mark as despatched');
echo $modal_mark_despatched->addContent(HTML::error('Are you sure?'))
    ->addForm('/orders/mark_despatched', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" value="' . $order->getId() . '">' .
        '<input type="hidden" name="back" value="' . View::toHtml($back) . '">' .
        $modal_mark_despatched->getButtons())
    ->put();

$modal_similar = new ConfirmModal('similarOrderModal', 'Create duplicate');
echo $modal_similar->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
    ->addForm('/orders/similar', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="similar_id">' . $modal_similar->getButtons())
    ->put();

$modal_cancel = new ConfirmModal('cancelOrderModal', 'Cancel order');
echo $modal_cancel->addContent('<div class="alert alert-danger mb-0">Are you sure?</div>')
    ->addForm('/orders/cancel', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="cancel_id">' . $modal_cancel->getButtons())
    ->put();

$modal_query = new ConfirmModal('queryOrderModal', 'Query order');
echo $modal_query->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
    ->addForm('/orders/query_order', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="query_id">' . $modal_query->getButtons())
    ->put();

$modal_revert_query = new ConfirmModal('revertQueryOrderModal', 'Revert order from query');
echo $modal_revert_query->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
    ->addForm('/orders/revert_query_order', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="revert_query_id">' . $modal_revert_query->getButtons())
    ->put();
?>
<?php
$body_shipping_data = '';
foreach ($shipping_data ?? [] as $package_name => $shipping_item) {

    $file_column = $carrier->getIsInternal() != 1 ? 'File' : '';

    $body_shipping_data .= '<div class="border p-2">
                              <h5 class="m-2">' . View::toHtml($package_name . (!empty($shipping_item[0]['box']) ? ' (' . $shipping_item[0]['box'] . ')' : '')) . '</h5>
                            <table class="table table-hover">
                              <colgroup>
                                  <col width="25%">
                                  <col width="65%">
                                  <col width="10%">
                              </colgroup>
                              <thead><tr><th>Date</th><th>Message</th><th>' . $file_column . '</th></tr></thead>
                              <tbody>';
    foreach ($shipping_item as $shipping) {
        $text_class = 'text-danger';
        if ($shipping['status'] == 31) {
            $text_class = 'text-warning';
        } elseif ($shipping['status'] == 4) {
            $text_class = 'text-success';
        }
        $link_to_file= '';
        if (!empty($shipping['tracking_number']) && $carrier->getIsInternal() != 1) {
            $file_for_link = urldecode($shipping['tracking_number'] . ($shipping['status'] == 4 ? '.txt' : ''));
            $link_to_file .= '<a class="btn btn-primary btn-icon-split check-file" data-file="' . $file_for_link . '"><span class="icon text-white-50"><i class="fa fa-download"></i></span></a>';
        }
        $body_shipping_data .= '<tr>
                                  <td>' . (!empty($shipping['create_date']) ? View::toHtml(date(WithDate::DATE_FRONT_FORMAT, strtotime($shipping['create_date']))) : '') . '</td>
                                  <td class="' . $text_class . ' font-weight-bold">' . ($shipping['status'] == 4 ? 'Success' : View::toHtml($shipping['url'])) . '</td>
                                  <td>' . $link_to_file . '</td>
                                </tr>';
    }
    $body_shipping_data .= '  </tbody>
                            </table>
                            </div>';
}

$modalShippingData = new InfoModal('shippingData', $carrier->getCode() . ' Requests', InfoModal::SIZE_LG);
echo $modalShippingData->addContent($body_shipping_data)->put();
?>
<?php if ($authUser->hasAdminAccess() && !in_array($order->getTypeCreate(), Sources::$not_show_requests)) { ?>
    <style>
        .modal-dialog-request,
        .modal-content-request {
            height: calc(100% - 30px);
        }
        .modal-body-request{
            max-height: calc(100% - 130px);
            overflow-y: auto;
        }
        #btn_up {
            padding: 8px 10px;
            opacity: .4;
            border-radius: 5px;
        }
    </style>
    <div class="modal" tabindex="-1" role="dialog" id="originalRequest">
        <div class="modal-dialog modal-lg modal-dialog-request" role="document">
            <div class="modal-content modal-content-request">
                <div class="modal-header">
                    <h3 class="modal-title">Original Request details</h3>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body modal-body-request pb-0">
                    <div id="log-loading">
                        <h5></h5>
                    </div>
                    <div id="log-request" class="logs d-none">
                        <div class="row">
                            <div class="col-md-10 pt-2">
                                <h5><b>Request</b></h5>
                            </div>
                            <div class="col-md-2 mb-2">
                                <button class="float-right border border-dark btn btn-xs mr-0 btn-primary" onclick="copyToClipboard('#log-request-content');">Copy</button>
                            </div>
                        </div>
                        <pre id="log-request-content" style="background-color: #fdfdfd; border: 1px solid #111111; padding: 20px"></pre>
                    </div>
                    <div id="log-response" class="logs d-none">
                        <div class="row">
                            <div class="col-md-10 pt-2">
                                <h5><b>Response</b></h5>
                            </div>
                            <div class="col-md-2 mb-2">
                                <button class="float-right border border-dark btn btn-xs mr-0 btn-primary" onclick="copyToClipboard('#log-response-content');">Copy</button>
                            </div>
                        </div>
                        <pre id="log-response-content" style="background-color: #fdfdfd; border: 1px solid #111111; padding: 20px"></pre>
                        <?php if ($order->printingDocumentsIssue() && $authUser->isRoleSA()) { ?>
                            <div class="row">
                                <div id="getError" class="col text-white text-center mb-1 d-none">
                                    <p class="form-control-static bg-danger p-1 m-0" id="msgError"></p>
                                </div>
                            </div>
                            <div class="row justify-content-end">
                                <div class="col-auto">
                                    <div class="input-group">
                                        <select class="form-control custom-select col-auto" id="changeCurrency">
                                            <?php foreach ($currencies as $id => $currency) { ?>
                                                <option value="<?= View::toHtml($id) ?>"><?= View::toHtml($currency) ?></option>
                                            <?php } ?>
                                        </select>
                                        <div class="input-group-append">
                                            <button class="form-group-element btn btn-success js-change-currency p-1" disabled>Currency change</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer px-1 justify-content-between">
                    <div class="col">
                        <button id="btn_up" onclick="$('.modal-body-request').show().scrollTop(0);" class="btn btn-secondary btn-icon-split">
                            <i class="fas fa-angle-up"></i>
                        </button>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-secondary btn-icon-split" data-dismiss="modal" type="button">
                            <span class="text">Close</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        let currency;

        $('#changeCurrency').change(function(e) {
            $('.js-change-currency').prop('disabled', currency == $('#changeCurrency').val());
        });

        $('.js-change-currency').click(function() {
            $.ajax({
               url: "/orders/change_currency",
               method: "POST",
               data: {
                   id: <?= $order->getId(); ?>,
                   currency: $('#changeCurrency').val()
               },
               dataType: "json",
               success: function (res) {
                   if (res.error !== undefined)
                       showErrorMessage('getError', 'msgError', res.error, 3000);
                   else
                       $('#originalRequest').modal('hide');
               },
               error: function () {
                   showErrorMessage('getError', 'msgError', 'Error. Please try again', 3000);
               }
            });
        });

        function copyToClipboard(element) {
            let $temp = $("<input>");
            $(element).append($temp);
            $temp.val($(element).text()).select();
            document.execCommand('copy');
            $temp.remove();
        }

        function showOriginalRequest() {
            const $modal = $('#originalRequest');
            const loader = $modal.find('#log-loading h5');
            loader.text('Loading...');
            $modal.modal('show');
            $.ajax({
                url: "/orders/get_original_request",
                method: "GET",
                data: {
                    id: <?= $order->getId(); ?>
                },
                dataType: "json",
                success: function (res) {
                    loader.text('');
                    if (res.error !== undefined) {
                        loader.text(res.error)
                    } else {
                        if (res.request !== undefined && res.request !== null) {
                            $modal.find('#log-request').removeClass('d-none');
                            $modal.find('#log-request pre').text(JSON.stringify(res.request, null, 4));
                        }
                        if (res.response !== undefined && res.response !== null) {
                            $modal.find('#log-response').removeClass('d-none');
                            currency = res.response.currency;
                            $('#changeCurrency option').filter(function () {
                                return $(this).val() == currency;
                            }).prop('selected', true);
                            $modal.find('#log-response pre').text(JSON.stringify(res.response, null, 4));
                        }
                    }
                },
                error: function () {
                    loader.text('Error. Please try again');
                    $modal.find('#log-request pre').html('');
                    $modal.find('#log-response pre').html('');
                }
            });
        }

        $('.modal-body-request').scroll(function () {
            const cl_list = document.getElementById('btn_up').classList;
            if ($(this).scrollTop() < 100) {
                if (!cl_list.contains('d-none'))
                    cl_list.add('d-none')
            } else if (cl_list.contains('d-none'))
                cl_list.remove('d-none')
        })

    </script>
<?php } ?>

<?= View::include_js(BACKEND_JS, 'show_error.js'); ?>
<script>
    $('.js-cancel-btn').click(function () {
        <?= $modal_cancel->show(); ?>;
        $('#cancel_id').val($(this).data('order-id'));
    });
    $('.js-similar-btn').click(function () {
        <?= $modal_similar->show(); ?>;
        $('#similar_id').val($(this).data('order-id'));
    });
    $('.js-query-btn').click(function () {
        <?= $modal_query->show(); ?>
        $('#query_id').val($(this).data('order-id'));
    });
    $('.js-reinstate-btn').click(function () {
        <?= $modal_revert_query->show(); ?>
        $('#revert_query_id').val($(this).data('order-id'));
    });

    $('#markDespatchedButton').click(function () {
        <?= $modal_mark_despatched->show(); ?>
    });

    $('#shippingButton').on("click", function() {
        <?= $modalShippingData->show(); ?>
    });

    $('.check-file').on("click", function(e) {
        e.preventDefault();
        let filename = this.getAttribute('data-file');
        $.post(
            "/pack/check_request_file",
            {filename},
            res => {
                if (res[0] !== undefined && res[0] === 'success')
                    window.open(`/pack/get_request_file/?filename=${filename}`,'_blank');
                else
                    alert('File not found. Contact your system administrator')
            },
            "json"
        );
    });

    $('#charge_update').click(() => {
        var preparation = parseFloat($('#charge_preparation').val().trim());
        var shipping = parseFloat($('#charge_shipping').val().trim());
        var additional = parseFloat($('#charge_additional').val().trim());
        var desc_preparation = $('#description_preparation').val().trim();
        var desc_shipping = $('#description_shipping').val().trim();
        var desc_additional = $('#description_additional').val().trim();
        if (isNaN(preparation) || isNaN(shipping) || isNaN(additional) ||
            preparation < 0 || shipping < 0 || additional < 0) {
            showError('charge_err', 'Incorrect price', 3000);
            return false;
        }
        if ((desc_preparation === '' && preparation != 0) || (desc_shipping === '' && shipping != 0) || (desc_additional === '' && additional != 0)) {
            showError('charge_err', 'Empty description', 3000);
            return false;
        }
        $.ajax({
            url: "/orders/charge",
            method: "POST",
            data: {
                order_id: <?= $order->getId(); ?>, desc_preparation: desc_preparation, preparation: preparation,
                desc_shipping: desc_shipping, shipping: shipping,
                desc_additional: desc_additional, additional: additional
            },
            success: function (json) {
                showError('charge_ok', 'Updated', 3000);
            },
            error: function (json) {
                showError('charge_err', 'Database error', 3000);
            }
        });
    });

    $('#btn_print_packing_slip').click(function () {
        printPdfDocument("<?= HTTP::getDomain() . "/orders/get_packing_slip?id=" . (int)$order->getId()?>");
    });

    <?php if (!empty($doc) && $doc->existFile()) { ?>
    $('#btn_print_doc').click(function () {
        printPdfDocument("<?= $doc->getUploadUrl((int)$order->getId()); ?>");
    });
    <?php } ?>

    $('.js-packing-slip').click(function (e) {
        var id = e.currentTarget.id.split('_');
        location = '/orders/get_packing_slip?id=' + id[1] + '&source_id=' + $('#printer_def').val();
    });

    $('.js-retry').click(function () {
        location = '/orders/retry_virtual?order_id=' + $('#order_id').val();
    });

    $('.show_serial_numbers').click(function () {
        let serial_numbers = <?= View::toJs($serial_numbers) ?>;
        let SKU_id = $(this).data("sku-id");
        let package_id = $(this).data("package-id");
        let tbody_serial_numbers = $('#tbody_serial_numbers');
        tbody_serial_numbers.empty();
        for (const serial_number in serial_numbers[SKU_id][package_id]) {
            let create_date = serial_numbers[SKU_id][package_id][serial_number];
            tbody_serial_numbers.append('<tr><td>' + html_escape(serial_number) + '</td><td>' + html_escape(create_date) + '</td></tr>');
        }
        <?= $show_serial_numbers->show(); ?>
    });
</script>

<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_orders.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_orders_bundles.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_orders_substitutions.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'edit_address.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'edit_ref.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'edit_doc.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'edit_shipping_service.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'edit_skus.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . 'short_ship.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'order_in_reality.php'; ?>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'order_webhook.php'; ?>
