<?php

/**
 * @var $authUser User
 * @var $SKUs array
 * @var $back string
 * @var $param string
 * @var $location Location
 * @var $SKUs_with_movements array
 */

use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\entities\Location;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Location <?= View::toHtml($location->getLocation($warehouse_label)); ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        SKUs list (<?= count($SKUs); ?> item<?= count($SKUs) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body mx-3">
            <div class="row justify-content-center">
                <div class="col">
                    <div class="row table-responsive">
                        <table class="table table-hover ">
                            <colgroup>
                                <col width="25%">
                                <col width="*">
                                <col width="20%">
                                <col width="10%">
                                <col width="10%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Barcode</th>
                                <th>Account</th>
                                <th>Quantity</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($SKUs as $SKU) { ?>
                                    <tr>
                                        <td><?= View::toHtml($SKU['name']) ?> (<?= View::toHtml($SKU['SKU_id']) ?>)</td>
                                        <td><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']) ?></td>
                                        <td><?= View::toHtml($SKU['sname']) ?> (<?= View::toHtml($SKU['id']) ?>)</td>
                                        <td><?php
                                            if (!empty($SKU['detail']) && !$location->DH()) {
                                                echo "<div class='col p-0'><button class='btn btn-primary btn-icon-split text-nowrap' data-toggle='collapse' data-target='#detail{$SKU['SKU_id']}'>
                                                          <span class='text' style='min-width: 50px;'>{$SKU['summa']}</span>
                                                          <span class='icon text-white'>
                                                              <i class='fas fa-fw fa-chevron-down'></i>
                                                          </span>
                                                      </button></div>";
                                                echo "<div id='detail{$SKU['SKU_id']}' class='collapse'>{$SKU['detail']}</div>";
                                            } else
                                                echo View::toHtml($SKU['summa']);
                                        ?></td>
                                        <td>
                                            <a class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap" href="/SKUs/view_sku/?id=<?= View::toHtml($SKU['SKU_id']) ?>&loc=<?= View::toHtml("{$location->getId()}&$param&bb=$back&back=/housekeeping/locations"); ?>">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-info"></i>
                                                </span>
                                                <span class="text">Detail</span>
                                            </a>
                                            <?php if (in_array($SKU['SKU_id'], $SKUs_with_movements) && !$authUser->isRoleWorker()) { ?>
                                                <button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap js-reconciliation-log" data-sku-id="<?= $SKU['SKU_id']; ?>">
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-book"></i>
                                                    </span>
                                                    <span class="text">Reconciliation</span>
                                                </button>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-outline-orange pl-3" href="<?= View::toHtml(HTTP::safeLinkUrl("$back/?$param")); ?>">
                        <i class="fas fa-fw fa-chevron-left mr-2"></i>
                        <span class="ml-1">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= View::include('utility/reconciliation_log_widget.php', ['secondary' => false]); ?>
<script>
    $(document).ready(function() {
        $('.js-reconciliation-log').click(function () {
            fillReconciliationLogWindow($(this).data('sku-id'));
        });
    });
</script>