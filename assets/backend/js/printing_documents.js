$(function () {

    if(doc_exist && !isDev) {
        printPdfDocument(doc_url, timer_print_pdf, true);
    }

    if(toPrintPackingSlip) {
        $('#btn_print_packing_slip').click(function () {
            printPdfDocument(packing_slip_url, timer_print_pdf);
        });
    }
    if (doc_exist) {
        $('#btn_print_doc').click(function () {
            printPdfDocument(doc_url, timer_print_pdf);
        });
    }

    $('.js-retainme-print').click(function () {
        var this_btn = $(this);
        this_btn.text("Printing...");
        this_btn.prop('disabled', true);
        $.get('/orders/print_retain_me?id=' + $(this).data('id') + '&source_id=' + $(this).data('source_id'), function(response) {
            if (response == 'success') {
                this_btn.hide();
                $('.js-retain-me-success').show();
                $('.js-retain-me-warning').hide();
            } else {
                this_btn.text("Try printing again");
                this_btn.prop('disabled', false);
                $('.js-retain-me-success').hide();
                $('.js-retain-me-warning').show();
            }
        });
    });

    $('.show_serial_numbers').click(function () {
        let SKU_id = $(this).data("sku-id");
        let package_id = $(this).data("package-id");
        let tbody_serial_numbers = $('#tbody_serial_numbers');
        tbody_serial_numbers.empty();
        for (const number of serial_numbers[SKU_id][package_id]) {
            let input = '<input readonly type="text" class="serial-number-changes form-control w-100 mb-1"  value="' + html_escape(number) + '">'
            tbody_serial_numbers.append('<div class="row"><div class="col-12 form-group-element">' + input + '</div></div>');
        }
        $('#serialNumbersModalInfo').modal('show');
    });

    $('#unpackBoxes').click(function() {
        hideUnpack();
        $('#unpackModalConfirm').modal('show');
    });

    $('.js-btn-conf').click(function() {
        hideUnpack();
        $('.js-cancel-unpack').prop('disabled', true);
        $('.js-btn-conf').prop('disabled', true);
        $.ajax({
            url: '/pack/cancel',
            method: 'POST',
            dataType: 'json',
            data: {order_id: order_id },
            success: function (json) {
                if ('error' in json) {
                    showError('manualUnpack', json.error, 3000);
                    $('.js-cancel-unpack').prop('disabled', false);
                    $('.js-btn-conf').prop('disabled', false);
                } else {
                    if (json.success != '-') {
                        $('#unpackModalConfirm').modal('hide');
                        $('#warningText').html(json.success);
                        showWarningUnpack();
                    } else
                        location = back_url;
                }
            },
            error: function (json) {
                showError('cancelError', 'Connection error!', 3000);
                $('.js-cancel-unpack').prop('disabled', false);
                $('.js-btn-conf').prop('disabled', false);
            }
        });
    });

    function hideUnpack()
    {
        $('#manualUnpack').html('');
        if (!$('#manualUnpack').prop('classList').contains('d-none'))
            $('#manualUnpack').prop('classList').add('d-none');
    }
});