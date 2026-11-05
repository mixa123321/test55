var tr_template = $('#sku_tr_template').html();
var bundle_tr_template = $('#bundle_tr_template').html();
var $sku_error_div = $('#add_sku_error');
var $sku_input = $('#search_skus');
var added_skus = {};
var added_bundles = {};

function add_sku(sku, qty, number, expired, price = '', order_bundle = null) {
    $sku_input.val('');
    $sku_error_div.hide();
    sku = sku.trim();
    if(!sku.length) {
        $sku_error_div.html("Please enter SKU barcode");
        $sku_error_div.show();
        return;
    }

    let exist_barcode = false;
    for (const barcode in global_skus_info) {
        let barcodes = global_skus_info[barcode].barcodes;
        if (barcodes.includes(sku) || sku === global_skus_info[barcode].barcode) {
            exist_barcode = true;
            sku = global_skus_info[barcode].barcode;
            break;
        }
    }

    let bundle_info = null;
    if (typeof bundles_info !== 'undefined') {
        for (const bundle_id in bundles_info) {
            let bundle_data = bundles_info[bundle_id];
            if (bundle_data['name'] === sku) {
                bundle_info = bundle_data;
                break;
            }
        }
    }
    if (exist_barcode) {
        if (sku in added_skus && !order_bundle) {
            $sku_error_div.html("SKU has already been added");
            $sku_error_div.show();
            return;
        }
        // if (order_bundle) {
        //     let bundles_SKUs = Object.keys(bundles_info[order_bundle.id].SKUs);
        //     for (const bundles_SKU of bundles_SKUs) {
        //         if (bundles_SKU in added_skus) {
        //             $('#bundle_' + order_bundle.id).remove();
        //             $sku_error_div.html("SKU has already been added");
        //             $sku_error_div.show();
        //             return;
        //         }
        //     }
        // }

        var sku_content = tr_template;

        if (price === '') {
            price = global_skus_info[sku]['market_value'];
        }
        if (price === null) {
            price = '';
        } else {
            price = parseFloat(price).toFixed(3);
        }

        let skus_tbody = $('#skus_tbody');

        sku_content = sku_content.replace(/\{\{barcode\}\}/g, escapeHtml(global_skus_info[sku]['barcode']));
        sku_content = sku_content.replace(/\{\{barcode_html\}\}/g, convertBarcodeWithPrimaryBarcode(global_skus_info[sku]['barcode']));
        sku_content = sku_content.replace(/\{\{left\}\}/g, global_skus_info[sku]['left']);
        sku_content = sku_content.replace(/\{\{name\}\}/g, escapeHtml(global_skus_info[sku]['name']));
        sku_content = sku_content.replace(/\{\{market_currency\}\}/g, escapeHtml(global_skus_info[sku]['market_currency'] ? global_skus_info[sku]['market_currency'] : '-'));
        sku_content = sku_content.replace(/\{\{id\}\}/g, global_skus_info[sku]['id']);
        sku_content = sku_content.replace(/\{\{price\}\}/g, price);
        sku_content = sku_content.replace(/\{\{batch_number\}\}/g, () => html_escape(number ?? ''));
        sku_content = sku_content.replace(/\{\{batch_expired\}\}/g, () => html_escape(expired ?? ''));
        sku_content = sku_content.replace(/\{\{active\}\}/g, +global_skus_info[sku]['state'] === 1 ? '' : ' text-danger');
        if (order_bundle) {
            sku_content = sku_content.replace(/\{\{from-bundle-id\}\}/g, 'bundle_' + order_bundle.id);
            sku_content = sku_content.replace(/\{\{from-bundle-qty\}\}/g, 'readonly disabled');
            sku_content = sku_content.replace(/\{\{qty\}\}/g, +qty * +order_bundle.qty);
            sku_content = sku_content.replace(/\{\{from-bundle-price\}\}/g, 'readonly disabled');
            sku_content = sku_content.replace('js-btn-remove-sku', 'd-none');
            added_bundles[bundles_info[order_bundle.id].name] = true;
            // let bundles_SKUs = Object.keys(bundles_info[order_bundle.id].SKUs);
            // for (const bundles_SKU of bundles_SKUs) {
            //     added_skus[bundles_SKU] = true;
            // }
        } else {
            sku_content = sku_content.replace(/\{\{from-bundle-id\}\}/g, '');
            sku_content = sku_content.replace(/\{\{from-bundle-qty\}\}/g, '');
            sku_content = sku_content.replace(/\{\{qty\}\}/g, +qty);
            sku_content = sku_content.replace(/\{\{from-bundle-price\}\}/g, '');
            added_skus[sku] = true;
        }
        skus_tbody.append(sku_content);
        $('.js-btn-remove-sku').click(function () {
            $(this).closest('tr').remove();
            delete added_skus[$(this).data('sku')];
            skus_tbody.trigger('changeSKUsAfterInit');
        });
    } else if (bundle_info) {
        if (sku in added_bundles) {
            $sku_error_div.html("Bundle has already been added");
            $sku_error_div.show();
            return;
        }
        if (addNewBundle !== undefined) {
            addNewBundle(bundle_info['bundle_id'], bundle_info['show_name'], bundle_info['quantity'], bundle_info['price'], bundle_info['SKUs']);
        }
        added_bundles[sku] = true;
    } else {
        $sku_error_div.html("SKU/bundle not found");
        $sku_error_div.show();
    }
}

function escapeHtml(text) {
    let map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function renderOrderSKUs(order_bundle = null) {
    if(typeof order_skus !== 'undefined')
        for(key in order_skus)
            add_sku(
                key,
                order_skus[key].quantity,
                typeof order_skus[key].batch_number == 'undefined' ? '' : order_skus[key].batch_number,
                typeof order_skus[key].batch_expired == 'undefined' ? '' : order_skus[key].batch_expired,
                typeof order_skus[key].price == 'undefined' ? '' : order_skus[key].price,
                order_bundle
            );
}

$(function () {
    $sku_input.autocomplete({source: global_skus_to_search});
    if ($sku_input.data()) {
        var ac = $sku_input.data('uiAutocomplete');
        var i = 0;
        if (ac) {
            ac._renderItem = function(ul, item) {
                i++;
                return $("<li class='ui-menu-item'>")
                    .append("<div class='ui-menu-item-wrapper'" + (item.active == 1 ? '' : " style='color: red'") + ">" + html_escape(item.label)  + "</div>")
                    .appendTo(ul);
            };
        }
    }

    $sku_input.keydown(function (e) {
        if(e.keyCode == 13) {
            add_sku($(this).val(), 1, '', '');
            $('#skus_tbody').trigger('changeSKUsAfterInit');
            e.preventDefault();
            return false;
        }
    });
    $('#btn_add_sku').click(function () {
        add_sku($sku_input.val(), 1, '', '');
        $('#skus_tbody').trigger('changeSKUsAfterInit');
    });

    if (typeof bundle_SKUs !== 'undefined') {
        for(key in bundle_SKUs) {
            add_sku(key, bundle_SKUs[key].quantity, '', '')
        }
        $('#skus_tbody').trigger('afterInitSKUs');
    }
});

