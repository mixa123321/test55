class AutocompleteWrapper {

    variables_functions;
    table;
    Select = class SelectWrapper {
        _error_1 = "Please enter SKU barcode";
        _error_2 = 'SKU not found';
        _last_searching = '';
        _last_response = [];
        _input_id = null;
        _source = (request, response) => {
        };
        _min_len = 1;
        _render_item = (ul, item) => {
        };
        _render_input_value = (ul, item) => {
        };
        _error = {};
        _eventAddValueAfterSelect = (item) => {
        }
        _eventAddValueAfterSelected = (item) => {
        }
        _searchValueInResponse = (self) => {
        }

        setInputId(input_id) {
            this._input_id = $('#' + input_id);
            return this;
        }

        setSource(source) {
            this._source = source;
            return this;
        }

        setMinLen(_min_len) {
            this._min_len = _min_len;
            return this;
        }

        setRenderItem(render_item) {
            this._render_item = render_item;
            return this;
        }

        setRenderInputValue(render_input_value) {
            this._render_input_value = render_input_value;
            return this;
        }

        setError(error) {
            this._error = error;
            return this;
        }

        setEventAddValueAfterSelect(func) {
            this._eventAddValueAfterSelect = func;
            return this;
        }

        setEventAddValueAfterSelected(func) {
            this._eventAddValueAfterSelected = func;
            return this;
        }

        setSearchValueInResponse(func) {
            this._searchValueInResponse = func;
            return this;
        }

        initAutocomplete() {
            if (!this._input_id)
                return this;
            let self = this;
            let instance = this._input_id.autocomplete({
                source: this._source,
                minLength: this._min_len,
                select: (event, ui) => {
                    self._render_input_value(event, ui);
                    self._eventAddValueAfterSelect(event, self, ui);
                    return false;
                },
                search: (event, ui) => {
                    self._error.hideError();
                },
                response: (event, ui) => {
                    self._last_response = ui.content;
                    self._last_searching = self._input_id.val();
                },
            }).autocomplete("instance");
            instance._renderItem = this._render_item;
            instance._renderMenu = function (ul, items) {
                $(ul).css("z-index", 2000);
                let self = this;
                $.each(items, function (index, item) {
                    self._renderItemData(ul, item);
                });
            };
            return this;
        }

        addEventInputEnter() {
            if (!this._input_id)
                return this;
            let self = this;
            this._input_id.keydown(function (e) {
                if (e.keyCode !== 13)
                    return null;
                e.preventDefault();
                self._addValue();
            });
            return this;
        }

        addEventButtonAdd(button_id) {
            let self = this;
            $('#' + button_id).click(function (e) {
                e.preventDefault();
                self._addValue();
            });
            return this;
        }

        _checkIsSearching(self, func, count) {
            if (self._last_searching !== self._input_id.val()) {
                if (count > 500)
                    return;
                count++;
                window.setTimeout(self._checkIsSearching, 200, self, func, count);
                return;
            }
            func(self);
        }

        _hideAutoComplete() {
            this._input_id.autocomplete("instance").selectedItem = undefined;
            this._input_id.autocomplete('close');
            this._input_id.val('');
        }

        _addValue() {
            if (!this._input_id.val()) {
                this._error.showError(this._error_1);
                return null;
            }
            let item = this._input_id.autocomplete("instance").selectedItem;
            if (item) {
                this._hideAutoComplete();
                this._eventAddValueAfterSelected(item, this);
                return null;
            }
            this._checkIsSearching(this, function (self) {
                let item = self._searchValueInResponse(self);
                if (item) {
                    self._hideAutoComplete();
                    self._eventAddValueAfterSelected(item, self);
                    return null;
                }
                self._error.showError(self._error_2);
                self._fixPosition();
            }, 1)
        }

        _fixPosition() {
            let top = this._input_id.offset().top + this._input_id.outerHeight();
            let widget = this._input_id.autocomplete("widget");
            widget.css('top', top);
            widget.css('width', 'auto');
        }
    }
    Table = class TableWrapper {
        _error_text_1 = "SKU has already been added";
        _error = {};
        _items = {};
        _row_id = 'id';
        _append_to_id = null;
        _tag = 'tr';
        _template = (tag, data) => {
        };
        _remove_buttons_class = null;
        _eventAfterAddValue = (key, data, items, self) => {
        }
        _eventAfterRemoveValue = (key, items, self) => {
        }

        setError(error) {
            this._error = error;
            return this;
        }

        setRowId(row_id) {
            this._row_id = row_id;
            return this;
        }

        setAppendToId(_append_to_id) {
            this._append_to_id = $('#' + _append_to_id);
            return this;
        }

        setTag(tag) {
            this._tag = tag;
            return this;
        }

        setTemplate(func) {
            this._template = func;
            return this;
        }

        setRemoveButtonsClass(remove_buttons_class) {
            this._remove_buttons_class = remove_buttons_class;
            return this;
        }

        setEventAfterAddValue(func) {
            this._eventAfterAddValue = func;
            return this;
        }

        setEventAfterRemoveValue(func) {
            this._eventAfterRemoveValue = func;
            return this;
        }

        getAppendTo() {
            return this._append_to_id;
        }

        clearTable = () => {
            if (!this._append_to_id)
                return;
            let self = this;
            this._append_to_id.find(this._tag).each(function () {
                let key = $(this).data('key');
                $(this).remove();
                delete self._items[key];
            });
        };

        getFunctionAddValueToTable() {
            let self = this;
            return (data) => {
                self._error.hideError();
                let key = data[this._row_id];
                if (key in this._items) {
                    self._error.showError(this._error_text_1);
                } else {
                    let tag = document.createElement(this._tag);
                    tag.setAttribute('data-key', key);
                    this._template(tag, data);
                    this._append_to_id.append(tag);
                    this._items[key] = true;
                    self._eventAfterAddValue(key, data, this._items, self);
                }
            };
        }

        addEventButtonRemove() {
            let self = this;
            $(document).on("click", '.' + this._remove_buttons_class, function () {
                let current_row = $(this).closest(self._tag);
                let key = current_row.data('key');
                current_row.remove();
                self._eventAfterRemoveValue(key, self._items, self);
                delete self._items[key];
            });
            return this;
        }
    }
    Error = class {
        error_id = null;

        setErrorId(error_id) {
            this.error_id = $('#' + error_id);
            return this;
        }

        showError(msg) {
            this.error_id.html(msg);
            this.error_id.removeClass('d-none');
        }

        hideError() {
            this.error_id.addClass('d-none');
        }
    }

    VariablesFunctions = class {
        locked_status = 0;
        active_status = 1;
        unexpected_status = 2;
        not_mapped_status = 3;
        bundle_status = 4;

        statuses_badge = {};

        type;
        special_objects = {};

        constructor(type) {
            this.type = type;
            this.statuses_badge[this.locked_status] =  "<span class=\"badge badge-danger\">Locked</span>";
            this.statuses_badge[this.active_status] =  "<span class=\"badge badge-success\">Active</span>";
            this.statuses_badge[this.unexpected_status] =  "<span class=\"badge badge-danger\">Unexpected</span>";
            this.statuses_badge[this.not_mapped_status] =  "<span class=\"badge badge-danger\">Not mapped</span>";
            this.statuses_badge[this.bundle_status] =  "<span class=\"badge badge-primary\">Bundle</span>";
        }

        getInitFunc = () => {
            if (this.type === 'ASN' || this.type === 'RASN') {
                return (add_value_to_table, items) => {
                    for (let item of items)
                        add_value_to_table({
                            id: item['id'],
                            barcodes: item['barcodes'],
                            name: item['name'],
                            quantity: item['quantity'],
                            state: item['state'],
                        });
                }
            } else if (this.type === 'ASNView') {
                return (add_value_to_table, items) => {
                    for (let SKU_id in items) {
                        let SKU = items[SKU_id];
                        add_value_to_table({
                            id: SKU['SKU_id'],
                            barcodes: SKU['barcodes'],
                            name: SKU['name'],
                            quantity: SKU['quantity'],
                            state: SKU['state'],
                        });
                    }
                }
            } else if (this.type === 'EditSKUs') {
                return (add_value_to_table, items) => {
                    for (let item of items)
                        add_value_to_table({
                            id: item['id'],
                            barcodes: item['barcodes'],
                            name: item['name'],
                            quantity: item['quantity'],
                            printing_issues_status: item['printing_issues_status'],
                            state: item['state'],
                            price : item['price'],
                            left: item['left']
                        });
                }
            } else if (this.type === 'bundle') {
                return (add_value_to_table, items) => {
                    for (let item of items)
                        add_value_to_table({
                            id: item['id'],
                            barcodes: item['barcodes'],
                            name: item['name'],
                            market_currency: item['market_currency'],
                            quantity: item['quantity'],
                            state: item['state'],
                        });
                }
            }
            throw new Error('Have not function for type ' + this.type);
        }

        getAfterSelectFunc = () => {
            if (this.type === 'ASN' || this.type === 'ASNView' || this.type === 'RASN' || this.type === 'EditSKUs' || this.type === 'bundle') {
                return (event, ui) => {
                    $(event.target).val(ui.item['barcodes'].join(', '));
                }
            } else if (this.type === 'arrival') {
                return (event, ui) => {
                    $(event.target).val(ui.item['barcodes'][0]);
                    document.getElementById('srchFrm').submit();
                }
            }
            throw new Error('Have not function for type ' + this.type);
        }

        getRenderItemFunc = () => {
            let self = this;
            if (this.type === 'ASN' || this.type === 'ASNView' || this.type === 'RASN' || this.type === 'EditSKUs' || this.type === 'bundle' || this.type === 'arrival') {
                return (ul, item) => {
                    let add_class = parseInt(item.state) === self.active_status ? '' : 'text-danger';
                    return $("<li>").append($("<div>").addClass(add_class).text(item.barcodes.join(', ') + " / " + item.name + " / " + item.customer_ref)).appendTo(ul);
                }
            }
            throw new Error('Have not function for type ' + this.type);
        }

        getSourcesBySupplierIdFunc = (supplier_id, error, url) => {
            if (this.type === 'ASN' || this.type === 'ASNView' || this.type === 'RASN' || this.type === 'EditSKUs' || this.type === 'bundle' || this.type === 'arrival') {
                return (request, response) => {
                    $.getJSON(url, {
                        supplier_id: supplier_id,
                        term: request.term,
                    }, (data) => {
                        response(data.data);
                        if (data.errors !== undefined && data.errors.length > 0)
                            error.showError(data.errors[0])
                    })
                };
            }
            throw new Error('Have not function for type ' + this.type);
        }

        getSearchValueInResponseFunc = () => {
            if (this.type === 'ASN' || this.type === 'ASNView' || this.type === 'RASN' || this.type === 'EditSKUs' || this.type === 'bundle' || this.type === 'arrival') {
                return (self) => {
                    return self._last_response.find(function (element) {
                        let input_value = self._input_id.val();
                        const found = element.barcodes.find((barcode) => barcode === input_value);
                        if (found)
                            return element['id'];
                        return false
                    });
                }
            }
            throw new Error('Have not function for type ' + this.type);
        }

        getEventAfterAddValue = () => {
            let context = this;
            if (this.type === 'ASN' || this.type === 'RASN') {
                return (key, data, items, self) => {
                    let append_to = self.getAppendTo();
                    append_to.parent().removeClass('d-none');
                    $('#send-form').attr("disabled", false);
                }
            } else if ( this.type === 'bundle') {
                return (key, data, items, self) => {
                    if (context.special_objects['UpdateCurrency'] !== undefined) {
                        let update_currency = context.special_objects['UpdateCurrency'];
                        update_currency.setCurrency();
                    }
                }
            } else if (this.type === 'ASNView' /*|| this.type === 'RASN'*/ || this.type === 'EditSKUs') {
                return (key, data, items, self) => {

                }
            }
            throw new Error('Have not function for type ' + this.type);
        }

        getEventAfterRemoveValue = () => {
            let context = this;
            if (this.type === 'ASN') {
                return (key, items, self) => {
                    let size = Object.keys(items).length;
                    if (size === 1) {
                        let append_to = self.getAppendTo();
                        append_to.parent().addClass('d-none');
                        $('#send-form').attr("disabled", true);
                    }
                    if (context.special_objects['BatchesModal'] !== undefined) {
                        let batches_modal = context.special_objects['BatchesModal'];
                        batches_modal.removeSKUId(key);
                    }
                }
            } else if (this.type === 'ASNView' || this.type === 'RASN') {
                return (key, items, self) => {
                    if (context.special_objects['BatchesModal'] !== undefined) {
                        let batches_modal = context.special_objects['BatchesModal'];
                        batches_modal.removeSKUId(key);
                    }
                }
            } else if (this.type === 'bundle') {
                return (key, data, items, self) => {
                    if (context.special_objects['UpdateCurrency'] !== undefined) {
                        let update_currency = context.special_objects['UpdateCurrency'];
                        update_currency.setCurrency();
                    }
                }
            } else if (this.type === 'EditSKUs') {
                return (key, items, self) => {

                }
            }
            throw new Error('Have not function for type ' + this.type);
        }
    }

    initTypeInput(type, supplier_id, error_id, input_id, url) {
        let error = (new this.Error()).setErrorId(error_id);

        this.variables_functions = new this.VariablesFunctions(type);
        let render_input_value_func = this.variables_functions.getAfterSelectFunc();
        let render_item_func = this.variables_functions.getRenderItemFunc();
        let source_func = this.variables_functions.getSourcesBySupplierIdFunc(supplier_id, error, url);
        let search_value_in_response_func = this.variables_functions.getSearchValueInResponseFunc();

        let select_autocomplete = (new this.Select()).setInputId(input_id).setSource(source_func).setRenderItem(render_item_func).setRenderInputValue(render_input_value_func).setError(error);
        select_autocomplete.setSearchValueInResponse(search_value_in_response_func);
        select_autocomplete.initAutocomplete();

        return this;
    }

    runInit(items) {
        this.table.clearTable();
        let add_value_to_table_func = this.table.getFunctionAddValueToTable();
        let init_func = this.variables_functions.getInitFunc();
        init_func(add_value_to_table_func, items);
        return this;
    }

    addSpecialObject(name, object) {
        this.variables_functions.special_objects[name] = object;
        return this;
    }
}