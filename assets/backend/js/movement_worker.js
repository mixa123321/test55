$(function () {
    $('#frm_movement').submit(() => {
        if ($('#fixed').val() == 1)
            return true;
        var search = $('#search').val().trim();
        if (search !== '') {
            if (search in locs_from) {
                $('#frm_movement').attr('action', '/movement/movement_' + (locs_from[search].loc_from_id == cart_id ? 'cart' : 'loc'));
                $('#id').val(locs_from[search].loc_from_id);
                return true;
            }
            if (search in locs_from_alias) {
                $('#frm_movement').attr('action', '/movement/movement_' + (locs_from_alias[search].loc_from_id == cart_id ? 'cart' : 'loc'));
                $('#id').val(locs_from_alias[search].loc_from_id);
                return true;
            }
            let supplier_id = $('#supplier_id').val();
            for (const barcode in SKUs) {
                let SKU_data = SKUs[barcode];
                if (SKU_data.barcodes.includes(search) && supplier_id === String(SKU_data.supplier_id)) {
                    $('#frm_movement').attr('action', '/movement/movement_bar');
                    $('#id').val(SKUs[barcode].SKU_id);
                    return true;
                }
            }
        }
        $('#search').val('');
        $('#search').focus();
        showError('err_msg', 'Scan origin location or SKU barcode', 3000);
        $('#barcode').focus();
        return false;
    });

    $('.js-btn-fixed').click((e) => {
        var btn_fix = $('#' + e.target.id);
        $('#fixed').val(1);
        $('#id').val(-1);
        $('#SKU_id').val(btn_fix.data('sku_id'));
        $('#loc_id').val(btn_fix.data('loc_id'));
        $('#loc_to_id').val(btn_fix.data('loc_to_id'));
        $('#frm_movement').attr('action', '/movement/movement_get' + ($('#loc_id').val() == cart_id ? '_B' : ''));
        $('#frm_movement').submit();
    });
});