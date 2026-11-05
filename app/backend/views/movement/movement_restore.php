<?php

use app\backend\models\MovementHistories;
use app\core\entities\Movement;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\statical\WithDate;
use app\core\helpers\modal\ConfirmModal;

/**
 * @var User $authUser
 * @var string $barcode
 * @var array $movements
 * @var int $total_count
 * @var int $total_pages
 * @var int $current_page
 * @var string $back
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Restore</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        Movements list (<?= $total_count ?> item<?= $total_count > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row justify-content-md-center">
                    <div class="input-group col-lg-10">
                        <?php (new Scan([
                            'name' => 'barcode',
                            'placeholder' => 'Scan Barcode',
                            'type' => Scan::TYPE__SKU,
                        ]))->render(); ?>
                    </div>
                </div>
            </form>
            <?php if (!empty($barcode)) { ?>
                <div class="row justify-content-center mb-2 mx-2">
                    <div class="col-10">
                        Scanned barcode: <b><?= View::toHtml($barcode) ?></b>
                    </div>
                </div>
                <?php if (count($movements)) {
                    View::pagination("/movement/restore_manually_removed_list/?barcode=" . urlencode($barcode) . '&', 1, $total_pages, $current_page, MovementHistories::LIMIT_PER_PAGE); ?>
                    <div class="row justify-content-center my-2 p-0">
                        <div class="col-12">
                            <table class="table table-hover">
                                <colgroup>
                                    <col width="*">
                                    <col width="12%">
                                    <col width="12%">
                                    <col width="5%">
                                    <col width="12%">
                                    <col width="12%">
                                    <col width="*">
                                    <col width="7%">
                                    <col width="5%">
                                </colgroup>
                                <thead>
                                <tr>
                                    <th>Supplier</th>
                                    <th>Origin location</th>
                                    <th>Destination location</th>
                                    <th>Quantity</th>
                                    <th>Created</th>
                                    <th>Removed</th>
                                    <th>Comment</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movements as $movement) { ?>
                                        <tr>
                                            <td><?= View::toHtml($movement['supplier_name']); ?></td>
                                            <td><?= View::toHtml($movement['loc_from']); ?></td>
                                            <td><?= View::toHtml($movement['loc_to']); ?></td>
                                            <td><?= View::toHtml($movement['qty']); ?></td>
                                            <td><?= View::toHtml(date(WithDate::DATE_FRONT_FORMAT, strtotime($movement['create_date']))); ?><br/>by <b><?= View::toHtml($movement['create_user']); ?></b></td>
                                            <td><?= View::toHtml(date(WithDate::DATE_FRONT_FORMAT, strtotime($movement['remove_date']))); ?><br/>by <b><?= View::toHtml($movement['remove_user']); ?></b></td>
                                            <td><?= str_replace(';' . PHP_EOL, ';<br/>', View::jsonToHtml($movement['comment'], ';' . PHP_EOL)); ?></td>
                                            <td>
                                                 <span class="badge badge-<?= View::toHtml($movement['colorStatus']); ?>">
                                                     <?= View::toHtml(Movement::getStatusName($movement['status'])); ?>
                                                 </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-primary p-2" onclick="restoreModal(<?= (int)$movement['movement_history_id'] ?>)">
                                                    <span class="icon text-white"><i class="fas fa-fw fa-trash-restore"></i></span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php View::pagination("/movement/restore_manually_removed_list/?barcode=" . urlencode($barcode) . '&', 1, $total_pages, $current_page, MovementHistories::LIMIT_PER_PAGE);
                } else
                    echo Html::warning("No movements found");
            } ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                        <i class="fas fa-fw fa-chevron-left mr-2"></i>
                        <span class="ml-1">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$modal_restore = new ConfirmModal('restore', 'Restore Movement');
echo $modal_restore->addContent('<span>Would you like to restore movement?</span><div id="response" class="my-2"></div>')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE , ['id' => 'btn-restore'])
    ->put();
?>

<script>

    let movement_history_id = null;
    let merge = 0;

    const responseBlock = $('#response');

    function restoreModal (id) {
        responseBlock.html('');
        movement_history_id = id;
        merge = 0;
        if (movement_history_id > 0)
            $('#restoreModalConfirm').modal('show');
    }

    $('#btn-restore').click(function() {
        responseBlock.html('');
        if (movement_history_id > 0) {
            $.ajax({
                url: '/movement/restore_manually_removed',
                method: "POST",
                data: {
                    movement_history_id,
                    merge
                },
                dataType: "json",
                success: response => {
                    if (response.result === undefined)
                        location.reload();
                    if (response.result === 'success')
                        location.href = <?= View::toJs(HTTP::safeLinkUrl($back)) ?>;
                    if (response.result === 'error') {
                        if (!merge)
                            responseBlock.html(`<div class="alert alert-danger">${response.error}</div>`);
                        else
                            location.href = <?= View::toJs(HTTP::safeLinkUrl($back)) ?>;
                    }
                    if (response.result === 'merge') {
                        merge = 1;
                        responseBlock.html(`<div class="alert alert-warning">Movement exist. Would you like merge them?</div>`);
                    }
                }
            });
        }
    });

</script>