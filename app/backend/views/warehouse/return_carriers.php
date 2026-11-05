<?php
/**
 * @var $records ReturnCarrier[]
 * @var $total_records int
 * @var $count_on_page int
 * @var $current_page int
 * @var $used_ids int[]
 */

use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\entities\ReturnCarrier;
use app\core\helpers\modal\ConfirmModal;

?>

<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Return carriers</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-map-marker-alt"></i>
                        Return carriers List (<?= $total_records; ?> item<?= $total_records > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
<!--                <div class="col-auto">-->
<!--                    <a class="btn btn-success btn-icon-split" href="/return_carriers/create?c=--><?//= $current_page ?><!--">-->
<!--                            <span class="icon text-white-50">-->
<!--                                <i class="fas fa-fw fa-plus"></i>-->
<!--                            </span>-->
<!--                        <span class="text">Create new return carrier</span>-->
<!--                    </a>-->
<!--                </div>-->
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <?php if(!empty($records)) { ?>
                        <?php View::pagination("/return_carriers/?", 1, ceil($total_records / $count_on_page), $current_page, $count_on_page); ?>
                        <table class="table table-hover">
                            <colgroup>
                                <col width="10%">
                                <col width="25%">
                                <col width="25%">
                                <col width="24%">
                                <col width="5%">
                                <col width="11%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Service</th>
                                <th>Name</th>
                                <th>Credentials key</th>
                                <th>Countries</th>
                                <th>Mode</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($records as $record) { ?>
                                <tr>
                                    <td><?= View::toHtml($record->getServiceId()) ?></td>
                                    <td><?= View::toHtml($record->getName()) ?></td>
                                    <td><?= View::toHtml($record->getCredentialsKey()) ?></td>
                                    <td><?= View::toHtml(implode(', ', $record->getCountries())) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $record->getIsTest() ? "warning" :  "success"; ?>">
                                            <?= $record->getIsTest() ? 'Test' : 'Live'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span <?= in_array($record->getId(), $used_ids) ? 'data-toggle="tooltip" title="Return carrier is attach to supplier"' : '' ?>>
                                            <a href="" data-toggle="modal" data-id="<?= $record->getId() ?>" data-state="<?= (int)(!$record->getState()) ?>" class="lockReturnCarrier btn btn-<?= $record->getState() ? "danger" : "success" ?> btn-icon-split btn-block d-flex justify-content-start text-nowrap mb-1 <?= in_array($record->getId(), $used_ids) ? 'disabled' : '' ?>">
                                                <span class="icon text-white-50">
                                                    <i class="fas fa-fw fa-<?=$record->getState() ? 'lock' : 'lock-open'?>"></i>
                                                </span>
                                                <span class="text flex-fill"><?= $record->getState() ? "LOCK" : "UNLOCK" ?></span>
                                            </a>
                                        </span>
                                        <a href="/return_carriers/edit?c=<?= $current_page ?>&id=<?= $record->getId() ?>" class="btn btn-primary btn-icon-split btn-block d-flex justify-content-start text-nowrap mb-1">
                                           <span class="icon text-white-50">
                                               <i class="fas fa-fw fa-pen"></i>
                                           </span>
                                           <span class="text flex-fill">EDIT</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    <?php
                        View::pagination("/return_carriers/?", 1, ceil($total_records / $count_on_page), $current_page, $count_on_page);
                    } else echo
                        Html::warning("No records found");
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$modal_lock = new ConfirmModal('lockReturn', 'Return carrier');
echo $modal_lock->addContent("Please confirm this action")
    ->addForm('/return_carriers/del', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" id="lockReturnId" name="id" value="">' .
        '<input type="hidden" id="lockReturnState" name="lock" value="">' .
        '<input type="hidden" name="c" value="' . View::toHtml($current_page) . '">' .
        $modal_lock->getButtons())
    ->put();
?>

<script>
    $('.lockReturnCarrier').click(function () {
        $('#lockReturnId').val(this.dataset.id);
        $('#lockReturnState').val(this.dataset.state);
        <?= $modal_lock->show(); ?>
    });
</script>
