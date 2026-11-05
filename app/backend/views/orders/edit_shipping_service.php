<?php
/**
 * @var $order Order
 * @var $carriers array
 * @var $carrier Carrier
 * @var $shipping_services array
 */

use app\core\entities\Carrier;
use app\core\entities\Order;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\models\Shippings;

$shipping_service_id = $order->getShippingServicesId() ?? 0;

?>

<div class="modal" tabindex="-1" role="dialog" id="editShippingService">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Shipping service</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pb-0">
                <div class="form-group">
                    <div class="form-group-element">
                        <?php
                            $options = [
                                'id' => 'carrier_id',
                                'class' => 'form-control form-control-lg custom-select',
                                'onchange' => 'changeServiceOption();',
                            ];
//                        if (count($carriers) < 2)
//                            $options['disabled'] = true;
                        echo Html::dropDownList('carrier_id', $carriers, $carrier->getId(), $options);
                        ?>
                        <label for="carrier_id">Carrier Service</label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-group-element">
                        <?php
                        $options = [
                            'class' => 'form-control form-control-lg custom-select',
                            'id' => 'shipping_services_id',
                        ];
                        echo Html::dropDownList('shipping_services_id', $shipping_services[$carrier->getId()], $shipping_service_id, $options);
                        ?>
                        <label for="shipping_services_id">Service Option</label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-group-element">
                        <?php
                        $options = [
                            'class' => 'form-control form-control-lg custom-select',
                            'id' => 'DDP',
                        ];
                        echo Html::dropDownList('DDP', Shippings::TERMS, $order->getDDP(), $options);
                        ?>
                        <label for="DDP">Terms</label>
                    </div>
                </div>
                <div id='essError' class='col bg-danger text-white p-2 text-center mb-3 d-none'>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-success" id="updateShippingService" type="button">Update</button>
            </div>
        </div>
    </div>
</div>
<script>
    var shipping_services = <?= View::toJs($shipping_services) ?>;

    $('#editShippingService').focus(() => {
        $('#carrier_id').val('<?= $carrier->getId(); ?>');
        changeServiceOption();
        $('#shipping_services_id').val('<?= $shipping_service_id; ?>');
    });

    function changeServiceOption()
    {
       $('#shipping_services_id option').remove();
       $.each(shipping_services[$('#carrier_id').val()], function (key, item) {
           $('#shipping_services_id').append($('<option>', { value : key }).text(item));
       });
    }

    $('.js-btn-edit-shipping-service').click(function () {
        $('#editShippingService').modal('show');
    });

    $('#updateShippingService').click(function () {
        $.ajax({
            url: "/orders/save_shipping_service",
            method: "POST",
            data: {
                carrier_id: $('#carrier_id').val(),
                service_id: $('#shipping_services_id').val(),
                DDP: $('#DDP').val(),
                order_id: $('.js-btn-edit-shipping-service').data('order_id')
            },
            dataType: "json",
            success :function (json) {
                if ('error' in json) {
                    $('#essError').text(json['error']);
                    $('#essError').prop('classList').remove('d-none');
                    setTimeout(() => {
                        $('#essError').prop('classList').add('d-none');
                    }, 3000);
                } else {
                    location = '/orders/view_order/?id=' + $('.js-btn-edit-shipping-service').data('order_id');
                }
            }
        });
    });
</script>
