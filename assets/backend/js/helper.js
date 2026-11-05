function convertBarcodeWithPrimaryBarcode(str) {
    var arrayWords = String(str).split(',').map(html_escape);
    if (arrayWords[0] != undefined && arrayWords[0] != '')
        arrayWords[0] = '<span class="primary_barcode">' + arrayWords[0] + '</span>';

    return '<span class="barcodes_list">' + arrayWords.join(",") + '</span>';
}

function html_escape(s) {
    var entity = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': '&quot;',
        "'": '&#39;',
        "/": '&#x2F;'
    };

    return String(s).replace(/[&<>"'\/]/g, function (symbol) {
        return entity[symbol];
    });
}

function template_filter_date_format(value, format) {
    if (!value)
        return '';
    if (typeof value === 'string')
        value = new Date(value);
    if (typeof moment === 'function')
        return moment(value).format(format);
    return value.toLocaleDateString();
}

function template_filter_SKU_barcodes(barcodes)
{
    if (!barcodes || barcodes.length === 0)
        return '';
    if (typeof barcodes === 'string')
        barcodes = barcodes.split(',');
    barcodes = barcodes.map(html_escape);
    barcodes[0] = '<span class="primary_barcode">' + barcodes[0] + '</span>';
    return '<span class="barcodes_list">' + barcodes.join(',') + '</span>';
}

const printPdfDocument = (url_obj, interval = 5000, is_init = false) => {
    if (interval === 0 && is_init) {
        return;
    }
    const printDoc = (url, t = interval) => {
        let frame = document.createElement('iframe');
        frame.style.cssText = 'position: absolute; width: 100vw; height: 100vh; left: -100%; top: 0; visibility: hidden; pointer-events: none; border: none';
        frame.addEventListener('load', () => {
            let frameWindow = frame.contentWindow;
            frameWindow.print();
            if (t !== null && t !== 0) {
                setTimeout(function checkReady() {
                    if (frameWindow.document.readyState === 'complete')
                        setTimeout(() => frame.remove(), t);
                    else setTimeout(checkReady, 250);
                }, 250);
            }
        });
        frame.src = url;
        window.document.body.append(frame);
    }
    if (typeof url_obj === 'string' && url_obj.length > 5)
        printDoc(url_obj)
    else if (Array.isArray(url_obj) && url_obj.length > 0) {
        let i = 0;
        for (let url of url_obj)
            printDoc(url, ++i*interval);
    }
}

$(function () {
    var js_form_submit_keys = [];
    $('.js-form-submit-once').submit(function (e) {
        var submit_key = $(this).data('submit-key');
        if(!(submit_key in js_form_submit_keys)) {
            js_form_submit_keys[submit_key] = true;
            return true;
        }
        e.preventDefault();
        return false;
    });
});

const copyByClick = (value) => {
    var textarea = document.createElement('textarea');
    textarea.textContent = value;
    document.body.appendChild(textarea);

    let selection = document.getSelection();
    let range = document.createRange();
    range.selectNode(textarea);
    selection.removeAllRanges();
    selection.addRange(range);

    let copy_result = document.execCommand('copy');

    selection.removeAllRanges();
    document.body.removeChild(textarea);

    return copy_result;
}

const getPaginationBlock = (append_block, click_class, func) => {
    class PaginationBlock {
        constructor(append_block, click_class, func) {
            this.page = 1;
            this.pages = 0;
            this.append_block = append_block;
            this.click_class = click_class;
            this.func = func;
        }

        setPage(page) {
            this.page = page;
            return this;
        }

        setPages(pages) {
            this.pages = pages;
            return this;
        }

        getPage() {
            return this.page;
        }

        getBlock(add_class, show_value, value) {
            return '<li class="page-item ' + this.click_class + ' ' + add_class + '" data-value="' + value + '"><a style="cursor: pointer;" class="page-link" tabindex="-1">' + show_value + '</a></li>'
        }

        toStart(disabled) {
            let add_class = disabled ? 'disabled' : '';
            return this.getBlock(add_class + ' d-none d-sm-block', '<i class="fas fa-angle-double-left"></i>', 1);
        }

        toPrev(disabled) {
            let add_class = disabled ? 'disabled' : '';
            return this.getBlock(add_class, '<i class="fas fa-angle-left"></i>', this.page - 1);
        }

        threePoints() {
            return this.getBlock('disabled', '<i class="fas fa-ellipsis-h"></i>', 0);
        }

        toPage(number, active) {
            let add_class = active ? 'active' : '';
            return this.getBlock(add_class + ' d-none d-sm-block', number, number);
        }

        toNext(disabled) {
            let add_class = disabled ? 'disabled' : '';
            return this.getBlock(add_class, '<i class="fas fa-angle-right"></i>', this.page + 1);
        }

        toEnd(disabled) {
            let add_class = disabled ? 'disabled' : '';
            return this.getBlock(add_class + ' d-none d-sm-block', '<i class="fas fa-angle-double-right"></i>', this.pages);
        }

        render() {
            let html = '<div class="row justify-content-center mt-2 mb-2 p-0"><nav><ul class="pagination m-0">';

            html += this.toStart(this.page === 1);
            html += this.toPrev(this.page === 1);

            let show_pages = this.getPages();
            if (show_pages[0] !== 1)
                html += this.threePoints();
            for (let show_page of show_pages)
                html += this.toPage(show_page, this.page === show_page);
            if (show_pages[show_pages.length - 1] !== this.pages)
                html += this.threePoints();

            html += this.toNext(this.page === this.pages);
            html += this.toEnd(this.page === this.pages);

            html += '</ul></nav></div>';
            this.append_block.html(html);
            this.addEvent();
        }

        getPages() {
            let page_number = this.page - 2;
            let show_pages = [];
            for (let i = 0; i < 5; i++) {
                if (page_number > 0 && page_number <= this.pages)
                    show_pages.push(page_number);
                page_number++;
            }
            return show_pages;
        }

        addEvent(func) {
            let self = this;
            $('.' + this.click_class).on('click', function () {
                let next_page = $(this).data('value');
                if (next_page < 1 || next_page > self.pages || next_page === self.page)
                    return;
                self.page = next_page;
                self.func();
            });
        }
    }

    return new PaginationBlock(append_block, click_class, func);
}

const sendPostForm = (data, action) => {
    let form = document.createElement("form");
    form.method = "POST";
    form.action = action;
    for (let prop in data) {
        let el = document.createElement("input");
        el.type = "hidden";
        el.name = prop
        el.value = data[prop];
        form.appendChild(el);
    }
    document.body.appendChild(form);
    form.submit();
}

$(function() {
    function fillBarcode(e)
    {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' || e.isDefaultPrevented())
            return;
        var scan = $('.js-scan-barcode');
        if (scan.length === 0)
            return;
        var barcode = $(this).data('scan-barcode'),
            type = $(this).data('scan-type');
        if (!barcode)
            return;
        if (typeof barcode != 'string')
            barcode = barcode.toString();
        if (barcode.indexOf(',') !== -1)
            barcode = barcode.split(',')[0].trim();
        for (var i = 0; i < scan.length; i++) {
            var scanElement = $(scan[i]);
            if ((scanElement.data('scan-type') & type) == type) {
                scanElement.find('input[type=text]').val(barcode);
                if (!$(this).data('no-submit'))
                    scanElement.find('.btn-success').click();
                return;
            }
        }
    }

    $('[data-scan-barcode][data-scan-type]')
        .on('mouseenter', function() {
            var barcode = $(this).data('scan-barcode');
            if (!barcode)
                return;
            var $others = $('[data-scan-barcode="' + barcode + '"]');
            $others.addClass('scan-barcode-hover');
        })
        .on('mouseleave', function() {
            var barcode = $(this).data('scan-barcode');
            if (!barcode)
                return;
            var $others = $('[data-scan-barcode="' + barcode + '"]');
            $others.removeClass('scan-barcode-hover');
        })
        .on('click', fillBarcode);
});