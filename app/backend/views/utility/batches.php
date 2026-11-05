<?php

use app\core\helpers\View;

?>
<div class="modal" tabindex="-1" role="dialog" id="editBatch">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit batch</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row justify-content-end">
                    <div class="col-12">
                        <div class="form-group mb-1">
                            <div class="form-group-element">
                                <input id="batch_number" type="text" class="form-control form-control-lg" placeholder=" " />
                                <label for="batch_number">Number</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group mb-1">
                            <div class="form-group-element">
                                <input id="batch_expired" type="date" class="form-control form-control-lg" placeholder=" " />
                                <label for="batch_expired">Expired</label>
                            </div>
                        </div>
                    </div>
                    <div id="getError" class="col-12 text-white text-center mb-1 d-none">
                        <p class="form-control-static bg-danger p-1 m-0" id="msgError"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-danger js-edit-confirm">Update</button>
            </div>
        </div>
    </div>
</div>
<script>
    let batch_id = 0;

    $('.js-btn-edit').click(function () {
        batch_id = $(this).data('batch_id');
        $('#batch_number').val($(this).data('number'));
        $('#batch_expired').val($(this).data('expired'));
        $('#editBatch').modal('show');
        return false;
    });

    $('.js-edit-confirm').click(function () {
        $.ajax({
            url: "/housekeeping/save_batch",
            method: "POST",
            dataType: 'json',
            data: { batch_id : batch_id, number : $('#batch_number').val(), expired : $('#batch_expired').val() },
            success: function (json) {
            if ('error' in json)
                    showErrorMessage('getError', 'msgError', json.error, 3000);
                else
                    location.reload();
            },
            error: function (json) {
                showErrorMessage('getError', 'msgError', 'Connection error', 3000);
        }
        });
    });

</script>
<?= View::include_js(BACKEND_JS, 'show_error.js'); ?>

