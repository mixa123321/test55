<?php

use app\core\helpers\modal\ConfirmModal;

/**
 * @var ConfirmModal $modal
 */
?>

<form method="POST" action="/SKUs/quick_merge">
    <input type="hidden" name="id">
    <input type="hidden" name="supplier">
    <input type="hidden" name="flash" value="1">
    <p class="form-control-static text-lg">Enter the SKU barcode for merging</p>
    <div class="form-group-element mb-2">
        <input type="text" name="barcode" id="merge_barcode" required class="form-control form-control-lg" placeholder=" ">
        <label for="merge_barcode">Barcode <span class="text-danger">*</span></label>
    </div>
    <div class="form-check">
        <input type="checkbox" name="calculate_max_capacity" value="1" id="calculate_max_capacity" class="form-check-input">
        <label for="calculate_max_capacity">Recalculate max capacity </label>
    </div>
    <div class="js-error alert alert-danger mt-0 mb-0 d-none">
        Enter barcode for merge
    </div>
    <div class="js-loading alert mt-2 mb-0 text-center d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <div class="diff_skus mt-2">

    </div>
</form>
<script>
    $(function() {
          var $modal = $('<?= $modal->getSelector(); ?>');
          var $form = $modal.find('form');
          var $submit = $modal.find('button[type="submit"]');
          var $error = $modal.find('.js-error');
          var $loading = $modal.find('.js-loading');
          var $barcode = $('[name="barcode"]', $form);

        $modal.on('show.bs.modal', function () {
            $('[name="id"]', $form).val(<?= $modal->jsGetBodyAttribute('id') ?>);
            $('[name="supplier"]', $form).val(<?= $modal->jsGetBodyAttribute('supplier_id') ?>);
            $error.html('Enter barcode for merge').addClass('d-none');
            $loading.addClass('d-none');
            $('[name="barcode"]', $form).val('');
            $submit.prop('disabled', true);
            $('.diff_skus').html('');
        }).on('shown.bs.modal', function () {
            $('[name="barcode"]', $form).focus();
        });
        $barcode.on('input', function () {
            $submit.prop('disabled', !$barcode.val());
            $('.diff_skus').html('');
        });
        $submit.on('click', function (e) {
            e.preventDefault();
            if (!$barcode.val()) {
                $error
                    .html('Enter barcode for merge')
                    .removeClass('d-none');
                return false;
            }
            $error.addClass('d-none');
            $form.submit();
        });
        $form.on('submit', function (e) {
            e.preventDefault();
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
                $('.diff_skus').html('');

                if (typeof data == 'string') {
                    $error
                        .html('An error occurred')
                        .removeClass('d-none');
                    return;
                }
                if (data.success) {
                    $modal.modal('hide');
                    // scroll to top for flash
                    window.scrollTo(0, 0);
                    window.location.reload();
                } else {
                    if (data.diff != undefined) {
                        $('.diff_skus').append('<table class="table table-hover mb-0"><tbody>' +
                            '<thead><tr><th>Property</th><th>Merging Values</th><th>Existing Values</th></tr></thead>' +
                            '</tbody></table>');
                        const tbody = $('.diff_skus table tbody');

                        for (const key in data.diff) {
                            if (data.diff.hasOwnProperty(key)) {
                                const values = data.diff[key];

                                const k = html_escape(key);
                                const oldValue = html_escape(values.value.old);
                                const newValue = html_escape(values.value.new);
                                var oldValueHtml = values.img != undefined && values.img.old ? '<img style="vertical-align: top;" src="' + html_escape(values.img.old) + '" width="80" />' : oldValue;
                                var newValueHtml = values.img != undefined && values.img.new ? '<img style="vertical-align: top;" src="' + html_escape(values.img.new) + '" width="80" />' : newValue;

                                tbody.append(
                                    '<tr>' +
                                    '<td><strong>' + html_escape(values.title) + '</strong></td>' +
                                    '<td class="form-group"><div class="form-check form-check-inline" style="display: block;"><input class="form-check-input" style="position: relative; top: 3px;" type="radio" name="diff[' + k + ']" value="' + oldValue + '" id="' + k + '_old"><label class="form-check-label" for="' + k + '_old">' + oldValueHtml + '</label></div></td>' +
                                    '<td class="form-group"><div class="form-check form-check-inline" style="display: block;"><input class="form-check-input"  style="position: relative; top: 3px;" type="radio" checked name="diff[' + k + ']" value="' + newValue + '" id="' + k + '_new"><label class="form-check-label"  for="' + k + '_new">' + newValueHtml + '</label></div></td>' +
                                    '</tr>'
                                );
                            }
                        }
                    }
                    $error
                        .text(data.error)
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