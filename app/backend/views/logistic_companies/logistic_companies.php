<?php
/**
 * @var $logistic_companies array
 * @var $langs array
 * @var $carrier_logos array
 * @var $carriers array
 */

use app\backend\models\Carriers;
use app\core\entities\LogisticCompany;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Logistic Companies</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-fw fa-road"></i>
                        Companies list (<?= count($logistic_companies); ?> item<?= count($logistic_companies) > 1 ? 's' : ''; ?>)
                    </h6>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split text-nowrap" type="button" id="btn_new_lc">
                        <span class="icon">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Create new</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($logistic_companies)) { ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="150px">
                        <col width="*">
                        <col width="10%">
                        <col width="25%">
                        <col width="14%">
                        <col width="10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Name</th>
                            <th>Language</th>
                            <th>Shipper</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logistic_companies as $lc) { ?>
                            <tr>
                                <td>
                                    <?php
                                        if ($lc->getLogo())
                                            echo Html::img(COMMON_IMG . DIRECTORY_SEPARATOR . $lc->getLogo(), [
                                                'title' => 'logo',
                                                'style' => [
                                                    'width' => '150px',
                                                    'background-color' => '#c5c5c5',
                                                    'padding' => '5px',
                                                ],
                                            ]);
                                    ?>
                                </td>
                                <td><?= View::toHtml("{$lc->getName()} ({$lc->getId()})") ?></td>
                                <td><?= View::toHtml($langs[$lc->getLangId()] ?? ''); ?></td>
                                <td>
                                    <?php
                                        $carrier = $carriers[$lc->getCarrierId()];
                                        if (!empty($carrier))
                                            echo Html::img("data:image/png;base64, " . $carrier_logos[$lc->getCarrierId()], [
                                                'style' => [
                                                    'height' => '30px',
                                                    'padding' => 0,
                                                ],
                                                'class' => 'img-profile'
                                            ]);
                                        else
                                            echo "<span class='badge badge-danger'>Shipper is not selected</span>";
                                    ?>
                                </td>
                                <td>
                                    <?php $status_label = !$lc->getStatus() ? "danger" : "success"; ?>
                                    <span class="badge badge-<?= $status_label?>">
                                        <?= View::toHtml(LogisticCompany::STATUSES[$lc->getStatus()]); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-success btn-block btn-icon-split text-nowrap mt-0 mb-1" type="button" onclick="initDialogLC(<?= $lc->getId(); ?>)">
                                        <span class="icon">
                                            <i class="fas fa-fw fa-edit"></i>
                                        </span>
                                        <span class="text flex-fill">Edit</span>
                                    </button>
                                    <button class="btn btn-success btn-block btn-icon-split text-nowrap mt-0 mb-1" type="button" onclick="initDialogShipper(<?= $lc->getId(); ?>)">
                                        <span class="icon">
                                            <i class="fas fa-fw fa-truck"></i>
                                        </span>
                                        <span class="text flex-fill">Shipper</span>
                                    </button>
                                    <a class="btn btn-primary btn-block btn-icon-split d-flex justify-content-start text-nowrap mt-0 mb-1" href="/logistic_companies/warehouses?id=<?= $lc->getId(); ?>">
                                        <span class="icon">
                                            <i class="fas fa-fw fa-warehouse"></i>
                                        </span>
                                        <span class="text flex-fill">Warehouses</span>
                                    </a>
                                    <a class="btn btn-primary btn-block btn-icon-split d-flex justify-content-start text-nowrap mt-0 mb-1" href="/logistic_companies/sa?id=<?= $lc->getId(); ?>">
                                        <span class="icon">
                                            <i class="fas fa-fw fa-users"></i>
                                        </span>
                                        <span class="text flex-fill">Super admins</span>
                                    </a>
                                    <button class="btn btn-<?= !$lc->isActive() ? 'success' : 'danger'; ?> btn-block btn-icon-split d-flex justify-content-start text-nowrap mt-0 mb-1" type="button" onclick="initDialogLock(<?= "{$lc->getId()}, {$lc->getStatus()}"; ?>)">
                                        <span class="icon">
                                            <i class="fas fa-fw fa-<?= !$lc->isActive() ? 'un' : ''; ?>lock"></i>
                                        </span>
                                        <span class="text flex-fill"><?= !$lc->isActive() ? 'Unl' : 'L'; ?>ock</span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else
                echo Html::warning('No records found!');
            ?>
        </div>
    </div>
</div>
<?php
    $modal_lc = new SwitchModal('editLC', '<span id="action_name"></span> logistic company', SwitchModal::SIZE_LG);
    echo $modal_lc
        ->addContent('
                <input type="hidden" name="id" id="id">
                <input type="hidden" name="status" id="status">
                <div id="response" class="alert alert-danger d-none"></div>
                <div class="row">
                    <div class="form-group col-9 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="name" id="name" class="form-control form-control-lg" value="">
                            <label for="carrier_name">Name <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-3 mt-0">
                        <div class="form-group-element">' .
                            Html::dropDownList('lang_id', $langs, '', [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'lang_id',
                            ]) . '
                            <label for="lang_id">Languages</label>
                        </div>
                    </div>
                    <div class="form-group col-6">
                        <div class="form-group-element">
                            <input type="text" name="logo" id="logo" class="form-control form-control-lg" value="">
                            <label for="carrier_name">Logo file <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-6">
                        <div class="form-group-element">
                            <input type="text" name="css" id="css" class="form-control form-control-lg" value="">
                            <label for="carrier_name">CSS file <span style="color:red">*</span></label>
                        </div>
                    </div>
                
                    <div class="form-group col-6 mb-0">
                        <div class="form-group-element">
                            <input type="text" name="shipping_class" id="shipping_class" class="form-control form-control-lg" value="">
                            <label for="carrier_name">Shipping class <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-6 mb-0">
                        <div class="form-group-element">
                            <input type="text" name="billing_class" id="billing_class" class="form-control form-control-lg" value="">
                            <label for="carrier_name">Billing class</label>
                        </div>
                    </div>
                </div>
          ')
        ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'sendLCForm'])
        ->addButton('Save', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
        ->put();
    $modal_shipper = new SwitchModal('editShipper', 'Select shipper');
    echo $modal_shipper
        ->addContent('
                <input type="hidden" name="id" id="lc_id">
                <div id="response_shipper" class="alert alert-danger d-none mb-0"></div>
                <div class="form-group col-12 mb-0" id="elem_shippers">
                    <div class="form-group-element">' .
                        Html::dropDownList('carrier_id', [], '', [
                            'class' => 'selectpicker form-control form-control-lg custom-select',
                            'id' => 'carrier_id',
                        ]) . '
                        <label for="carrier_id">Shippers</label>
                    </div>
                </div>
          ')
        ->addForm('/logistic_companies/set_shipper', SwitchModal::METHOD_POST)
        ->addButton('Select', SwitchModal::TYPE_SUBMIT, SwitchModal::BUTTON_DONE, ['id' => 'btn_shipper', 'disabled' => 'disabled'])
        ->put();
    $modal_lock = new ConfirmModal('lockLC', 'Change status');
    echo $modal_lock
        ->addContent('
                Do you want to change status of logistic company?
                <input type="hidden" name="id" id="lock_lc_id">
                <input type="hidden" name="status" id="lock_status">
          ')
        ->addForm('/logistic_companies/lock_LC', SwitchModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
        ->put();
?>
<script>
    let lcs = <?= View::toJs(array_map(function ($item) { return $item->toArray(); }, $logistic_companies)) ?>;

    $('#btn_new_lc').click(() => {
        initDialogLC(null);
    });

    function initDialogLC(lc_id)
    {
        $('#action_name').text(lc_id === null ? 'Create' : 'Edit');
        let lc = lc_id === null
            ? <?= View::toJs((new LogisticCompany())->toArray()) ?>
            : lcs.find(item => item.id == lc_id);
        $('#id').val(lc.id);
        $('#name').val(lc.name);
        $('#lang_id').val(lc.lang_id);
        $('#logo').val(lc.logo);
        $('#css').val(lc.css);
        $('#shipping_class').val(lc.shipping_class);
        $('#billing_class').val(lc.billing_class);
        $('#status').val(lc.status);
        <?= $modal_lc->show(); ?>;
    }

    $('#sendLCForm').submit((e) => {
        e.preventDefault();
        $.ajax({
            url: "/logistic_companies/create_LC",
            type : 'POST',
            data : $('#sendLCForm').serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.error !== undefined)
                    showErrorHtml('response', res.error, 3000);
                else
                    window.location.reload(false);
            },
            error: function (res) {
                showError('response', 'Connection error', 3000);
            }
        });
    });

    function initDialogShipper(lc_id)
    {
        hideError('response_shipper');
        $('#carrier_id option').remove();
        $('#elem_shippers').hide();
        $('#btn_shipper').prop('disabled', true);
        $.ajax({
            url: "/logistic_companies/get_shippers",
            type : 'POST',
            data : {lc_id : lc_id},
            dataType: 'json',
            async: false,
            success: function (res) {
                if (res.error !== undefined)
                    showError('response_shipper', res.error, 0);
                else {
                    $('#elem_shippers').show();
                    $.each(res.success, function(key, value) {
                        $('#carrier_id').append($('<option>').val(key).text(value));
                    });
                    $('#carrier_id').val(lcs.find(item => item.id == lc_id).carrier_id);
                    $('#lc_id').val(lc_id);
                    $('#btn_shipper').prop('disabled', false);
                }
            },
            error: function (res) {
                showError('response_shipper', 'Connection error', 0);
            }
        });
        <?= $modal_shipper->show(); ?>;
    }

    function initDialogLock(lc_id, status)
    {
        $('#lock_lc_id').val(lc_id);
        $('#lock_status').val(status);
        <?= $modal_lock->show(); ?>;
    }
</script>