<?php
/**
 * @var $authUser User
 * @var $shipping_services array
 * @var $carrier Carrier
 * @var $logo string
 */

use app\core\entities\Carrier;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Shipping Services (<?= View::toHtml("{$carrier->getName()} - {$carrier->getCode()}")?>)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <div class="col">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-server"></i> Service List</h6>
            </div>
            <div class="col-auto">
                <button class="btn btn-success btn-icon-split" onclick="setShippingServiceForm()" type="button">
                    <span class="icon text-white">
                        <i class="fas fa-fw fa-plus"></i>
                    </span>
                    <span class="text">Create new service</span>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-2">
                    <img class="img-profile" style="height: 150px; padding: 0"
                         src="data:image/png;base64, <?= $logo; ?>"/>
                </div>
                <div class="col-8">
                    <?php if (count($shipping_services)) { ?>
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Code</th>
                                    <th>Edit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shipping_services as $shipping_service) { ?>
                                    <tr>
                                        <td><?= View::toHtml($shipping_service['name']) ?></td>
                                        <td><?= View::toHtml($shipping_service['code']) ?></td>
                                        <td>
                                            <button class="btn btn-success btn-icon-split" type="button" onclick="setShippingServiceForm(<?= $shipping_service['id']; ?>)">
                                                <span class="icon text-white">
                                                    <i class="fas fa-fw fa-edit"></i>
                                                </span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else echo Html::warning("No records found"); ?>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row justify-content-end">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/system_settings/carriers">
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
    $modal_shipping_services = new SwitchModal('editShippingServices', '<span id="action_name"></span> shipping service', SwitchModal::SIZE_LG);
    echo $modal_shipping_services->addContent('
            <div id="response"></div>
            <input type="hidden" name="id" id="shipping_service_id">
            <input type="hidden" name="carrier_id" value="' . $carrier->getId() . '">
            <div class="form-group">
                <div class="form-group-element">
                    <input type="text" name="name" id="shipping_service_name" class="form-control form-control-lg" value="">
                    <label for="shipping_service_name">Shipping Service <span style="color:red">*</span></label>
                </div>
            </div>
            <div class="form-group mb-0">
                <div class="form-group-element">
                    <input type="text" name="code" id="shipping_service_code"
                           class="form-control form-control-lg" value="">
                    <label for="shipping_service_code">Code <span style="color:red">*</span></label>
                </div>
            </div>')
        ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'sendShippingServiceForm'])
        ->addButton('Save', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
        ->put();
?>
<script>
    const shipping_services = <?= View::toJs($shipping_services); ?>;
    const responseBlock = $('#response');

    document.getElementById('sendShippingServiceForm').addEventListener('submit', e => {
        e.preventDefault();
        responseBlock.html(null);
        $.ajax({
            type: "POST",
            url: '/system_settings/adding_shipping_service',
            data: $(e.target).serialize(),
            dataType: "json",
            success: function (res) {
                if (res.result !== undefined) {
                    if (res.result === 'error' && res.errors.length > 0) {
                        responseBlock.html(res.errors.reduce((temp, error) => {
                            temp += `<li class="alert-danger pl-3">${html_escape(error)}</li>`;
                            return temp;
                        }, ''));
                    } else window.location.reload(false);
                }
            }
        });
    });

    function setShippingServiceForm(service_id = null) {
        responseBlock.html(null);
        document.getElementById('action_name').textContent = service_id ? 'Edit' : 'Create';
        const service = service_id ? shipping_services.find(s => +s.id === +service_id)
            : {id: '', name: '', code: ''};
        document.getElementById('shipping_service_id').value = service.id;
        document.getElementById('shipping_service_name').value = service.name;
        document.getElementById('shipping_service_code').value = service.code;
        <?= $modal_shipping_services->show(); ?>
    }
</script>
