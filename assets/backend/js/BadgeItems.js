// The following HTML elements are required
// ========================================
// <div id="[name]"></div>
// <div class="[name]s-content d-none"></div>
class BadgeItems {
    constructor(items, name, func) {
        this.setItems(items);
        this.name = name;
        this.funcRemove = func;
    }
    setItems(items) {
        this.items = items;
    }
    addEvents() {
        let self = this;
        $(document).on("click", '.active-' + this.name + 's', function () {
            if (self.funcRemove !== null)
                self.funcRemove(this);
            else
                self.removeItem(this.innerText);
        });
        return this;
    }
    removeItem(item) {
        this.removeButton(item);
        this.removeHidden(item);
    }
    checkExist(item) {
        let exist_value = false;
        $('.' + this.name + 's-values').each(function () {
            if (this.value.toUpperCase() === item.toUpperCase())
                exist_value = true;
        });
        return exist_value;
    }
    getItem(item) {
        let object = null, self = this;
        this.items.some(function (value) {
            if (self.getName(value).toUpperCase() === item.toUpperCase()) {
                object = value;
                return true;
            }
        });
        return object;
    }
    removeButton(item) {
        $('.active-' + this.name + 's[name="' + item + '"]').remove();
    }
    removeHidden(item) {
        $('.' + this.name + 's-values[value="' + item + '"]').remove();
    }
    reFill() {
        $('.' + this.name + 's-values-common').remove();
        $('.active-' + this.name + 's').remove();
        for (const item of this.items) {
            this.appendButton(item);
            this.appendHidden(item);
        }
    }
    getState(item) {
        return 'success';
    }
    getName(item) {
        return item.name;
    }
    getButton(item) {
        return '<span class="p-2 mr-1 mb-1 text-lg badge badge-' + html_escape(this.getState(item)) + ' active-' + this.name + 's" style="cursor: pointer" name="' + html_escape(this.getName(item)) + '">' + html_escape(this.getName(item)) + '</span>';
    }
    getHidden(item) {
        return '<input type="hidden" class="' + this.name + 's-values-common ' + this.name + 's-values" name="' + this.name + 's[]" value="' + html_escape(item) + '">';
    }
    appendButton(item) {
        $('#' + this.name + 's').append(this.getButton(item));
    }
    appendHidden(item) {
        $('.' + this.name + 's-content').append(this.getHidden(item));
    }
}
