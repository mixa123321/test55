<?php
/**
 * @var $authUser User
 */

use app\core\entities\User;
use app\core\helpers\modal\InfoModal;

$modal_list = new InfoModal('listOrders', 'List of active orders', InfoModal::SIZE_XL);
echo $modal_list->addContent('
        <div class="table-responsive">
             <div id="notFound" class="alert alert-warning d-none mb-0">Not found data</div>
             <table class="table table-hover mb-0" id="tableOrders">
                 <colgroup>
                     <col width="*">
                     <col width="*">
                     <col width="*">
                     <col width="10%">' .
                     (!$authUser->isClientSide() ? '<col width="20%">' : '') . '
                 </colgroup>
                 <thead>
                 <tr>
                     <th>Order reference</th>
                     <th>Status</th>
                     <th>Batches</th>
                     <th>Quantity</th>' .
                     (!$authUser->isClientSide() ? '<th>Location</th>' : '') . '
                 </tr>
                 </thead>
                 <tbody>
                 </tbody>
             </table>
         </div>
     ')->put();

?>
<script>
    $('.js-btn-orders').click(function (e) {
        <?= $modal_list->show(); ?>
        var id = $('#' + e.currentTarget.id);
        var order_id = 0;
        if (typeof id.data('order_id') != 'undefined')
            order_id = id.data('order_id');
        $.ajax({
            url: "/SKUs/get_orders_by_SKU",
            method: "POST",
            data: { SKU_id: e.currentTarget.id, order_id: order_id },
            dataType: "json",
            success :function (json) {
                $('#tableOrders > tbody').children().remove().end();
                if (json.length > 0) {
                    $('#tableOrders').prop('classList').remove('d-none');
                    $('#notFound').prop('classList').add('d-none');
                    json.forEach(function (item) {
                        $('#tableOrders > tbody:last-child').append('<tr><td>' + html_escape(item.ref) + '</td><td>' + item.status + '</td><td class="text-xs">' + item.batches + '</td><td>' + item.quantity + '</td><?= !$authUser->isClientSide() ? "<td>' + item.consist + '</td>" : ''; ?></tr>');
                    });
                } else {
                    $('#tableOrders').prop('classList').add('d-none');
                    $("#notFound").prop('classList').remove('d-none');
                }
            }
        });
        return false;
    });
</script>
