<?php

use app\backend\models\OrderDocs;
use app\backend\models\Shippings;
use app\core\entities\Carrier;
use app\core\entities\Order;
use app\core\entities\OrderDoc;
use app\core\entities\ShippingService;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\HTTP;
use app\core\helpers\scan\Scan;
use app\core\helpers\status\Status;
use app\core\helpers\View;
use app\core\models\PackingSlips;
use app\core\Permission;
use app\core\helpers\modal\InfoModal;
use app\core\models\OrderCategories;
use app\core\statical\WithDate;

/**
 * @var User $authUser
 * @var Order $order
 * @var array $orderDetails
 * @var Carrier $carrier
 * @var bool $warehouse_virtual
 * @var Permission $permission
 * @var string $eori_number
 * @var string $vat_number
 * @var string $ioss_number
 * @var string $tax_id
 * @var int $printed_matter
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Orders (Minified)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col-md">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-sign-in-alt"></i>
                             Scan order
                        </span>
                    </h6>
                </div>
            </div>
            <form method="GET">
                <div class="row justify-content-md-center">
                    <div class="input-group col-lg-10">
                        <?php (new Scan([
                            'name' => 'id',
                            'placeholder' => 'Scan Order ID here to get details',
                            'type' => Scan::TYPE__ORDER,
                        ], ['id' => 'order_id']))->render(); ?>
                    </div>
                </div>
            </form>
            <?php if (!empty($order)) { ?>
                <hr>
                <div class="row">
                    <div class="col mb-1">
                        <h1 class="h3 text-gray-800">
                            View order #<?= $order->getId(); ?>
                            <?= $orderDetails['special'] ? Status::getStatus(ShippingService::SPECIAL_NAMES[$orderDetails['special']], 'success') : ''; ?>
                            <?= $printed_matter ? Status::getStatus('Printed Matter', 'success') : ''; ?>
                        </h1>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3 text-lg">
                        <div class="row">
                            <div class="col-auto">
                                Current status:
                            </div>
                            <div class="col-auto">
                                <span class="text-primary"><?= ucwords($order->getStatusName()); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col text-lg">
                        <div class="float-right">
                            <?php
                            $num_stage = $order->getStage();
                            foreach (Order::getStages() as $key => $stage)
                                echo "<span class='text-" . ($num_stage >= $key ? 'success' : 'dark') . "'>$stage" . ($num_stage == $key && !empty($orderDetails['percent']) && !$warehouse_virtual ? " ({$orderDetails['percent']})" : '') . "</span>" . ($key < 5 ? ' > ' : '');
                            if (!empty($orderDetails['returns'])) {
                                echo '<br/><div class="row text-left justify-content-end"><div class="col-auto bg-primary text-white">Return ASNs:</div><div class="col-auto bg-primary text-white">';
                                foreach ($orderDetails['returns'] as $return)
                                    echo "<b>{$return['id']} / " . (!empty($return['ref']) ? View::toHtml($return['ref']) : '-') . '</b> (' . View::toHtml($return['comment']) . ')<br/>';
                                echo '</div></div>';
                            }
                            ?>
                        </div>

                    </div>
                </div>
            <?php } ?>
        </div>
        <?php  if (!empty($order)) { ?>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col h3 mt-2">SKUs</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <colgroup>
                                <col width="10%">
                                <col width="*">
                                <col width="*">
                                <col width="20%">
                                <col width="10%">
                                <col width="15%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>SKU ID</th>
                                <th>Barcode</th>
                                <th>Name</th>
                                <th>Batches</th>
                                <th>Quantity</th>
                                <?php if (!$warehouse_virtual) { ?>
                                    <th>Current location</th>
                                <?php } ?>
                                <th>Active orders</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($orderDetails['SKUs'] as $SKU) { ?>
                                <tr>
                                    <td>
                                        <a class="btn-link" href="/SKUs/view_sku/?id=<?= View::toHtml($SKU['id']); ?>&back=/orders/minified^id=<?= $order->getId(); ?>">
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
                                            <?php if (!empty($orderDetails['SKU_locations'][$SKU['id']])) { ?>
                                                <?php foreach ($orderDetails['SKU_locations'][$SKU['id']] as $sku_location) { ?>
                                                    <?= $sku_location['batch_number']; ?>
                                                    <br/>
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
                                    <?php if (!$warehouse_virtual) { ?>
                                        <td>
                                            <?php if ($order->despatched()) { ?>
                                                <b>Already despatched</b>
                                            <?php } elseif (!empty($orderDetails['SKU_locations'][$SKU['id']])) { ?>
                                                <?php foreach ($orderDetails['SKU_locations'][$SKU['id']] as $sku_location) { ?>
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
                    </div>
                    <hr/>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="row">
                                <div class="col">
                                    <h3>General info</h3>
                                </div>
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
                                            <p class="form-control-static mb-0"><b>Purchase Order Number:</b>
                                                <?= View::toHtml(empty($order->getPurchaseOrderNumber()) ? '-' : $order->getPurchaseOrderNumber()); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <p class="form-control-static"><b>Account Name:</b>
                                        <?= View::toHtml($orderDetails['supplier']['name'] ?? ''); ?>
                                    </p>
                                    <p class="form-control-static">
                                        <b>Created:</b> <?= View::toHtml(date('jS M Y H:i',
                                            strtotime($order->getCreateDate()))) ?>
                                        <br/>
                                        <b>Despatch:</b> <?= (!empty($order->getDespatchDate()) && strtotime($order->getDespatchDate()) > 0) ? View::toHtml(date('jS M Y',
                                            $order->getDespatchDateInt())) : "-" ?>
                                        <br/>
                                        <b>Despatched:</b> <?= (!empty($order->getDespatchedDate()) && strtotime($order->getDespatchedDate()) > 0) ? View::toHtml(date('jS M Y H:i',
                                            $order->getDespatchedDateInt())) : "-" ?>
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
                                            <p class="form-control-static mb-0"><b>Delivery address</b></p>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col">
                                            <?= $orderDetails['delivery_address']; ?>
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
                            </div>
                            <?php if (empty($orderDetails['doc'])) { ?>
                                <div class="row mt-0">
                                    <div class="col"><p class="m-0">No files</p></div>
                                </div>
                            <?php } elseif ($orderDetails['doc'] instanceof OrderDoc) {
                                $doc = $orderDetails['doc']; ?>
                                <div class="row mt-1">
                                    <div class="col">
                                        <p class="form-control-static m-0">
                                            <b>Status:</b> <span class="badge badge-<?= OrderDocs::colorStatus($doc->getStatus()); ?>"><?= $doc->getStatus(); ?></span>
                                        </p>
                                    </div>
                                    <div class="col-auto">
                                        <?php if (!empty($doc->getName()) && $doc->existFile()) { ?>
                                            <button id="btn_print_doc"
                                                    class="btn btn-primary"
                                                    type="button"
                                                    data-toggle="tooltip" data-placement="top" title="<?= View::toHtml($doc->getName()) ?>"
                                                    onmouseover="$(event.target).tooltip('show')">
                                                Print Doc File
                                            </button>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php if (!empty($doc->getDocumentUrl()) && !$doc->existFile()) { ?>
                                    <div class="row mt-1">
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
                                <?php if (!empty($orderDetails['shipping_data']) && !$warehouse_virtual && empty($order->getIsHash())) { ?>
                                    <div class="col-auto">
                                        <button id="shippingButton" class="btn btn-lg btn-outline-<?= $order->printingDocumentsIssue() ? 'primary' : 'primary'; ?> p-1">
                                            <?= $carrier->getCode(); ?> Requests
                                        </button>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="row mb-1">
                                <div class="col-md-7 border p-2 ml-2 mr-2 mb-2">
                                    <div class="row">
                                        <div class="col">
                                            <p class="form-control-static mb-0"><b>Shipping service:</b>
                                                <?= View::toHtml($orderDetails['shipping_service_name']); ?>
                                            </p>
                                            <p class="form-control-static mb-0"><b>Terms:</b>
                                                <?= View::toHtml(Shippings::TERMS[(int)$order->getDDP()]); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col mx-1 p-0 pr-2">
                                    <?php if (!empty($orderDetails['shipping_errors']) && !$warehouse_virtual) { ?>
                                        <div class="alert alert-danger mb-0">
                                            <?php
                                            for ($i = 0; $i < count($orderDetails['shipping_errors']) - 1; $i++)
                                                echo View::toHtml($orderDetails['shipping_errors'][$i]) . '<hr class="my-1"/>';
                                            echo View::toHtml($orderDetails['shipping_errors'][$i]);
                                            ?>
                                        </div>
                                    <?php } ?>
                                    <?php if (!empty($orderDetails['risk_messages'])) { ?>
                                        <div class="alert alert-danger mb-0">
                                            <?= View::toHtmlHistory($orderDetails['risk_messages']); ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php if (!$warehouse_virtual && !$order->getIsHash()) { ?>
                                <div class="row mb-1">
                                    <div class="col-md-7 px-3 mx-2 mb-0">
                                        <div class="row">
                                            <?php if ($orderDetails['packing_slip_type'] === PackingSlips::TYPE_RETAIN_ME) { ?>
                                                <button class="col my-3 btn btn-secondary js-packing-slip" id="prndoc_<?= $order->getId(); ?>" type="button">
                                                    Request Retain.me packing slip
                                                </button>
                                                <h6 class="col-auto font-weight-bold text-primary pr-0">Select Retain Me printer
                                                    <?php
                                                    echo Html::dropDownList('printer_def', $orderDetails['printer_list'], $orderDetails['printer_def'] . "",
                                                        [
                                                            'class' => 'selectpicker form-control',
                                                            'id' => 'printer_def',
                                                        ]);
                                                    ?>
                                                </h6>
                                            <?php } elseif($orderDetails['packing_slip_type'] !== PackingSlips::TYPE_NONE && $permission->isAccessByUrl('orders/get_packing_sli')) { ?>
                                                <a target="_blank" class="col btn btn-primary mr-2" href="/orders/get_packing_slip?id=<?= (int)$order->getId() ?>">
                                                    View Packing slip
                                                </a>
                                                <button class="col btn btn-primary ml-2 d-none d-sm-block" id="btn_print_packing_slip">
                                                    Print Packing slip
                                                </button>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <?php if (!empty($orderDetails['packages'])) {
                                foreach ($orderDetails['packages'] as $package) { ?>
                                    <div class="order-box p-3 mb-2 mt-2">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="order-box-header p-2">
                                                    <div class="row">
                                                        <div class="col-sm-4">
                                                            <h4 class="title"><?= View::toHtml($package['name']) ?></h4>
                                                            <div class="form-check-inline">
                                                                <input type="checkbox" class="form-check-input"
                                                                    <?= (int)$package['order_inserts'] ? ' checked' : ''; ?> disabled>
                                                                <label for="order_inserts" class="mt-2">Order insert used</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-3">
                                                            <span><?= empty($orderDetails['package_types'][$package['package_type_id']]) ? '-' : View::toHtml($orderDetails['package_types'][$package['package_type_id']]); ?></span>
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
                                                                <span><strong><?= View::toHtml($sku['barcode']) ?></strong></span>
                                                            </div>
                                                            <?php if ($sku['serial_number']) { ?>
                                                                <div class="col-2 text-right">
                                                                    <button type="button" class="btn btn-primary btn-icon-split mt-2 show_serial_numbers" id="show_serial_number_<?= $sku['id'] ?>" data-package-id="<?= $package['id'] ?>" data-sku-id="<?= $sku['id'] ?>">
                                                                        <span class="icon text-white-50">
                                                                            <i class="fas fa-fw fa-cube"></i>
                                                                        </span>
                                                                        <span class="text d-none d-md-block pl-1 pr-1"><span id="quantity_serial_numbers_<?= $sku['id']; ?>"><?= count($orderDetails['serial_numbers'][$sku['id']][$package['id']]); ?></span></span>
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
                                                    <hr class="d-block d-sm-none">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if ($order->getShippingServicesId()) {
                                                    if (!empty($package['shipping']['tracking_number'])) { ?>
                                                        <h4>Assigned Tracking Number:
                                                            <?php if (!empty($package['shipping']['url'])) { ?>
                                                                <span class="badge badge-light text-white">
                                                                    <a target="_blank" href="<?= View::toHtml($package['shipping']['url']) ?>">
                                                                        <?= View::toHtml($package['shipping']['tracking_number']) ?>
                                                                    </a>
                                                                </span>
                                                            <?php } else { ?>
                                                                <span class="badge badge-secondary">
                                                            <?= View::toHtml($package['shipping']['tracking_number']) ?>
                                                        </span>
                                                            <?php } ?>
                                                        </h4>
                                                        <?php if (!$warehouse_virtual) { ?>
                                                            <?php if (!empty($package['shipping']['url']) || $carrier->getIsInternal()) { ?>
                                                                <a class="btn btn-primary w-100 mb-3" target="_blank"
                                                                   href="<?= HTTP::getZprnUrl("/main/print_label?shipment_id={$package['shipping']['id']}&hash=" . md5($package['shipping']['tracking_number'])) ?>">
                                                                    Print Shipping Label
                                                                </a>
<!--                                                                <a class="btn btn-primary w-100" target="_blank"-->
<!--                                                                   href="--><?php //= HTTP::getDomain(); ?><!--/main/print_label?shipment_id=--><?php //= $package['shipping']['id']; ?><!--&hash=--><?php //= md5($package['shipping']['tracking_number']) ?><!--&viewMode=1">-->
<!--                                                                    View Shipping Label-->
<!--                                                                </a>-->
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
                                                        <a class="btn btn-primary w-100 mb-3" target="_blank"
                                                           href="<?= HTTP::getZprnUrl("/main/print_label?return_id={$package['returns']['id']}&hash=" . md5($package['returns']['tracking_number'])) ?>">
                                                            Print Return Label
                                                        </a>
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
                            <?php foreach ($orderDetails['history'] as $item) { ?>
                                <tr>
                                    <td><?= View::toHtml(date('jS M Y H:i', strtotime($item['create_date']))) ?></td>
                                    <td><?= View::toHtml(!empty($orderDetails['users'][$item['user_id']]) ? $orderDetails['users'][$item['user_id']] : 'System') ?></td>
                                    <td><?= View::toHtmlHistory($item['comment']); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!$warehouse_virtual && !$order->getIsHash()) { ?>
                        <hr/>
                        <h3>Additional charge</h3>
                        <div class="row">
                            <?php
                            $charge = false;
                            if ($order->getBillingChargePreparation()) {
                                $charge = true;
                                ?>
                                <div class="col-lg-3 col-12 border py-2 mx-1">
                                    <p class="form-control-static mt-1 mb-1"><b>Custom additional charge for
                                            preparation:</b></p>
                                    <p class="form-control-static mt-1 mb-1"><?= View::toHtml($order->getBillingDescriptionPreparation()); ?></p>
                                    <p class="form-control-static mt-1 mb-1 mr-4 text-right"><?= sprintf("%5.3f %s",
                                            $order->getBillingChargePreparation(),
                                            $order->getBillingChargeCurrency()); ?></p>
                                </div>
                            <?php } ?>
                            <?php
                            if ($order->getBillingChargeShipping()) {
                                $charge = true;
                                ?>
                                <div class="col-lg-3 col-12 border py-2 mx-1">
                                    <p class="form-control-static mt-1 mb-1"><b>Custom additional charge for shipping:</b></p>
                                    <p class="form-control-static mt-1 mb-1"><?= View::toHtml($order->getBillingDescriptionShipping()); ?></p>
                                    <p class="form-control-static mt-1 mb-1 mr-4 text-right"><?= sprintf("%5.3f %s",
                                            $order->getBillingChargeShipping(),
                                            $order->getBillingChargeCurrency()); ?></p>
                                </div>
                            <?php } ?>
                            <?php
                            if ($order->getBillingChargeAdditional()) {
                                $charge = true;
                                ?>
                                <div class="col-lg-3 col-12 border py-2 mx-1">
                                    <p class="form-control-static mt-1 mb-1"><b>Custom additional charge for additional shipping:</b></p>
                                    <p class="form-control-static mt-1 mb-1"><?= View::toHtml($order->getBillingDescriptionAdditional()); ?></p>
                                    <p class="form-control-static mt-1 mb-1 mr-4 text-right"><?= sprintf("%5.3f %s",
                                            $order->getBillingChargeAdditional(),
                                            $order->getBillingChargeCurrency()); ?></p>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if (!$charge) { ?>
                            <p class="form-control-static mt-1">No additional charge</p>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>

<?php
$show_serial_numbers = new InfoModal('serialNumbers', 'Serial numbers');
echo $show_serial_numbers->addContent('
<table class="table table-hover mb-0">
    <thead><tr><th>Serial number</th><th>Created</th></tr></thead>
    <tbody id="tbody_serial_numbers"></tbody>
</table>')->put();
?>

<?php if (!empty($order)) {
    $body_shipping_data = '';
    foreach ($orderDetails['shipping_data'] ?? [] as $package_name => $shipping_item) {

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

    <script>

        $('#shippingButton').on('click', function () {
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

        $('.show_serial_numbers').click(function () {
            let serial_numbers = <?= View::toJs($orderDetails['serial_numbers']); ?>;
            let SKU_id = $(this).data("sku-id");
            let package_id = $(this).data("package-id");
            let tbody_serial_numbers = $('#tbody_serial_numbers');
            tbody_serial_numbers.empty();
            for (var serial_number in serial_numbers[SKU_id][package_id]) {
                let create_date = serial_numbers[SKU_id][package_id][serial_number];
                tbody_serial_numbers.append('<tr><td>' + html_escape(serial_number) + '</td><td>' + html_escape(create_date) + '</td></tr>');
            }
            <?= $show_serial_numbers->show(); ?>
        });

    </script>

    <?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_orders.php'; ?>

<?php } ?>