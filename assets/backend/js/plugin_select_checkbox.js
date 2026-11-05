(function($){
    /**
     * @param element
     * @param options
     * @constructor
     */
    function select_checkbox(element, options) {
        this.$element = $(element);
        this.options = $.extend({
            name: this.$element.data('name') || 'select[]',
            on_change: null,
            title_label: 'Select'
        }, options);
        this.elements = options.elements || {};
        this.selected = options.selected || 'all';
        this.id_added = false;

        if(options.selected_source !== undefined) {
            var selected = this.$element.attr('data-selected');

            if(selected === undefined) {
                selected = this.$element.find(options.selected_source).attr('data-selected');
            }
        } else {
            var selected = this.$element.attr('data-selected');
        }

        this.selected = (selected === '["all"]'  || selected === undefined || selected === '')
            ? 'all'
            : $.parseJSON(selected);

        this.init();
    }

    select_checkbox.prototype = {
        _is_checked: function(code) {
            if (typeof this.selected === 'string' && this.selected === 'all') {
                return true;
            }
            // Don't use jquery inArray and native indexOf because this methods use strict equality
            for (var i = 0; i < this.selected.length; i++) {
                if (code == this.selected[i]) {
                    return true;
                }
            }
            return false;
        },
        _is_all: function () {
            if (typeof this.selected === 'string' && this.selected === 'all') {
                return true;
            }

            return Object.keys(this.elements).length == this.selected.length;
        },
        _get_selected_names: function() {
            var selected = [];
            if (this._is_all()) {
                selected = ['All'];
                return selected.join(', ');
            }
            for (var i = 0; i < this.selected.length; i++) {
                var code = this.selected[i];
                selected.push(this.elements[code]);
            }
            return selected.join(', ');
        },
        _get_unique_id: function () {
            var id = this.$element.attr('id');
            if (!id) {
                var i;
                for (i = 0; i < 10; i++) {
                    id = 'select_checkbox_' + (Math.random() * 1000).toFixed(0);
                    if (!$('#' + id).length) {
                        break;
                    }
                }
                // unique id not found for 10 iterations
                if (i == 10) {
                    return false;
                }
                this.$element.attr('id', id);
            }
            return id;
        },

        _list_element_html: function(code, name, is_checked) {
            var checked = is_checked || this._is_all() ? ' checked ' : '',
                disabled = this._is_all() ? 'disabled   ' : '';

            var control_name = this.options.name.length>6 && this.options.name.substr(-6, 6) === '[code]'
                ? this.options.name.substr(0, this.options.name.length-6) + '[' + code + ']'
                : this.options.name;

            return '<div>' +
                '<label>' +
                '<input ' +
                'class="select_checkbox_one"' +
                'name="' + control_name + '" ' +
                'value="' + code + '" ' +
                'type="checkbox" ' +
                checked +
                disabled +
                '> ' +
                name +
                '</label>' +
                '</div>';
        },

        _all_element_html: function() {
            var checked = this._is_all() ? ' checked ' : '';
            var control_name = this.options.name.length>6 && this.options.name.substr(-6, 6) === '[code]'
                ? this.options.name.substr(0, this.options.name.length-6) + '[]'
                : this.options.name;
            return '<div>' +
                '<label>' +
                '<input ' +
                'class="select_checkbox_all"' +
                'name="' + control_name + '" ' +
                'value="all" ' +
                'type="checkbox" ' +
                checked +
                '> All ' +
                '</label>' +
                '</div>';
        },

        _update_value: function () {
            var $value = this.$block_selected.find('.select_checkbox_value');
            $value.html(this._get_selected_names());
        },

        init: function() {
            this.$element.addClass('js_select_checkbox');
            this.id = this._get_unique_id();
            if (!this.id) {
                if (typeof console != 'undefined') {
                    console.log('Id did not find and did not generate.')
                }
                return;
            }

            var list = '', code, selected = [], is_checked;
            for (code in this.elements) {
                if (this.elements.hasOwnProperty(code)) {
                    is_checked = this._is_checked(code);
                    list += this._list_element_html(code, this.elements[code], is_checked);
                    if (this._is_checked(code)) {
                        selected.push(this.elements[code]);
                    }
                }
            }

            this.$label_title = $('<label>' + this.options.title_label + '</label>');

            this.$block_selected = $(
                '<div class="select_checkbox form-control"> ' +
                '<span class="caret pull-right select_checkbox_caret"></span>' +
                '<div class="select_checkbox_value">' + this._get_selected_names() + '</div>' +
                '</div>'
            );
            this.$block_dropdown = $(
                '<div class="select_checkbox_dropdown dropdown_hide">' +
                this._all_element_html() +
                list +
                '</div>'
            );

            this.$element.append(this.$label_title).append(this.$block_selected).append(this.$block_dropdown);

            this.$all_checkbox = this.$block_dropdown.find('input.select_checkbox_all');
            this.$list_checkboxes = this.$block_dropdown.find('input.select_checkbox_one');

            // Init actions
            var self = this;
            this.$block_selected.click(function(event) {
                event.stopPropagation();
                var is_visible = self.$block_dropdown.is(':visible');
                // Hide all opened select_checkbox_dropdown
                Dropdown.hide_all();
                if (!is_visible) {
                    self.$block_dropdown.slideDown(300);
                }
            });
            this.$block_dropdown.click(function(event){
                event.stopPropagation();
            });

            this.$all_checkbox.change(function() {
                if ($(this).prop('checked') == false) {
                    self.$list_checkboxes.prop('disabled', false).prop('checked', false);
                    self.selected = [];
                } else {
                    self.$list_checkboxes.prop('disabled', true).prop('checked', true);
                    self.selected = 'all';
                }
                if (self.options.on_change) {
                    self.$list_checkboxes.each(function() {
                        var code = $(this).attr('value');
                        self.options.on_change(code, self._is_all(), $(this));
                    });
                }
                self._update_value();
            });

            this.$list_checkboxes.change(function(event) {
                var code = $(this).attr('value');
                if ($(this).prop('checked')) {
                    self.selected.push(code);
                    if (self._is_all()) {
                        self.$all_checkbox.prop('checked', true);
                        self.$list_checkboxes.prop('checked', true).prop('disabled', true);
                    }
                } else {
                    self.selected.splice(self.selected.indexOf(code), 1);
                }
                if (self.options.on_change) {
                    self.options.on_change(code, self._is_all(), $(this));
                }
                self._update_value();
            });
        },

        destroy: function() {
            if (this.id_added) {
                this.$element.attr('id', '');
            }
            this.$label_title.remove();
            this.$block_selected.remove();
            this.$block_dropdown.remove();
        }
    };

    // jQuery plugin interface
    $.fn.select_checkbox = function(options) {
        if(typeof options === 'string') {
            var args = Array.prototype.slice.call(arguments, 1);
        }
        return $(this).each(function() {
            var $this = $(this), instance = $this.data('select_checkbox');
            if(!instance) {
                $this.data('select_checkbox', new select_checkbox(this, options));
            } else {
                // if instance already created call method
                if(typeof options === 'string') {
                    // slice arguments to leave only arguments after function name
                    instance[options].apply(instance, args);
                }
            }
        });
    };

}(jQuery));