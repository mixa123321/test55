<?php
/**
 * @var $authUser User
 * @var $services array
 */

use app\core\entities\User;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Collection Time Management</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <div class="col">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-server"></i> Service List</h6>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($services)) { ?>
                <div class="row justify-content-center my-0 p-0">
                    <div class="col-md-10">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col width="*"/>
                                <col width="10%"/>
                                <col width="15%"/>
                                <col width="20%"/>
                                <col width="12%"/>
                                <col width="7%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Code</th>
                                    <th>Collection Time</th>
                                    <th>Override Collection Time Priority</th>
                                    <th>Priority Level</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service) { ?>
                                    <tr>
                                        <td><?= View::toHtml($service['name']) ?></td>
                                        <td><?= View::toHtml($service['code']) ?></td>
                                        <td><?= View::toHtml($service['collection']); ?></td>
                                        <td><?= $service['is_priority'] ? '<i class="far fa-check-square"></i>' : '' ?></td>
                                        <td><?= !empty($service['priority_level']) ? $service['priority_level'] : '' ?></td>
                                        <?php if ($authUser->isRoleSA()) { ?>
                                            <td>
                                                <button class="btn btn-success btn-icon-split" type="button" onclick="setCollectionTimeForm(<?= $service['shipping_service_id'] ?: 0; ?>)">
                                                    <span class="icon text-white">
                                                        <i class="fas fa-fw fa-edit"></i>
                                                    </span>
                                                </button>
                                            </td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } else { ?>
                <div class="alert alert-warning mt-2 mb-2">
                    No records found
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php
    $modal_collection_times = new SwitchModal('editCollectionTime', '<span id="title"></span>');
    echo $modal_collection_times->addContent('
        <div class="alert alert-danger p-2 d-none" id="response"></div>
        <div class="row">
            <div class="col-5">
                <div class="form-group mb-0">
                    <div class="form-group-element">
                        <input type="time" name="collection" id="collection" class="form-control form-control-lg" value="">
                        <label for="collection">Collection Time</label>
                    </div>
                </div>
            </div>
            <div class="col-3">
                <div class="form-check" id="priority_block_1">
                    <input type="checkbox" class="form-check-input" name="is_priority" id="is_priority">
                    <label class="form-check-label" for="is_priority">Priority</label>
                </div>
            </div>
            <div class="col-4">
                <div class="form-group mb-0" id="priority_block_2">
                    <div class="form-group-element">
                        <input type="number" name="priority_level" id="priority_level" class="form-control form-control-lg"
                               value="" min="0" step="any">
                        <label for="priority_level">Level</label>
                    </div>
                </div>
            </div>
            <input type="hidden" name="service_id" id="service_id" />
        </div>')
        ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'sendCollectionTimeForm'])
        ->addButton('Save', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
        ->put();
//?>
<script>
    const services = <?= View::toJs($services) ?>;

    document.getElementById('sendCollectionTimeForm').addEventListener('submit', e => {
        e.preventDefault();
        $.ajax({
            type: "POST",
            url: '/collection_times/update_collection_time',
            data: $(e.target).serialize(),
            dataType: "json",
            success: function (res) {
                if (res.error !== undefined)
                    showErrorHtml('response', res.error, 3000);
                else
                    location = '/collection_times';
            },
            error: function (res) {
                showError('response', 'Connection is not found!', 3000);
            }
        });
    });

    function setCollectionTimeForm(service_id = null)
    {
        const service = services.find(s => +s.shipping_service_id === +service_id);
        $('#service_id').val(service_id);
        $('#title').text(service.name + ' (' + service.code + ')');
        $('#collection').val(service.collection);
        $('#is_priority').prop('checked', service.is_priority !== '0');
        $('#priority_level').val(service.priority_level);
        showPriority();
        <?= $modal_collection_times->show(); ?>
    }

    $('#collection').change(showPriority);
    $('#is_priority').change(showLevel);

    function showLevel()
    {
        if ($('#is_priority').is(':checked'))
            $('#priority_block_2').show();
        else
            $('#priority_block_2').hide();
    }

    function showPriority()
    {
        if ($('#collection').val() === '') {
            $('#priority_block_1').hide();
            $('#priority_block_2').hide();
        } else {
            $('#priority_block_1').show();
            showLevel();
        }
    }
</script>