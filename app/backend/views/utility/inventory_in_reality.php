<div class="modal" tabindex="-1" role="dialog" id="inventory_in_reality">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="inventory_name"></h3>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pb-0">
                <div class="alert alert-danger d-none" id="errorReality"></div>
                <div id="waitingReality" class="text-center">
                    <img src="<?= COMMON_IMG . DIRECTORY_SEPARATOR . 'tenor.gif'; ?>" style="width: 150px"/>
                </div>
                <div id="log-request" class="logs d-none">
                    <div class="row">
                        <div class="col pt-2">
                            <h5><b>Inventory</b></h5>
                        </div>
                        <div class="col-auto mb-2">
                            <input type="hidden" id="sync_response" value=""/>
                            <button id="btn_sync" class="btn btn-primary rounded rounded-circle p-2" type="button">
                                <i class="fas fa-fw fa-cloud-download-alt"></i>
                            </button>&nbsp;
                            <button class="float-right border border-dark btn btn-xs mr-0" onclick="copyToClipboard('#log-request-content');">Copy</button>
                        </div>
                    </div>
                    <pre id="log-request-content" style="background-color: #fdfdfd; border: 1px solid #111111; padding: 20px"></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" data-dismiss="modal" aria-label="Close" id="closeReality">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>
<script>
    $('.js-inventory').click((e) => {
        var SKU = $('#' + e.currentTarget.id).data('sku_obj');
        $('#closeReality').prop('disabled', true);
        $('#log-request pre').html('');
        $('#log-request').prop('classList').add('d-none');
        $('#errorReality').prop('classList').add('d-none');
        $('#waitingReality').prop('classList').remove('d-none');
        $('#inventory_name').text(SKU.name);
        $('#inventory_in_reality').modal();
        $.ajax({
            url: '/housekeeping/get_inventory',
            method: 'POST',
            dataType: 'json',
            data: {SKU_id: SKU.id },
            success: function (json) {
                if ('error' in json)
                    showError('errorReality', json.error, 0);
                else {
                    $('#sync_response').val(JSON.stringify(json.success));
                    $('#log-request pre').text(JSON.stringify(json.success, null, 4));
                    $('#log-request').prop('classList').remove('d-none');
                }
                $('#waitingReality').prop('classList').add('d-none');
                $('#closeReality').prop('disabled', false);
            },
            error: function (json) {
                showError('errorReality', 'Connection error!', 0);
                $('#waitingReality').prop('classList').add('d-none');
                $('#closeReality').prop('disabled', false);
            }
        });
    });

    $('#btn_sync').click(() => {
        var result = $('#sync_response').val();
        if (result.trim() === '')
            return;
        $.post(
            '/housekeeping/sync_inventory',
            {result},
            data => {
                if (data == 'success')
                    location.reload();
                else {
                    showError('errorReality', data, 3000);
                }
            }
        );
    });

    function copyToClipboard(element) {
        let $temp = $("<input>");
        $(element).append($temp);
        $temp.val($(element).text()).select();
        document.execCommand('copy');
        $temp.remove();
    }
</script>