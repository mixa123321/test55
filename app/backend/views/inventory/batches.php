<?php
/**
 * @var $authUser User
 * @var $back string
 * @var $batches array
 * @var $SKU SKU
 * @var $SKU_barcode string
 */

use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\entities\SKU;
use app\core\helpers\modal\ConfirmModal;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= View::toHtml($SKU->getName()); ?> (<?= View::toHtmlConvertWithPrimaryBarcode($SKU_barcode); ?>)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-clock"></i>
                            Batches
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-lg-11 table-responsive">
                    <table class="table table-hover mb-0" style="color: black;">
                        <colgroup>
                            <col width="*"/>
                            <col width="*"/>
                            <col width="35%"/>
                            <col width="10%"/>
                            <col width="170px"/>
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Number</th>
                                <th>Expired</th>
                                <th>Locations</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batches as $batch_id => $batch) { ?>
                                <tr<?= $batch['danger'] ? ' style="background-color: lightpink; color: black;"' : ''; ?>>
                                    <td><?= View::toHtml($batch['number']); ?></td>
                                    <td><?= date('jS M Y', strtotime($batch['expired'])); ?></td>
                                    <td>
                                        <?php if (!empty($batch['locs'])) { ?>
                                            <table class="table table-borderless mb-0" style="color: black;">
                                                <colgroup>
                                                    <col width="35%" />
                                                    <col width="35%" />
                                                    <col width="30%" />
                                                </colgroup>
                                                <?php foreach ($batch['locs'] as $loc) { ?>
                                                    <tr>
                                                        <td class="py-0"><?= View::toHtml($loc['loc_name']); ?></td>
                                                        <td class="py-0"><?= View::toHtml($loc['name']); ?></td>
                                                        <td class="py-0"><?= View::toHtml($loc['quantity']); ?> item<?= $loc['quantity'] > 1 ? 's' : ''; ?></td>
                                                    </tr>
                                                <?php } ?>
                                            </table>
                                        <?php } else { ?>
                                            Not found
                                        <?php } ?>
                                    </td>
                                    <td><?= View::toHtml($batch['total']); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-block justify-content-start btn-icon-split mt-0 mb-1 js-btn-edit"
                                                data-batch_id='<?= $batch_id; ?>'
                                                data-number='<?= View::toHtml($batch['number']); ?>'
                                                data-expired='<?= date('Y-m-d', strtotime($batch['expired'])); ?>'
                                        >
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-pen"></i>
                                            </span>
                                            <span class="text flex-fill">Edit</span>
                                        </button>
                                        <?php if (empty($batch['locs'])) { ?>
                                            <button class="btn btn-danger btn-block justify-content-start btn-icon-split mt-0 mb-1 js-btn-delete"
                                                    data-batch_id='<?= $batch_id; ?>'>
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-trash"></i>
                                                </span>
                                                <span class="text flex-fill">Delete</span>
                                            </button>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php }?>
                        </tbody>
                    </table>
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
$modal_delete_batch = new ConfirmModal('deleteBatch', 'Delete batch');
echo $modal_delete_batch->addContent('Are you sure?')
    ->addForm('/housekeeping/delete_batch', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" id="batch_id" name="batch_id">' .
        '<input type="hidden" id="SKU_id" name="SKU_id">' .
        '<input type="hidden" name="back" value="'. View::toHtml($back) . '">' .
        $modal_delete_batch->getButtons())
    ->put();
?>
<script>
    let SKU_id = <?= $SKU->getId(); ?>;

    $('.js-btn-delete').click(function () {
        $('#batch_id').val($(this).data('batch_id'));
        $('#SKU_id').val(SKU_id);
        <?= $modal_delete_batch->show(); ?>
        return false;
    });
</script>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'batches.php'; ?>
