<?php

use app\core\helpers\Html;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;
use app\core\helpers\tooltip\Tooltip;

/**
 * @var array $records
 * @var int $log_comp_id
 * @var array $logos
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Carriers</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-truck"></i>
                            Carriers List (<?= count($records); ?> item<?= count($records) > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split" onclick="setCarriersForm()" type="button">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Create new carrier</span>
                    </button>
                </div>
            </div>
        </div>
        <form id="frm_carriers">
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-12">
                        <?php if (!empty($records)) { ?>
                            <table class="table table-hover mb-0">
                                <colgroup>
                                    <col width="*">
                                    <col width="25%">
                                    <col width="15%">
                                    <col width="8%">
                                    <col width="8%">
                                    <col width="8%">
                                    <col width="8%">
                                    <col width="8%">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Logo</th>
                                        <th>Code</th>
                                        <th>Internal</th>
                                        <th>Special</th>
                                        <th>Max Weight</th>
                                        <th>Active</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyCarriers">
                                    <?php foreach ($records as $record) { ?>
                                        <tr>
                                            <td><?= View::toHtml($record['name']); ?></td>
                                            <td>
                                                <img class="img-profile" id="logo-<?= $record['id']; ?>" style="height: 30px; padding: 0"
                                                     src="data:image/png;base64, <?= $logos[$record['logo']]; ?>"/>
                                            </td>
                                            <td><?= View::toHtml($record['code']); ?></td>
                                            <td><?= $record['is_internal'] ? '<i class="far fa-check-square"></i>' : '' ?></td>
                                            <td><?= $record['is_special'] ? '<i class="far fa-check-square"></i>' : '' ?></td>
                                            <td><?= $record['max_weight'] ? "{$record['max_weight'] } kg" : ''; ?></td>
                                            <td>
                                                <input type="checkbox" name="<?= $record['id']; ?>"
                                                    <?= $record['active'] ? 'checked' : ''; ?> <?= $record['id'] ?? 'onclick="defaultCarrier(event)"'; ?>>
                                            </td>
                                            <td>
                                                <button class="btn btn-success btn-icon-split" type="button" onclick="setCarriersForm(<?= $record['id']; ?>)">
                                                    <span class="icon text-white w-100 text-left">
                                                        <i class="fas fa-fw fa-edit"></i>
                                                    </span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } else { echo Html::warning("No records found"); } ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($records)) { ?>
                <div class="card-footer">
                    <div class="row justify-content-end">
                        <div class="col-auto">
                            <button class="btn btn-success btn-icon-split" type="button" id="btn_save"
                                    onclick="submitCarrierList()" disabled>
                                <span class="text text-white">Update</span>
                                <span class="icon text-white">
                                    <i class="fas fa-fw fa-sync"></i>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </form>
    </div>
</div>
<?php
$modal_carriers = new SwitchModal('editCarriers', '<span id="action_name"></span> carriers', SwitchModal::SIZE_LG);
echo $modal_carriers
    ->addContent('
                <div id="response"></div>
                <input type="hidden" name="id" id="carrier_id">
                <input type="hidden" name="log_company_id" id="log_company_id">
                <div class="form-group mt-0">
                    <div class="form-group-element">
                        <input type="text" name="name" id="carrier_name" class="form-control form-control-lg" value="" placeholder=" ">
                        <label for="carrier_name">Carrier name <span style="color:red">*</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-group-element">
                        <input type="text" name="code" id="carrier_code" class="form-control form-control-lg" value="" placeholder=" ">
                        <label for="carrier_code">Code <span style="color:red">*</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-group-element">
                        <input type="text" name="url" id="carrier_url" class="form-control form-control-lg" value="" placeholder=" ">
                        <label for="carrier_url">URL <span id="required_span" class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="row p-0 mb-0">
                    <div class="col-6 my-0">
                         <div class="row border m-0">
                            <div class="col">
                                <div class="form-group">
                                    <div class="row mt-2">
                                        <div class="col">
                                            <label for="imgPhoto">Select photo <span class="text-danger">*</span></label>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" id="cancel_img" class="btn btn-sm btn-icon-split btn-orange mb-2">
                                                <span class="icon">
                                                    <i class="fas fa-fw fa-undo"></i>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                    '. Html::hiddens([
                                            ['MAX_FILE_SIZE', 10485760],
                                            ['null_logo', '', true],
                                            ['carrier_logo', '', true]
                                        ])
                                    .'
                                    <input name="logo" id="logo" type="file" class="d-none">
                                    <img class="img-thumbnail m-0" title="logo" src="" id="imgLogo"/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 my-0">
                        <div class="row">
                            <div class="col-6 form-check">
                                <input type="checkbox" class="form-check-input" name="internal" id="carrier_internal">
                                <label class="form-check-label" for="carrier_internal">Internal</label>
                            </div>
                            <div class="col-6 form-check" id="special_checkbox">
                                <input type="checkbox" class="form-check-input" name="special" id="carrier_special">
                                <label class="form-check-label" for="carrier_special">Special</label>
                            </div>
                            <div class="col-12 mt-2 pl-0">
                                <div class="form-group-element">
                                    <input type="text" name="max_weight" id="max_weight" class="form-control form-control-lg" value="" placeholder=" ">
                                    <label for="max_weight">Max Weight</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>')
    ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'sendCarriersForm', 'enctype' => 'multipart/form-data'])
    ->addButton('Save', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
    ->put();
?>
<script>
    const carriers = <?= View::toJs(array_values($records)); ?>;
    const logos = <?= View::toJs($logos); ?>;
    let is_logo_changed = false;
    const responseBlock = $('#response');

    document.getElementById("sendCarriersForm").addEventListener('submit', e => {
        e.preventDefault();
        responseBlock.html(null);
        let formData = new FormData();
        formData.append('logo', $('#logo').prop("files")[0]);
        formData.append('id', $('#carrier_id').val());
        formData.append('name', $('#carrier_name').val());
        formData.append('code', $('#carrier_code').val());
        formData.append('url', $('#carrier_url').val());
        formData.append('lcs', $('#log_company_id').val());
        formData.append('is_internal', $('#carrier_internal').is(':checked') ? 1 : 0);
        formData.append('is_special', $('#carrier_special').is(':checked') ? 1 : 0);
        formData.append('max_weight', $('#max_weight').val());
        formData.append('change', is_logo_changed ? 1 : 0);
        $.ajax({
            url: "/carriers/create_carrier",
            type : 'POST',
            data : formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.result !== undefined) {
                    if (res.result === 'error' && res.errors.length > 0) {
                        responseBlock.html(res.errors.reduce((temp, error) => {
                            temp += `<li class="alert-danger p-0 pl-3">${error}</li>`;
                            return temp;
                        }, ''));
                    } else window.location.reload(false);
                }
            }
        });
    });

    function setCarriersForm(carrier_id = null) {
        let photo = document.getElementById('imgLogo');
        let logo_src = '<?= addslashes(COMMON_IMG . DIRECTORY_SEPARATOR); ?>' + 'photo.png';
        responseBlock.html(null);
        document.getElementById('action_name').textContent = carrier_id ? 'Edit' : 'Create';
        const carrier = carrier_id ? carriers.find(c => +c.id === +carrier_id)
            : {id: '', name: '', code: '', url: '', logo: 'photo.png', lc: '', is_internal: '0', is_special: '0', max_weight: ''};
        if (carrier.logo == '' || carrier.logo == null || carrier.id == '') {
            photo.classList.remove('w-100');
        } else {
            logo_src = "data:image/png;base64, " + logos[carrier.logo];
            photo.classList.add('w-100');
        }
        document.getElementById('carrier_id').value = carrier.id;
        document.getElementById('carrier_name').value = carrier.name;
        document.getElementById('carrier_code').value = carrier.code;
        document.getElementById('carrier_url').value = carrier.url;
        document.getElementById('carrier_logo').value = carrier.logo;
        document.getElementById('log_company_id').value = <?= $log_comp_id ?>;
        document.getElementById('carrier_internal').checked = carrier.is_internal !== '0';
        document.getElementById('carrier_special').checked = carrier.is_special !== '0';
        document.getElementById('max_weight').value = carrier.max_weight;
        currentSrc = document.getElementById('imgLogo').src = logo_src;
        changeInternal();
        is_logo_changed = false;
        const fileInput = document.querySelector('input[type="file"]');
        const myFile = new File([''], currentSrc);
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(myFile);
        fileInput.files = dataTransfer.files;
        <?= $modal_carriers->show(); ?>
    }

    $('#cancel_img').click(e => {
        document.getElementById('carrier_logo').value = currentSrc;
        is_logo_changed = false;
    });

    $('#logo').click( e => {
        $('#imgLogo').prop('classList').remove('w-100');
        is_logo_changed = true;
    });

    $('#logo').change( e => {
        $('#imgLogo').prop('classList').add('w-100');
    });

    function defaultCarrier(e){
        e.preventDefault();
        return false;
    }

    let is_changed = false;
    if (document.getElementById('bodyCarriers'))
        document.getElementById('bodyCarriers')
            .addEventListener('change', () => {
                is_changed = true;
                document.getElementById('btn_save').disabled = false
            });

    function submitCarrierList() {
        $.ajax({
            type: "POST",
            url: '/carriers/change_mode',
            data: $('#frm_carriers').serialize(),
            success: function () {
                is_changed = false;
                document.getElementById('btn_save').disabled = true
            }
        });
    }

    function changeInternal() {
        let internal = $('#required_span');
        let special = $('#special_checkbox');
        let checkbox = $('#carrier_internal');
        if (checkbox.is(':checked')) {
            internal.prop('classList').add('d-none');
            special.prop('classList').remove('d-none');
        } else {
            internal.prop('classList').remove('d-none');
            special.prop('classList').add('d-none');
        }
    }

    document.getElementById('carrier_internal').addEventListener('change', changeInternal);
</script>
<?= View::include_js_with_params(BACKEND_JS, "handle_image_form.js", [
    'imgFileInputId' => 'logo',
    'imgId' => 'imgLogo',
    'cancelImgId' => 'cancel_img',
    'setNullId' => 'null_logo',
    'defaultCurrentSrc' => addslashes(COMMON_IMG . DIRECTORY_SEPARATOR . 'photo.png'),
    'currentSrc' => null
]); ?>
<?= (new Tooltip('Undo Photo', 'cancel_img'))->get(); ?>
