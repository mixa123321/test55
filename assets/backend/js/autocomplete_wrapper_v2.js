class AutocompleteWrapperV2 {
    func_show_error = (errors) => {};
    func_hide_error = () => {};
    func_event_after_select = (item, self) => {}
    func_event_add_selected = (item, self) => {}
    func_event_add_searched = (item, self) => {}

    _input_obj = null;
    _url = '';
    _min_len = 1;
    _supplier_id = 0;

    _last_response = null;
    _last_searching = null;

    _locked_status = 0;
    _active_status = 1;
    _unexpected_status = 2;
    _not_mapped_status = 3;
    _bundle_status = 4;

    setSupplierId = (supplier_id) => {
        this._supplier_id = supplier_id;
        return this;
    }

    initAutocomplete(input, url) {
        this._input_obj = input;
        this._url = url;
        let self = this;
        let instance = this._input_obj.autocomplete({
            source: self._getSourceFunc(),
            minLength: this._min_len,
            select: (event, ui) => {
                self._renderInputValue(event, ui);
                self.func_event_after_select(ui.item, self);
                return false;
            },
            search: (event, ui) => {
                self.func_hide_error();
            },
            response: (event, ui) => {
                self._last_response = ui.content;
                self._last_searching = self._input_obj.val();
            },
        }).autocomplete("instance");
        instance._renderItem = self._renderItem();
        let rendered_value = self._rendered_value();
        instance._renderMenu = function (ul, items) {
            $(ul).css("z-index", 2000);
            let self = this;
            let all_rendered_value = [];
            $.each(items, function (index, item) {
                item.rendered_value = rendered_value(item);
                if (all_rendered_value.includes(item.rendered_value)) {
                    return;
                }
                all_rendered_value.push(item.rendered_value);
                self._renderItemData(ul, item);
            });
        }
        return this;
    }

    addEventInputEnter() {
        if (!this._input_obj)
            return this;
        let self = this;
        this._input_obj.keydown(function (e) {
            if (e.keyCode !== 13)
                return null;
            e.preventDefault();
            self._addValue();
        });
        return this;
    }

    addEventButtonClick(button_obj) {
        if (!this._input_obj)
            return this;
        let self = this;
        button_obj.click(function (e) {
            e.preventDefault();
            self._addValue();
        });
        return this;
    }

    getDuplicateValue(item) {
        let search_barcodes = item.barcodes;
        let duplicates = [item];
        for (let last_response of this._last_response) {
            if (item.id === last_response.id)
                continue;
            let intersect_barcodes = last_response.barcodes.filter(value => search_barcodes.includes(value));
            if (intersect_barcodes.length === 0)
                continue;
            duplicates.push(last_response);
        }
        return duplicates;
    }

    _getSourceFunc() {
        let self = this;
        return (request, response) => {
            let param = {};
            if (request.term)
                param['term'] = request.term;
            if (self._supplier_id)
                param['supplier_id'] = self._supplier_id;
            $.getJSON(self._url, param, (data) => {
                response(data.data);
                if (data.errors !== undefined && data.errors.length > 0)
                    self.func_show_error(data.errors);
            })
        };
    }

    _renderInputValue(event, ui) {
        $(event.target).val(ui.item['barcodes'].join(', '));
    }

    _renderItem() {
        let self = this;
        return (ul, item) => {
            let add_class = parseInt(item.state) === self._active_status ? '' : 'text-danger';
            return $("<li>").append($("<div style='border:0;margin: 0;'>").addClass(add_class).text(item.rendered_value)).appendTo(ul);
        };
    }

    _rendered_value() {
        return (item) => {
            return item.barcodes.join(', ') + " / " + item.name + " / " + item.customer_ref;
        };
    }

    _addValue() {
        if (!this._input_obj.val()) {
            this.func_show_error("Please enter SKU barcode");
            return null;
        }
        let item = this._input_obj.autocomplete("instance").selectedItem;
        if (item) {
            this._hideAutoComplete();
            this.func_event_add_selected(item, this);
            return null;
        }
        this._checkIsSearching(this, function (self) {
            let item = self._searchValueInResponse(self);
            if (item) {
                self._hideAutoComplete();
                self.func_event_add_searched(item, self);
                return null;
            }
            self.func_show_error('SKU not found');
            self._fixPosition();
        }, 1)
    }


    _hideAutoComplete() {
        this._input_obj.autocomplete("instance").selectedItem = undefined;
        this._input_obj.autocomplete('close');
        this._input_obj.val('');
    }

    _checkIsSearching(self, func, count) {
        if (self._last_searching !== self._input_obj.val()) {
            if (count > 500)
                return;
            count++;
            window.setTimeout(self._checkIsSearching, 200, self, func, count);
            return;
        }
        func(self);
    }

    _searchValueInResponse = (self) => {
        return self._last_response.find(function (element) {
            let input_value = self._input_obj.val();
            const found = element.barcodes.find((barcode) => barcode === input_value);
            if (found)
                return element['id'];
            return false
        });
    }

    _fixPosition() {
        let top = this._input_obj.offset().top + this._input_obj.outerHeight();
        let widget = this._input_obj.autocomplete("widget");
        widget.css('top', top);
        widget.css('width', 'auto');
    }
}