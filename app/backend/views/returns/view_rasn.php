<?php
/**
 * @var $authUser User
 * @var $RASN ASN
 * @var $SKUs array
 * @var $comments array
 * @var $order Order
 * @var $delivery Delivery
 * @var $action int
 * @var $back string
 * @var $timezone string
 */

use app\backend\models\Suppliers;
use app\core\entities\ASN;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\statical\WithDate;
use app\core\statical\Timezones;

$user = $authUser->isRoleSupplierUser();

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Return ASN</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between px-2">
            <h6 class="m-0 text-primary col">
                <i class="fas fa-fw fa-sign-in-alt"></i> <b>Return ASN #<?= $RASN->getId() . ' / ' . View::toHtml($RASN->getRef() ?: '-'); ?></b>
                <span class="ml-4 badge badge-<?= ASN::getStateStyleReturn($RASN->getStatus()); ?>"><?= View::toHtml(ASN::getStateNameReturn($RASN->getStatus())); ?></span>
                <?php if ($RASN->getUnscheduled()) { ?>
                    <span class="ml-4 badge badge-danger">Unassigned</span>
                <?php } ?>
                <br/>
                <?php if ($RASN->getUnscheduled()) { ?>
                    <button class="btn btn-secondary btn-icon-split my-2" disabled>
                        <span class="icon text-white-50">
                            <i class="far fa-fw fa-list-alt"></i>
                        </span>
                        <span class="text text-white">Order #<?= $RASN->getOrderId() . ' / Ref: ' . View::toHtml($order->getRef() ?: '-') . ' / Ref2: ' . View::toHtml($order->getRef2() ?: '-'); ?></span>
                    </button>
                <?php } else { ?>
                    <a class="btn btn-primary btn-icon-split my-2" href='/orders/<?= $authUser->isRoleWorker() ? 'minified' : 'view_order'; ?>/?id=<?= $RASN->getOrderId(); ?>' target="_blank">
                        <span class="icon text-white-50">
                            <i class="far fa-fw fa-list-alt"></i>
                        </span>
                        <span class="text text-white">Order #<?= $RASN->getOrderId() . ' / Ref: ' . View::toHtml($order->getRef() ?: '-') . ' / Ref2: ' . View::toHtml($order->getRef2() ?: '-'); ?></span>
                    </a>
                <?php } ?>
                <?php if (!$order->isHashed() && $authUser->isRoleSupplierUser()) { ?>
                    <button type="button"
                            class="btn btn-success btn-icon-split my-2 js-similar-btn"
                            data-order-id="<?= $RASN->getOrderId(); ?>">
                        <span class="icon text-white-50">
                            <i class="far fa-fw fa-copy"></i>
                        </span>
                        <span class="text text-white">Duplicate</span>
                    </button>
                <?php } ?>
                <br/>
                <b>Despatched date</b>: <?= date('jS M Y H:i', $timezone ? strtotime(Timezones::getDiff($timezone, $order->getDespatchedDate())) : $order->getDespatchedDateInt()); ?><br/>
                <b><?= View::toHtml($delivery->getContact()); ?></b> (<?= View::toHtml($delivery->getEmail()); ?>)
            </h6>
            <div class="col-auto justify-content-end">
                <a class="btn btn-success btn-icon-split" href='/return_ASNs/manifest/?id=<?= $RASN->getId(); ?>' target="_blank">
                    <span class="icon text-white-50">
                        <i class="fas fa-fw fa-print"></i>
                    </span>
                    <span class="text text-white">Manifest</span>
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-lg-11">
                    <div class="row">
                        <div class="col">
                            <?php if (!empty($RASN->getComment())) { ?>
                                <p><span class="text-success"><b>Comment: </b></span><?= View::toHtml($RASN->getComment()); ?></p>
                            <?php } ?>
                            <?php if (!$RASN->completed() && !$RASN->cancelled()) { ?>
                                <p class="mb-1"><span class="text-success"><b>Stored location: </b></span></p>
                                <form method="post" action="/return_ASNs/update_location">
                                    <input type="hidden" name="id" value="<?= $RASN->getId(); ?>">
                                    <div class="row mb-3">
                                        <div class="input-group col-4">
                                            <input class="form-control" type="text" id="location" name="location" value="<?= View::toHtml($RASN->getShipToLocation()); ?>"/>
                                            <div class="input-group-append align-self-center">
                                                <button class="btn btn-primary p-2" id="btn_location" type="submit" disabled>
                                                    <i class="fas fa-fw fa-sync"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            <?php } elseif (!empty($RASN->getShipToLocation())) { ?>
                                <p class="mb-3"><span class="text-success"><b>Stored location: </b></span><?= View::toHtml($RASN->getShipToLocation()); ?></p>
                            <?php } ?>
                        </div>
                        <div class="col-auto">
                            <div class="form-group">
                                <label for="actioned" class="badge badge-nextday">CS Actioned</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="actioned" <?= $RASN->getActioned() ? ' checked' : ''; ?> onclick="changeActioned();">
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="form-control-static text-success"><b>Returned SKUs</b></p>
                    <form method="post" action="/return_ASNs/next_status">
                        <input type="hidden" name="id" value="<?= $RASN->getId(); ?>">
                        <div class="row">
                            <table class="table table-hover">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Barcode</th>
                                    <th>Product Name</th>
                                    <th>Batch No.</th>
                                    <th>Expired</th>
                                    <th>Quantity Returned</th>
                                    <th>Condition</th>
                                    <th>Reason</th>
                                    <th>Refund or Exchange</th>
                                    <th>Action Required</th>
                                </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($SKUs as $SKU) { ?>
                                        <tr>
                                            <td>
                                                <a class="btn-link" href="/SKUs/<?= $authUser->isRoleWorker() ? 'view_sku' : 'edit_sku' ?>/?id=<?= View::toHtml($SKU['SKU_id']); ?>&back=/return_ASNs/view_rasn^id=<?= $RASN->getId(); ?>">
                                                    <?= View::toHtml($SKU['SKU_id']); ?>
                                                </a>
                                            </td>
                                            <td><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></td>
                                            <td><?= View::toHtml($SKU['name']); ?></td>
                                            <td><?= View::toHtml($SKU['batch']['number'] ?? ''); ?></td>
                                            <td><?= !empty($SKU['batch']['expired']) ? date('Y-m-d', strtotime($SKU['batch']['expired'])) : ''; ?></td>
                                            <td><?= View::toHtml($SKU['quantity']); ?></td>
                                            <td>
                                                <?php if ($RASN->new())
                                                    echo Html::dropDownList("condition[{$SKU['SKU_id']}]", ASN::CONDITIONS, (string)$SKU['SKU']->getCondition(), [
                                                        'class' => 'form-control custom-select js-condition',
                                                        'id' => "condition_{$SKU['SKU_id']}",
                                                        'onchange' => 'changeAction(getAction());'
                                                    ]);
                                                else
                                                    echo '<span class="text-info font-weight-bolder">' . ASN::CONDITIONS[$SKU['SKU']->getCondition()] . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($RASN->new())
                                                    echo Html::dropDownList("reason[{$SKU['SKU_id']}]", ASN::REASONS, (string)$SKU['SKU']->getReason() . "", [
                                                        'class' => 'form-control custom-select js-reason',
                                                        'id' => "reason_{$SKU['SKU_id']}",
                                                        'onchange' => 'changeAction(getAction());'
                                                    ]);
                                                else
                                                    echo '<span class="text-info font-weight-bolder">' . ASN::REASONS[$SKU['SKU']->getReason()] . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($RASN->new())
                                                    echo Html::dropDownList("refund[{$SKU['SKU_id']}]", ASN::REFUNDS, (string)$SKU['SKU']->getRefund(), [
                                                        'class' => 'form-control custom-select js-refund',
                                                        'id' => "refund_{$SKU['SKU_id']}",
                                                        'onchange' => 'changeAction(getAction());'
                                                    ]);
                                                else
                                                    echo '<span class="text-info font-weight-bolder">' . ASN::REFUNDS[$SKU['SKU']->getRefund()] . '</span>';
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($RASN->action() && (!$authUser->isWarehouseSide() || $action))
                                                    echo Html::dropDownList("action[{$SKU['SKU_id']}]", ASN::ACTIONS, (string)$SKU['SKU']->getAction(), [
                                                        'class' => 'form-control custom-select js-action',
                                                        'id' => "action_{$SKU['SKU_id']}",
                                                        'onchange' => 'changeWarehouse(getWarehouse());'
                                                    ]);
                                                else
                                                    echo !$RASN->new() ? ('<span class="badge badge-light">' . ASN::ACTIONS[$SKU['SKU']->getAction()] . '</span>') : '-';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($RASN->new()) { ?>
                            <div class="row justify-content-end">
                                <div class="col-auto">
                                    <button class="btn btn-success btn-icon-split" type="submit" id="btn_change_action">
                                        <span class="text text-white">To action</span>
                                        <span class="icon text-white">
                                            <i class="fas fa-fw fa-play"></i>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ($RASN->action() && (!$authUser->isWarehouseSide() || $action)) { ?>
                            <div class="row justify-content-end">
                                <div class="col-auto">
                                    <button class="btn btn-success btn-icon-split" type="submit" id="btn_change_warehouse">
                                        <span class="text text-white">To warehouse</span>
                                        <span class="icon text-white">
                                            <i class="fas fa-fw fa-warehouse"></i>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        <?php } ?>
                        <?php if ($RASN->warehouse() && $authUser->isWarehouseSide()) { ?>
                            <div class="row justify-content-end">
                                <div class="col-auto">
                                    <a class="btn btn-success btn-icon-split mb-1" href='/return_ASNs/complete/?id=<?= $RASN->getId() . '&view=1'; ?>'>
                                        <span class="text text-white">Complete</span>
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-power-off"></i>
                                        </span>
                                    </a>
                                </div>
                            </div>
                        <?php } ?>
                    </form>
                    <p class="form-control-static text-success mb-1"><b>Comments</b></p>
                    <?php if (!$RASN->completed() && !$RASN->cancelled()) { ?>
                        <form method="post" action="/return_ASNs/add_comment">
                            <input type="hidden" name="id" value="<?= $RASN->getId(); ?>">
                            <div class="row justify-content-end">
                                <div class="form-group col-12 mb-0">
                                    <textarea class="form-control" id="comment" name="comment" rows="2" onkeyup="addComment(event)"></textarea>
                                </div>
                                <div class="col-auto">
                                    <button class="btn btn-primary btn-icon-split my-2" type="submit" id="add_comment_btn" disabled>
                                        <span class="text text-white">Add Comment</span>
                                        <span class="icon text-white">
                                            <i class="fas fa-fw fa-comment"></i>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    <?php } ?>
                    <div class="row px-2">
                        <div class="col-lg-12">
                            <?php foreach ($comments as $comment) { ?>
                                <div class="row alert p-1 mt-2 mb-0" style="background-color: #f8f8f8">
                                    <div class="col-auto p-0">
                                        <img class="img-thumbnail" style="max-width: 35px" src="<?=
                                            ($comment['status'] == 1 ? COMMON_IMG . DIRECTORY_SEPARATOR . 'system.png' :
                                                (empty($comment['supplier_id']) ? COMMON_IMG . DIRECTORY_SEPARATOR . 'system_logo.png' :
                                                    (empty($comment['logo']) ? COMMON_IMG . DIRECTORY_SEPARATOR . 'client.png' :
                                                        Suppliers::showLogoSrc($comment['logo'])))); ?>"/>
                                    </div>
                                    <div class="col">
                                        <div class="row justify-content-between mb-2">
                                            <div class="col font-weight-bold"><?= View::toHtml($comment['login'] . ($comment['name'] !== '' ? $comment['name'] : "")); ?></div>
                                            <div class="col-auto text-xs"><?= date('jS F Y @ H:i:s', WithDate::dateToInt($comment['create_date'])); ?></div>
                                        </div>
                                        <div class="row"><div class="col"><?= View::toHtmlHistory($comment['comment']); ?></div></div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
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
            </div>
        </div>
    </div>
</div>
<?php

$modal_similar = new ConfirmModal('similarOrderModal', 'Create duplicate');
echo $modal_similar->addContent('<div class="alert alert-info mb-0">Are you sure?</div>')
    ->addForm('/orders/similar', ConfirmModal::METHOD_POST, ['target' => '_new'])
    ->changeYes(ConfirmModal::TYPE_DEFAULT, ConfirmModal::BUTTON_DONE, ['id' => 'btn_similar_conf'])
    ->addCustomButtons('<input type="hidden" name="id" id="similar_id"><input type="hidden" name="RASN_id" value="' . $RASN->getId() . '">' . $modal_similar->getButtons())
    ->put();

?>
<script>
    function addComment(el) {
        const btn = document.getElementById('add_comment_btn');
        if (btn)
            btn.disabled = !(el.target.value.trim().length > 0)
    }

    $('#location').keyup(function () {
        $('#btn_location').attr('disabled', $('#location').val().trim() == <?= View::toJs((string)$RASN->getShipToLocation()); ?>);
    });

    function changeAction(show) {
        $('#btn_change_action').attr('disabled', !show);
    }

    function changeWarehouse(show) {
        $('#btn_change_warehouse').attr('disabled', !show);
    }

    function getAction() {
        var sum = 1;
        $.find('.js-condition').forEach(function (values, item) {
            sum *= +values.value;
        });
        $.find('.js-reason').forEach(function (values, item) {
            sum *= +values.value;
        });
        $.find('.js-refund').forEach(function (values, item) {
            sum *= +values.value;
        });
        return sum !== 0;
    }

    function getWarehouse() {
        var sum = 1;
        $.find('.js-action').forEach(function (values, item) {
            if (values.disabled)
                sum = 0;
            sum *= +values.value;
        });
        return sum !== 0;
    }

    function changeActioned() {
        $.ajax({
            url: "/return_ASNs/actioned",
            method: "POST",
            data: { ASN_id: <?= $RASN->getId(); ?>, actioned: $('#actioned').is(':checked') ? 1 : 0 },
            dataType: "json",
            success: function (json) {
                if (json.result === 'error')
                    $('#actioned').prop('checked', !$('#actioned').is(':checked'));
            },
            error: function (json) {
                $('#actioned').prop('checked', !$('#actioned').is(':checked'));
            }
        });
    }

    $('.js-similar-btn').click(function () {
        <?= $modal_similar->show(); ?>;
        $('#similar_id').val($(this).data('order-id'));
    });

    $('#btn_similar_conf').click(function () {
        <?= $modal_similar->hide(); ?>;
    });

    changeAction(getAction());
    changeWarehouse(getWarehouse());

</script>
