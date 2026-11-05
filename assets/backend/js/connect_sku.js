$(function () {
    $('.js-btn-connect').click(function () {
        changeFields($('#channel_id').val());
        $('#connectSKU').modal('show');
        $('#get_product').prop('disabled', false);
        $('#product_id').val('');
        $('#variant_id').val('');
        clearData();
    });

    $('.js-connect-confirm').click(function () {
        var SKU_id = $('#SKU_id').val();
        $.ajax({
            url: "/SKUs/add_product",
            method: "POST",
            dataType: 'json',
            data: {
                channel : $('#channel_id').val(),
                SKU_id : SKU_id,
                shop_id : $('#shop_id').val(),
                product_id : $('#product_id').val(),
                inventory_id : $('#inventory_id').val(),
                variant_id : $('#variant_id').val()
            },
            success: function (json) {
                if (json == 'exist') {
                    clearData();
                    showErrorMessage('getError', 'msgError', 'This SKU is already connected', 3000);
                } else if (json == 'insert') {
                    location = '/SKUs/edit_connections/?id=' + SKU_id;
                } else if (json.res == 'duplicate') {
                    clearData();
                    showErrorMessage('getError', 'msgError', 'This SKU is already connected to SKU #' + (+json.id), 3000);
                }
            },
            error: function (json) {
                showErrorMessage('getError', 'msgError', 'Connection error', 3000);
            }
        });
        return false;
    });

    $('#channel_id').change(function () {
        $('#shop_id option').remove();
        var shops = supplier_shops[$('#channel_id').val()];
        $.each(shops, function(key, value) {
            $('#shop_id').append($('<option>').val(key).text(value['shop_url']));
        });
        changeFields($(this).val());
        clearData();
    });

    function changeFields(channel)
    {
        switch (channel) {
            case shopify:
                $('#product_name').html('Product Id');
                $('#variant_name').html('Variant Id');
                break;
            case woocommerce:
                $('#product_name').html('Product Id');
                $('#variant_name').html('Variation Id');
                break;
            case magento:
                $('#product_name').html('Product Id');
                $('#variant_name').html('Variation Id');
                break;
        }
    }

    $('#shop_id').change(function () {
        clearData();
    });

    $('#get_product').click(function () {
        var product_id = $('#product_id').val().trim();
        var variant_id = $('#variant_id').val().trim();
        clearData();
        if (product_id == '') {
            showErrorMessage('getError', 'msgError', 'Product is empty', 3000);
            return false;
        }
        $('#get_product').prop('disabled', true);
        $.ajax({
            url: "/SKUs/get_product",
            method: "POST",
            dataType: 'json',
            data: { channel : $('#channel_id').val(), shop_id : $('#shop_id').val(), product_id : product_id, variant_id : variant_id },
            success: function (json) {
                if ('error' in json) {
                    showErrorMessage('getError', 'msgError', html_escape(json.error), 3000);
                } else {
                    $('#inventory_id').val(json.inventory_item_id != null ? json.inventory_item_id : '');
                    $('#product_info').html(json.product_info);
                    $('#get_product').prop('disabled', false);
                    $('.js-connect-confirm').prop('disabled', false);
                }
            },
            error: function (json) {
                showErrorMessage('getError', 'msgError', 'Connection error', 3000);
            }
        });
    });

    function clearData()
    {
        $('#product_info').html('');
        $('.js-connect-confirm').prop('disabled', true);
    }
});