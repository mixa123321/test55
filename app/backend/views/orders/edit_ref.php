<div class="modal" tabindex="-1" role="dialog" id="editRef">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit general info</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php
                /** @var \app\core\entities\Order $order */
                $can_edit_purchase_order_number = $order->canEditPurchaseOrderNumber(); ?>
                <div class="form-group row"<?= $can_edit_purchase_order_number ? ' style="display:none;"' : ''; ?>>
                    <label for="er_ref" class="col-md-4 col-form-label">Customer Reference</label>
                    <div class="col-md-8">
                        <input id="er_ref" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row"<?= $can_edit_purchase_order_number ? ' style="display:none;"' : ''; ?>>
                    <label for="er_ref" class="col-md-4 col-form-label">Customer Reference 2</label>
                    <div class="col-md-8">
                        <input id="er_ref2" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <label for="er_purchase_order_number" class="col-md-4 col-form-label">
                        Purchase Order Number
                    </label>
                    <div class="col-md-8">
                        <input id="er_purchase_order_number" type="text" class="form-control" maxlength="20"/>
                    </div>
                </div>
                <div id='erError' class='col bg-danger text-white p-1 rounded text-center mt-1 mb-0 d-none'>

                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-success" id="updateRef" type="button">Update</button>
            </div>
        </div>
    </div>
</div>

<script>
    $('.js-btn-edit-ref').click(function () {
        let ref = $(this).data('ref');
        let ref2 = $(this).data('ref2');
        let purchase_order_number = $(this).data('purchase_order_number');
        $('#er_ref').val(ref);
        $('#er_ref2').val(ref2);
        $('#er_purchase_order_number').val(purchase_order_number);
        $('#editRef').modal('show');
    });

    $('#updateRef').click(function () {
        $.ajax({
            url: "/orders/save_ref",
            method: "POST",
            data: {
                ref: $('#er_ref').val(),
                ref2: $('#er_ref2').val(),
                purchase_order_number: $('#er_purchase_order_number').val(),
                order_id: $('.js-btn-edit-ref').data('order_id')
            },
            dataType: "json",
            success :function (json) {
                if ('error' in json) {
                    $('#erError').text(json['error']);
                    $('#erError').prop('classList').remove('d-none');
                    setTimeout(() => {
                        $('#erError').prop('classList').add('d-none');
                    }, 3000);
                } else {
                    location = '/orders/view_order/?id=' + $('.js-btn-edit-ref').data('order_id');
                }
            }
        });
    });

    $('#er_ref').change(function () {
        this.value = this.value.replace(/\s/g, '');
    });
</script>
