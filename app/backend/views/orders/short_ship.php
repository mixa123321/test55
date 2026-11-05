<?php

use app\core\helpers\modal\ConfirmModal;

$modal_short_ship = new ConfirmModal('shortShip', 'Short Ship');
echo $modal_short_ship->addContent("Are you sure?<div id='shshError' class='col bg-danger text-white p-1 rounded text-center mt-2 mb-0 d-none'></div>")
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['id' => 'btn-short-ship-button'])
    ->put();
?>
<script>
    $('.js-short-ship').click(function () {
        <?= $modal_short_ship->show(); ?>
        $('#order_id').val($(this).data('order_id'));
    });

    $('#btn-short-ship-button').click(function () {
        $.ajax({
            url: "/orders/short_ship",
            method: "POST",
            data: { order_id: $('.js-short-ship').data('order_id') },
            dataType: 'json',
            success :function (json) {
                if ('error' in json) {
                    $('#shshError').text(json['error']);
                    $('#shshError').prop('classList').remove('d-none');
                    setTimeout(() => {
                        $('#shshError').prop('classList').add('d-none');
                    }, 3000);
                } else {
                    location = '/orders/view_order/?id=' + $('.js-short-ship').data('order_id');
                }
            }
        });
    });
</script>
