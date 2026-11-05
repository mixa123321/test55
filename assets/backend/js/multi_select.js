class MultiSelect {
    constructor(element) {
        this.element = $(element);
        this.id = this.element.attr('id');
        this.name = this.element.attr('name');
        this.selected = this.element.data('selected') || [];
        if (typeof this.selected === 'string') {
            this.selected = this.selected.split(',');
        }
        this.allowDuplicates = this.element.data('allow-duplicates') || false;
        this.allowEmpty = this.element.data('allow-empty') || true;
        this.emptyValue = this.element.data('empty-value');
        this.regexp = this.element.data('regexp') ? new RegExp(this.element.data('regexp')) : null;
        
        this.init();
    }

    init() {
        // Create container if not exists
        if (!this.element.parent().hasClass('multi-select-container')) {
            this.element.wrap('<div class="multi-select-container"></div>');
        }

        // Move label into container if it exists
        const label = $(`label[for="${this.id}"]`);
        if (label.length) {
            label.appendTo(this.element.parent());
        }

        // Change select element's name to avoid conflicts
        if (this.element.is('select')) {
            this.element.attr('name', this.name.replace('[]', '_select'));
        }
        
        // Create selected items container
        this.element.after(`
            <div class="alert alert-danger d-none" id="${this.id}_error"></div>
            <div id="${this.id}_selected">
                ${this.getSelectedItemsHtml()}
            </div>
        `);

        // Initialize elements
        this.errorElement = $(`#${this.id}_error`);
        this.selectedContainer = $(`#${this.id}_selected`);
        
        // Add hidden input if needed
        this.updateHiddenInput();
        
        // Update options state
        this.updateOptions();
        
        this.addEvents();
    }

    isEmptyValue(value) {
        return this.emptyValue !== undefined && value == this.emptyValue;
    }

    getSelectedItemsHtml() {
        return this.selected
            .map(value => {
                if (this.isEmptyValue(value)) {
                    return '';
                }
                const option = this.element.find('option').filter(function() {
                    return $(this).val() == value;
                });
                const text = option.length ? option.text() : value;
                return `
                    <button class="btn btn-success btn-icon-split mr-1 mt-1 selected-item" type="button" value="${html_escape(value)}" data-id="${this.id}">
                        <span class="text text-white">${html_escape(text)}</span>
                        <span class="icon text-white"><i class="fas fa-fw fa-times"></i></span>
                    </button>
                `;
            })
            .join('');
    }

    showError(msg) {
        this.errorElement.html(msg || 'Invalid input format');
        this.errorElement.removeClass('d-none');
        this.element.trigger('multiSelect:error', [msg]);
    }

    hideError() {
        this.errorElement.addClass('d-none');
        this.element.trigger('multiSelect:error', [null]);
    }

    validate(value) {
        // First check regexp if provided
        if (this.regexp && !this.regexp.test(value)) {
            this.showError('Invalid input format');
            return false;
        }

        // Then check custom validation
        const validationResult = this.element.triggerHandler('multiSelect:validate', [value]);
        if (validationResult === false) {
            this.showError('Invalid value');
            return false;
        }
        if (typeof validationResult === 'string') {
            this.showError(validationResult);
            return false;
        }

        this.hideError();
        return true;
    }

    updateOptions() {
        // Get all options except empty value
        const options = this.element.find('option');
        
        // Disable options that are already selected
        options.each((_, option) => {
            const value = $(option).val();
            if (this.selected.includes(value) || this.isEmptyValue(value)) {
                $(option).prop('disabled', true);
            } else {
                $(option).prop('disabled', false);
            }
        });

        // Check if there are any enabled options left
        const hasEnabledOptions = options.filter(':not(:disabled)').length > 0;
        
        // Disable select if no valid options left
        this.element.prop('disabled', !hasEnabledOptions);
    }

    addItem(value) {
        // Don't add if it's the empty value
        if (this.isEmptyValue(value)) {
            return false;
        }

        if (!this.allowDuplicates && this.selected.includes(value)) {
            this.element.trigger('multiSelect:duplicate', [value]);
            return false;
        }

        if (!this.validate(value)) {
            return false;
        }

        // Trigger beforeAdd event and check if it was prevented
        const beforeAddResult = this.element.triggerHandler('multiSelect:beforeAdd', [value, this.selected]);
        if (beforeAddResult === false) {
            return false;
        }

        this.selected.push(value);
        this.updateHiddenInput();
        this.addItemHtml(value);
        this.updateOptions(); // Update options after adding item
        this.element.trigger('change');
        this.element.trigger('multiSelect:add', [value, this.selected]);

        return true;
    }

    removeItem(value) {
        const index = this.selected.indexOf(value);
        if (index > -1) {
            // Trigger beforeRemove event and check if it was prevented
            const beforeRemoveResult = this.element.triggerHandler('multiSelect:beforeRemove', [value, this.selected]);
            if (beforeRemoveResult === false) {
                return;
            }

            this.selected.splice(index, 1);
            this.updateHiddenInput();
            $('.selected-item', this.selectedContainer).filter(function() {
                return $(this).val() == value;
            }).remove();
            this.updateOptions(); // Update options after removing item
            this.element.trigger('change');
            this.element.trigger('multiSelect:remove', [value, this.selected]);
        }
    }

    addItemHtml(value) {
        const option = this.element.find('option').filter(function() {
            return $(this).val() == value;
        });
        const text = option.length ? option.text() : value;
        const html = `
            <button class="btn btn-success btn-icon-split mr-1 mt-1 selected-item" type="button" value="${html_escape(value)}" data-id="${this.id}">
                <span class="text text-white">${html_escape(text)}</span>
                <span class="icon text-white"><i class="fas fa-fw fa-times"></i></span>
            </button>`;
        this.selectedContainer.append(html);
    }

    getHiddenValue() {
        if (this.allowEmpty) {
            return this.selected.join(',');
        }
        return this.selected.filter(value => value !== '' && !this.isEmptyValue(value)).join(',');
    }

    updateHiddenInput() {
        const shouldHaveInput = this.selected.length > 0 || this.allowEmpty;
        const isArrayName = this.name.endsWith('[]');
        
        if (shouldHaveInput) {
            if (!this.hiddenInput) {
                // Remove any existing hidden inputs first
                $(`input[name="${this.name}"]`).remove();
                
                if (isArrayName) {
                    // For array names, create multiple hidden inputs
                    this.hiddenInput = this.selected.map(value => {
                        const input = $('<input type="hidden">').attr('name', this.name).val(value);
                        this.selectedContainer.after(input);
                        return input;
                    })
                    if (this.allowEmpty && this.selected.length == 0) {
                        this.hiddenInput.push($('<input type="hidden">').attr('name', this.name).val(this.emptyValue).appendTo(this.selectedContainer));
                    }
                } else {
                    // For single name, create one hidden input with comma-separated values
                    this.hiddenInput = $('<input type="hidden">').attr({id: this.id + '_hidden', name: this.name}).val(this.getHiddenValue());
                    this.selectedContainer.after(this.hiddenInput);
                }
            } else {
                if (isArrayName) {
                    // Remove existing hidden inputs
                    $(`input[name="${this.name}"]`).remove();
                    
                    // Create new hidden inputs for each selected value
                    this.hiddenInput = this.selected.map(value => {
                        const input = $('<input type="hidden">').attr('name', this.name).val(value);
                        this.selectedContainer.after(input);
                        return input;
                    })
                    if (this.allowEmpty && this.selected.length == 0) {
                        this.hiddenInput.push($('<input type="hidden">').attr('name', this.name).val(this.emptyValue).appendTo(this.selectedContainer));
                    }
                } else {
                    // Update existing single hidden input value
                    this.hiddenInput.val(this.getHiddenValue());
                }
            }
        } else if (this.hiddenInput) {
            // Remove hidden input(s) if they exist and shouldn't
            if (isArrayName) {
                $(`input[name="${this.name}"]`).remove();
            } else {
                this.hiddenInput.remove();
            }
            this.hiddenInput = null;
        }
        
        // Trigger change events
        this.element.trigger('multiSelect:change', [this.selected]);
    }

    setSelected(selected) {
        this.selected = selected;
        this.selectedContainer.html(this.getSelectedItemsHtml());
        this.updateHiddenInput();
    }

    addEvents() {
        if (this.element.is('input')) {
            this.element.change((e) => {
                const value = this.element.val();
                if (value) {
                    if (!this.addItem(value)) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    this.element.val('').focus();
                }
            });
        } else if (this.element.is('select')) {
            this.element.change((e) => {
                const value = this.element.val();
                if (value) {
                    if (!this.addItem(value)) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    this.element.val(this.emptyValue);
                }
            });
        }

        $(document).on('click', `.selected-item[data-id="${this.id}"]`, (e) => {
            const value = $(e.currentTarget).val();
            this.removeItem(value);
        });
    }
}

// Initialize all multi-select components
$(document).ready(() => {
    $('input.multi-select, select.multi-select').each((_, element) => {
        new MultiSelect(element);
    });
}); 