$(function () {
    $('#btn_cancel').click(() => {
        $('#cancelMovementModalConfirm').modal('show');
        return false;
    });

    $('#btn-cancel-movement').click(() => {
        $('#cancelMovementModalConfirm').modal('hide');
        location = back || '/';
    });

    var $sku_input = $('#search');
    $sku_input.autocomplete({source: global_skus_to_search});
    var selected_sku = '';

    $('#createFrm').submit(function(e) {
        $('#selectSupplierModal').modal('hide');
        add_sku($sku_input.val());
        e.preventDefault();
        return false;
    });

    $('#qty').blur(() => {
        if ($('#qty').val() < 1) {
            $('#qty').val(1);
            showError(error_id, 'Quantity must be greater than 0', 3000);
        }
    });

    $('[data-scan-barcode]').on('click', () => {
        $('#qty').val(+$('#qty').val() + 1);
    });

    $('#origin_location').keydown(function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            if ($('#origin_location').val().trim() == '')
                showError(error_id, 'Origin location cannot by empty', 3000);
            else
                $('#dest_location').focus();
            return false;
        }
    });

    $('#dest_location').keydown(function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            let dest_location = $('#dest_location').val().trim().toUpperCase();
            if (dest_location === '') {
                showError(error_id, 'Destination location cannot by empty', 3000);
                return false;
            }
            let origin_location = $('#origin_location').val().trim().toUpperCase();
            if (origin_location === '') {
                showError(error_id, 'Origin location cannot by empty', 3000);
                $('#origin_location').focus();
                return false;
            }
            if (origin_location === dest_location) {
                showError(error_id, 'Locations must be different', 3000);
                return false;
            }
            $('#btn_conf').attr('disabled', false);
            $('#btn_conf').focus();
            return false;
        }
    });

    function add_sku(sku)
    {
        $sku_input.val('');
        let clear_sku = sku.trim();
        let supplier_id = $('#supplier_id').val();
        sku = '';
        if (!clear_sku.length) {
            showError(error_id, 'Please enter SKU barcode', 3000);
            return;
        }
        let send_sku = '';
        for (const barcode in global_skus_info) {
            if (supplier_id && String(global_skus_info[barcode].supplier_id) !== supplier_id)
                continue;
            let barcodes = global_skus_info[barcode].barcodes;
            if (barcodes.includes(clear_sku) || clear_sku === global_skus_info[barcode].barcode) {
                sku = global_skus_info[barcode].barcode + '_' + global_skus_info[barcode].supplier_id;
                send_sku = barcodes[0] + '_' + global_skus_info[barcode].supplier_id;
                break;
            }
        }
        if (!send_sku) {
            showError(error_id, 'SKU not found', 3000);
            return;
        }
        if (sku == selected_sku) {
            showError(error_id, 'SKU has already been added', 3000);
            return;
        }
        $('#sku_barcode').html(convertBarcodeWithPrimaryBarcode(global_skus_info[sku]['barcode']));
        $('#sku_name').text(global_skus_info[sku]['name']);
        $('#sku_id').text(global_skus_info[sku]['id']);
        var photo = global_skus_info[sku]['photo'];
        $.ajax({
            url: "/movement/get_SKU_data",
            method: "POST",
            data: { barcode: send_sku },
            success: function (json) {
                photo = json.photo;
                if (photo !== null && photo !== '') {
                    $('#sku_photo').attr('src', photo);
                    if ($('#sku_is_photo').prop('classList').contains('d-none'))
                        $('#sku_is_photo').prop('classList').remove('d-none');
                } else {
                    $('#sku_photo').removeAttr('src');
                    if (!$('#sku_is_photo').prop('classList').contains('d-none'))
                        $('#sku_is_photo').prop('classList').add('d-none');
                }
                $('#origin_location').val('');
                $('#dest_location').val('');
                $('#qty').val(1);
            },
            error: function (json) {
                showError(error_id, 'Database error', 3000);
            }
        });
        showItem($('#goods'));
        $('#btn_conf').attr('disabled', true);
        selected_sku = sku;
    }

    function showItem(item)
    {
        if (item.prop('classList').contains('d-none'))
            item.prop('classList').remove('d-none');
    }

    $('#btn_conf').click(() => {
        let SKU_id = $('#sku_id').text().trim();
        if (SKU_id === '') {
            showError(error_id, 'Select SKU for movement', 3000);
            return;
        }
        let qty = parseInt($('#qty').val());
        let loc_from = $('#origin_location').val().trim().toUpperCase();
        let loc_to = $('#dest_location').val().trim().toUpperCase();
        if (loc_from === '') {
            showError(error_id, 'Origin location cannot by empty', 3000);
            $('#origin_location').focus();
            return;
        }
        if (loc_to === '') {
            showError(error_id, 'Destination location cannot by empty', 3000);
            $('#dest_location').focus();
            return;
        }
        if (loc_from === loc_to) {
            showError(error_id, 'Locations must be different', 3000);
            return;
        }
        $.ajax({
            url: '/processing/is_processing',
            method: 'GET',
            async: false,
            data: { SKU_id: SKU_id, qty: qty, loc_from: loc_from, loc_to: loc_to },
            dataType: 'json',
            success: function (res) {
                if (res.error !== undefined) {
                    showError(error_id, res.error, 3000);
                    return false;
                }
                location = back || '/';
            },
            error: function (res) {
                showError(error_id, 'Access denied', 3000);
            }
        });
    });
});
