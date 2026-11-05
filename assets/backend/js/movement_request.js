$(function () {
    let $btn_conf = $('#btn_create');

    $('.js-btn-cancel').click(function () {
        $('#movement_id').val($(this).data('id'));
        $('#cancelMovementModalConfirm').modal('show');
    });

    function getMovement(id)
    {
        hideItem($('#panel_src'));
        hideItem($('#panel_dest'));
        hideItem($('#err_create'));
        $.ajax({
            url: "/movement/get_movement_data",
            method: "POST",
            data: { id: id },
            dataType: 'json',
            async: false,
            success: function (json) {
                let photo = json.data.photo;
                if (photo !== null && photo !== '') {
                    $('#sku_photo').attr('src', photo);
                    showItem($('#sku_is_photo'));
                } else {
                    $('#sku_photo').removeAttr('src');
                    hideItem($('#sku_is_photo'));
                }
                $('#sku_name').text(json.data.name);
                $('#sku_barcode').html(convertBarcodeWithPrimaryBarcode(json.data.barcode));
                $('#sku_id').html(json.data.SKU_id);
                let quantity = Math.min(json.data.quantity, json.data.max_quantity);
                $('#sku_qty').prop('max', quantity);
                $('#sku_max_quantity').html(quantity);
                $('#sku_qty').val(quantity);
                $('#sku_location_id').val(json.data.location_id);
                $('#sku_movement_id').val(json.data.id);
                $('#sku_type_location').html(json.data.type_location);
                $('#sku_location').text(json.data.loc_name);
                let is_from = json.data.type_location === 'from';
                let type_id = json.data.type_id;
                let is_exist = false;
                if (is_from) {
                    $('#location_to').children().remove();
                    $('#available_to').children().remove();
                    if (isObject(json.locs_to.PF) || isObject(json.locs_to.ST)) {
                        is_exist = true;
                        addButton('location_to', 'locLocto', 'to_location', 'fa-map-marker-alt', 'loc_to_name', 'Location')
                        if (isObject(json.locs_to.PF))
                            addSelect('available_to', 'locPFto', 'Pick Face', 'locPFto_items', json.locs_to.PF);
                        if (isObject(json.locs_to.ST))
                            addSelect('available_to', 'locSTto', 'Storage', 'locSTto_items', json.locs_to.ST);
                    }
                    if (type_id !== 'PA') {
                        is_exist = true;
                        addButton('location_to', 'locPAto', 'to_PA', 'fa-sign-in-alt', 'loc_to_PA', 'PA zone')
                    }
                    if (isObject(json.locs_processing)) {
                        is_exist = true;
                        addButton('location_to', 'locPrTo', 'to_PR', 'fa-sign-in-alt', 'loc_to_PR', 'Processing location')
                        addSelectProcessing('available_to', 'locProcessingTo', 'Processing locations', 'locPRto_items', json.locs_processing);
                    }
                    if (type_id !== 'DH') {
                        is_exist = true;
                        addButton('location_to', 'locDHto', 'to_DH', 'fa-ghost', 'loc_to_DH', 'DH')
                    }
                    if (isObject(json.locs_trolleys)) {
                        is_exist = true;
                        addButton('location_to', 'locCartto', 'to_trolley', 'fa-shopping-cart', 'loc_to_trolley', 'Trolley')
                        addSelectProcessing('available_to', 'locTrolleyTo', 'Trolleys', 'locTrolleyTo_items', json.locs_trolleys);
                    }
                } else {
                    $('#location_from').children().remove();
                    $('#available_locs').children().remove();
                    if (isObject(json.locs.PF) || isObject(json.locs.ST)) {
                        is_exist = true;
                        addButton('location_from', 'locLocFrom', 'from_location', 'fa-map-marker-alt', 'loc_from_name', 'Location');
                        if (isObject(json.locs.PF))
                            addSelect('available_locs', 'locPFfrom', 'Pick Face', 'locPFfrom_items', json.locs.PF);
                        if (isObject(json.locs.ST))
                            addSelect('available_locs', 'locSTfrom', 'Storage', 'locSTfrom_items', json.locs.ST);
                    }
                    if (type_id !== 'PA' && isObject(json.locs.PA)) {
                        is_exist = true;
                        addButton('location_from', 'locPAFrom', 'from_PA', 'fa-sign-in-alt', 'loc_from_PA', 'PA zone');
                        addSelect('available_locs', 'locPAfrom', 'Putaway', 'locPAfrom_items', json.locs.PA);
                    }
                    if (isObject(json.locs.PR)) {
                        is_exist = true;
                        addButton('location_from', 'locPRFrom', 'from_PR', 'fa-sign-in-alt', 'loc_from_PR', 'Processing location');
                        addSelect('available_locs', 'locPRfrom', 'Processing location', 'locPRfrom_items', json.locs.PR);
                    }
                    if (type_id !== 'DH') {
                        is_exist = true;
                        addButton('location_from', 'locDHFrom', 'from_DH', 'fa-ghost', 'loc_from_DH', 'DH');
                    }
                    if (isObject(json.locs.CA) || isObject(json.locs.US)) {
                        is_exist = true;
                        var CA_locations = isObject(json.locs.CA) ? json.locs.CA : {};
                        var US_locations = isObject(json.locs.US) ? json.locs.US : {};
                        var CA_US_locations = Object.assign({}, CA_locations, US_locations)
                        addButton('location_from', 'locCartFrom', 'from_cart', 'fa-shopping-cart', 'loc_from_cart', 'Trolley');
                        addSelect('available_locs', 'locCartfrom', 'Trolley', 'locCartfrom_items', CA_US_locations);
                    }
                }
                if (is_exist)
                    showItem($('#' + (is_from ? 'panel_dest' : 'panel_src')));
                else
                    showError('err_create', 'Movement cannot be created - no available locations', 0);
            },
            error: function (json) {
                showError('err_create', "Database error", 3000);
            }
        });
        hideItem($('#is_available_locs'));
        hideItem($('#is_available_to'));
        $btn_conf.attr('disabled', true);
        loc_from = loc_to = '';
    }

    function getHistories(id)
    {
        hideItem($('#history_request'));
        hideItem($('#err_history'));
        $.ajax({
            url: "/movement/get_history_data",
            method: "POST",
            data: { id: id },
            dataType: 'json',
            async: false,
            success: function (json) {
                if (json.error)
                    showError('err_history', json.error, 0);
                else {
                    $('#history_request').find('tbody').html(json.success.reduce((t, history_data) => {
                        quantity = history_data.quantity ? `Create movement for ${+history_data.quantity} unit(s)` : '';
                        t += `<tr>
                                  <td>${html_escape(history_data.login)}</td>
                                  <td>${html_escape(history_data.event)}</td>
                                  <td>${html_escape(history_data.event_date)}</td>
                                  <td>${quantity}</td>
                              </tr>`;
                        return t;
                    }, ''));
                    showItem($('#history_request'));
                }
            },
            error: function (json) {
                showError('err_history', "Database error", 0);
            }
        });
        hideItem($('#is_available_locs'));
        hideItem($('#is_available_to'));
        $btn_conf.attr('disabled', true);
        loc_from = loc_to = '';
    }

    let loc_from = '';
    var locs_from = ['locPFfrom_items', 'locSTfrom_items'];
    let loc_to = '';
    var locs_to = ['locPFto_items', 'locSTto_items'];

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
        $btn_conf.attr('disabled', false);
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
        $btn_conf.attr('disabled', false);
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

    $('.js-btn-create').click(function () {
        let movement_id = $(this).data('id');
        $('#movement_id').val(movement_id);
        getMovement(movement_id);
        $('#createMovementModalConfirm').modal('show');
    });

    $('.js-btn-history').click(function () {
        let movement_id = $(this).data('id');
        getHistories(movement_id);
        $('#historyMovementModalInfo').modal('show');
    });

    $btn_conf.click(() => {
        let location_id = $('#sku_location_id').val();
        let qty = parseInt($('#sku_qty').val());
        let type_location = $('#sku_type_location').text();
        let loc_id = 0, loc_qty = 0;
        switch (type_location) {
            case 'from':
                if (loc_to === 'to_location') {
                    let PF_sel = $('#locPFto_items').val(), ST_sel = $('#locSTto_items').val();
                    if (PF_sel == null) {
                        if (ST_sel == null) {
                            showError('err_create', 'Select destination location', 3000);
                            return;
                        } else {
                            loc_id = ST_sel;
                            loc_qty = $('#locSTto_items :selected');
                        }
                    } else {
                        loc_id = PF_sel;
                        loc_qty = $('#locPFto_items :selected');
                    }
                    loc_qty = getInt(loc_qty);
                }
                if (loc_to === 'to_PR') {
                    let PR_sel = $('#locPRto_items').val();
                    if (PR_sel == null) {
                        showError('err_create', 'Select destination location', 3000);
                        return;
                    }
                    loc_id = PR_sel;
                    loc_qty = qty;
                }
                if (loc_to === 'to_PA') {
                    loc_id = 'PA';
                    loc_qty = qty;
                }
                if (loc_to === 'to_DH') {
                    loc_id = 'DH';
                    loc_qty = qty;
                }
                if (loc_to === 'to_trolley') {
                    let CA_sel = $('#locTrolleyTo_items').val();
                    if (CA_sel == null) {
                        showError('err_create', 'Select destination location', 3000);
                        return;
                    }
                    loc_id = CA_sel;
                    loc_qty = qty;
                }
                if (qty > loc_qty) {
                    showError('err_create', 'Quantity cannot be more than ' + loc_qty, 3000);
                    return;
                }
                break;
            case 'to':
                if (loc_from === 'from_location') {
                    let PF_sel = $('#locPFfrom_items').val(), ST_sel = $('#locSTfrom_items').val();
                    if (PF_sel == null) {
                        if (ST_sel == null) {
                            showError('err_create', 'Select origin location', 3000);
                            return false;
                        } else {
                            loc_id = ST_sel;
                            loc_qty = $('#locSTfrom_items :selected');
                        }
                    } else {
                        loc_id = PF_sel;
                        loc_qty = $('#locPFfrom_items :selected');
                    }
                    loc_qty = getInt(loc_qty);
                }
                if (loc_from === 'from_PR') {
                    let PR_sel = $('#locPRfrom_items').val();
                    if (PR_sel == null) {
                        showError('err_create', 'Select origin location', 3000);
                        return;
                    }
                    loc_id = PR_sel;
                    loc_qty = getInt($('#locPRfrom_items :selected'));
                }
                if (loc_from === 'from_PA') {
                    let PA_sel = $('#locPAfrom_items').val();
                    if (PA_sel == null) {
                        showError('err_create', 'Select origin location', 3000);
                        return;
                    }
                    loc_id = PA_sel;
                    loc_qty = getInt($('#locPAfrom_items :selected'));
                }
                if (loc_from === 'from_DH') {
                    loc_id = 'DH';
                    loc_qty = qty;
                }
                if (loc_from === 'from_cart') {
                    let CA_sel = $('#locCartfrom_items').val();
                    if (CA_sel == null) {
                        showError('err_create', 'Select origin location', 3000);
                        return;
                    }
                    loc_id = CA_sel;
                    loc_qty = getInt($('#locCartfrom_items :selected'));
                }
                if (qty > loc_qty) {
                    showError('err_create', 'Quantity cannot be more than ' + loc_qty, 3000);
                    return;
                }
                break;
        }
        if (location_id === loc_id) {
            showError('err_create', 'Same origin and destination location', 3000);
            return;
        }
        $.ajax({
            url: "/movement/create_from_request",
            method: "POST",
            data: { id: $('#sku_movement_id').val(), qty: qty, loc_id: loc_id, mode: mode},
            dataType: 'json',
            async: false,
            success: function (json) {
                if (json.error)
                    showError('err_create', json.error, 3000);
                else
                    location = json.success;
            },
            error: function (json) {
                showError('err_create', "Database error", 3000);
            }
        });
    });

    $('#available_locs').change(function(e) {
        changeSelected(locs_from, e.target.id);
    });

    $('#available_to').change(function(e) {
        changeSelected(locs_to, e.target.id);
    });
});