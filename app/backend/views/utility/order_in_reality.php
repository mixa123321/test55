<?php

use app\core\helpers\View;
use app\core\models\Warehouses;

if (!empty($warehouse_id)) {
    $warehouse_name = Warehouses::getById($warehouse_id)->getName();
} else
    $warehouse_name = '';
?>

<div class="modal" tabindex="-1" role="dialog" id="in_reality">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Warehouse "<?= View::toHtml($warehouse_name); ?>"</h3>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body pb-0">
                <div class="alert alert-danger d-none" id="errorReality"></div>
                <div id="waitingReality" class="text-center">
                    <img src="<?= COMMON_IMG . DIRECTORY_SEPARATOR . 'tenor.gif'; ?>" style="width: 150px"/>
                </div>
                <div id="log-request-reality" class="logs d-none">
                    <div class="row">
                        <div class="col-md-10 pt-2">
                            <h5><b>Order</b></h5>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button class="float-right border border-dark btn btn-xs mr-0" onclick="copyToClipboard('#log-request-reality-content');">Copy</button>
                        </div>
                    </div>
                    <pre id="log-request-reality-content" style="background-color: #fdfdfd; border: 1px solid #111111; padding: 20px"></pre>
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
    $('.js-in-reality').click(() => {
        $('#closeReality').prop('disabled', true);
        $('#log-request-reality pre').html('');
        $('#log-request-reality').prop('classList').add('d-none');
        $('#errorReality').prop('classList').add('d-none');
        $('#waitingReality').prop('classList').remove('d-none');
        $('#in_reality').modal();
        $.ajax({
            url: '/orders/get_from_reality',
            method: 'POST',
            dataType: 'json',
            data: {order_id: $('#order_id').val() },
            success: function (json) {
                if ('error' in json)
                    showError('errorReality', json.error, 0);
                else {
                    $('#log-request-reality pre').text(JSON.stringify(json.success, null, 4));
                    $('#log-request-reality').prop('classList').remove('d-none');
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

    function copyToClipboard(element) {
        let $temp = $("<input>");
        $(element).append($temp);
        $temp.val($(element).text()).select();
        document.execCommand('copy');
        $temp.remove();
    }
</script>