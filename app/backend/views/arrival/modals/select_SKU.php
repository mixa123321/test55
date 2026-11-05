<?php

use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\modal\Modal;
use app\core\helpers\View;

/**
 * @var Modal $modal
 * @var int $supplier_id
 * @var mixed $filter_type
 */

$cancel_label = View::getTranslate('Cancel');
$select_label = View::getTranslate('Select');
$modal->addCustomButtons(
    Html::button($cancel_label, Html::TYPE_BUTTON__CANCEL, ['data-dismiss' => 'modal'])
    . Html::button($select_label, Html::TYPE_BUTTON__PRIMARY, ['disabled' => true, 'type' => 'submit'])
);
?>

<div class="js-loader text-center">
    <div class="spinner-border text-primary" role="status">
        <span class="sr-only"><?= View::getTranslate('Loading'); ?>...</span>
    </div>
</div>
<div class="js-error alert alert-danger d-none"></div>
<form class="d-none">
    <select class="form-control form-control-lg custom-select"></select>
</form>

<script>
    $(function() {
        const $modal = $('<?= $modal->getSelector(); ?>'),
            $form = $modal.find('form'),
            $select = $form.find('select'),
            $loader = $modal.find('.js-loader'),
            $error = $modal.find('.js-error'),
            $submit = $modal.find('[type="submit"]');
            supplierId = <?= $supplier_id; ?>;
        $modal.on('show.bs.modal', function() {
            $loader.removeClass('d-none');
            $form.addClass('d-none');
            $select.prop('disabled', true);
            $error.addClass('d-none');
            var data = {
                id: <?= $modal->jsGetBodyAttribute('data-ASN_id'); ?>,
                barcode: <?= $modal->jsGetBodyAttribute('data-barcode'); ?>,
                <?= Filter::getFilterTypeKey('barcode'); ?>: <?= $filter_type; ?>,
            };
            $.ajax({
                url: '/goods_in/arrival_skus',
                method: 'POST',
                dataType: 'json',
                data: data,
            }).done(function(json) {
                if (json.error) {
                    $error.text(json.error).removeClass('d-none');
                    return;
                }
                if (json.SKUs.length === 0) {
                    $error.text('No SKUs found').removeClass('d-none');
                    return;
                }
                $select.prop('disabled', false);
                $select.children().remove().end();
                $select.append($('<option>', { value: 0 }).text('Select SKU...'));
                for (var i in json.SKUs) {
                    var item = json.SKUs[i];
                    var caption = item.name + ' (' + item.barcode + ')';
                    $select.append($('<option>', { value: item.barcode }).text(caption));
                }
                $form.removeClass('d-none');
            }).always(function() {
                $loader.addClass('d-none');
            });
        });
        $select.on('change', function() {
            $submit.prop('disabled', this.value === '0');
        });
        $submit.on('click', function() {
            var barcode = $select.val();
            if (barcode === '0') {
                $select.focus();
                return false;
            }
            $modal.modal('hide');
            var id = <?= $modal->jsGetBodyAttribute('data-ASN_id'); ?>;
            window.location = '/goods_in/arrival_skus/?id=' + id + '&sku=' + barcode + '&supplier_id=' + supplierId;
        });
    });
</script>