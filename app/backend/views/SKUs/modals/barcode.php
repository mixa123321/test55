<?php

use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\Barcode128Rule;
/**
 * @var ConfirmModal $modal
 */
?>

<form method="POST" action="/SKUs/quick_add_barcode">
    <input type="hidden" name="id">
    <input type="hidden" name="supplier">
    <input type="hidden" name="flash" value="1">
    <p class="form-control-static text-lg">Enter the SKU barcode for adding</p>
    <div class="form-group-element">
        <input type="text" name="barcode" id="merge_barcode" autocomplete="off" required class="form-control form-control-lg" placeholder=" ">
        <label for="merge_barcode">Barcode <span class="text-danger">*</span></label>
    </div>
    <div class="js-success alert alert-success mt-2 mb-0 d-none">

    </div>
    <div class="js-error alert alert-danger mt-2 mb-0 d-none">
        Enter new barcode for adding
    </div>
    <div class="js-loading alert mt-2 mb-0 text-center d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
</form>


<script>
    $(function() {
        const $modal = $('<?= $modal->getSelector(); ?>'),
            $form = $modal.find('form')
            $submit = $modal.find('button[type="submit"]'),
            $error = $modal.find('.js-error'),
            $success = $modal.find('.js-success'),
            $loading = $modal.find('.js-loading'),
            $barcode = $('[name="barcode"]', $form);
        $modal.on('show.bs.modal', function () {
            $('[name="id"]', $form).val(<?= $modal->jsGetBodyAttribute('id') ?>);
            $('[name="supplier"]', $form).val(<?= $modal->jsGetBodyAttribute('supplier_id') ?>);

            var skuName = <?= $modal->jsGetBodyAttribute('name') ?>;
            $("#barcodeModalConfirm .modal-title").html('Add barcode for SKU <br> <strong>' + html_escape(skuName) + "</strong>");

            $error.html('Enter barcode for merge').addClass('d-none');
            $loading.addClass('d-none');
            $('[name="barcode"]', $form).val('');
            $submit.prop('disabled', true);
        }).on('shown.bs.modal', function () {
            $('[name="barcode"]', $form).focus();
        });
        $barcode.on('input', function () {
            $submit.prop('disabled', !$barcode.val());
        });
        $submit.on('click', function (e) {
            e.preventDefault();
            barcode = $.trim($barcode.val());
            if (!barcode) {
                $error
                    .html('Enter new barcode for adding')
                    .removeClass('d-none');
                return false;
            }
            reg_exp = new RegExp('<?= addslashes(Barcode128Rule::REGEXP_BARCODE_128); ?>');

            if (!reg_exp.test(barcode)) {
                $barcode.val('');
                $barcode.focus();
                $error
                    .html('Not allowed symbols')
                    .removeClass('d-none');
                return false;
            }

            $error.addClass('d-none');
            $form.submit();
        });
        $form.on('submit', function (e) {
            e.preventDefault();

            barcode = $.trim($barcode.val());
            if (!barcode) {
                $error
                    .html('Enter new barcode for adding')
                    .removeClass('d-none');
                return false;
            }
            reg_exp = new RegExp('<?= addslashes(Barcode128Rule::REGEXP_BARCODE_128); ?>');

            if (!reg_exp.test(barcode)) {
                $barcode.val('');
                $barcode.focus();
                $error
                    .html('Not allowed symbols')
                    .removeClass('d-none');
                return false;
            }

            $error.addClass('d-none');
            $submit.prop('disabled', true);
            $loading.removeClass('d-none');
            $.ajax({
                url: $form.attr('action'),
                type: $form.attr('method'),
                data: $form.serialize(),
                dataType: 'json',
            })
                .done(function (data, textStatus, jqXHR) {
                    if (typeof data == 'string') {
                        $error
                            .html('An error occurred')
                            .removeClass('d-none');
                        return;
                    }
                    if (data.success) {

                        $success.html(data.success)
                            .removeClass('d-none');
                        window.scrollTo(0, 0);

                        setTimeout(() => {
                            $modal.modal('hide');
                            window.location.reload();
                        }, 2000);

                    } else {
                        $error
                            .html(data.error)
                            .removeClass('d-none');
                    }
                })
                .fail(function (jqXHR, textStatus, errorThrown) {
                    $error
                        .html('An error occurred')
                        .removeClass('d-none');
                })
                .always(function () {
                    $submit.prop('disabled', false);
                    $loading.addClass('d-none');
                });
        });
    });
</script>