<div class="modal" tabindex="-1" role="dialog" id="webhook_history">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Webhooks history</h3>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pb-0">
                <div class="alert alert-danger d-none" id="errorWebhook"></div>
                <div id="waitingWebhook" class="text-center">
                    <img src="<?= COMMON_IMG . DIRECTORY_SEPARATOR . 'tenor.gif'; ?>" style="width: 150px"/>
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
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-dismiss="modal" aria-label="Close" id="closeWebhook">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>
<script>
    $('.js-webhook').click(() => {
        var ASN_id = $('.js-webhook').data('asn_id');
        $('#closeWebhook').prop('disabled', true);
        $('#log-request-webhook').prop('classList').add('d-none');
        $('#errorWebhook').prop('classList').add('d-none');
        $('#waitingWebhook').prop('classList').remove('d-none');
        $('#webhook_history').modal();
        $.ajax({
            url: '/goods_in/get_webhook_history',
            method: 'POST',
            dataType: 'json',
            data: { ASN_id: ASN_id },
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