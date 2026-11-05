$(document).ready(function () {
    class PackingParcelTemplates {
        constructor(order_inserts, packages) {
            this.order_inserts = order_inserts;
            this.packages = packages;
        }
        boxTemplate(box_id, packing_mode, box) {
            let name = box.name;
            let package_options = '';
            for (const p of this.packages)
                package_options += `<option value="${+p.id}" ${p.is_default ? 'selected' : ''}>${html_escape(p.name)}</option>`;
            let order_inserts = this.order_inserts;
            let length = box.length ? box.length : 0.01;
            let width = box.width ? box.width : 0.01;
            let height = box.height ? box.height : 0.01;
            let weight = box.weight ? box.weight : '';
            let max_weight = box.max_weight;
            let all_weight = box.all_weight;
            return `
            <div class="order-box order-box-hover p-3 mb-3" data-box-id="${box_id}">
                <input type="hidden" class="package_name" name="box[${box_id}][package_name]" value="${html_escape(name)}">
                <div class="order-box-header p-2">
                    <div class="row">
                        <div class="col-sm-6">
                            <h4 class="title">${html_escape(name)}</h4>
                            <div class="form-check-inline">
                                <input type="checkbox" class="form-check-input" id="order_inserts_${box_id}"
                                       name="box[${box_id}][order_inserts]" ${order_inserts ? 'checked' : ''}>
                                <label for="order_inserts_${box_id}" class="mt-2">Order insert used</label>
                            </div>
                        </div>
                        <div class="col-sm-6 ${packing_mode === 's' ? 'd-none' : ''}">
                            <div class="row">
                                <div class="col-sm-2"></div>
                                <div class="col-sm-5">
                                    <button type="button" class="float-right btn btn-primary btn-sm js-btn-copy-box btn-block">Copy box</button>
                                </div>
                                <div class="col-sm-5">
                                    <button type="button" class="float-right btn btn-danger btn-sm js-btn-remove-box btn-block" >Remove box</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-7">
                            <select class="form-control form-control-sm box-type-select" name="box[${box_id}][package_type_id]" id="package_type_id_${box_id}">
                                ${package_options}
                            </select>
                            <div class="row mt-1">
                                <div class="col-4 form-group-element">
                                    <input type="number"
                                           class="form-control form-control-sm package_dims_length"
                                           id="length_${box_id}"
                                           name="box[${box_id}][length]"
                                           value="${length}"
                                           min="0.01" step="0.01"
                                           style="padding-top: 1.2rem;">
                                    <label for="length_${box_id}" style="top: 0.4rem;">Length (cm)</label>
                                </div>
                                <div class="col-4 form-group-element">
                                    <input type="number"
                                           class="form-control form-control-sm package_dims_width"
                                           id="width_${box_id}"
                                           name="box[${box_id}][width]"
                                           value="${width}"
                                           min="0.01" step="0.01"
                                           style="padding-top: 1.2rem;">
                                    <label for="width_${box_id}" style="top: 0.4rem;">Width (cm)</label>
                                </div>
                                <div class="col-4 form-group-element">
                                    <input type="number"
                                           class="form-control form-control-sm package_dims_height"
                                           id="height_${box_id}"
                                           name="box[${box_id}][height]"
                                           value="${height}"
                                           min="0.01" step="0.01"
                                           style="padding-top: 1.2rem;">
                                    <label for="height_${box_id}" style="top: 0.4rem;">Height (cm)</label>
                                </div>
                            </div>
                                                    </div>
                        <div class="col-md-1"></div>
                        <div class="col-md-4">
                            <div class="row">
                                <div class="col-12 form-group-element">
                                    <input type="number"
                                           class="form-control w-100 weight"
                                           id="weight_${box_id}"
                                           placeholder="Enter weight"
                                           name="box[${box_id}][weight]"
                                           value="${weight}"
                                           min="0"
                                           step="any">
                                </div>
                                <div class="col-12 form-group-element mt-1">
                                    <div class="text-center pt-0" style="font-size: 0.85rem; padding-top: 0.5rem; margin-bottom: 0;">Max weight <span id="max_weight_${box_id}" class="text-danger font-weight-bold">${max_weight}</span></div>
                                    <div class="text-center pt-0" style="font-size: 0.85rem; padding-top: 0.5rem; margin-bottom: 0;">Expected weight <span id="all_weight_${box_id}" class="text-danger font-weight-bold">${all_weight}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="skus-area mt-3">
                </div>
            </div>`;
        }
        boxSKUTemplate(box_id, SKU, packing_mode) {
            let button_close = packing_mode === 's' ? '' : '<button type="button" class="float-right btn btn-sm btn-danger js-item-box-remove pl-3 pr-3">X</button>';
            let disabled_qty = packing_mode === 's' ? 'disabled' : '';
            let hide_serial_number = SKU.serial_number === 1 ? '' : 'd-none';
            return `<div class="row mb-3 pb-3 border-bottom">
                    <div class="col-8">
                        <div class="row">
                            <div class="col-2">
                                <img class="img-thumbnail" style="max-height: 70px;" src="${html_escape(SKU.photo)}"/>                           
                            </div>
                            <div class="col-6">
                                <span>${html_escape(SKU.name)}</span>
                                <br/>
                                <span><strong>${html_escape(SKU.barcode)}</strong></span>                                            
                            </div>
                            <div class="col-4">
                                <input type="text" class="serial-number serial-number-${+SKU.id} form-control w-100  ${hide_serial_number}" data-sku-id="${+SKU.id}" placeholder="Enter serial number">
                            </div>
                        </div>
                    </div>
                    <div class="col-2">
                        <input type="text"
                               class="form-control form-control-sm js-${+SKU.id}-qty js-SKU-qty"
                               value="1"
                               name="box[${box_id}][SKUs][${+SKU.id}]"
                               data-sku="${+SKU.id}"
                               ${disabled_qty}
                               >
                    </div>
                    <div class="col-2">
                        ${button_close}
                    </div>
                </div>`;
        }
    }

    class PackingParcelView {
        constructor(templates, packing_mode, packing_parcel_main_model) {
            this.templates = templates;
            this.packing_mode = packing_mode;
            this.packing_parcel_main_model = packing_parcel_main_model;
            this.boxes_div = $('#boxes_div');
            this.btn_conf = $('.js-btn-conf');
            this.scan_input = $('#scan_input');
            this.scan_btn = $('#scan_btn');
            this.global_SKU_list = $('#global-SKU-list');
            this.add_box_btn = $('#add_box_btn');
            this.show_serial_numbers = $('.show_serial_numbers');
            this.tbody_serial_numbers = $('#tbody_serial_numbers');
            this.prefer_pack_all_focus = false;
            
            // Initialize parcel category modal if selector is available
            if (typeof parcelCategoryModalSelector !== 'undefined') {
                this.initParcelCategoryModal(parcelCategoryModalSelector);
            }
        }
        
        initParcelCategoryModal(selector) {
            // Store the selector for later use
            this.parcelCategoryModalSelector = selector;
            
            const self = this;
            
            // Listen for parcelCategorySelected event
            if (typeof selector === 'string' && $(selector).length > 0) {
                $(selector).on('parcelCategorySelected', function(e, packageCategories) {
                    const $confirmForm = $('#confirm_pack_form');
                    
                    if (packageCategories && typeof packageCategories === 'object') {
                        // Clear any existing category inputs from previous selections
                        $confirmForm.find('input[name^="box"][name$="[category]"]').remove();
                        
                        // Add category as a property of each specific box
                        Object.keys(packageCategories).forEach(function(packageId) {
                            const categoryValue = packageCategories[packageId];
                            
                            $confirmForm.append($('<input>').attr({
                                type: 'hidden',
                                name: 'box[' + packageId + '][category]',
                                value: categoryValue
                            }));
                        });
                    }
                    
                    // Proceed with submission
                    self.btn_conf.attr('disabled', true);
                    self.btn_conf.find('.text').text('Processing...');
                    
                    setTimeout(function() {
                        self.packing_parcel_main_model.fireBeforeSubmit();
                    }, 300);
                });
            }
        }
        
        addEvents() {
            let self = this;
            this.scan_btn.click(function () {
                const scanned_barcode = self.scan_input.val().trim();
                self.scan_input.val('');
                self.prefer_pack_all_focus = false;
                self.packing_parcel_main_model.fireSetBarcode(scanned_barcode);
            });
            this.scan_input.keydown(function (e) {
                if (e.keyCode === 13) {
                    self.prefer_pack_all_focus = false;
                    self.scan_btn.trigger('click');
                }
            });
            this.add_box_btn.click(function () {
                self.packing_parcel_main_model.fireAddBox();
            });
            this.boxes_div.on('click', '.order-box', function (e) {
                if (e.target.classList.contains('js-btn-remove-box') || e.target.classList.contains('js-item-box-remove'))
                    return;
                let box_id = +$(this).closest('.order-box').data('box-id');
                self.packing_parcel_main_model.fireSetActivateBox(box_id);
            });
            this.boxes_div.on('change', '.serial-number', function () {
                let serial_number = $.trim($(this).val());
                let box_id = +$(this).closest('.order-box').data('box-id');
                let SKU_id = $(this).data("sku-id");
                if (self.packing_mode !== 's')
                    $(this).val('');
                if (self.packing_mode === 's' && !serial_number) {
                    self.packing_parcel_main_model.fireClearSerialNumber(box_id, SKU_id);
                    return;
                }
                self.packing_parcel_main_model.fireSetSerialNumber(box_id, serial_number, SKU_id);
            });
            this.boxes_div.on('click', '.js-item-box-remove', function () {
                let context_to_remove = $(this).closest('.row').find('.js-SKU-qty');
                let SKU_id = +context_to_remove.data('sku');
                let box_id = +$(this).closest('.order-box').data('box-id');
                self.packing_parcel_main_model.fireRemoveSKUFromBox(box_id, SKU_id);
            });
            this.boxes_div.on('click', '.js-btn-remove-box', function () {
                let parent_box = $(this).closest('.order-box');
                const box_id = +parent_box.data('box-id');
                self.packing_parcel_main_model.fireRemoveBox(box_id);
            });
            this.boxes_div.on('click', '.js-btn-copy-box', function () {
                let parent_box = $(this).closest('.order-box');
                const box_id = +parent_box.data('box-id');
                self.packing_parcel_main_model.fireCopyBox(box_id);
            });
            this.boxes_div.on('change', '.js-SKU-qty', function () {
                const box_id = +$(this.closest('div.order-box')).data('box-id');
                let SKU_id = +$(this).data('sku');
                self.packing_parcel_main_model.fireChangeSKUQty(box_id, SKU_id, +this.value);
            });
            this.boxes_div.on('keyup', '.js-SKU-qty', function (e) {
                if (e.keyCode === 13)
                    $(this).trigger('change');
            });
            this.boxes_div.on('change', '.weight', function () {
                const box_id = +$(this.closest('div.order-box')).data('box-id');
                let value = parseFloat(this.value);
                value = value ? value : 0;
                self.packing_parcel_main_model.fireChangeWeight(box_id, value);
            });
            this.boxes_div.on('change', '.box-type-select', function () {
                const package_type_id = +this.value;
                const box_id = +$(this.closest('div.order-box')).data('box-id');
                self.packing_parcel_main_model.fireChangePackageType(box_id, package_type_id);
            });
            this.boxes_div.on('change', '.package_dims_length', function () {
                const length = +this.value;
                const box_id = +$(this.closest('div.order-box')).data('box-id');
                self.packing_parcel_main_model.fireChangeLength(box_id, length);
            });
            this.boxes_div.on('change', '.package_dims_width', function () {
                const width = +this.value;
                const box_id = +$(this.closest('div.order-box')).data('box-id');
                self.packing_parcel_main_model.fireChangeWidth(box_id, width);
            });
            this.boxes_div.on('change', '.package_dims_height', function () {
                const height = +this.value;
                const box_id = +$(this.closest('div.order-box')).data('box-id');
                self.packing_parcel_main_model.fireChangeHeight(box_id, height);
            });
            this.show_serial_numbers.click(function () {
                let SKU_id = $(this).data("sku-id");
                self.packing_parcel_main_model.fireShowSerialNumbers(SKU_id);
            });
            this.tbody_serial_numbers.on('change', '.serial-number-changes', function () {
                let serial_number = $.trim($(this).val());
                if (!serial_number)
                    return;
                let SKU_id = +$(this).data("sku-id");
                let old_serial_number = $.trim($(this).data("prev-value"));
                self.packing_parcel_main_model.fireChangeSerialNumber(SKU_id, serial_number, old_serial_number);
            });
            $('.js-pack-all-btn', this.global_SKU_list).on('click', function (e) {
                e.preventDefault();
                self.prefer_pack_all_focus = true;
                let SKU_id = +$(this).data("sku-id");
                self.packing_parcel_main_model.firePackAllSKU(SKU_id);
            });
            $('.js-pack-all-btn', this.global_SKU_list).on('keydown', function (e) {
                if (e.keyCode === 13) {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });
            $('#scanError').on('hidden.bs.modal', function (e) {
                self.focusScanInput();
            });
            $('#weightError').on('hidden.bs.modal', function (e) {
                let box_id = $(this).data("box-id");
                self.focusWeightInput(box_id);
                $(this).removeData("box-id");
            });
            $('#serialNumberError').on('hidden.bs.modal', function (e) {
                let box_id = $(this).data("box-id");
                let SKU_id = $(this).data('SKU-id');
                if (box_id !== undefined && SKU_id !== undefined) {
                    self.focusSeriaNumberInput(box_id, SKU_id);
                    $(this).removeData("box-id");
                    $(this).removeData('SKU-id');
                }
            });
            function before_unload_event(e) {
                // Cancel the event
                e.preventDefault();
                // Chrome requires returnValue to be set
                e.returnValue = '';
            }
            window.addEventListener('beforeunload', before_unload_event);
            this.btn_conf.click(function () {
                window.removeEventListener('beforeunload', before_unload_event);
                
                // Check if we should show the category modal first
                if (self.parcelCategoryModalSelector && typeof self.parcelCategoryModalSelector === 'string' && $(self.parcelCategoryModalSelector).length > 0) {
                    $(self.parcelCategoryModalSelector).modal('show');
                    return false;
                }
                
                // Otherwise proceed with the normal submission
                self.btn_conf.attr('disabled', true);
                self.btn_conf.find('.text').text('Processing...');
                self.packing_parcel_main_model.fireBeforeSubmit();
            });
        }
        getBox(box_id) {
            return this.boxes_div.find('[data-box-id="' + box_id + '"]');
        }
        addToSKUsArea(box_id, template) {
            this.getBox(box_id).find('.skus-area').append(template);
        }
        changeBoxName(box_id, name) {
            this.getBox(box_id).find('.title').text(name);
            this.getBox(box_id).find('.package_name').val(name);
        }
        addTooltipToNewBox(box_id) {
            this.getBox(box_id).find('.weight').tooltip({
                "trigger": "focus",
                "title": "Enter weight"
            });
        }
        addTooltipToNewSKU(box_id, SKU_id) {
            this.getBox(box_id).find('.js-' + SKU_id + '-qty').tooltip({
                "trigger": "focus",
                "title": "Press Enter or lose focus to confirm"
            });
            this.getBox(box_id).find('.serial-number-' + SKU_id).tooltip({
                "trigger": "focus",
                "title": "Enter serial number"
            });
        }
        showScannedQtyById(SKU_id, count) {
            $('.SKU-' + SKU_id).find('.js-scanned-qty').text(count);
        }
        fireChangeNameBox(box_id, name) {
            this.changeBoxName(box_id, name);
        }
        fireCopyOrderInserts(from_box_id, to_box_id) {
            this.boxes_div.find('#order_inserts_' + to_box_id).prop('checked',  this.boxes_div.find('#order_inserts_' + from_box_id).is(':checked'));
        }
        fireCopyPackageValue(from_box_id, to_box_id) {
            this.boxes_div.find('#package_type_id_' + to_box_id).val(this.boxes_div.find('#package_type_id_' + from_box_id).val());
        }
        fireSetWeightOrder(weight) {
            $('#all_weight').html(+weight.toFixed(3));
        }
        fireAddNewBox(box_id, box) {
            const template = this.templates.boxTemplate(box_id, this.packing_mode, box);
            this.boxes_div.append(template);
            this.addTooltipToNewBox(box_id);
        }
        fireAddSKUToBox(box_id, SKU, is_new, count_in_box, all_weight) {
            if (is_new) {
                let template = this.templates.boxSKUTemplate(box_id, SKU, this.packing_mode);
                this.addToSKUsArea(box_id, template);
                this.addTooltipToNewSKU(box_id, SKU.id);
            }
            this.fireSetAllWeight(box_id, all_weight);
            this.getBox(box_id).find('.js-' + SKU.id + '-qty').val(count_in_box);
        }
        fireRemoveSKUFromBox(box_id, SKU_id, all_weight) {
            this.fireSetAllWeight(box_id, all_weight);
            this.getBox(box_id).find('.js-' + SKU_id + '-qty').closest('.row').remove();
        }
        fireAddSKU(SKU_id, count) {
            this.showScannedQtyById(SKU_id, count);
            this.updatePackAllButtonState(SKU_id);
        }
        updatePackAllButtonState(SKU_id) {
            const packAllBtn = $('#pack_all_' + SKU_id);
            if (packAllBtn.length) {
                const SKU = this.packing_parcel_main_model.SKUs_model.getSKUById(SKU_id);
                const scannedQty = this.packing_parcel_main_model.SKUs_model.getScannedBarcodeQty(SKU_id);
                const remainingQty = SKU.qty - scannedQty;
                
                if (remainingQty > 0) {
                    packAllBtn.prop('disabled', false);
                    packAllBtn.attr('title', 'Pack all ' + remainingQty + ' remaining items of this SKU into the active box');
                    packAllBtn.find('i').removeClass('fa-box').addClass('fa-box-open');
                } else {
                    packAllBtn.prop('disabled', true);
                    packAllBtn.attr('title', 'All items of this SKU have been packed');
                    packAllBtn.find('i').removeClass('fa-box-open').addClass('fa-box');
                }
            }
        }
        fireAllSKUAdded(SKU_id, state) {
            let SKU_el = this.global_SKU_list.find('.SKU-' + SKU_id);
            state ? SKU_el.addClass("alert-success") : SKU_el.removeClass("alert-success");
        }
        fireShowScanError(text) {
            $('#scanError').modal('show');
            $('#error_text').html(text);
        }
        fireShowSerialNumberError(text, box_id, SKU_id) {
            if (box_id !== undefined)
                $('#serialNumberError').data('box-id', box_id);
            if (SKU_id !== undefined)
                $('#serialNumberError').data('SKU-id', SKU_id);
            $('#serialNumberError').modal('show');
            $('#error_text_serial_number').text(text);
        }
        fireShowWeightError(box_id, common_max_weight, max = true) {
            $('#weightError').data('box-id', box_id);
            $('#weightError').modal('show');
            let name = this.getBox(box_id).find('.package_name').val();
            const message = max ? `${name} weight exceeds the maximum of ${common_max_weight} kg!` : `${name} weight is below acceptable`;
            $('#weight-error_text').html(message);
        }
        fireAddSerialNumber(SKU_id, count) {
            $('#quantity_serial_numbers_' + SKU_id).html(count);
            $('#show_serial_number_' + SKU_id).attr('disabled', !count);
        }
        fireSetLength(box_id, length) {
            $("#length_" + box_id).val(length ? length : '');
        }
        fireSetWidth(box_id, width) {
            $("#width_" + box_id).val(width ? width : '');
        }
        fireSetHeight(box_id, height) {
            $("#height_" + box_id).val(height ? height : '');
        }
        fireSetWeight(box_id, weight) {
            this.getBox(box_id).find('input.weight').val(weight ? weight : '');
        }
        fireSetMaxWeight(box_id, max_weight) {
            $("#max_weight_" + box_id).html(max_weight ? max_weight : '');
        }
        fireSetAllWeight(box_id, all_weight) {
            $("#all_weight_" + box_id).html(+all_weight.toFixed(3));
        }
        fireActivateBox(box_id) {
            $('.order-box-active').each(function () {
                $(this).removeClass('order-box-active');
            });
            this.getBox(box_id).addClass('order-box-active');
            
            if (this.packing_mode === 'l') {
                this.updateAllPackAllButtonStates();
            }
        }
        fireRemoveOneBox(box_id) {
            this.getBox(box_id).remove();
        }
        fireBoxNotFilled(box_id) {
            this.getBox(box_id).removeClass("alert-success");
        }
        fireBoxFilled(box_id) {
            this.getBox(box_id).addClass("alert-success");
        }
        fireHideAddBoxBtn(disabled) {
            this.add_box_btn.attr('hidden', disabled);
            $('.js-btn-copy-box').attr('hidden', disabled);
        }
        fireConfirm(all_boxes_filled) {
            this.btn_conf.attr('disabled', !all_boxes_filled);
            document.getElementById('confirm-dialog').style.display = all_boxes_filled ? '' : 'none';
            
            if (this.packing_mode === 'l') {
                this.updateAllPackAllButtonStates();
            }
        }
        updateAllPackAllButtonStates() {
            const self = this;
            const hasActiveBox = this.packing_parcel_main_model.boxes_model.checkExistActiveBox();
            
            $('.js-pack-all-btn').each(function() {
                const SKU_id = +$(this).data('sku-id');
                if (hasActiveBox) {
                    self.updatePackAllButtonState(SKU_id);
                } else {
                    $(this).prop('disabled', true);
                    $(this).attr('title', 'Please select an active box first');
                    $(this).find('i').removeClass('fa-box-open').addClass('fa-box');
                }
            });
        }
        fireShowSerialNumbersModal(SKU_id, serial_numbers) {
            this.tbody_serial_numbers.empty();
            for (let i = 0; i < serial_numbers.length; i++) {
                let input = '<input type="text" class="serial-number-changes form-control w-100 mb-1" data-prev-value="' + html_escape(serial_numbers[i]) + '" data-sku-id="' + +SKU_id + '" value="' + html_escape(serial_numbers[i]) + '" placeholder="Enter serial number">'
                this.tbody_serial_numbers.append('<div class="row"><div class="col-12 form-group-element">' + input + '</div></div>');
            }
            $('#serialNumbersModalInfo').modal('show');
        }
        fireHideSerialNumbersModal() {
            $('#serialNumbersModalInfo').modal('hide');
        }
        fireHideCopyBox(box_id, hide) {
            if (hide)
                this.getBox(box_id).find('.js-btn-copy-box').addClass('d-none');
            else
                this.getBox(box_id).find('.js-btn-copy-box').removeClass('d-none');
        }
        fireAddDataBeforeSubmit(serial_numbers_in_SKUs, serial_numbers_in_boxes) {
            for (let box_id in serial_numbers_in_boxes)
                for (let i in serial_numbers_in_boxes[box_id])
                    $('#confirm_pack_form').append($('<input>', {type: 'hidden', class: 'serial_numbers_box_hidden', name: 'serial_numbers_box[' + serial_numbers_in_boxes[box_id][i] + ']', value: box_id}));
            for (let SKU_id in serial_numbers_in_SKUs)
                for (let i in serial_numbers_in_SKUs[SKU_id])
                    $('#confirm_pack_form').append($('<input>', {type: 'hidden', class: 'serial_numbers_hidden', name: 'serial_numbers[' + SKU_id + '][]', value: serial_numbers_in_SKUs[SKU_id][i]}));
            $('#confirm_pack_form').submit();
        }
        focusScanInput() {
            this.scan_input.focus();
        }
        focusSeriaNumberInput(box_id, SKU_id) {
            this.getBox(box_id).find('.serial-number-' + SKU_id).focus();
            this.fireActivateBox(box_id);
        }
        focusWeightInput(box_id) {
            this.getBox(box_id).find('.weight').focus();
            this.fireActivateBox(box_id);
        }
        focusOutFromAll() {
            $(':focus').blur();
        }
        focusFirstAvailablePackAllButton() {
            if (this.packing_mode !== 'l' || !this.prefer_pack_all_focus) {
                return false;
            }
            
            let firstUnpackedButton = null;
            this.global_SKU_list.find('.js-pack-all-btn').each(function() {
                if (!$(this).prop('disabled') && $(this).is(':visible')) {
                    firstUnpackedButton = $(this);
                    return false;
                }
            });
            
            if (firstUnpackedButton) {
                firstUnpackedButton.focus();
                return true;
            }
            return false;
        }
        
        focusBasedOnPackingState() {
            let SKUs_is_added = this.packing_parcel_main_model.SKUs_model.getSKUsAddedAll();
            const all_SKUs_added = Object.values(SKUs_is_added).every(value => value === true);
            
            if (all_SKUs_added) {
                const activeBoxId = this.packing_parcel_main_model.boxes_model.getActiveIndex();
                if (activeBoxId !== null) {
                    this.focusWeightInput(activeBoxId);
                } else {
                    this.focusOutFromAll();
                }
            } else {
                if (!this.focusFirstAvailablePackAllButton()) {
                    this.focusScanInput();
                }
            }
        }
        
        focusNextPackAllButton(currentSKU_id) {
            let currentIndex = -1;
            
            const allPackAllButtons = this.global_SKU_list.find('.js-pack-all-btn');
            allPackAllButtons.each(function(index) {
                if (+$(this).data('sku-id') === currentSKU_id) {
                    currentIndex = index;
                    return false;
                }
            });
            
            let nextButton = null;
            allPackAllButtons.each(function(index) {
                if (index > currentIndex && !$(this).prop('disabled') && $(this).is(':visible')) {
                    nextButton = $(this);
                    return false;
                }
            });
            
            if (nextButton) {
                nextButton.focus();
            } else {
                this.focusBasedOnPackingState();
            }
        }
    }

    class BoxesModel {
        constructor(packing_parcel_view, max_package_weight) {
            this.packing_parcel_view = packing_parcel_view;
            this.max_package_weight = max_package_weight;
            this.active_box_index = null;
            this.boxes = {};
            this.indexes = [];
            this.box_name_pre = 'Box';
        }
        getLength(box_id) {
            return this.boxes[box_id].length;
        }
        setLength(box_id, length) {
            this.boxes[box_id].length = length;
            this.packing_parcel_view.fireSetLength(box_id, length);
        }
        getWidth(box_id) {
            return this.boxes[box_id].width;
        }
        setWidth(box_id, width) {
            this.boxes[box_id].width = width;
            this.packing_parcel_view.fireSetWidth(box_id, width);
        }
        getHeight(box_id) {
            return this.boxes[box_id].height;
        }
        setHeight(box_id, height) {
            this.boxes[box_id].height = height;
            this.packing_parcel_view.fireSetHeight(box_id, height);
        }
        getWeight(box_id) {
            return this.boxes[box_id].weight;
        }
        getMaxWeight(box_id) {
            return this.boxes[box_id].max_weight;
        }
        getBoxWeight(box_id) {
            return this.boxes[box_id].box_weight;
        }
        getWeightOrder(weight) {
            let sum = 0;
            for (const box_id in this.boxes) {
                sum += this.boxes[box_id].box_weight;
            }
            return sum + weight;
        }
        getAllWeight(box_id) {
            let sum = 0;
            Object.values(this.boxes[box_id].SKUs_weight).forEach(value => { sum += value; });
            return sum + this.boxes[box_id].box_weight;
        }
        setWeight(box_id, weight) {
            this.boxes[box_id].weight = weight;
            this.packing_parcel_view.fireSetWeight(box_id, weight);
        }
        getCommonMaxWeight(box_id) {
            let max_weight = this.boxes[box_id].max_weight;
            let common_max = Math.min(this.max_package_weight, max_weight);
            if (common_max === 0)
                common_max = Math.max(this.max_package_weight, max_weight);
            return common_max;
        }
        removeCurrentBox(box_id) {
            if (box_id === this.active_box_index)
                this.active_box_index = null;
            delete this.boxes[box_id];
            this.packing_parcel_view.fireRemoveOneBox(box_id);
            this.changeNameBoxes();
        }
        removeSKUFromBox(box_id, SKU_id) {
            delete this.boxes[box_id]['SKUs'][SKU_id];
            delete this.boxes[box_id]['SKUs_weight'][SKU_id];
            this.packing_parcel_view.fireRemoveSKUFromBox(box_id, SKU_id, this.getAllWeight(box_id));
        }
        checkExistActiveBox() {
            return this.active_box_index !== null;
        }
        getActiveIndex() {
            return this.active_box_index;
        }
        setActiveIndex(box_id) {
            this.active_box_index = box_id;
            this.packing_parcel_view.fireActivateBox(this.active_box_index);
        }
        getQtySKUInBox(box_id, SKU_id) {
            if (this.boxes[box_id]['SKUs'][SKU_id] === undefined)
                return 0;
            return this.boxes[box_id]['SKUs'][SKU_id];
        }
        getSKUs(box_id) {
            return this.boxes[box_id]['SKUs'];
        }
        getAllBoxesIds() {
            return Object.keys(this.boxes);
        }
        getFilledBoxes(SKUs_with_serial_number, qty_serial_numbers_in_boxes) {
            let box_filled_state = {};
            for (const box_id in this.boxes) {
                let box = this.boxes[box_id];
                box_filled_state[box_id] = true;
                if (!box.height || !box.length || !box.width || !box.weight || box['SKUs'].length === 0) {
                    box_filled_state[box_id] = false;
                    continue;
                }
                let SKUs = this.getSKUs(box_id);
                for (const SKU_id in SKUs) {
                    if (SKUs_with_serial_number[SKU_id] === undefined)
                        continue;
                    if (qty_serial_numbers_in_boxes[box_id] === undefined || qty_serial_numbers_in_boxes[box_id][SKU_id] === undefined) {
                        box_filled_state[box_id] = false;
                        break;
                    }
                    if (SKUs[SKU_id] === qty_serial_numbers_in_boxes[box_id][SKU_id])
                        continue;
                    box_filled_state[box_id] = false;
                    break;
                }
            }
            return box_filled_state;
        }
        getCount() {
            return Object.keys(this.boxes).length;
        }
        addBoxData(length, width, height, weight, max_weight, box_weight) {
            this.active_box_index = this.getNewIndex();
            this.boxes[this.active_box_index] = {
                length: length,
                width: width,
                height: height,
                weight: weight,
                max_weight: max_weight,
                box_weight: box_weight,
                all_weight: box_weight,
                name: this.box_name_pre + (this.active_box_index + 1),
                SKUs: {},
                SKUs_weight: {},
            };
            this.packing_parcel_view.fireAddNewBox(this.active_box_index, this.boxes[this.active_box_index]);
            this.packing_parcel_view.fireActivateBox(this.active_box_index);
            this.changeNameBoxes();
            return this.active_box_index;
        }
        getNewIndex() {
            let max_index = this.indexes.length === 0 ? 0 : Math.max(...this.indexes);
            this.indexes.push(++max_index)
            return max_index;
        }
        changeNameBoxes() {
            let ids = Object.keys(this.boxes);
            if (ids.length === 1) {
                this.packing_parcel_view.fireChangeNameBox(ids[0], this.box_name_pre);
                return;
            }
            for (let i = 0; i < ids.length; i++) {
                this.packing_parcel_view.fireChangeNameBox(ids[i], this.box_name_pre + (i + 1));
            }
        }
        updateBoxData(box_id, length, width, height, weight, max_weight, box_weight) {
            this.boxes[box_id]['length'] = length;
            this.boxes[box_id]['width'] = width;
            this.boxes[box_id]['height'] = height;
            this.boxes[box_id]['weight'] = weight;
            this.boxes[box_id]['max_weight'] = max_weight;
            this.boxes[box_id]['box_weight'] = box_weight;
            this.packing_parcel_view.fireSetLength(box_id, length);
            this.packing_parcel_view.fireSetWidth(box_id, width);
            this.packing_parcel_view.fireSetHeight(box_id, height);
            this.packing_parcel_view.fireSetWeight(box_id, weight);
            this.packing_parcel_view.fireSetMaxWeight(box_id, max_weight);
            this.packing_parcel_view.fireSetAllWeight(box_id, this.getAllWeight(box_id));
        }
        addSKUToBox(box_id, SKU, count = 1) {
            let box = this.boxes[box_id];
            let is_new = box['SKUs'][SKU.id] === undefined;
            if (is_new) {
                box['SKUs'][SKU.id] = 0;
                box['SKUs_weight'][SKU.id] = 0;
            }
            box['SKUs'][SKU.id] += count;
            box['SKUs_weight'][SKU.id] = box['SKUs'][SKU.id]*SKU.weight;
            this.packing_parcel_view.fireAddSKUToBox(box_id, SKU, is_new, box['SKUs'][SKU.id], this.getAllWeight(box_id));
        }
        removeEmptyBoxes() {
            for (const box_id in this.boxes) {
                let box = this.boxes[box_id];
                if (Object.keys(box['SKUs']).length === 0)
                    this.removeCurrentBox(box_id);
            }
        }
    }

    class PackagesModel {
        constructor(packing_parcel_view, packages) {
            this.packing_parcel_view = packing_parcel_view;
            this.packages = packages;
        }
        getDefaultPackage() {
            function getDefaultPackageId(packages) {
                for (const p of packages)
                    if (p.is_default)
                        return +p.id;
                return 0;
            }
            function getPackageById(package_id, packages) {
                return packages.find(p => +p.id === +package_id);
            }
            const sel_package_id = getDefaultPackageId(this.packages);
            return sel_package_id > 0 ? getPackageById(sel_package_id, this.packages) : null;
        }
        getById(id) {
            return this.packages.find(p => +p.id === id);
        }
    }

    class SKUsModel {
        constructor(packing_parcel_view, global_sku_data) {
            this.packing_parcel_view = packing_parcel_view;
            this.global_sku_data = global_sku_data;
            this.scanned_barcode_qty = {};
            this.global_sku_data.forEach(sku => this.scanned_barcode_qty[sku.id] = 0);
        }
        getFirstSKU() {
            return this.global_sku_data[0];
        }
        getSKUsAddedAll() {
            let SKUs = {};
            for (let i = 0; i < this.global_sku_data.length; i++)
                SKUs[+this.global_sku_data[i].id] = +this.scanned_barcode_qty[this.global_sku_data[i].id] === +this.global_sku_data[i].qty;
            return SKUs;
        }
        getListOfSKUWithSerialNumber() {
            let SKUs_with_serial_number = {};
            for (let i = 0; i < this.global_sku_data.length; i++) {
                if (!this.global_sku_data[i]['serial_number'])
                    continue;
                SKUs_with_serial_number[+this.global_sku_data[i].id] = +this.global_sku_data[i].qty;
            }
            return SKUs_with_serial_number;
        }
        getSKUByBarcode(barcode) {
            return this.global_sku_data.find(function (sku) {
                return sku.barcodes.includes(barcode);
            });
        }
        getSKUById(SKU_id) {
            return this.global_sku_data.find(sku => +sku.id === +SKU_id);
        }
        getFirstNotEnoughSKUForCopyBox(SKUs) {
            for (const SKU_id in SKUs) {
                let SKU = this.getSKUById(SKU_id);
                let qty = SKUs[SKU_id];
                let max_qty = SKU.qty;
                let current_qty = this.getScannedBarcodeQty(SKU_id);
                if (qty + current_qty > max_qty) {
                    return SKU;
                }
            }
            return null;
        }
        getScannedBarcodeQty(SKU_id) {
            return this.scanned_barcode_qty[SKU_id];
        }
        addSKU(SKU_id, count = 1) {
            this.scanned_barcode_qty[SKU_id] += count;
            this.packing_parcel_view.fireAddSKU(SKU_id, this.scanned_barcode_qty[SKU_id]);
        }
        removeSKU(SKU_id, count) {
            this.scanned_barcode_qty[SKU_id] -= count;
            this.packing_parcel_view.fireAddSKU(SKU_id, this.scanned_barcode_qty[SKU_id]);
        }
    }

    class SerialNumbersModel {
        constructor(packing_parcel_view, regexp_serial_number) {
            this.packing_parcel_view = packing_parcel_view;
            this.regexp_serial_number = regexp_serial_number;
            this.serial_numbers = {};
        }
        addSerialnumber(serial_number, SKU_id, box_id) {
            if (this.serial_numbers[box_id] === undefined)
                this.serial_numbers[box_id] = {};
            if (this.serial_numbers[box_id][SKU_id] === undefined)
                this.serial_numbers[box_id][SKU_id] = [];
            this.serial_numbers[box_id][SKU_id].push(serial_number);
            this.packing_parcel_view.fireAddSerialNumber(SKU_id, this.getQtySerialNumberBySKU(SKU_id));
        }
        removeSKUFromBox(box_id, SKU_id) {
            if (this.serial_numbers[box_id] !== undefined && this.serial_numbers[box_id][SKU_id] !== undefined)
                delete this.serial_numbers[box_id][SKU_id];
            this.packing_parcel_view.fireAddSerialNumber(SKU_id, this.getQtySerialNumberBySKU(SKU_id));
        }
        sliceLast(box_id, SKU_id, count) {
            for (let i = 0; i < count; i++) {
                if (this.serial_numbers[box_id] !== undefined && this.serial_numbers[box_id][SKU_id] !== undefined && this.serial_numbers[box_id][SKU_id] > count)
                    this.serial_numbers[box_id][SKU_id].pop();
            }
            this.packing_parcel_view.fireAddSerialNumber(SKU_id, this.getQtySerialNumberBySKU(SKU_id));
        }
        updateSerialnumber(serial_number, SKU_id, box_id) {
            let length = this.serial_numbers[box_id][SKU_id].length;
            this.serial_numbers[box_id][SKU_id][length - 1] = serial_number;
        }
        changeSerialNumber(serial_number, old_serial_number) {
            for (const box_id in this.serial_numbers)
                for (const SKU_id in this.serial_numbers[box_id]) {
                    let index = this.serial_numbers[box_id][SKU_id].indexOf(old_serial_number);
                    if (index === -1)
                        continue;
                    this.serial_numbers[box_id][SKU_id][index] = serial_number;
                    return;
                }
        }
        getQtySerialNumberBySKUInBox(box_id, SKU_id) {
            if (this.serial_numbers[box_id] === undefined || this.serial_numbers[box_id][SKU_id] === undefined)
                return 0;
            return this.serial_numbers[box_id][SKU_id].length;
        }
        getQtySerialNumberBySKU(SKU_id) {
            let count = 0;
            for (const box_id in this.serial_numbers) {
                if (this.serial_numbers[box_id][SKU_id] === undefined)
                    continue;
                count += this.serial_numbers[box_id][SKU_id].length;
            }
            return count;
        }
        getBySKU(SKU_id) {
            let serial_numbers = [];
            for (const box_id in this.serial_numbers) {
                if (this.serial_numbers[box_id][SKU_id] === undefined)
                    continue;
                serial_numbers = serial_numbers.concat(this.serial_numbers[box_id][SKU_id]);
            }
            return serial_numbers;
        }
        getQtySerialNumbersInBoxes() {
            let result = {};
            for (const box_id in this.serial_numbers) {
                if (result[box_id] === undefined)
                    result[box_id] = {};
                for (const SKU_id in this.serial_numbers[box_id])
                    result[box_id][SKU_id] = this.serial_numbers[box_id][SKU_id].length;
            }
            return result;
        }
        getBoxIndexBySerialNumber(serial_number) {
            for (const box_id in this.serial_numbers)
                for (const SKU_id in this.serial_numbers[box_id]) {
                    let index = this.serial_numbers[box_id][SKU_id].indexOf(serial_number);
                    if (index !== -1)
                        return index;
                }
            return null;
        }
        isErrorRegexp(serial_number) {
            return this.regexp_serial_number && !serial_number.match(this.regexp_serial_number);
        }
        getSerialNumbersInSKUs() {
            let serial_numbers = {};
            for (const box_id in this.serial_numbers) {
                for (const SKU_id in this.serial_numbers[box_id]) {
                    if (serial_numbers[SKU_id] === undefined)
                        serial_numbers[SKU_id] = [];
                    serial_numbers[SKU_id] = serial_numbers[SKU_id].concat(this.serial_numbers[box_id][SKU_id]);
                }
            }
            return serial_numbers;
        }
        getSerialNumbersInBoxes() {
            let serial_numbers = {};
            for (const box_id in this.serial_numbers) {
                if (serial_numbers[box_id] === undefined)
                    serial_numbers[box_id] = [];
                for (const SKU_id in this.serial_numbers[box_id]) {
                    serial_numbers[box_id] = serial_numbers[box_id].concat(this.serial_numbers[box_id][SKU_id]);
                }
            }
            return serial_numbers;
        }
    }

    class PackingParcelController {
        constructor(packages, packing_mode, order_inserts, global_sku_data, regexp_serial_number, weight_order, max_package_weight) {
            this.packing_mode = packing_mode;
            this.weight_order = weight_order;
            this.packing_parcel_view = new PackingParcelView(new PackingParcelTemplates(order_inserts, packages), packing_mode, this);
            this.packing_parcel_view.addEvents();
            this.boxes_model = new BoxesModel(this.packing_parcel_view, max_package_weight);
            this.packages_model = new PackagesModel(this.packing_parcel_view, packages);
            this.SKUs_model = new SKUsModel(this.packing_parcel_view, global_sku_data);
            this.serial_numbers_model = new SerialNumbersModel(this.packing_parcel_view, regexp_serial_number);
        }
        checkIfPackingFinished() {
            let SKUs_is_added = this.SKUs_model.getSKUsAddedAll();
            let all_SKUs_added = !!Object.keys(SKUs_is_added).length;
            
            let total_scanned = 0;
            for (const SKU_id in SKUs_is_added) {
                total_scanned += this.SKUs_model.getScannedBarcodeQty(SKU_id);
                all_SKUs_added = all_SKUs_added && SKUs_is_added[SKU_id];
                this.packing_parcel_view.fireAllSKUAdded(SKU_id, SKUs_is_added[SKU_id]);
            }
            
            if (total_scanned === 0) {
                all_SKUs_added = false;
            }
            if (all_SKUs_added) {
                this.boxes_model.removeEmptyBoxes();
                this.packing_parcel_view.fireHideAddBoxBtn(true);
            } else {
                this.packing_parcel_view.fireHideAddBoxBtn(false);
            }
            let SKUs_with_serial_number = this.SKUs_model.getListOfSKUWithSerialNumber();
            let all_serial_numbers_added = true;
            for (const SKU_id in SKUs_with_serial_number) {
                let qty_need = SKUs_with_serial_number[SKU_id];
                let qty = this.serial_numbers_model.getQtySerialNumberBySKU(SKU_id);
                if (qty_need > qty) {
                    all_serial_numbers_added = false;
                    break;
                }
            }
            let qty_serial_numbers_in_boxes = this.serial_numbers_model.getQtySerialNumbersInBoxes();
            let box_filled_state = this.boxes_model.getFilledBoxes(SKUs_with_serial_number, qty_serial_numbers_in_boxes);
            let all_boxes_filled = !!this.boxes_model.getCount() && all_SKUs_added;
            
            let all_boxes_have_weight = true;
            let all_box_ids = this.boxes_model.getAllBoxesIds();
            for (const box_id of all_box_ids) {
                let weight = this.boxes_model.getWeight(box_id);
                if (!weight || weight <= 0) {
                    all_boxes_have_weight = false;
                    break;
                }
            }
            
            for (const box_id in box_filled_state) {
                let state = box_filled_state[box_id];
                if (state === false) {
                    all_boxes_filled = false;
                    this.packing_parcel_view.fireBoxNotFilled(box_id);
                } else if (box_id === 0 && (!all_SKUs_added || !all_serial_numbers_added)) {
                    all_boxes_filled = false;
                    this.packing_parcel_view.fireBoxNotFilled(box_id);
                } else {
                    this.packing_parcel_view.fireBoxFilled(box_id);
                }
            }
            
            all_boxes_filled = all_boxes_filled && all_boxes_have_weight;
            this.packing_parcel_view.fireSetWeightOrder(this.boxes_model.getWeightOrder(this.weight_order));
            this.packing_parcel_view.fireConfirm(all_boxes_filled);
            return all_boxes_filled;
        }
        fireCopyBox(box_id) {
            let length = this.boxes_model.getLength(box_id);
            let width = this.boxes_model.getWidth(box_id);
            let height = this.boxes_model.getHeight(box_id);
            let weight = this.boxes_model.getWeight(box_id);
            let max_weight = this.boxes_model.getMaxWeight(box_id);
            let box_weight = this.boxes_model.getBoxWeight(box_id);
            let SKUs = this.boxes_model.getSKUs(box_id);
            let not_enough_SKU = this.SKUs_model.getFirstNotEnoughSKUForCopyBox(SKUs);
            let new_box_id = this.boxes_model.addBoxData(length, width, height, weight, max_weight, box_weight);
            if (!not_enough_SKU) {
                for (const SKU_id in SKUs) {
                    let SKU = this.SKUs_model.getSKUById(SKU_id);
                    let qty = SKUs[SKU_id];
                    this.SKUs_model.addSKU(SKU.id, qty);
                    this.boxes_model.addSKUToBox(new_box_id, SKU, qty);
                    this.fireChangeSKUQty(new_box_id, SKU_id, +qty);
                }
            }
            this.packing_parcel_view.fireCopyOrderInserts(box_id, new_box_id);
            this.packing_parcel_view.fireCopyPackageValue(box_id, new_box_id);
            this.checkIfPackingFinished();
        }
        fireAddBox() {
            let pack = this.packages_model.getDefaultPackage();
            let box_id = null;
            if (pack)
                box_id = this.boxes_model.addBoxData(parseFloat(pack.length), parseFloat(pack.width), parseFloat(pack.height), null, parseFloat(pack.weight), pack.box_weight);
            else
                box_id = this.boxes_model.addBoxData(null, null, null, null, null, null);
            if (this.packing_mode === 's') {
                let SKU = this.SKUs_model.getFirstSKU();
                this.SKUs_model.addSKU(SKU.id);
                this.boxes_model.addSKUToBox(box_id, this.SKUs_model.getFirstSKU(SKU));
                this.checkIfPackingFinished();
                if (SKU.serial_number) {
                    this.packing_parcel_view.focusSeriaNumberInput(box_id, SKU.id)
                } else {
                    this.packing_parcel_view.focusWeightInput(box_id);
                }
            } else {
                this.checkIfPackingFinished();
                if (!this.packing_parcel_view.focusFirstAvailablePackAllButton()) {
                    this.packing_parcel_view.focusScanInput();
                }
            }
            this.packing_parcel_view.fireSetWeightOrder(this.boxes_model.getWeightOrder(this.weight_order));
        }
        fireSetBarcode(scanned_barcode) {
            let scanned_SKU = this.SKUs_model.getSKUByBarcode(scanned_barcode);
            if (!this.boxes_model.checkExistActiveBox() === null) {
                this.packing_parcel_view.fireShowScanError("Please select an active box first!");
                return;
            } else if (!scanned_SKU) {
                this.packing_parcel_view.fireShowScanError("Barcode <strong><span>" + html_escape(scanned_barcode) + "</span></strong> is not a part of this order");
                return;
            } else if (+scanned_SKU.qty === +this.SKUs_model.getScannedBarcodeQty(scanned_SKU.id)) {
                this.packing_parcel_view.fireShowScanError("Maximum amount of <strong><span>" + html_escape(scanned_SKU.barcode) + "</span></strong> barcodes have already been scanned");
                return;
            }
            let box_id = this.boxes_model.getActiveIndex();
            if (box_id === null) {
                this.packing_parcel_view.fireShowScanError("Please select an active box first!");
                return;
            }
            let all_boxes_ids = this.boxes_model.getAllBoxesIds();
            for (const one_box_id of all_boxes_ids) {
                let SKUs_in_box = this.boxes_model.getSKUs(one_box_id);
                for (const SKU_id in SKUs_in_box) {
                    if (!this.SKUs_model.getSKUById(SKU_id).serial_number)
                        continue;
                    let SKU_qty = SKUs_in_box[SKU_id];
                    let serial_number_qty = this.serial_numbers_model.getQtySerialNumberBySKUInBox(one_box_id, SKU_id);
                    if (SKU_qty > serial_number_qty) {
                        this.packing_parcel_view.fireShowSerialNumberError('Enter serial number', one_box_id, SKU_id);
                        return;
                    }
                }
            }
            this.SKUs_model.addSKU(scanned_SKU.id);
            this.boxes_model.addSKUToBox(box_id, scanned_SKU);
            this.boxes_model.setWeight(box_id, 0);
            this.checkIfPackingFinished();
            if (scanned_SKU.serial_number) {
                let qty_serial_number = this.serial_numbers_model.getQtySerialNumberBySKUInBox(box_id, scanned_SKU.id);
                let qty_SKU = this.boxes_model.getQtySKUInBox(box_id, scanned_SKU.id);
                if (qty_SKU > qty_serial_number) {
                    this.packing_parcel_view.focusSeriaNumberInput(box_id, scanned_SKU.id);
                } else {
                    this.packing_parcel_view.focusScanInput();
                }
            } else {
                let SKUs_is_added = this.SKUs_model.getSKUsAddedAll();
                const all_SKUs_added = Object.values(SKUs_is_added).every(value => value === true);
                if (all_SKUs_added) {
                    this.packing_parcel_view.focusWeightInput(box_id);
                } else
                    this.packing_parcel_view.focusScanInput();
            }
        }
        fireClearSerialNumber(box_id, SKU_id) {
            let qty = this.serial_numbers_model.getQtySerialNumberBySKUInBox(box_id, SKU_id);
            this.serial_numbers_model.sliceLast(box_id, SKU_id, qty);
            this.checkIfPackingFinished();
        }
        fireSetSerialNumber(box_id, serial_number, SKU_id) {
            if (!serial_number)
                return;
            else if (this.serial_numbers_model.isErrorRegexp(serial_number)) {
                this.packing_parcel_view.fireShowSerialNumberError('Wrong serial number format', box_id, SKU_id);
                return;
            } else if (this.serial_numbers_model.getBoxIndexBySerialNumber(serial_number) !== null) {
                this.packing_parcel_view.fireShowSerialNumberError('Duplicate serial numbers ' + serial_number, box_id, SKU_id);
                return;
            }
            let qty_SKU = +this.boxes_model.getQtySKUInBox(box_id, SKU_id);
            let qty_serial_number = this.serial_numbers_model.getQtySerialNumberBySKUInBox(box_id, SKU_id);
            if (qty_SKU === qty_serial_number)
                this.serial_numbers_model.updateSerialnumber(serial_number, SKU_id, box_id);
            else if (qty_SKU > qty_serial_number)
                this.serial_numbers_model.addSerialnumber(serial_number, SKU_id, box_id);
            else
                return;
            this.checkIfPackingFinished();
            let weight = this.boxes_model.getWeight(box_id);
            if (this.packing_mode !== 's') {
                let qty_serial_number = this.serial_numbers_model.getQtySerialNumberBySKUInBox(box_id, SKU_id);
                let qty_SKU_in_box = this.boxes_model.getQtySKUInBox(box_id, SKU_id);
                if (qty_SKU_in_box > qty_serial_number) {
                    this.packing_parcel_view.focusSeriaNumberInput(box_id, SKU_id);
                    return;
                }
                let SKUs_is_added = this.SKUs_model.getSKUsAddedAll();
                const all_SKUs_added = Object.values(SKUs_is_added).every(value => value === true);
                if (!all_SKUs_added) {
                    this.packing_parcel_view.focusScanInput();
                    return;
                }
            }
            if (!weight) {
                this.packing_parcel_view.focusWeightInput(box_id);
                return;
            }
            let all_boxes_ids = this.boxes_model.getAllBoxesIds();
            for (const one_box_id of all_boxes_ids) {
                let weight = this.boxes_model.getWeight(one_box_id);
                if (!weight) {
                    this.packing_parcel_view.focusWeightInput(one_box_id);
                    return;
                }
            }
            this.packing_parcel_view.focusOutFromAll();
        }
        fireRemoveSKUFromBox(box_id, SKU_id) {
            this.boxes_model.setWeight(box_id, 0);
            this.SKUs_model.removeSKU(SKU_id, this.boxes_model.getQtySKUInBox(box_id, SKU_id));
            this.boxes_model.removeSKUFromBox(box_id, SKU_id);
            this.serial_numbers_model.removeSKUFromBox(box_id, SKU_id);
            this.checkIfPackingFinished();
            
            if (this.packing_mode === 'l') {
                this.packing_parcel_view.updatePackAllButtonState(SKU_id);
            }
        }
        fireSetActivateBox(box_id) {
            this.boxes_model.setActiveIndex(box_id);
        }
        fireRemoveBox(box_id) {
            let SKUs = this.boxes_model.getSKUs(box_id);
            for (const SKU_id in SKUs) {
                let qty = SKUs[SKU_id];
                this.SKUs_model.removeSKU(SKU_id, qty);
                this.boxes_model.removeSKUFromBox(box_id, SKU_id);
                this.serial_numbers_model.removeSKUFromBox(box_id, SKU_id);
            }
            this.boxes_model.removeCurrentBox(box_id);
            this.packing_parcel_view.fireSetWeightOrder(this.boxes_model.getWeightOrder(this.weight_order));
            this.checkIfPackingFinished();
            
            if (this.packing_mode === 'l') {
                this.packing_parcel_view.updateAllPackAllButtonStates();
            }
        }
        fireChangeSKUQty(box_id, SKU_id, qty) {
            let SKU = this.SKUs_model.getSKUById(SKU_id);
            let current_qty_in_box = this.boxes_model.getQtySKUInBox(box_id, SKU_id);
            let current_qty = this.SKUs_model.getScannedBarcodeQty(SKU_id);
            let max_qty = SKU.qty;
            if (qty === current_qty_in_box)
                return;
            if (qty === 0) {
                this.packing_parcel_view.fireShowScanError('Error quantity');
                this.SKUs_model.addSKU(SKU_id, 0);
                this.boxes_model.addSKUToBox(box_id, SKU, 0);
                return;
            }
            let count_diff = 0;
            if (qty > current_qty_in_box) {
                count_diff = qty - current_qty_in_box;
                if (count_diff + current_qty > max_qty) {
                    this.packing_parcel_view.fireShowScanError("Exceeded the maximum amount of <strong><span>" + html_escape(SKU.barcode) + "</span></strong> barcodes that can be scanned");
                    count_diff = 0;
                }
                this.SKUs_model.addSKU(SKU_id, count_diff);
                this.boxes_model.addSKUToBox(box_id, SKU, count_diff);
            } else if (current_qty_in_box > qty) {
                count_diff = current_qty_in_box - qty;
                this.SKUs_model.removeSKU(SKU_id, count_diff);
                this.boxes_model.addSKUToBox(box_id, SKU, -count_diff);
                this.serial_numbers_model.sliceLast(box_id, SKU_id, count_diff);
            }
            if (count_diff) {
                this.boxes_model.setWeight(box_id, 0);
                this.checkIfPackingFinished();
            }
            if (SKU.serial_number) {
                let qty_serial_numbers_in_SKU_in_box = this.serial_numbers_model.getQtySerialNumberBySKUInBox(box_id, SKU_id);
                let qty_SKU_in_box = this.boxes_model.getQtySKUInBox(box_id, SKU_id);
                if (qty_SKU_in_box > qty_serial_numbers_in_SKU_in_box) {
                    this.packing_parcel_view.focusSeriaNumberInput(box_id, SKU_id);
                    return;
                }
            }
            let SKUs_is_added = this.SKUs_model.getSKUsAddedAll();
            const all_SKUs_added = Object.values(SKUs_is_added).every(value => value === true);
            if (!all_SKUs_added) {
                this.packing_parcel_view.focusScanInput();
                return;
            }
            let weight = this.boxes_model.getWeight(box_id);
            if (!weight) {
                this.packing_parcel_view.focusWeightInput(box_id);
                return;
            }
        }
        fireChangeWeight(box_id, weight) {
            const common_max_weight = this.boxes_model.getCommonMaxWeight(box_id);
            if (common_max_weight !== 0 && weight > common_max_weight) {
                this.boxes_model.setWeight(box_id, 0);
                this.packing_parcel_view.fireShowWeightError(box_id, common_max_weight);
            } else if (weight && weight < 0.01) {
                this.boxes_model.setWeight(box_id, 0);
                this.packing_parcel_view.fireShowWeightError(box_id, common_max_weight, false);
            } else
                this.boxes_model.setWeight(box_id, weight);
            let all_boxes_filled = this.checkIfPackingFinished();
            if (all_boxes_filled) {
                this.packing_parcel_view.focusOutFromAll();
                return;
            }
            if (this.packing_mode !== 's') {
                let SKUs_is_added = this.SKUs_model.getSKUsAddedAll();
                const all_SKUs_added = Object.values(SKUs_is_added).every(value => value === true);
                if (!all_SKUs_added) {
                    if (!this.packing_parcel_view.focusFirstAvailablePackAllButton()) {
                        this.packing_parcel_view.focusScanInput();
                    }
                } else {
                    let all_boxes_ids = this.boxes_model.getAllBoxesIds();
                    for (const one_box_id of all_boxes_ids) {
                        let weight = this.boxes_model.getWeight(one_box_id);
                        if (!weight) {
                            this.packing_parcel_view.focusWeightInput(one_box_id);
                            return;
                        }
                    }
                }
            }
        }
        fireShowSerialNumbers(SKU_id) {
            let serial_numbers = this.serial_numbers_model.getBySKU(SKU_id)
            this.packing_parcel_view.fireShowSerialNumbersModal(SKU_id, serial_numbers);
        }
        fireChangeSerialNumber(SKU_id, serial_number, old_serial_number) {
            if (this.serial_numbers_model.getBoxIndexBySerialNumber(serial_number) !== null) {
                this.packing_parcel_view.fireHideSerialNumbersModal();
                this.packing_parcel_view.fireShowSerialNumberError('Duplicate serial numbers ' + serial_number, undefined, undefined);
                return;
            }
            this.serial_numbers_model.changeSerialNumber(serial_number, old_serial_number);
        }
        fireChangePackageType(box_id, package_type_id) {
            let pack = this.packages_model.getById(package_type_id);
            this.boxes_model.updateBoxData(box_id, parseFloat(pack.length), parseFloat(pack.width), parseFloat(pack.height), null, parseFloat(pack.weight), parseFloat(pack.box_weight));
            this.packing_parcel_view.fireSetWeightOrder(this.boxes_model.getWeightOrder(this.weight_order));
            this.checkIfPackingFinished();
        }
        fireChangeLength(box_id, length) {
            if (!length || 0 > length)
                length = this.boxes_model.getLength(box_id);
            this.boxes_model.setLength(box_id, length);
        }
        fireChangeWidth(box_id, width) {
            if (!width || 0 > width)
                width = this.boxes_model.getWidth(box_id);
            this.boxes_model.setWidth(box_id, width);
        }
        fireChangeHeight(box_id, height) {
            if (!height || 0 > height)
                height = this.boxes_model.getHeight(box_id);
            this.boxes_model.setHeight(box_id, height);
        }
        fireCopyPackageTypeToAll(packageTypeId) {
            let allBoxIds = this.boxes_model.getAllBoxesIds();
            
            // Skip box 0 (first box) as that's the source
            for (let i = 0; i < allBoxIds.length; i++) {
                const boxId = allBoxIds[i];
                if (boxId != 0) {
                    this.fireChangePackageType(boxId, packageTypeId);
                }
            }
        }
        /**
         * Packs all remaining items of a specific SKU into the active box
         * This is particularly useful when dealing with multiple items of the same SKU
         * without having to scan each item individually
         * @param {number} SKU_id - The ID of the SKU to pack all remaining items
         */
        firePackAllSKU(SKU_id) {
            const packAllBtn = $('#pack_all_' + SKU_id);
            if (packAllBtn.prop('disabled')) {
                return;
            }
            
            let SKU = this.SKUs_model.getSKUById(SKU_id);
            if (!SKU) {
                this.packing_parcel_view.fireShowScanError("SKU not found");
                return;
            }
            
            let box_id = this.boxes_model.getActiveIndex();
            if (box_id === null) {
                this.packing_parcel_view.fireShowScanError("Please select an active box first!");
                return;
            }
            
            let total_qty = SKU.qty;
            let scanned_qty = this.SKUs_model.getScannedBarcodeQty(SKU_id);
            let remaining_qty = total_qty - scanned_qty;
            
            if (remaining_qty <= 0) {
                this.packing_parcel_view.fireShowScanError("All items of this SKU have already been packed");
                return;
            }
            
            let all_boxes_ids = this.boxes_model.getAllBoxesIds();
            for (const one_box_id of all_boxes_ids) {
                let SKUs_in_box = this.boxes_model.getSKUs(one_box_id);
                for (const sku_id in SKUs_in_box) {
                    if (!this.SKUs_model.getSKUById(sku_id).serial_number)
                        continue;
                    let SKU_qty = SKUs_in_box[sku_id];
                    let serial_number_qty = this.serial_numbers_model.getQtySerialNumberBySKUInBox(one_box_id, sku_id);
                    if (SKU_qty > serial_number_qty) {
                        this.packing_parcel_view.fireShowSerialNumberError('Enter serial number', one_box_id, sku_id);
                        return;
                    }
                }
            }
            
            this.SKUs_model.addSKU(SKU_id, remaining_qty);
            this.boxes_model.addSKUToBox(box_id, SKU, remaining_qty);
            this.boxes_model.setWeight(box_id, 0);
            
            this.packing_parcel_view.updatePackAllButtonState(SKU_id);
            this.checkIfPackingFinished();
            
            if (SKU.serial_number) {
                let qty_serial_number = this.serial_numbers_model.getQtySerialNumberBySKUInBox(box_id, SKU_id);
                let qty_SKU = this.boxes_model.getQtySKUInBox(box_id, SKU_id);
                if (qty_SKU > qty_serial_number) {
                    this.packing_parcel_view.focusSeriaNumberInput(box_id, SKU_id);
                    return;
                }
            }
            
            this.packing_parcel_view.focusNextPackAllButton(SKU_id);
        }
        fireBeforeSubmit() {
            let serial_numbers_in_SKUs = this.serial_numbers_model.getSerialNumbersInSKUs();
            let serial_numbers_in_boxes = this.serial_numbers_model.getSerialNumbersInBoxes();
            this.packing_parcel_view.fireAddDataBeforeSubmit(serial_numbers_in_SKUs, serial_numbers_in_boxes);
        }
    }

    /**
     * Структура
     * PackingParcelController - В классе находится бизнес логика.
     *                           Не должно быть прямого общения с html, только через PackingParcelView.
     *                           Не должно быть общения на прямую с хранилищем данных(переменные в моделях BoxesModel, PackagesModel, SKUsModel, SerialNumbersModel)
     * PackingParcelView - Класс нужен исключительно для работы с html/dom
     * BoxesModel, PackagesModel, SKUsModel, SerialNumbersModel - Классы для доступа к данным.
     *                                                            Также при изменении данных могут обращаться к классу PackingParcelView для отображение результата изменения
     *                                                            Но обращаться к этим классам из PackingParcelView нельязя только через PackingParcelController
     * PackingParcelTemplates - Класс для шаблонов. Можно было поместить шаблоны в PackingParcelView. Но для удобства вынесены в отдельный класс
     *
     * Суффиксы fire и focus используется для обращения к PackingParcelView из PackingParcelController, BoxesModel, PackagesModel, SKUsModel, SerialNumbersModel
     * Также суффикс fire используется для обращения к PackingParcelController из PackingParcelView
     *
     * @type {PackingParcelController}
     */
    let packing_parcel_controller = new PackingParcelController(packages, packing_mode, order_inserts, global_sku_data, regexp_serial_number, weigth_order, max_package_weight);
    packing_parcel_controller.fireAddBox();
    
    // Initialize Pack All button states
    if (packing_mode === 'l') {
        global_sku_data.forEach(sku => {
            packing_parcel_controller.packing_parcel_view.updatePackAllButtonState(sku.id);
        });
    }
});