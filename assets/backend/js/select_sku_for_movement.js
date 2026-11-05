$(function () {
    const SCAN_TYPE__SKU = 1,
        SCAN_TYPE__LOCATION = 2;
    var $sku_input = $('#search'),
        $scan = $sku_input.closest('.js-scan-barcode'),
        scan_type = $scan.data('scan-type'),
        $pallet = $('#pallet'),
        $destination = $('#destination'),
        $btn_conf = $('#btn_conf'),
        $title = $('.movement-title'),
        $btn_cancel = $('#btn_cancel'),
        $btn_back = $('#btn_back'),
        $footer = $('.card-footer'),
        $panel_src = $('#panel_src'),
        $panel_dest = $('#panel_dest'),
        origin_title = $title.text();
    var selected_sku = '',
        selected_location = null,
        selected_destination = null;

    function renderSKURow(SKU) {
        return `
        <tr>
            <td class="p-2 align-middle">
                ${SKU.photo_url ? `<img class="img-thumbnail" style="max-width: 100px;" src="${html_escape(SKU.photo_url)}"/><br/>` : ''}
            </td>
            <td class="p-2 align-middle">
                <span class="d-none d-md-block">
                    <b>${html_escape(SKU.name)}</b> (#${html_escape(SKU.SKU_id)})
                </span>
                <span class="scan-barcode">${template_filter_SKU_barcodes(SKU.barcodes)}</span>
                ${SKU.number ? `<br/><span class="text-xs">${html_escape(SKU.number)} (${template_filter_date_format(SKU.expired, 'Do MMMM YYYY')})</span>` : ''}
            </td>
            <td class="p-2 align-middle"><span class="text-success font-weight-bold">${html_escape(SKU.summa)}</span></td>
        </tr>`;
    }

    if (scan_type & SCAN_TYPE__SKU) {
        let get_suppliers_by_barcode = new GetSuppliersByBarcode();
        get_suppliers_by_barcode.addEvents();

        let autocomplete = new AutocompleteWrapperV2();
        autocomplete.initAutocomplete($sku_input, '/movement/get_skus_for_search').addEventInputEnter().addEventButtonClick($('.js-btn-scan'));
        autocomplete.func_show_error = function (error) {
            showError('add_sku_error', error, 3000);
        }
        autocomplete.func_hide_error = function (error) {
            $('#add_sku_error').addClass('d-none');
        }
        autocomplete.func_event_after_select = autocomplete.func_event_add_searched = function (item, self) {
            let duplicate = self.getDuplicateValue(item);
            get_suppliers_by_barcode.setDuplicates(duplicate);
            if (get_suppliers_by_barcode.showSuppliersByBarcode()) {
                get_suppliers_by_barcode.setAfterSelectSupplier(function (selected_duplicate) {
                    let global_skus_info = {};
                    global_skus_info[selected_duplicate.barcodes[0] + '_' + selected_duplicate.supplier_id] = {
                        barcode: selected_duplicate.barcodes[0],
                        name: selected_duplicate.name,
                        id: selected_duplicate.id,
                        photo: selected_duplicate.photo,
                    };
                    add_sku(selected_duplicate.barcodes[0], global_skus_info);
                });
            } else {
                self._input_obj.val(item['barcodes'].join(', '));
                let global_skus_info = {};
                global_skus_info[item.barcodes[0] + '_' + item.supplier_id] = {
                    barcode: item.barcodes[0],
                    name: item.name,
                    id: item.id,
                    photo: item.photo,
                };
                add_sku(item.barcodes[0], global_skus_info);
            }
        }
    }
    changeTypeLocations($('#type_location').val());

    $('#type_location').change(function() {
        changeTypeLocations(this.value);
    });

    $('#createFrm').submit(function(e) {
        $('#selectSupplierModal').modal('hide');
        add_sku($sku_input.val());
        e.preventDefault();
        return false;
    });

    if (typeof previous_movement !== 'undefined' && previous_movement) {
        if (previous_movement.barcodes != undefined) {
            let global_skus_info = {};
            global_skus_info[previous_movement.barcodes[0] + '_' + previous_movement.supplier_id] = {
                barcode: previous_movement.barcodes[0],
                name: previous_movement.name,
                id: previous_movement.id,
                photo: previous_movement.photo,
            };
            $('#supplier_id').val(previous_movement.supplier_id);
            add_sku(previous_movement.barcodes[0], global_skus_info);
            $('#createAnotherMovementModalConfirm').modal('show');
        }
    }

    $('#destFrm').submit(function(e) {
        e.preventDefault();
        addDestination($('#search_destination').val());
        $('#search_destination').val('');
        return false;
    });

    $('#qty').blur(() => {
        if ($('#qty').val() < 1) {
            $('#qty').val(1);
            showError('add_sku_error','Quantity must be greater than 0', 3000);
        }
    });

    $('#btn_delete_pallet').click(() => {
        hidePallet();
    });
    $('#btn_delete_destination').click(() => {
        hideDestination();
    });
    $('#btn_back').click(() => {
        if (selected_destination) {
            hideDestination();
            return;
        }
        if (selected_location) {
            hidePallet();
            return;
        }
    });

    function add_sku(sku, global_skus_info = {}) {
        $sku_input.val('');
        let clear_sku = sku.trim();
        let types = [];
        if (scan_type & SCAN_TYPE__LOCATION)
            types.push('Location');
        if (scan_type & SCAN_TYPE__SKU)
            types.push('SKU barcode');
        if (types.length === 0)
            return showError('add_sku_error', 'Invalid scan type', 3000);
        let supplier_id = $('#supplier_id').val();
        if (!clear_sku.length)
            return showError('add_sku_error', `Please enter ${types.join(' or ')}`, 3000);
        sku = '';

        let send_sku = '';
        if (scan_type & SCAN_TYPE__SKU) {
            sku = clear_sku.trim() + '_' + $('#supplier_id').val();
            send_sku = clear_sku;
        }
        if (!send_sku && (scan_type & SCAN_TYPE__LOCATION)) {
            // no supplier id means no SKU found...
            $.ajax({
                method: 'GET',
                async: false,
                url: '/locations/scan',
                data: {barcode: clear_sku, include_skus: true, for_movement: true},
                success: function (data) {
                    if (data.success) {
                        if (!data.location.is_moveable)
                            showError('add_sku_error', 'Location is not moveable', 3000);
                        else if (data.location.state != 1)
                            showError('add_sku_error', 'Location is not active', 3000);
                        else
                            drawPallet(data.location, data.SKUs, data.destinations);
                    } else {
                        showError('add_sku_error', data.error, 3000);
                    }
                },
                error: function (xhr, status, error) {
                    var message = (xhr.status == 404
                        ? `${types.join(' or ')} not found`
                        : xhr.responseJSON.error) || error || 'Error';
                    showError('add_sku_error', message, 3000);
                }
            });
            return;
        }

        hidePallet();
        if (!send_sku)
            return showError('add_sku_error', "SKU not found", 3000);
        if (sku === selected_sku)
            return showError('add_sku_error', "SKU has already been added", 3000);
        $('#sku_barcode').html(convertBarcodeWithPrimaryBarcode(global_skus_info[sku]['barcode']));
        $('#sku_name').text(global_skus_info[sku]['name']);
        $('#sku_id').text(global_skus_info[sku]['id']);
        var photo = global_skus_info[sku]['photo'];
        $.ajax({
            url: "/movement/get_SKU_data",
            method: "POST",
            data: {
                barcode: send_sku,
                supplier_id: supplier_id
            },
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
                $('#location_from').children().remove();
                $('#available_locs').children().remove();
                if (isObject(json.locs.PF) || isObject(json.locs.ST)) {
                    addButton('location_from', 'locLocFrom', 'from_location', 'fa-map-marker-alt', 'loc_from_name', 'Location')
                    if (isObject(json.locs.PF))
                        addSelect('available_locs', 'locPFfrom', 'Pick Face', 'locPFfrom_items', json.locs.PF);
                    if (isObject(json.locs.ST))
                        addSelect('available_locs', 'locSTfrom', 'Storage', 'locSTfrom_items', json.locs.ST);
                }
                if (isObject(json.locs.PA)) {
                    addButton('location_from', 'locPAFrom', 'from_PA', 'fa-sign-in-alt', 'loc_from_PA', 'PA zone')
                    addSelect('available_locs', 'locPAfrom', 'Putaway', 'locPAfrom_items', json.locs.PA);
                }
                if (isObject(json.locs.PR)) {
                    addButton('location_from', 'locPRFrom', 'from_PR', 'fa-sign-in-alt', 'loc_from_PR', 'Processing location')
                    addSelect('available_locs', 'locPRfrom', 'Processing location', 'locPRfrom_items', json.locs.PR);
                }

                addButton('location_from', 'locDHFrom', 'from_DH', 'fa-ghost', 'loc_from_DH', 'DH')
                if (isObject(json.locs.CA) || isObject(json.locs.US)) {

                    var CA_locations = isObject(json.locs.CA) ? json.locs.CA : {};
                    var US_locations = isObject(json.locs.US) ? json.locs.US : {};
                    var CA_US_locations = Object.assign({}, CA_locations, US_locations)

                    addButton('location_from', 'locCartFrom', 'from_cart', 'fa-shopping-cart', 'loc_from_cart', 'Trolley')
                    addSelect('available_locs', 'locCartfrom', 'Trolley', 'locCartfrom_items', CA_US_locations);
                }
                $('#location_to').children().remove();
                $('#available_to').children().remove();
                if (isObject(json.locs_to.PF) || isObject(json.locs_to.ST)) {
                    addButton('location_to', 'locLocto', 'to_location', 'fa-map-marker-alt', 'loc_to_name', 'Location')
                    if (isObject(json.locs_to.PF))
                        addSelect('available_to', 'locPFto', 'Pick Face', 'locPFto_items', json.locs_to.PF);
                    if (isObject(json.locs_to.ST))
                        addSelect('available_to', 'locSTto', 'Storage', 'locSTto_items', json.locs_to.ST);
                }
                addButton('location_to', 'locPAto', 'to_PA', 'fa-sign-in-alt', 'loc_to_PA', 'PA zone')
                if (isObject(json.locs_processing)) {
                    addButton('location_to', 'locPrTo', 'to_PR', 'fa-sign-in-alt', 'loc_to_PA', 'Processing location')
                    addSelectProcessing('available_to', 'locProcessingTo', 'Processing locations', 'locProcessingto_items', json.locs_processing);
                }
                addButton('location_to', 'locDHto', 'to_DH', 'fa-ghost', 'loc_to_DH', 'DH')
                if (cart_id) {
                    addButton('location_to', 'locCartto', 'to_cart', 'fa-shopping-cart', 'loc_to_cart', 'Trolley');
                } else {
                    addButton('location_to', 'locCartto', 'to_trolley', 'fa-shopping-cart', 'loc_to_trolley', 'Trolley')
                    addSelectProcessing('available_to', 'locTrolleyTo', 'Trolleys', 'locTrolleyTo_items', json.locs_trolleys);
                }

                $('#location_from #' + previous_movement.loc_from).prop('checked', true).trigger('change');
                $('#is_available_locs select').val(previous_movement.loc_from_id);
            },
            error: function (json) {
                showError('add_sku_error', "Database error", 3000);
            }
        });
        showItem($('#goods'));
        hideItem($('#is_available_locs'));
        hideItem($('#is_available_to'));
        $btn_conf.attr('disabled', true);
        loc_from = loc_to = '';
        selected_sku = sku;
    }

    $('#location_from').change(function(e) {
        var nums = 0;
        loc_from = e.target.id;
        if (loc_from === 'from_location')
            nums += toggleLocs([0, 1], ['locPFfrom', 'locSTfrom', 'locPAfrom', 'locCartfrom', 'locPRfrom']);
        else if (loc_from === 'from_PA')
            nums += toggleLocs([2], ['locPFfrom', 'locSTfrom', 'locPAfrom', 'locCartfrom', 'locPRfrom']);
        else if (loc_from === 'from_cart')
            nums += toggleLocs([3], ['locPFfrom', 'locSTfrom', 'locPAfrom', 'locCartfrom', 'locPRfrom']);
        else if (loc_from === 'from_PR')
            nums += toggleLocs([4], ['locPFfrom', 'locSTfrom', 'locPAfrom', 'locCartfrom', 'locPRfrom']);
        if (nums)
            showItem($('#is_available_locs'));
        else
            hideItem($('#is_available_locs'));
        $btn_conf.attr('disabled', loc_to === '' && !is_request);
        checkType();
    });

    $('#location_to').change(function(e) {
        var nums = 0;
        loc_to = e.target.id;
        if (loc_to === 'to_location')
            nums += toggleLocs([0, 1], ['locPFto', 'locSTto', 'locProcessingTo', 'locTrolleyTo']);
        if (loc_to === 'to_PR')
            nums += toggleLocs([2], ['locPFto', 'locSTto', 'locProcessingTo', 'locTrolleyTo']);
        if (loc_to === 'to_trolley')
            nums += toggleLocs([3], ['locPFto', 'locSTto', 'locProcessingTo', 'locTrolleyTo']);
        if (nums)
            showItem($('#is_available_to'));
        else
            hideItem($('#is_available_to'));
        $btn_conf.attr('disabled', loc_from === '' && !is_request);
        checkType();
    });

    function checkType() {
        $('#type option[value=0]').hide();
        $('#type').removeAttr('disabled', 'disabled');
        if ($('#type').val() == 0)
            $('#type').val('1');
        if (loc_from === 'from_PR' || loc_to === 'to_PR') {
            $('#type option[value=0]').show();
            $('#type').val('0').attr('disabled', 'disabled');
        }
    }

    var loc_from = '';
    var locs_from = ['locPFfrom_items', 'locSTfrom_items'];
    var loc_to = '';
    var locs_to = ['locPFto_items', 'locSTto_items'];
    var pallet_to = [];

    $btn_cancel.click(() => {
        $('#cancelMovementModalConfirm').modal('show');
        return false;
    });

    $('#btn-cancel-movement').click(() => {
        $('#cancelMovementModalConfirm').modal('hide');
        location = back || '/';
    });

    $('#btn-cancel-create-another-movement').click(() => {
        $('#createAnotherMovementModalConfirm').modal('hide');
        location = back || '/movement';
    });

    $('#available_locs').change(function(e) {
        changeSelected(locs_from, e.target.id);
    });

    $('#available_to').change(function(e) {
        changeSelected(locs_to, e.target.id);
    });

    $('#pallet_destination_btn').change(function(e) {
        var sels = [];
        var selected = false;
        switch (e.target.id)  {
            case 'palletDestinationLocs':
                sels = [0, 1];
                selected = $('#pallet_destinations').find(':selected').length > 0;
                break;
            case 'palletDestinationPA':
                sels = [2];
                selected = true;
                break;
            case 'palletDestinationDH':
                sels = [3];
                selected = true;
                break;
        }
        var nums = toggleLocs(sels, ['palletDestinationST', 'palletDestinationPF']);
        $('.destinations-container', $pallet).toggleClass('d-none', nums == 0);
        $btn_conf.attr('disabled', !selected);
        $btn_conf.toggleClass('d-none', !selected);
    });

    // container for all location selects
    $('#pallet_destinations').change(function(e) {
        // switch all other selects off
        changeSelected(pallet_to, e.target.id);
        $btn_conf.attr('disabled', false);
        showItem($btn_conf);
    });

    $btn_conf.click(() => {
        if (selected_location) {
            // pallet movement
            var to_loc_id = selected_destination ? selected_destination.id : 0;
            if (to_loc_id === 0) {
                var selected_loc_type = $('#pallet_destination_btn input:checked').attr('id');
                switch (selected_loc_type) {
                    case 'palletDestinationPA':
                        to_loc_id = 'PA';
                        break;
                    case 'palletDestinationDH':
                        to_loc_id = 'DH';
                        break;
                    case 'palletDestinationLocs':
                        to_loc_id = $('#pallet_destinations :checked').val();
                        break;
                }
            }
            if (to_loc_id === 0) {
                showError('add_sku_error', 'Select destination location', 3000);
                return;
            }
            location = '/movement/save_movement/?loc_from_id=' + selected_location.id +
                '&loc_to_id=' + to_loc_id + '&is_pallet=1';
            return;
        }
        var SKU_id = $('#sku_id').text().trim();
        var type_id = $('[name=type]').val();
        var type_location = $('[name=type_location]').val();
        let check_from = !is_request || type_location === 'from';
        let check_to = !is_request || type_location === 'to';
        if (SKU_id === '') {
            showError('add_sku_error', 'Select SKU for movement', 3000);
            return;
        }
        var qty = parseInt($('#qty').val());
        if (loc_from === '' && !is_request) {
            showError('add_sku_error', 'Select origin location', 3000);
            return;
        }
        var from_loc_id = 0;
        var from_loc_qty = 0;
        if (loc_from === 'from_location' && check_from) {
            var PF_sel = $('#locPFfrom_items').val();
            var ST_sel = $('#locSTfrom_items').val();
            if (PF_sel == null) {
                if (ST_sel == null) {
                    showError('add_sku_error', 'Select origin location', 3000);
                    return;
                } else {
                    from_loc_id = ST_sel;
                    from_loc_qty = $('#locSTfrom_items :selected');
                }
            } else {
                from_loc_id = PF_sel;
                from_loc_qty = $('#locPFfrom_items :selected');
            }
            from_loc_qty = getInt(from_loc_qty);
        }
        if (loc_from === 'from_PR' && check_from) {
            var PR_sel = $('#locPRfrom_items').val();
            if (PR_sel == null) {
                showError('add_sku_error', 'Select origin location', 3000);
                return;
            }
            from_loc_id = PR_sel;
            from_loc_qty = getInt($('#locPRfrom_items :selected'));
        }
        if (loc_from === 'from_PA' && check_from) {
            var PA_sel = $('#locPAfrom_items').val();
            if (PA_sel == null) {
                showError('add_sku_error', 'Select origin location', 3000);
                return;
            }
            from_loc_id = PA_sel;
            from_loc_qty = getInt($('#locPAfrom_items :selected'));
        }
        if (loc_from === 'from_DH' && check_from) {
            from_loc_id = 'DH';
            from_loc_qty = qty;
        }
        if (loc_from === 'from_cart' && check_from) {
            var CA_sel = $('#locCartfrom_items').val();
            if (CA_sel == null) {
                showError('add_sku_error', 'Select origin location', 3000);
                return;
            }
            from_loc_id = CA_sel;
            from_loc_qty = getInt($('#locCartfrom_items :selected'));
        }
        if (qty > from_loc_qty && check_from) {
            showError('add_sku_error', 'Quantity cannot be more than ' + from_loc_qty, 3000);
            return;
        }
        if (loc_to === '' && !is_request) {
            showError('add_sku_error', 'Select destination location', 3000);
            return;
        }
        var to_loc_id = 0;
        var to_loc_qty = 0;
        if (loc_to === 'to_location' && check_to) {
            var PF_sel = $('#locPFto_items').val();
            var ST_sel = $('#locSTto_items').val();
            if (PF_sel == null) {
                if (ST_sel == null) {
                    showError('add_sku_error', 'Select destination location', 3000);
                    return;
                } else {
                    to_loc_id = ST_sel;
                    to_loc_qty = $('#locSTto_items :selected');
                }
            } else {
                to_loc_id = PF_sel;
                to_loc_qty = $('#locPFto_items :selected');
            }
            to_loc_qty = getInt(to_loc_qty);
        }
        if (loc_to === 'to_PR' && check_to) {
            to_loc_id = $('#locProcessingto_items').val();
            to_loc_qty = qty;
        }
        if (loc_to === 'to_trolley' && check_to) {
            to_loc_id = $('#locTrolleyTo_items').val();
            to_loc_qty = qty;
        }
        if (loc_to === 'to_PA' && check_to) {
            to_loc_id = 'PA';
            to_loc_qty = qty;
        }
        if (loc_to === 'to_DH' && check_to) {
            to_loc_id = 'DH';
            to_loc_qty = qty;
        }
        if (loc_to === 'to_cart' && check_to) {
            to_loc_id = cart_id;
            to_loc_qty = qty;
        }
        if (qty > to_loc_qty && check_to) {
            showError('add_sku_error', 'Quantity cannot be more than ' + to_loc_qty, 3000);
            return;
        }
        if (from_loc_id === to_loc_id && !is_request) {
            showError('add_sku_error', 'Same origin and destination location', 3000);
            return;
        }
        location = '/movement/save_movement/?id=' + SKU_id +
            '&qty=' + qty +
            '&loc_from_id=' + from_loc_id +
            '&loc_to_id=' + to_loc_id +
            '&type=' + type_id +
            '&type_location=' + type_location +
            '&loc_from=' + loc_from +
            '&mode=' + mode;
    });

    function addDestination(destination)
    {
        destination = destination.trim();
        if (destination === '')
            return showError('dest_error', 'Please enter destination location', 3000);
        $.ajax({
            method: 'GET',
            async: false,
            url: '/locations/scan',
            data: {barcode: destination, include_skus: true, from: selected_location.id},
        }).done(function(data) {
            if (data.success)
                drawDestination(data.location, data.SKUs);
            else
                showError('dest_error', data.error, 3000);
        }).fail(function(xhr, status, error) {
            var message = (xhr.status == 404
                ? 'Location not found'
                : xhr.responseJSON.error) || error || 'Error';
            showError('dest_error', message, 3000);
        });
    }

    function drawPallet(location, SKUs, destinations)
    {
        if ($pallet.length == 0)
            return;
        if (selected_location && selected_location.id === location.id)
            return showError('add_sku_error', 'Location has already been scanned', 3000);
        hideItem($('#goods'));
        hideItem($('#is_available_locs'));
        hideItem($('#is_available_to'));
        hideItem($pallet);
        hideItem($('.destinations-container', $pallet));
        $btn_conf.attr('disabled', true);
        hideItem($btn_conf);
        selected_sku = '';
        $('.name', $pallet).text(location.name);
        let $table = $('.js-goods', $pallet),
            $tbody = $('tbody', $table);
        if (SKUs.length === 0) {
            $table.addClass('d-none');
            return showError('add_sku_error', 'No SKUs found', 3000);
        }
        selected_location = location;
        $table.removeClass('d-none');
        $tbody.empty();
        for (const SKU of SKUs) {
            $tbody.append(renderSKURow(SKU));
        }
        $('.destinations', $pallet).empty();
        $('#pallet_destination_btn').empty();
        pallet_to = [];
        if (destinations === 'scan') {
            $('.destination-scan', $pallet).removeClass('d-none');
        } else {
            if (Object.keys(destinations).length > 0) {
                addButton('pallet_destination_btn', 'palletDestinationLocs_name', 'palletDestinationLocs', 'fa-map-marker-alt', 'palletDestinationLocs_for', 'Location');
                for (const type in destinations) {
                    addSelect('pallet_destinations', `palletDestination${type}`, destinations[type].name, `palletDestination${type}Items`, destinations[type].locations);
                    pallet_to.push(`palletDestination${type}Items`);
                }
            }
            if (destinations.PA)
                addButton('pallet_destination_btn', 'palletDestinationPA_name', 'palletDestinationPA', 'fa-sign-in-alt', 'palletDestinationPA_for', 'PA zone')
            if (destinations.DH)
                addButton('pallet_destination_btn', 'palletDestinationDH_name', 'palletDestinationDH', 'fa-ghost', 'palletDestinationDH_for', 'DH')
        }
        $sku_input.attr('disabled', true);
        $scan.find('button').attr('disabled', true);
        hideItem($('#createFrm'));
        showItem($pallet);
        showItem($btn_back);
        showItem($footer);
        renderTitle();
    }

    function drawDestination(location, SKUs)
    {
        if (selected_destination && selected_destination.id === location.id)
            return showError('dest_error', 'Location has already been scanned', 3000);
        selected_destination = location;
        // hideItem($('.destination-scan', $pallet));
        hideItem($destination);
        $('.destination-name').text(location.name);
        let $table = $('.js-goods', $destination),
            $tbody = $('tbody', $table);
        $table.toggleClass('d-none', SKUs.length === 0);
        $tbody.empty();
        for (const SKU of SKUs)
            $tbody.append(renderSKURow(SKU));
        showItem($destination);
        $btn_conf.attr('disabled', false);
        showItem($btn_conf);
        showItem($footer);
        renderTitle();
    }

    function hidePallet()
    {
        if ($pallet.length == 0)
            return;
        hideItem($pallet);
        hideItem($('.destinations-container', $pallet));
        selected_location = null;
        $scan.find('button').attr('disabled', false);
        $sku_input.attr('disabled', false);
        showItem($('#createFrm'));
        hideDestination();
        hideItem($btn_back);
        renderTitle();
        hideItem($footer);
    }

    function hideDestination()
    {
        showItem($('.destination-scan', $pallet));
        $('.destination-name').text('');
        hideItem($destination);
        selected_destination = null;
        $btn_conf.attr('disabled', true);
        hideItem($btn_conf);
        renderTitle();
    }

    function renderTitle()
    {
        if (selected_destination && selected_location)
            $title.text('Movement ' + selected_location.name + ' -> ' + selected_destination.name);
        else if (selected_location)
            $title.text('Location ' + selected_location.name);
        else
            $title.text(origin_title);
    }

    function changeTypeLocations(type)
    {
        if (!is_request) {
            showItem($panel_src);
            showItem($panel_dest);
        } else {
            $('#btn_conf').attr('disabled', true);
            if (type === 'from') {
                clearActive('location_from');
                hideItem($('#is_available_locs'));
                showItem($panel_src);
                hideItem($panel_dest);
            } else {
                clearActive('location_to');
                hideItem($('#is_available_to'));
                hideItem($panel_src);
                showItem($panel_dest);
            }
        }
    }

    function clearActive(element)
    {
        element = document.getElementById(element);
        if (!element)
            return;
        let buttons = element.childNodes;
        for (const button of buttons)
            if (button.nodeName === 'LABEL' && button.classList.contains('active'))
                button.classList.remove('active');
    }

    function hideItem($element)
    {
        if (!$element)
            return;
        $element.addClass('d-none');
    }

    function showItem($element)
    {
        if (!$element)
            return;
        $element.removeClass('d-none');
    }
});