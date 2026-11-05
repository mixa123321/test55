<?php
/**
 * @var $logistic_company LogisticCompany
 * @var $countries array
 * @var $langs array
 * @var $default_lang int
 * @var $currencies array
 * @var $timezones array
 * @var $warehouses array
 */

use app\core\entities\LogisticCompany;
use app\core\entities\Warehouse;
use app\core\helpers\Html;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;
use app\core\statical\Currencies;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Warehouses of <?= View::toHtml($logistic_company->getName()); ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-fw fa-warehouse"></i>
                        Warehouses list (<?= count($warehouses); ?> item<?= count($warehouses) > 1 ? 's' : ''; ?>)
                    </h6>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split text-nowrap" type="button" id="btn_new_warehouse">
                        <span class="icon">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Create new</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($warehouses)) { ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="*">
                        <col width="8%">
                        <col width="10%">
                        <col width="12%">
                        <col width="8%">
                        <col width="15%">
                        <col width="8%">
                        <col width="10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Country</th>
                            <th>Language</th>
                            <th>Start warehouse</th>
                            <th>Currency</th>
                            <th>Timezone</th>
                            <th>Billing</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warehouses as $warehouse) { ?>
                            <tr>
                                <td><?= View::toHtml("{$warehouse['name']} ({$warehouse['id']})"); ?></td>
                                <td><?= View::toHtml($warehouse['country_id']); ?></td>
                                <td><?= View::toHtml($langs[$warehouse['lang_id']] ?? ''); ?></td>
                                <td><?= View::toHtml(date('jS M Y', strtotime($warehouse['start_warehouse']))); ?></td>
                                <td><?= Currencies::getCurrency($warehouse['currency_id']); ?></td>
                                <td><?= View::toHtml($warehouse['timezone']); ?></td>
                                <td><?= View::toHtml($warehouse['billing']); ?></td>
                                <td>
                                    <button class="btn btn-success btn-block btn-icon-split text-nowrap mt-0 mb-1" type="button" onclick="initDialogWarehouse(<?= $warehouse['id']; ?>);">
                                        <span class="icon">
                                            <i class="fas fa-fw fa-edit"></i>
                                        </span>
                                        <span class="text flex-fill">Edit</span>
                                    </button>
                                    <button class="btn btn-primary btn-block btn-icon-split text-nowrap mt-0 mb-1" type="button" onclick="initDialogLoad(<?= $warehouse['id']; ?>);">
                                        <span class="icon">
                                            <i class="fas fa-fw fa-map-marker-alt"></i>
                                        </span>
                                        <span class="text flex-fill">Locations</span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/logistic_companies">
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
    $options = [
        'class' => 'form-control form-control-lg custom-select'
    ];
    $modal_warehouse = new SwitchModal('editWarehouse', '<span id="action_name"></span> warehouse', SwitchModal::SIZE_LG);
    echo $modal_warehouse
        ->addContent('
                <input type="hidden" name="id" id="id">
                <input type="hidden" name="lc_id" value="' . $logistic_company->getId() . '">
                <div id="response" class="alert alert-danger d-none"></div>
                <div class="row">
                    <div class="form-group col-8 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="name" id="name" class="form-control form-control-lg" value="">
                            <label for="name">Name <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-4 mt-0">
                        <div class="form-group-element">
                            <input type="date" name="start_warehouse" id="start_warehouse" class="form-control form-control-lg" value="">
                            <label for="start_warehouse">Start <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <p class="form-control-static col-12">Address</p>
                    <div class="form-group col-8 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="address[company]" id="company" class="form-control form-control-lg" value="">
                            <label for="company">Company <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-4 mt-0">
                        <div class="form-group-element">
                            ' . Html::dropDownList('address[country_id]', $countries, null, $options + ['id' => 'country_id']) . '
                            <label for="country_id">Country <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-12 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="address[address1]" id="address1" class="form-control form-control-lg" value="">
                            <label for="address1">Line 1 <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-12 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="address[address2]" id="address2" class="form-control form-control-lg" value="">
                            <label for="address2">Line 2</label>
                        </div>
                    </div>
                    <div class="form-group col-5 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="address[town]" id="town" class="form-control form-control-lg" value="">
                            <label for="town">Town <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-4 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="address[county]" id="county" class="form-control form-control-lg" value="">
                            <label for="county">County</span></label>
                        </div>
                    </div>
                    <div class="form-group col-3 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="address[postcode]" id="postcode" class="form-control form-control-lg" value="">
                            <label for="postcode">Postcode <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <p class="form-control-static col-12">Settings</p>
                    <div class="form-group col-3 mt-0">
                        <div class="form-group-element">
                            ' . Html::dropDownList('currency_id', $currencies, null, $options + ['id' => 'currency_id']) . '
                            <label for="currency_id">Currency <span style="color:red">*</span></label>
                        </div>
                    </div>
                    <div class="form-group col-3 mt-0">
                        <div class="form-group-element">
                            ' . Html::dropDownList('lang_id', $langs, null, $options + ['id' => 'lang_id']) . '
                            <label for="lang_id">Language</label>
                        </div>
                    </div>
                    <div class="form-group col-4 mt-0">
                        <div class="form-group-element">
                            ' . Html::dropDownList("timezone", $timezones,null, $options + ['id' => 'timezone']) . '
                            <label for="timezone">Timezone</label>
                        </div>
                    </div>
                    <div class="form-group col-2 mt-0">
                        <div class="form-group-element">
                            <input type="text" name="billing" id="billing" class="form-control form-control-lg" value="">
                            <label for="billing">Billing <span style="color:red">*</span></label>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-between">
                    <div class="form-group col-2 my-0">
                        <div class="form-group-element">
                            <input type="text" name="label" id="label" class="form-control form-control-lg" value="">
                            <label for="label">Label</label>
                        </div>
                    </div>
                    <div class="form-group col"></div>
                    <div class="form-group col-auto my-0">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_virtual" name="is_virtual">
                            <label class="form-check-label" for="is_virtual">Virtual</label>
                        </div>
                    </div>
                    <div class="form-group col-auto my-0">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="mezzanine" name="mezzanine">
                            <label class="form-check-label" for="mezzanine">Mezzanine</label>
                        </div>
                    </div>
                    <div class="form-group col-auto my-0">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="collection_by_customer" name="collection_by_customer">
                            <label class="form-check-label" for="collection_by_customer">Collection by customer</label>
                        </div>
                    </div>
                </div>
            ')
        ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'sendWarehouseForm'])
        ->addButton('Save', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
        ->put();
    $modal_load = new SwitchModal('loadLocations', 'Upload locations');
    echo $modal_load
        ->addContent('
                <div id="response_load" class="alert alert-danger d-none mb-0"></div>
                <div class="row justify-content-center">
                    <div class="col-12">
                        <p class="form-control-static mb-1"><b>Select CSV to upload</b></p>
                        <input type="hidden" name="MAX_FILE_SIZE" value="2000000" />
                        <input type="hidden" name="warehouse_id" id="warehouse_id" />
                        <div class="form-group mb-0">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="upload_file" id="upload_file">
                                <label class="custom-file-label" for="upload_file" id="name_file">Choose file</label>
                            </div>
                        </div>
                    </div>
                </div>
            ')
        ->addForm('/logistic_companies/upload_locations', SwitchModal::METHOD_POST, ['enctype' => 'multipart/form-data', 'id' => 'frm_upload'])
        ->addButton('Upload', SwitchModal::TYPE_SUBMIT, SwitchModal::BUTTON_DONE, ['id' => 'btn_upload', 'disabled' => 'disabled'])
        ->put();
?>
<script>
    let ws = <?= View::toJs(array_map(function($warehouse) {
            return array_map(function($item) {
                $tmp = json_decode($item, true);
                return is_array($tmp) ? $tmp : $item;
            }, $warehouse);
    }, $warehouses)) ?>;

    $('#btn_new_warehouse').click(() => {
        initDialogWarehouse(null);
    });

    function initDialogWarehouse(w_id)
    {
        $('#action_name').text(w_id === null ? 'Create' : 'Edit');
        let warehouse = w_id === null
            ? <?= View::toJs((new Warehouse())->toArray()) ?>
            : ws.find(item => item.id == w_id);
        $('#id').val(warehouse.id);
        $('#name').val(warehouse.name);
        $('#start_warehouse').val(warehouse.start_warehouse);
        $('#company').val(warehouse.address !== null ? warehouse.address.company : '');
        $('#country_id').val(warehouse.address !== null ? warehouse.address.country_id : 'GB');
        $('#address1').val(warehouse.address !== null ? warehouse.address.address1 : '');
        $('#address2').val(warehouse.address !== null ? warehouse.address.address2 : '');
        $('#town').val(warehouse.address !== null ? warehouse.address.town : '');
        $('#county').val(warehouse.address !== null ? warehouse.address.county : '');
        $('#postcode').val(warehouse.address !== null ? warehouse.address.postcode : '');
        $('#currency_id').val(warehouse.currency_id !== null ? warehouse.currency_id : 50);
        $('#lang_id').val(warehouse.address !== null ? warehouse.lang_id : <?= $default_lang; ?>);
        $('#billing').val(warehouse.billing);
        $('#label').val(warehouse.label);
        setTimezones($('#country_id').val());
        $('#timezone').val(warehouse.timezone);
        $('#is_virtual').prop('checked', warehouse.is_virtual == 1);
        $('#mezzanine').prop('checked', warehouse.mezzanine == 1);
        $('#collection_by_customer').prop('checked', warehouse.collection_by_customer == 1);
         <?= $modal_warehouse->show(); ?>;
    }

    function setTimezones(country_id)
    {
        $('#timezone option').remove();
        let timezones = getTimezones(country_id);
        $.each(timezones, function (key, item) {
            $('#timezone').append($('<option>', { value : key }).text(key));
        });
    }

    $('#country_id').change(() => {
        setTimezones($('#country_id').val());
    });

    function getTimezones(country_id)
    {
        let timezones = [];
        $.ajax({
            url: "/logistic_companies/get_timezones",
            type : 'POST',
            data : {country_id : country_id},
            dataType: 'json',
            async: false,
            success: function (res) {
                timezones = res.success;
            },
            error: function (res) {
                timezones = [{'UTC': 'UTC'}];
            }
        });
        return timezones;
    }

    $('#sendWarehouseForm').submit((e) => {
        e.preventDefault();
        $.ajax({
            url: "/logistic_companies/create_warehouse",
            type : 'POST',
            data : $('#sendWarehouseForm').serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.error !== undefined)
                    showError('response', res.error, 3000);
                else
                    window.location.reload(false);
            },
            error: function (res) {
                showError('response', 'Connection error', 3000);
            }
        });
    });

    $('#upload_file').change(({target}) => {
        let selectedFile = null;
        if (target.files.length > 0)
            selectedFile = target.files[0];
        const getFileName = name => {
            let max = 50;
            if (name.length < max)
                return name;
            let split = name.split('.');
            if (split.length === 2)
                return split[0].substr(0, max - 4) + '...' + split[1];
            return name.substr(0, max);
        };
        $('#name_file').text(selectedFile === null ? 'Choose file' : getFileName(selectedFile.name));
        $('#btn_upload').prop('disabled', selectedFile === null);
    });

    function initDialogLoad(w_id)
    {
        $('#name_file').text('Choose file');
        $('#btn_upload').prop('disabled', true);
        $('#warehouse_id').val(w_id);
        <?= $modal_load->show(); ?>;
    }

    $('#frm_upload').submit((e) => {
        e.preventDefault();
        let formData = new FormData();
        formData.append('csv', $('#upload_file').prop("files")[0]);
        formData.append('warehouse_id', $('#warehouse_id').val());
        $.ajax({
            url: "/logistic_companies/upload_locations",
            type : 'POST',
            data : formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                if (res.error !== undefined)
                    showError('response_load', res.error, 3000);
                else
                    window.location.reload(false);
            },
            error: function (res) {
                showError('response_load', 'Connection error', 3000);
            }
        });
    });
</script>