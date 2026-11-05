<?php
/**
 * @var User $authUser
 * @var Printer[] $printers
 */

use app\core\entities\Printer;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\modal\ConfirmModal;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Printers</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-print"></i>
                        Printers list (<?= count($printers); ?> item<?= count($printers) > 1 ? 's' : ''; ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-lg-10 col">
                    <div class="row mb-2">
                        <div class="col">
                            <h4>Retain Me printers</h4>
                        </div>
                        <?php if (!$authUser->isRoleWorker()) { ?>
                            <div class="col-auto">
                                <button class="btn btn-success btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 js-btn-add"
                                        type="button">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-plus"></i>
                                    </span>
                                    <span class="text flex-fill">Add printer</span>
                                </button>
                            </div>
                        <?php } ?>
                    </div>
                    <?php if (count($printers) > 0) { ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <colgroup>
                                        <col width="5%">
                                        <col width="*">
                                        <col width="10%">
                                        <?php if (!$authUser->isRoleWorker())
                                            echo '<col width="12%">';
                                        ?>
                                    </colgroup>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Source ID</th>
                                        <?php if (!$authUser->isRoleWorker())
                                            echo '<th>Action</th>';
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($printers as $printer_id => $printer) { ?>
                                        <tr<?= $printer->getState() == Printer::PRINTER_DEFAULT ? ' class="alert-success"' : ''; ?>>
                                            <td><?= View::toHtml($printer_id); ?></td>
                                            <td><?= View::toHtml($printer->getName()); ?></td>
                                            <td><?= View::toHtml($printer->getSourceId()); ?></td>
                                            <?php if (!$authUser->isRoleWorker()) { ?>
                                                <td>
                                                    <?php if ($printer->getState() != Printer::PRINTER_BLOCKED) { ?>
                                                        <button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 js-btn-edit"
                                                                id='lock_<?= $printer_id; ?>'
                                                                type="button">
                                                            <span class="icon text-white-50">
                                                                <i class="fas fa-fw fa-pen"></i>
                                                            </span>
                                                            <span class="text flex-fill">Edit</span>
                                                        </button>
                                                    <?php } ?>
                                                    <?php if ($printer->getState() == Printer::PRINTER_ACTIVE) { ?>
                                                        <button class="btn btn-success btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1 js-btn-default"
                                                                id='lock_<?= $printer_id; ?>'
                                                                type="button">
                                                            <span class="icon text-white-50">
                                                                <i class="fas fa-fw fa-check"></i>
                                                            </span>
                                                            <span class="text flex-fill">Default</span>
                                                        </button>
                                                    <?php } ?>
                                                    <button class="btn btn-<?= !$printer->getState() ? 'success' : 'danger'; ?> btn-block btn-icon-split justify-content-start text-nowrap mt-0 js-btn-lock"
                                                            id='lock_<?= $printer_id . '_' . (int)$printer->getState(); ?>'
                                                            type="button">
                                                        <span class="icon text-white-50">
                                                            <i class="fas fa-fw fa-lock<?= !$printer->getState() ? '-open' : '' ?>"></i>
                                                        </span>
                                                        <span class="text text-white flex-fill"><?= !$printer->getState() ? 'Unl' : 'L'; ?>ock</span>
                                                    </button>
                                                </td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } else
                        echo Html::warning('Records is not found');
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$modal_lock_printer = new ConfirmModal('lockPrinter', 'Change status printer');
echo $modal_lock_printer->addBodyAttributes(['id' => 'ansLock'])
    ->addForm('/printers/lock_printer', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" id="printer_id" name="printer_id"/><input type="hidden" id="printer_lock" name="printer_lock"/>' . $modal_lock_printer->getButtons())
    ->put();
$modal_default_printer = new ConfirmModal('defaultPrinter', 'Change status printer');
echo $modal_default_printer->addContent('Would you like to set printer as default?')
    ->addForm('/printers/default_printer', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" id="printer_def" name="printer_id"/>' . $modal_default_printer->getButtons())
    ->put();
$content_edit_printer = '<input type="hidden" id="printer_edit" name="printer[id]"/>
                         <input type="hidden" id="printer_type" name="printer[type]"/>
                         <input type="hidden" id="printer_state" name="printer[state]"/>
                         <div class="modal-body m-0 p-0">
                             <div class="row">
                                 <div class="col-12">
                                     <div class="form-group">
                                         <div class="form-group-element">
                                             <input id="printer_name" name="printer[name]" type="text" class="form-control form-control-lg" placeholder=" ">
                                             <label for="printer_name">Name <span class="text-danger">*</span></label>
                                         </div>
                                     </div>
                                 </div>
                                 <div class="col-12">
                                     <div class="form-group mb-0">
                                         <div class="form-group-element">
                                             <input id="printer_source" name="printer[source_id]" type="number" class="form-control form-control-lg" placeholder=" ">
                                             <label for="printer_source">Source ID <span class="text-danger">*</span></label>
                                         </div>
                                     </div>
                                 </div>
                             </div>
                             <div class="col-12 bg-danger text-white mb-0 mt-3 p-2 d-none" id="err_msg">Data cannot be empty</div>
                         </div>';
$modal_edit_printer = new ConfirmModal('editPrinter', 'Printer');
echo $modal_edit_printer->addContent($content_edit_printer)
    ->addForm('/printers/edit_printer', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->put();
?>
<script>
    var printers = <?= View::toJs(array_map(function ($el) { return $el->toArray(); }, $printers)) ?>;

    $('.js-btn-lock').click(function (e) {
        var printer = e.currentTarget.id.split('_');
        $('#printer_id').val(printer[1]);
        $('#printer_lock').val(printer[2]);
        $('#ansLock').html('Would you like to ' + (printer[2] == 0 ? 'un' : '') + 'lock printer?');
        <?= $modal_lock_printer->show(); ?>
    });

    $('.js-btn-default').click(function (e) {
        var printer = e.currentTarget.id.split('_');
        $('#printer_def').val(printer[1]);
        <?= $modal_default_printer->show(); ?>
    });

    $('.js-btn-add').click(function () {
        $('#printer_edit').val();
        $('#printer_type').val('retain_me');
        $('#printer_name').val('');
        $('#printer_source').val('');
        $('#printer_state').val(1);
        <?= $modal_edit_printer->show(); ?>
    });

    $('.js-btn-edit').click(function (e) {
        var printer = e.currentTarget.id.split('_');
        $('#printer_edit').val(printer[1]);
        $('#printer_type').val('retain_me');
        $('#printer_name').val(printers[printer[1]].name);
        $('#printer_source').val(printers[printer[1]].source_id);
        $('#printer_state').val(printers[printer[1]].state);
        <?= $modal_edit_printer->show(); ?>
    });

    $('#editConf').click(function () {
        if ($('#printer_name').val().trim() == '') {
            $('#err_msg').prop('classList').remove('d-none');
            setTimeout(function() {
                $('#err_msg').prop('classList').add('d-none');
            }, 3000);
            return false;
        }
    });
</script>