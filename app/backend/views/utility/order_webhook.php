<?php

use app\core\helpers\modal\InfoModal;

$modal_webhook_history_data = '<div class="alert alert-danger d-none" id="errorWebhook"></div>
                                <div id="waitingWebhook" class="text-center">
                                    <img src="' . COMMON_IMG . DIRECTORY_SEPARATOR . 'tenor.gif' .'" style="width: 150px"/>
                                </div>
                                <div class="d-none" id="log-request-webhook">
                                    <table class="table table-hover mb-3">
                                        <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Create date</th>
                                            <th>User</th>
                                            <th>Status</th>
                                            <th>Detail</th>
                                        </tr>
                                        </thead>
                                        <tbody id="tableInfo"></tbody>
                                    </table>
                                </div>';
$modal_webhook_history = new InfoModal('webhook_history', 'Webhooks history', InfoModal::SIZE_LG);
echo $modal_webhook_history->addContent($modal_webhook_history_data)->put();
?>
<script>
    $('.js-webhook').click(() => {
        var order_id = $('.js-webhook').data('order_id');
        $('#closeWebhook').prop('disabled', true);
        $('#log-request-webhook').prop('classList').add('d-none');
        $('#errorWebhook').prop('classList').add('d-none');
        $('#waitingWebhook').prop('classList').remove('d-none');
        <?= $modal_webhook_history->show() ?>
        $.ajax({
            url: '/orders/get_webhook_history',
            method: 'POST',
            dataType: 'json',
            data: { order_id: order_id },
            success: function (json) {
                if ('error' in json)
                    showError('errorWebhook', json.error, 0);
                else {
                    $('#tableInfo').children().remove().end();
                    $.each(json.success, function (key, val) {
                        $('#tableInfo').append('<tr><td>' + html_escape(val.name) + '</td><td>' + html_escape(val.create_date) + '</td><td>' + html_escape(val.login) + '</td><td>' + val.status + '</td><td><a href="/reports/view_webhook/?id=' + encodeURIComponent(key) + '" target="_blank"><i class="fa fa-info-circle"></i></a></td></tr>');
                    });
                    $('#log-request-webhook').prop('classList').remove('d-none');
                }
                $('#waitingWebhook').prop('classList').add('d-none');
                $('#closeWebhook').prop('disabled', false);
            },
            error: function (json) {
                showError('errorWebhook', 'Connection error!', 0);
                $('#waitingWebhook').prop('classList').add('d-none');
                $('#closeWebhook').prop('disabled', false);
            }
        });
    });
</script>