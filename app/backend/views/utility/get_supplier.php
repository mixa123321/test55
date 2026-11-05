<?php

use app\core\helpers\View;

?>
<div class="modal" tabindex="-1" role="dialog" id="selectSupplierModal">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select company</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <select id="supplierId" class="form-control form-control-lg custom-select">
                    </select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-orange" data-dismiss="modal" id="cancelSupplier" type="button"><?= View::getTranslate('Cancel'); ?></button>
                    <button class="btn btn-orange" id="selectSupplier" disabled type="button"><?= View::getTranslate('Select'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $('.js-btn-scan').click(function () {
        var cont = true;
        var search = $('#search').val().trim();
        if (!$('.js-btn-scan').data('empty'))
            if (search == '') {
                $('#search').focus()
                    .trigger('select-sku-error', { error: 'Please enter a barcode' });
                return false;
            }
        $.ajax({
            url: "/SKUs/get_suppliers_by_barcode",
            method: "POST",
            data: { barcode: search, suppliers: JSON.stringify($('.js-btn-scan').data('suppliers')) },
            dataType: "json",
            success :function (json) {
                if (json.length > 1) {
                    $('#supplierId').children().remove().end();
                    $('#supplierId').append($('<option>', { value : 0 }).text('Select company...'));
                    json.forEach(function (item) {
                        $('#supplierId').append($('<option>', { value : item.id }).text(item.name));
                    });
                    $('#selectSupplierModal').modal('show');
                    $('#selectSupplier').prop('disabled', true);
                    cont = false;
                } else if (json.length == 1 && json != 'error') {
                    $('#supplier_id').val(json[0].id);
                    $('#search').trigger('select-sku', { barcode: search, supplier_id: json[0].id });
                } else {
                    $('#search').trigger('select-sku-error', { error: 'SKU not found' });
                }
            },
            async: false
        });
        return cont;
    });

    $('#supplierId').change(function () {
        $('#selectSupplier').prop('disabled', $('#supplierId').val() == 0);
    });

    $('#cancelSupplier').click(function () {
        $('#selectSupplierModal').modal('hide');
        $('#search').val('');
        $('#search').focus();
        $('#search').trigger('deselect-sku');
    });

    $('#selectSupplier').click(function () {
        $('#supplier_id').val($('#supplierId').val());
        $('#selectSupplierModal').modal('hide');
        $('#' + $('.js-btn-scan').data('send')).submit();
        if ($('#supplierId').val())
            $('#search').trigger('select-sku', { barcode: $('#search').val().trim(), supplier_id: $('#supplierId').val() });
        else
            $('#search').trigger('select-sku-error', { error: 'Please select a company' });
    });
</script>
