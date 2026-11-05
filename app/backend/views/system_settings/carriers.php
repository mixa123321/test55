<?php
/**
 * @var $records array
 * @var $logistic_companies array
 * @var $logos array
 */

use app\core\entities\LogisticCompany;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\modal\SwitchModal;

$show_lc = function ($lcs) {
    $res = [];
    foreach ($lcs as $lc)
        $res[] = "<span class='m-1 p-1 px-3 bg-" . ($lc[1] ? 'success' : 'danger') . " text-white'>" . View::toHtml($lc[0]) . "</span>";
    return implode($res);
};

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
        <div class="card-body">
            <?php if (!empty($records)) { ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="*">
                        <col width="25%">
                        <col width="15%">
                        <col width="*">
                        <col width="10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Logo</th>
                            <th>Code</th>
                            <th>Logistic Companies</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bodyCarriers">
                        <?php foreach ($records as $record) { ?>
                            <tr>
                                <td><?= View::toHtml($record['name']) ?></td>
                                <td>
                                    <img class="img-profile" id="logo-<?= $record['id']; ?>" style="height: 30px; padding: 0"
                                         src="data:image/png;base64, <?= $logos[$record['logo']]; ?>"/>
                                </td>
                                <td><?= View::toHtml($record['code']) ?></td>
                                <td><?= $show_lc($record['lc']); ?></td>
                                <td>
                                    <button class="btn btn-success btn-icon-split w-100" type="button" onclick="setCarriersForm(<?= $record['id']; ?>)">
                                        <span class="icon text-white w-100 text-left">
                                            <i class="fas fa-fw fa-edit"></i> Edit
                                        </span>
                                    </button>
                                    <a class="btn btn-primary btn-icon-split w-100 mt-1" href="/system_settings/shipping_services?id=<?= $record['id']; ?>">
                                        <span class="icon text-white w-100 text-left">
                                            <i class="fas fa-fw fa-server"></i> Services
                                        </span>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else echo Html::warning("No records found"); ?>
        </div>
    </div>
</div>
<?php
    $modal_carriers = new SwitchModal('editCarriers', '<span id="action_name"></span> carriers', SwitchModal::SIZE_LG);
    $items = [];
    /** @var LogisticCompany $logistic_company */
    foreach ($logistic_companies as $logistic_company)
        $items[] = '<option'. ($logistic_company->isActive() ? '' : ' disabled') . " value='{$logistic_company->getId()}'>{$logistic_company->getName()}</option>";
    echo $modal_carriers
            ->addContent('
                <div id="response"></div>
                <input type="hidden" name="id" id="carrier_id">
                <div class="form-group mt-0">
                    <div class="form-group-element">
                        <input type="text" name="name" id="carrier_name" class="form-control form-control-lg" value="">
                        <label for="carrier_name">Carrier name <span style="color:red">*</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-group-element">
                        <input type="text" name="code" id="carrier_code"
                               class="form-control form-control-lg" value="">
                        <label for="carrier_code">Code <span style="color:red">*</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-group-element">
                        <input type="text" name="url" id="carrier_url"
                               class="form-control form-control-lg" value="">
                        <label for="carrier_url">URL <span style="color:red">*</span></label>
                    </div>
                </div>
                <div class="row p-2 pb-0 mb-0">
                    <div class="col-6 border border-1 p-2 my-0">
                        <div class="form-group mb-0">
                            <div class="form-group-element">
                                <div class="mb-1">Logo <span style="color: red">*</span></div>
                                <div class="form-group m-0 ">
                                    <input type="hidden" id="carrier_logo" name="carrier_logo" value="">
                                    <img class="img-thumbnail" style="max-height: 90px;" title="logo" src="" id="imgLogo"/>
                                </div>
                            </div>
                            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
                            <input name="null_logo" id="null_logo" type="hidden">
                            <input name="logo" id="logo" type="file" class="d-none">
                            <button type="button" id="cancel_img" class="btn btn-sm btn-outline-orange mt-2">Undo Logo</button>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group-element">
                            <p class="mb-2">Logistic companies</p>
                            <select class="form-control custom-select" id="lc_ids" multiple size="' . min(5, count($items)) . '">' .
                                implode($items) . '
                            </select>
                        </div>
                    </div>
                </div>')
            ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'sendCarrierForm', 'enctype' => 'multipart/form-data'])
            ->addButton('Save', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
            ->put();
//?>
<script>
    const carriers = <?= View::toJs(array_values($records)); ?>;
    const logos = <?= View::toJs($logos) ?>;
    let is_logo_changed = false;
    const responseBlock = $('#response');

    document.getElementById("sendCarrierForm").addEventListener('submit', e => {
        e.preventDefault();
        responseBlock.html(null);
        let formData = new FormData();
        formData.append('logo', $('#logo').prop("files")[0]);
        formData.append('id', $('#carrier_id').val());
        formData.append('name', $('#carrier_name').val());
        formData.append('code', $('#carrier_code').val());
        formData.append('url', $('#carrier_url').val());
        formData.append('lcs', $('#lc_ids').val());
        formData.append('change', is_logo_changed ? 1 : 0);
        $.ajax({
            url: "/system_settings/create_carrier",
            type : 'POST',
            data : formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.result !== undefined) {
                    if (res.result === 'error' && res.errors.length > 0) {
                        responseBlock.html(res.errors.reduce((temp, error) => {
                            temp += `<li class="alert-danger p-0 pl-3">${html_escape(error)}</li>`;
                            return temp;
                        }, ''));
                    } else window.location.reload(false);
                }
            }
        });
    });

    function setCarriersForm(carrier_id = null) {
        responseBlock.html(null);
        document.getElementById('action_name').textContent = carrier_id ? 'Edit' : 'Create';
        const carrier = carrier_id ? carriers.find(c => +c.id === +carrier_id)
                                   : {id: '', name: '', code: '', url: '', logo: 'photo.png', lc: null};
        document.getElementById('carrier_id').value = carrier.id;
        document.getElementById('carrier_name').value = carrier.name;
        document.getElementById('carrier_code').value = carrier.code;
        document.getElementById('carrier_url').value = carrier.url;
        document.getElementById('carrier_logo').value = carrier.logo;
        is_logo_changed = false;
        $('#lc_ids option:selected').each(function(){
            this.selected = false;
        });
        if (carrier.lc)
            Object.entries(carrier.lc).forEach(entry => {
                const [key, value] = entry;
                $('#lc_ids option[value=' + key + ']').prop('selected', true);
            });
        currentSrc = document.getElementById('imgLogo').src = "data:image/png;base64, " + logos[carrier.logo];
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
        is_logo_changed = true;
    })
</script>
<?= View::include_js_with_params(BACKEND_JS, "handle_image_form.js", [
    'imgFileInputId' => 'logo',
    'imgId' => 'imgLogo',
    'cancelImgId' => 'cancel_img',
    'setNullId' => 'null_logo',
    'defaultCurrentSrc' => addslashes(COMMON_IMG . DIRECTORY_SEPARATOR . 'photo.png'),
    'currentSrc' => null
]); ?>
