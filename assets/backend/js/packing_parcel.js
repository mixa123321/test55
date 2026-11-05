function before_unload_event(e) {
    // Cancel the event
    e.preventDefault();
    // Chrome requires returnValue to be set
    e.returnValue = '';
}

window.addEventListener('beforeunload', before_unload_event);
$(function () {
    let $scan_input = $('#scan_input');
    let $btn_conf = $('.js-btn-conf');
    let $boxes_div = $('#boxes_div');
    let scanned_barcode_qty = {};
    let serial_numbers = {};
    let serial_numbers_boxes = {};
    let add_SKU_after_serial_number = false;
    let need_to_add_serial_number = null;
    let disable_add_SKU_after_serial_number = {};
    let boxes = [];
    let active_box_index = -1;
    let current_focused_qty = 0;

    global_sku_data.forEach(sku => scanned_barcode_qty[sku.id] = 0);
    const box_SKU_template = SKU => `<div class="row mb-3 pb-3 border-bottom">
                                            <div class="col-8">
                                                <div class="row">
                                                    <div class="col-8">
                                                        <span>${html_escape(SKU.name)}</span>
                                                        <br/>
                                                        <span><strong>${html_escape(SKU.barcode)}</strong></span>
                                                    </div>
                                                    <div class="col-4">
                                                        <input type="text" class="serial-number serial-number-${+SKU.id} form-control w-100  ${SKU.serial_number === 1 ? '' : 'd-none'}" data-sku-id="${+SKU.id}" placeholder="Enter serial number">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-2">
                                                <input type="text" 
                                                       class="form-control form-control-sm js-${+SKU.id}-qty js-SKU-qty"
                                                       value="1" 
                                                       name="box[${active_box_index}][SKUs][${+SKU.id}]" 
                                                       data-sku="${+SKU.id}"
                                                       ${packing_mode === 's' ? 'disabled' : ''}
                                                       >
                                            </div>
                                            <div class="col-2">
                                                ${(() => {
                                                    if (packing_mode !== 's')
                                                        return '<button type="button" class="float-right btn btn-sm btn-danger js-item-box-remove pl-3 pr-3">X</button>';
                                                    return '';
                                                })()}
                                            </div>
                                        </div>`;

    /**
     SCAN SKU
     */
    $('#scan_btn').click(function () {
        const scanned_barcode = $scan_input.val().trim();
        $scan_input.val('');
        if (active_box_index === -1 || !(active_box_index in boxes)) {
            show_scan_error("Please select an active box first!");
            return;
        }

        let scanned_SKU = global_sku_data.find(sku => sku.barcode === scanned_barcode);
        if (scanned_SKU) {
            if (+scanned_SKU.qty === +scanned_barcode_qty[scanned_SKU.id]) {
                show_scan_error("Maximum amount of <strong><span>"
                    + html_escape(scanned_SKU.barcode) + "</span></strong> barcodes have already been scanned");
                return;
            }
            if (need_to_add_serial_number) {
                show_serial_number_error('Enter serial number');
                return;
            }
            scanned_barcode_qty[scanned_SKU.id]++;
            const $active_box = $('.order-box-active');
            $('.SKU-' + scanned_SKU.id).find('.js-scanned-qty').text(scanned_barcode_qty[scanned_SKU.id]);
            //find the active box and add a new row or +1 an existing item
            if (!(scanned_SKU.id in boxes[active_box_index])
                || (scanned_SKU.id in boxes[active_box_index] && +boxes[active_box_index][scanned_SKU.id] === 0)) {
                boxes[active_box_index][scanned_SKU.id] = 1;
                $active_box.find('.skus-area').append(box_SKU_template(scanned_SKU));
                $('.js-' + scanned_SKU.id + '-qty').keydown(function (e) {
                    let keyCode = e.keyCode;
                    if (keyCode !== 13 && keyCode !== 8 && keyCode !== 46 && (keyCode < 48 || keyCode > 57)) {
                        e.preventDefault();
                        return false;
                    }
                    if (e.keyCode === 13)
                        $scan_input.focus();
                });
                const $SKU = $('.js-SKU-qty');
                $SKU.focus(function () {
                    $btn_conf.attr('disabled', true);
                    current_focused_qty = $(this).val();
                });
                $SKU.tooltip({
                    "trigger": "focus",
                    "title": "Press Enter or lose focus to confirm"
                });
                $('.weight').tooltip({
                    "trigger": "focus",
                    "title": "Enter weight"
                });
                $('.serial-number').tooltip({
                    "trigger": "focus",
                    "title": "Enter serial number"
                });
            } else
                boxes[active_box_index][scanned_SKU.id]++;
            $active_box.find('.js-' + scanned_SKU.id + '-qty').val(boxes[active_box_index][scanned_SKU.id]);
            $active_box.find('input.weight').val('');
            boxes[active_box_index].weight = null;

            add_SKU_after_serial_number = true;
            if (scanned_SKU.serial_number) {
                need_to_add_serial_number = $active_box.find('.serial-number-' + scanned_SKU.id);
                need_to_add_serial_number.focus();
            }
            check_if_packing_finished();
        } else {
            $('#barcode_error').text(scanned_barcode);
            show_scan_error("Barcode <strong><span>" + html_escape(scanned_barcode) + "</span></strong> is not a part of this order");
        }
    });

    $(document).on('click', '.js-item-box-remove', function () {
        let context_to_remove = $(this).closest('.row').find('.js-SKU-qty');
        context_to_remove.val(0);
        let parent_box_id = +$(this).closest('.order-box').data('box-id');
        if (parent_box_id in boxes && +context_to_remove.data('sku') in boxes[parent_box_id]) {
            delete boxes[parent_box_id][+context_to_remove.data('sku')];
            context_to_remove.tooltip('hide');
            context_to_remove.closest('.row').remove();
            const w_input = $boxes_div.find(`[data-box-id="${parent_box_id}"]`).find('input.weight');
            if (w_input.length > 0) {
                w_input[0].value = '';
                boxes[parent_box_id].weight = null;
            }
            confirm_qty_change(context_to_remove);
        }
    });

    $scan_input.keydown(function (e) {
        if (e.keyCode === 13)
            $('#scan_btn').trigger('click');
    });

    function setDimensions(sel_package_id, index, dim = null) {
        const p = packages.find(p => +p.id === sel_package_id);
        if (!dim) {
            document.getElementById(`length_${index}`).value = p.length;
            document.getElementById(`width_${index}`).value = p.width;
            document.getElementById(`height_${index}`).value = p.height;
            document.getElementById(`max_weight_${index}`).textContent = p.weight;
            boxes[index].length = parseFloat(p.length);
            boxes[index].width = parseFloat(p.width);
            boxes[index].height = parseFloat(p.height);
            boxes[index].max_weight = parseFloat(p.weight);
        } else {
            const input = document.getElementById(dim + '_' + index);
            if (input && p[dim] !== undefined) {
                input.value = p[dim];
                boxes[index][dim] = parseFloat(p[dim]);
            }
        }
    }

    function deactivateAllBoxes() {
        $('.order-box').each(function () {
            $(this).removeClass('order-box-active');
        });
    }

    function activateBox(box) {
        deactivateAllBoxes();
        $(box).addClass('order-box-active');
        active_box_index = $(box).data('box-id');
    }

    /** ADD NEW BOX */
    $('#add_box_btn').click(function () {
        boxes.push({
            length: null,
            width: null,
            height: null,
            weight: null,
            max_weight: null,
        });
        deactivateAllBoxes();
        const index = boxes.length - 1;
        const name = "Box" + boxes.length;
        boxes[index].name = name;
        const template = `
            <div class="order-box order-box-hover order-box-active p-3 mb-3" data-box-id="${index}">
                <input type="hidden" class="package_name" name="box[${boxes.length - 1}][package_name]" value="${name}">
                <div class="order-box-header p-2">
                    <div class="row">
                        <div class="col-sm-6">
                            <h4 class="title">#${name}</h4>
                            <div class="form-check-inline">
                                <input type="checkbox" class="form-check-input" id="order_inserts_${index}"
                                       name="box[${index}][order_inserts]" ${order_inserts ? 'checked' : ''}>
                                <label for="order_inserts_${index}" class="mt-2">Order insert used</label>
                            </div>
                        </div>
                        <div class="col-sm-6 ${packing_mode === 's' ? 'd-none' : ''}">
                            <button type="button" class="float-right btn btn-danger btn-sm js-btn-remove-box">Remove box</button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-7">
                            <select class="form-control form-control-sm box-type-select"
                                    name="box[${index}][package_type_id]" id="package_type_id_${index}">
                                ${(packages => {
                                    return packages.reduce((temp, p) => {
                                        temp += `<option value="${+p.id}" ${p.is_default ? 'selected' : ''}>${html_escape(p.name)}</option>`;
                                        return temp;
                                    }, '')
                                })(packages)}
                            </select>
                            <div class="row mt-1">
                                <div class="col-4 form-group-element">
                                    <input type="number" 
                                           class="form-control form-control-sm package_dims" 
                                           id="length_${index}"
                                           name="box[${index}][length]" 
                                           min="0.01" step="0.01"
                                           style="padding-top: 1.2rem;">     
                                    <label for="length_${index}" style="top: 0.4rem;">Length (cm)</label>           
                                </div>
                                <div class="col-4 form-group-element">
                                    <input type="number" 
                                           class="form-control form-control-sm package_dims" 
                                           id="width_${index}"
                                           name="box[${index}][width]" 
                                           min="0.01" step="0.01"
                                           style="padding-top: 1.2rem;">
                                    <label for="width_${index}" style="top: 0.4rem;">Width (cm)</label>  
                                </div>
                                <div class="col-4 form-group-element">
                                    <input type="number" 
                                           class="form-control form-control-sm package_dims" 
                                           id="height_${index}"
                                           name="box[${index}][height]" 
                                           min="0.01" step="0.01"
                                           style="padding-top: 1.2rem;">
                                    <label for="height_${index}" style="top: 0.4rem;">Height (cm)</label>   
                                </div>
                            </div>
                        </div>
                        <div class="col-md-1"></div>
                        <div class="col-md-4">
                            <div class="row">
                                <div class="col-12 form-group-element">
                                    <input type="number"
                                           class="form-control w-100 weight"
                                           id="weight_${index}"
                                           placeholder="Enter weight"
                                           name="box[${index}][weight]"
                                           min="0"
                                           step="any">
                                </div>
                                <div class="col-12 form-group-element mt-1">
                                    <p class="text-center" style="font-size: 0.85rem; padding-top: 0.5rem; margin-bottom: 0;">Max weight <span id="max_weight_${index}" class="text-danger font-weight-bold"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="skus-area mt-3">
                </div>
            </div>`;
        $('#boxes_div').append(template);
        let sel_package_id;
        try {
            sel_package_id = +document.getElementById(`package_type_id_${index}`).value
        } catch (e) {
            sel_package_id = 0;
        }
        if (sel_package_id > 0)
            setDimensions(sel_package_id, index);
        active_box_index = (boxes.length - 1);
        if (packing_mode === 's') {
            $btn_conf.attr('disabled', true);
            const single_SKU = global_sku_data[0];
            $boxes_div.find('.skus-area').append(box_SKU_template(single_SKU));
            add_SKU_after_serial_number = true;
            if (single_SKU.serial_number && (serial_numbers[single_SKU['id']] === undefined || serial_numbers[single_SKU['id']].length === 0)) {
                $boxes_div.find('.serial-number-' + single_SKU['id']).focus();
            } else
                document.querySelector('.weight').focus();
            boxes[0][single_SKU.id] = 1;
        } else {
            check_if_packing_finished();
            $scan_input.focus();
        }
    }).trigger('click');

    function show_serial_number_error(text) {
        $('#serialNumberError').modal('show');
        $('#error_text_serial_number').text(text);
    }

    $('#serialNumberError').on('hidden.bs.modal', function (e) {
        if (packing_mode === 's')
            $('.serial-number').focus();
        else if (need_to_add_serial_number)
            need_to_add_serial_number.focus();
    });

    $(".serial-number").on('focusout', function (e) {
        if (packing_mode === 's' && !$(this).val()) {
            let SKU_id = $(this).data("sku-id");
            if (SKU_id && serial_numbers[SKU_id] !== undefined && serial_numbers[SKU_id][0] !== undefined)
                $(this).val(serial_numbers[SKU_id][0]);
        }
    });

    $(document).on('change', '.serial-number', function () {
        let serial_number = $.trim($(this).val());
        let SKU_id = $(this).data("sku-id");
        if (serial_numbers[SKU_id] === undefined)
            serial_numbers[SKU_id] = [];
        if (serial_number) {
            if (regexp_serial_number && !serial_number.match(regexp_serial_number)) {
                show_serial_number_error('Wrong serial number format');
                return;
            }
            let parent_box = $(this).closest('.order-box');
            let parent_box_id = +parent_box.data('box-id');

            if (serial_numbers_boxes[serial_number] !== undefined) {
                $(this).val('');
                show_serial_number_error('Duplicate serial numbers ' + serial_number);
                return;
            }

            let qty = global_sku_data.find(sku => +sku.id === +SKU_id).qty;
            if (+qty === serial_numbers[SKU_id].length) {
                add_SKU_after_serial_number = false;
                let last_key = serial_numbers[SKU_id].length - 1;
                delete serial_numbers_boxes[serial_numbers[SKU_id][last_key]];
                serial_numbers_boxes[serial_number] = parent_box_id;
                serial_numbers[SKU_id][last_key] = serial_number;
            } else if (disable_add_SKU_after_serial_number[SKU_id] !== undefined || add_SKU_after_serial_number) {
                add_SKU_after_serial_number = false;
                serial_numbers[SKU_id].push(serial_number);
                serial_numbers_boxes[serial_number] = parent_box_id;
                $('#show_serial_number_' + SKU_id).prop("disabled", false);
            } else {
                let last_key = serial_numbers[SKU_id].length - 1;
                delete serial_numbers_boxes[serial_numbers[SKU_id][last_key]];
                serial_numbers_boxes[serial_number] = parent_box_id;
                serial_numbers[SKU_id][last_key] = serial_number;
            }
            need_to_add_serial_number = null;

            $('#quantity_serial_numbers_' + SKU_id).html(serial_numbers[SKU_id].length);
            if (packing_mode !== 's')
                $(this).val('');
            $scan_input.focus();
            check_if_packing_finished();
        }
    });

    $('.show_serial_numbers').click(function () {
        let SKU_id = $(this).data("sku-id");

        let tbody_serial_numbers = $('#tbody_serial_numbers');
        tbody_serial_numbers.empty();
        for (const key in serial_numbers[SKU_id]) {
            let input = '<input type="text" class="serial-number-changes form-control w-100 mb-1"  data-key="' + +key + '" data-sku-id="' + +SKU_id + '" value="' + html_escape(serial_numbers[SKU_id][key]) + '" placeholder="Enter serial number">'
            tbody_serial_numbers.append('<div class="row"><div class="col-12 form-group-element">' + input + '</div></div>');
        }
        $('#serialNumbersModalInfo').modal('show');
    });

    $(document).on('change', '.serial-number-changes', function () {
        let serial_number = $.trim($(this).val());
        let SKU_id = $(this).data("sku-id");
        let key = $(this).data("key");
        if (serial_numbers_boxes[serial_number] !== undefined) {
            $('#serialNumbersModalInfo').modal('hide');
            show_serial_number_error('Duplicate serial numbers ' + serial_number);
            return;
        }
        if (serial_number)
            serial_numbers[SKU_id][key] = serial_number;
    });

    $(document).on('click', '.order-box', function () {
        if (this.classList.contains('js-btn-remove-box') || this.classList.contains('js-item-box-remove'))
            return;
        activateBox(this);
    });

    $(document).on('focus', '.order-box input', function () {
        const box = this.closest('.order-box');
        if (box)
            activateBox(box);
    });

    $(document).on('click', '.js-btn-remove-box', function () {
        let $parent_box = $(this).closest('.order-box');
        const parent_box_id = +$parent_box.data('box-id');
        $parent_box.find('.js-SKU-qty').each(function () {
            $(this).val(0);
            confirm_qty_change($(this));
        });
        $parent_box.remove();
        delete boxes[parent_box_id];
        if (active_box_index === parent_box_id)
            active_box_index = -1;
        check_if_packing_finished();
    });


    $('#scanError').on('hidden.bs.modal', function (e) {
        $scan_input.focus();
    });

    $('#weightError').on('hidden.bs.modal', function (e) {
        $('.order-box-active').find('input.weight').focus();
    });

    $btn_conf.click(function () {
        window.removeEventListener('beforeunload', before_unload_event);
        $btn_conf.attr('disabled', true);
        $btn_conf.find('.text').text('Processing...');

        for (const SKU_id in serial_numbers)
            for (const key in serial_numbers[SKU_id])
                $('#confirm_pack_form').append($('<input>', {type: 'hidden', class: 'serial_numbers_hidden', name: 'serial_numbers[' + SKU_id + '][]', value: serial_numbers[SKU_id][key]}));
        for (const serial_number in serial_numbers_boxes)
            $('#confirm_pack_form').append($('<input>', {type: 'hidden', class: 'serial_numbers_box_hidden', name: 'serial_numbers_box[' + serial_number + ']', value: serial_numbers_boxes[serial_number]}));

        $('#confirm_pack_form').submit();
    });

    function show_scan_error(text) {
        $('#scanError').modal('show');
        $('#error_text').html(text);
    }

    function show_weight_error(box_id, common_max_weight, max = true) {
        $('#weightError').modal('show');
        const message = max ? `Box ${box_id} weight exceeds the maximum of ${common_max_weight} kg!`
                            : `Box ${box_id} weight is below acceptable`;
        $('#weight-error_text').html(message);
    }

    function confirm_qty_change($context) {
        let found_SKU = global_sku_data.find(sku => +sku.id === +$context.data('sku'));
        let scanned_qty_in_box = parseInt($context.val()) || 0;
        let total = 0;
        $('.order-box').each(function () {
            total += +$(this).find('.js-' + found_SKU.id + '-qty').val() || 0;
        });
        if (found_SKU.qty < total) {
            show_scan_error("Exceeded the maximum amount of <strong><span>" + html_escape(found_SKU.barcode) + "</span></strong> barcodes that can be scanned");
            $context.val(current_focused_qty);
            return;
        }
        scanned_barcode_qty[found_SKU.id] = total;
        $('.SKU-' + found_SKU.id).find('.js-scanned-qty').text(scanned_barcode_qty[found_SKU.id]);
        check_if_packing_finished();
        if (scanned_qty_in_box === 0)
            try {
                $context.tooltip('hide');
                $context.closest('.row').remove()
            } catch (e) {
                show_scan_error('Error quantity')
            }
    }

    function has_items(index) {
        let has_items = false;
        if (boxes[index] !== undefined) {
            has_items = Object.keys(boxes[index]).reduce((res, key) => {
                if (+key > 0)
                    res = true;
                return res;
            }, false);
        }
        return has_items;
    }

    function get_common_max_weight(max_weight) {
        let common_max = Math.min(max_package_weight, max_weight);
        if (common_max === 0)
            common_max = Math.max(max_package_weight, max_weight);
        return common_max;
    }

    function check_max_weight(common_max_weight, w) {
        if (common_max_weight === 0)
            return true;
        return parseFloat(w) <= common_max_weight;
    }

    function check_weight() {
        let not_weighted = [];
        document.querySelectorAll('.order-box input.weight').forEach(w => {
            const box = w.closest('.order-box');
            const ident = +$(box).data('box-id');
            const common_max_weight = get_common_max_weight(boxes[ident].max_weight);
            if (common_max_weight !== 0 && w.value > common_max_weight) {
                w.value = '';
                show_weight_error(ident + 1, common_max_weight);
            }
            if (+w.value <= 0 && has_items(ident))
                not_weighted.push(ident);
            if (w.value !== '' && +w.value < 0.01) {
                show_weight_error(ident + 1, common_max_weight, false);
                w.value = 0.01;
            }
        });
        return not_weighted;
    }

    function check_if_packing_finished() {
        $btn_conf.attr('disabled', true);
        const setSuccess = (el, add = true) => {
            const el_classes = el.classList;
            const exist = el_classes.contains('alert-success');
            if (add) {
                if (!exist)
                    el_classes.add('alert-success');
            } else
                if (exist)
                    el_classes.remove('alert-success');
        };
        const global_SKU_list = document.getElementById('global-SKU-list');
        const scan_input = document.getElementById('scan_input');
        const scan_btn = document.getElementById('scan_btn');
        let check_items = true;

        let added_all_serial_numbers = true;
        for (let i = 0; i < global_sku_data.length; i++) {
            if (!global_sku_data[i].serial_number)
                continue;
            let qty_serial_numbers = serial_numbers[global_sku_data[i].id] === undefined ? 0 : serial_numbers[global_sku_data[i].id].length;
            added_all_serial_numbers = added_all_serial_numbers && +global_sku_data[i].qty === qty_serial_numbers;
        }

        if (packing_mode !== 's')
            for (let i = 0; i < global_sku_data.length; i++) {
                let SKU_el = global_SKU_list.querySelector('.SKU-' + global_sku_data[i].id);
                if (+scanned_barcode_qty[+global_sku_data[i].id] !== +global_sku_data[i].qty) {
                    scan_input.disabled = false;
                    scan_btn.disabled = false;
                    check_items = false;
                    setSuccess(SKU_el, false);
                } else
                    setSuccess(SKU_el);
            }

        if (check_items) {
            scan_input.disabled = true;
            scan_btn.disabled = true;
        }
        const not_weighted = check_weight();
        boxes.forEach((box, index, boxes) => {
            const box_el = $boxes_div.find(`[data-box-id="${index}"]`)[0];
            const w = parseFloat(box.weight);
            const common_max_weight = get_common_max_weight(box.max_weight);
            let i = 0;
            boxes.forEach(() => i++);
            if (i > 1) {
                if (!isNaN(w) && w > 0 && has_items(index) && check_max_weight(common_max_weight, w)) {
                    setSuccess(box_el);
                } else {
                    setSuccess(box_el, false);
                }
            } else {
                if (check_items && !isNaN(w) && w > 0 && check_max_weight(common_max_weight, w)) {
                    setSuccess(box_el);
                } else {
                    setSuccess(box_el, false);
                }
            }
        });

        if (added_all_serial_numbers && check_items && not_weighted.length > 0) {
            const input_weight = $boxes_div.find(`[data-box-id="${not_weighted[0]}"]`).find('input.weight');
            if (input_weight.length > 0)
                $(input_weight[0]).focus();
        }
        const result = added_all_serial_numbers && check_items && not_weighted.length === 0;
        $btn_conf.attr('disabled', !result);
        document.getElementById('confirm-dialog').style.display = result ? '' : 'none';
    }

    function actionChangeQtySKU(target) {
        const box_id = +$(target.closest('div.order-box')).data('box-id');
        const input_res = $boxes_div.find(`[data-box-id="${box_id}"]`).find('input.weight');
        if (input_res.length > 0) {
            input_res[0].value = '';
            boxes[box_id].weight = null;
        }
        if (+boxes[box_id][+$(target).data('sku')] !== +target.value)
            disable_add_SKU_after_serial_number[+$(target).data('sku')] = true;
        boxes[box_id][+$(target).data('sku')] = +target.value;
        confirm_qty_change($(target));
    }

    document.getElementById('boxes_div').addEventListener('focusout', ({target}) => {
        if (target.classList.contains('js-SKU-qty'))
            actionChangeQtySKU(target);
    });

    document.getElementById('boxes_div').addEventListener('keyup', (e) => {
        if (e.target.classList.contains('serial-number') && e.keyCode === 13) {
            if (e.target.value)
                check_if_packing_finished();
        } else if (e.target.classList.contains('weight') && e.keyCode === 13) {
            const value = parseFloat(e.target.value);
            if (packing_mode === 's' && !$('.serial-number').val() && !$('.serial-number')[0].classList.contains('d-none')) {
                show_serial_number_error('Please enter a serial number');
                $('.order-box-active').find('input.weight').val('');
                return;
            }
            if (!isNaN(value) && value > 0) {
                $(e.target).blur();
                $btn_conf.attr('disabled', true);
                check_if_packing_finished();
            }
        } else if (e.target.classList.contains('js-SKU-qty') && e.keyCode === 13)
            actionChangeQtySKU(e.target)
    });

    document.getElementById('boxes_div').addEventListener('change', ({target}) => {
        if (target.classList.contains('js-SKU-qty')) {
            const box_id = +$(target.closest('div.order-box')).data('box-id');
            const input_res = $boxes_div.find(`[data-box-id="${box_id}"]`).find('input.weight');
            if (input_res.length > 0) {
                input_res[0].value = '';
                boxes[box_id].weight = null;
            }
        } else if (target.classList.contains('box-type-select')) {
            $scan_input.focus();
            const sel_id = +target.value;
            const sel_index = +target.id.replace('package_type_id_', '');
            if (sel_id > 0 && sel_index >= 0)
                setDimensions(sel_id, sel_index)
        } else if (target.classList.contains('package_dims')) {
            const dim_index = target.id.split('_');
            const dim = dim_index[0];
            const index = dim_index[1];
            const value =  parseFloat(target.value);
            if (isNaN(value) || value <= 0) {
                const sel_package = document.getElementById('package_type_id_' + index);
                if (sel_package)
                    setDimensions(+sel_package.value, index, dim);
            } else {
                if (boxes[index] !== undefined && boxes[index][dim] !== undefined)
                    boxes[index][dim] = value;
            }
        } else if (target.classList.contains('weight')) {
            $btn_conf.attr('disabled', true);
            const index = +target.id.replace('weight_', '');
            let value = parseFloat(target.value);
            if (isNaN(value) || value <= 0) {
                value = null;
                target.value = value;
            }
            if (boxes[index] !== undefined)
                boxes[index].weight = value;
            check_if_packing_finished();
        }
    });
});
