<?php

use app\core\entities\ShippingService;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\tooltip\Tooltip;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var array $shipping_services
 * @var array $carriers
 * @var array $shipping_services_lst
 * @var int $default_shipping_service_id
 * @var array $logos
 * @var array $carrier_logos
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Shipping Services Map</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <div class="col px-0">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-server"></i> Service List</h6>
            </div>
            <div class="col-auto px-0">
                <form method="post" action="/shipping_services/set_default">
                    <div class="form-group mb-0">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'id' => 'default_shipping_service_id',
                                    'class' => 'form-control form-control-lg custom-select',
                                    'onchange' => 'submit();',
                                    'style' => 'min-width: 250px',
                                ];
                                echo Html::dropDownList('default_shipping_service_id', $shipping_services_lst,$default_shipping_service_id, $options);
                            ?>
                            <label for="default_shipping_service_id">Default Shipping Service</label>
                        </div>
                    </div>
                </form>
            </div>
            <?php if ($authUser->isRoleSA()) { ?>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split" onclick="setShippingServiceForm()" type="button">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Create new service</span>
                    </button>
                </div>
            <?php } ?>
        </div>
        <form id="shippingServiceForm">
            <div class="card-body">
                <?php if (count($shipping_services)) { ?>
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Service</th>
                            <th>Code</th>
                            <th>Carrier</th>
                            <th>Logo</th>
                            <th>Next day</th>
                            <th>Untracked</th>
                            <th>Special</th>
                            <th>Max weight / Carrier</th>
                            <th>Active</th>
                            <?php if ($authUser->isRoleSA()) { ?>
                                <th>Action</th>
                            <?php } ?>
                        </tr>
                        </thead>
                        <tbody id="bodyShippingServices">
                            <?php foreach ($shipping_services as $shipping_service) { ?>
                                <tr id="shipping_service_<?= $shipping_service['id']; ?>"<?= ($shipping_service['id'] == $default_shipping_service_id) ? ' class="alert-success"' : ''; ?>>
                                    <td><?= View::toHtml($shipping_service['name']) ?></td>
                                    <td><?= View::toHtml($shipping_service['code']) ?></td>
                                    <td>
                                        <?php if (!empty($carrier_logos[$shipping_service['carrier_logo']])) { ?>
                                            <img class="img-profile" style="height: 50px; padding: 0"
                                                 title="<?= View::toHtml($shipping_service['ccode']); ?>"
                                                 src="data:image/png;base64, <?= $carrier_logos[$shipping_service['carrier_logo']] ?>"/>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($logos[$shipping_service['logo']])) { ?>
                                            <img class="img-profile" style="height: 50px; padding: 0"
                                                 title="<?= View::toHtml($shipping_service['logo']); ?>"
                                                 src="data:image/png;base64, <?= $logos[$shipping_service['logo']] ?>"/>
                                        <?php } ?>
                                    </td>
                                    <td class="text-center"><?= $shipping_service['next_day'] ? '<i class="far fa-check-square"></i>' : '' ?></td>
                                    <td class="text-center"><?= $shipping_service['is_untracked'] ? '<i class="far fa-check-square"></i>' : '' ?></td>
                                    <td><?= $shipping_service['special'] ? ShippingService::SPECIAL_NAMES[$shipping_service['special']] : ''; ?></td>
                                    <td class="text-center">
                                        <?php
                                            $max_weight = $shipping_service['max_weight'] ?: '-';
                                            if ($shipping_service['carrier_max_weight'] && $shipping_service['max_weight'] > $shipping_service['carrier_max_weight'])
                                                $max_weight = "<span class=\"bg-danger text-white px-2\">$max_weight</span>";
                                        ?>
                                        <?= "$max_weight / " . ($shipping_service['carrier_max_weight'] ?: '-'); ?>
                                    </td>
                                    <td class="text-center">
                                        <input type="checkbox"
                                               name="shipping_service[<?= $shipping_service['id'] ?>]"
                                               <?= $shipping_service['warehouse_id'] ? 'checked' : '' ?> <?= ($shipping_service['id'] == $default_shipping_service_id) ? 'onclick="defaultShippingService(event)"' : ''; ?>>
                                    </td>
                                    <?php if ($authUser->isRoleSA()) { ?>
                                        <td>
                                            <button class="btn btn-success btn-icon-split" type="button" onclick="setShippingServiceForm(<?= $shipping_service['id'] ?>)">
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
                <?php } else
                    echo Html::warning('No records found');
                ?>
            </div>
            <?php if (count($shipping_services)) { ?>
                <div class="card-footer">
                    <div class="row justify-content-end">
                        <div class="col-auto">
                            <button class="btn btn-success btn-icon-split" id="storeBtn" type="button" onclick="submitShippingServiceList()" disabled>
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
    $modal_shipping_services = new SwitchModal('editShippingServices', '<span id="action_name"></span> shipping service', SwitchModal::SIZE_LG);
    echo $modal_shipping_services
        ->addContent('
            <div id="response"></div>
            <input type="hidden" name="id" id="shipping_service_id">
            <div class="form-group">
                <div class="form-group-element">
                    <input type="text" name="name" id="shipping_service_name" class="form-control form-control-lg">
                    <label for="shipping_service_name">Shipping Service <span style="color:red">*</span></label>
                </div>
            </div>
            <div class="form-group">
                <div class="form-group-element">
                    <input type="text" name="code" id="shipping_service_code"
                           class="form-control form-control-lg" value="">
                    <label for="shipping_service_code">Code <span style="color:red">*</span></label>
                </div>
            </div>
            <div class="form-group">
                <div class="form-group-element">
                    <input type="number" name="max_weight" id="shipping_service_max_weight"
                           class="form-control form-control-lg" value="" min="0" step="any">
                    <label for="shipping_service_max_weight">Max weight <span class="text-danger" id="carrier_max_weight"></span></label>
                </div>
            </div> 
            <div class="form-group">
                <div class="form-group-element">' .
                    Html::dropDownList('carrier_id', Arr::columnAssoc($carriers, 'title'), null, ['id' => 'carrier_id', 'class' => 'form-control form-control-lg custom-select']) . '
                    <label for="carrier_id">Carrier</label>
                </div>
            </div>
            <div class="row p-0 mb-0">
                <div class="col-8 my-0">
                     <div class="row border m-0">
                        <div class="col">
                            <div class="form-group">
                                <div class="row mt-2">
                                    <div class="col">
                                        <label for="imgPhoto">Select photo</label>
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
                                    ['shipping_service_logo', '', true]
                                ])
                                .'
                                <input name="logo" id="logo" type="file" class="d-none">
                                <img class="img-thumbnail m-0" title="logo" src="" id="imgLogo"/>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="row">
                        <div class="col">                        
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="next_day" id="shipping_service_next_day">
                                <label class="form-check-label" for="shipping_service_next_day">Next day</label>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">                        
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_untracked" id="shipping_service_is_untracked">
                                <label class="form-check-label" for="shipping_service_is_untracked">Is untracked</label>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="special_block">
                        <div class="col">                        
                            <div class="form-group mt-2">
                                <div class="form-group-element">' .
                                    Html::dropDownList('special', ShippingService::SPECIAL_NAMES, null, ['id' => 'shipping_service_special', 'class' => 'form-control form-control-lg custom-select']) . '
                                    <label for="shipping_service_special">Special</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>')
        ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'sendShippingServiceForm', 'enctype' => 'multipart/form-data'])
        ->addButton('Save', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
        ->put();
?>
<script>
    const shipping_services = <?= View::toJs($shipping_services); ?>;
    const carriers = <?= View::toJs($carriers); ?>;
    const logos = <?= View::toJs($logos); ?>;

    function defaultShippingService(e){
        e.preventDefault();
        return false;
    }

    let is_changed = false;
    let is_logo_changed = false;
    if (document.getElementById('bodyShippingServices'))
        document.getElementById('bodyShippingServices')
            .addEventListener('change', () => {
                is_changed = true;
                document.getElementById('storeBtn').disabled = false
            });

    const responseBlock = $('#response');

    document.getElementById('sendShippingServiceForm').addEventListener('submit', e => {
        e.preventDefault();
        responseBlock.html(null);
        let formData = new FormData();
        formData.append('logo', $('#logo').prop("files")[0]);
        formData.append('id', $('#shipping_service_id').val());
        formData.append('name', $('#shipping_service_name').val());
        formData.append('code', $('#shipping_service_code').val());
        formData.append('max_weight', $('#shipping_service_max_weight').val());
        formData.append('carrier_id', $('#carrier_id').val());
        formData.append('next_day', $('#shipping_service_next_day').prop('checked') ? 1 : 0);
        formData.append('is_untracked', $('#shipping_service_is_untracked').prop('checked') ? 1 : 0);
        formData.append('special', $('#shipping_service_special').val());
        formData.append('change', is_logo_changed ? 1 : 0);
        $.ajax({
            type: "POST",
            url: '/shipping_services/adding_shipping_service',
            processData: false,
            contentType: false,
            data: formData,
            dataType: "json",
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

    function submitShippingServiceList() {
        $.ajax({
            type: "POST",
            url: '/shipping_services/change_services',
            data: $('#shippingServiceForm').serialize(),
            success: function () {
                is_changed = false;
                document.getElementById('storeBtn').disabled = true
            }
        });
    }

    function setShippingServiceForm(service_id) {
        let logo_src = '<?= addslashes(COMMON_IMG . DIRECTORY_SEPARATOR); ?>' + 'photo.png';
        let photo = document.getElementById('imgLogo');
        if (is_changed)
            submitShippingServiceList();
        responseBlock.html(null);
        document.getElementById('action_name').textContent = service_id ? 'Edit' : 'Create';
        const service = service_id
            ? shipping_services.find(s => +s.id === +service_id)
            : {id: '', name: '', code: '', is_untracked: '0',  logo: 'photo.png', next_day: '0', max_weight: 0, carrier_id: <?= array_keys($carriers)[0]; ?>, carrier_max_weight: 0, special: 0};
        if (service.logo === '' || service.logo == null || service.id === '')
            photo.classList.remove('w-100');
        else {
            logo_src = "data:image/png;base64, " + logos[service.logo];
            photo.classList.add('w-100');
        }
        document.getElementById('shipping_service_id').value = service.id;
        document.getElementById('shipping_service_name').value = service.name;
        document.getElementById('shipping_service_code').value = service.code;
        document.getElementById('carrier_id').value = service.carrier_id;
        document.getElementById('shipping_service_logo').value = service.logo;
        document.getElementById('shipping_service_max_weight').value = parseFloat(service.max_weight) > 0 ? parseFloat(service.max_weight) : '';
        document.getElementById('shipping_service_next_day').checked = service.next_day !== '0';
        document.getElementById('shipping_service_is_untracked').checked = service.is_untracked !== '0';
        showCarrierMaxWeight(service.id);
        is_logo_changed = false;
        currentSrc = photo.src = logo_src;
        <?= $modal_shipping_services->show(); ?>
    }

    $('#cancel_img').click(e => {
        document.getElementById('shipping_service_logo').value = currentSrc;
        is_logo_changed = false;
    });

    $('#logo').click( e => {
        $('#imgLogo').prop('classList').remove('w-100');
        is_logo_changed = true;
    });

    $('#logo').change( e => {
        $('#imgLogo').prop('classList').add('w-100');
    });

    function showCarrierMaxWeight(service_id)
    {
        let carrier = carriers[$('#carrier_id').val()];
        let max_weight = carrier.max_weight;
        $('#carrier_max_weight').html(max_weight ? (' (Carrier ' + max_weight + ' kg)') : '');
        if (carrier.is_special == 1) {
            $('#special_block').show();
            let special = service_id ? (shipping_services.find(s => +s.id === +service_id)).special : 0;
            $('#shipping_service_special').val(special ?? 0);
        } else
            $('#special_block').hide();
    }

    $('#carrier_id').change(() => {
        showCarrierMaxWeight($('#shipping_service_id').val())
    });
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
