<?php

use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;

$modal_select_supplier = new ConfirmModal('selectSupplier', 'Select company');
echo $modal_select_supplier
    ->addContent(
        Html::dropDownList('supplierId', [], null, [
            'id' => 'supplierId',
            'class' => 'form-control form-control-lg custom-select',
        ]))
    ->changeNo(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_CANCEL | ConfirmModal::BUTTON_CONFIRM, ['aria-label' => 'Close', 'id' => 'cancelSupplier'], 'Cancel')
    ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE, ['disabled' => 'disabled', 'id' => 'selectSupplier'], 'Select')
    ->put();

?>
<script>
    class GetSuppliersByBarcode {
        duplicates;
        _setSupplierId(id) {
            $('#supplier_id').val(id);
        }
        _after_select_supplier = (supplier_id) => {

        };
        setDuplicates(duplicates) {
            this.duplicates = duplicates;
        }
        setAfterSelectSupplier(after_select_supplier) {
            this._after_select_supplier = after_select_supplier;
            return this;
        }
        showSuppliersByBarcode = function () {
            let self = this;
            if (self.duplicates.length > 1) {
                $('#supplierId option').remove();
                $('#supplierId').append($('<option>', {value: 0}).text('Select company...'));
                self.duplicates.forEach(function (item) {
                    $('#supplierId').append($('<option>', {value: item.supplier_id}).text(item.s_name));
                });
                <?= $modal_select_supplier->show(); ?>;
                return true;
            } else {
                self._setSupplierId(self.duplicates[0].supplier_id);
                return false;
            }
        }
        addEvents = function () {
            let self = this;
            $('#supplierId').change(function () {
                $('#selectSupplier').prop('disabled', $('#supplierId').val() == 0);
            });

            $('#cancelSupplier').click(function () {
                <?= $modal_select_supplier->hide(); ?>
                let search = $('#search');
                search.val('');
                search.focus();
            });

            $('#selectSupplier').click(function () {
                let supplier_id = parseInt($('#supplierId').val());
                self._setSupplierId(supplier_id);
                <?= $modal_select_supplier->hide(); ?>
                for (const duplicate of self.duplicates) {
                    if (duplicate.supplier_id === supplier_id) {
                        self._after_select_supplier(duplicate);
                        break;
                    }
                }
            });
        }
    }
</script>
