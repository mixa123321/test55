<?php

use app\core\helpers\Html;
use app\core\helpers\modal\InfoModal;

$modal_orders = new InfoModal('listOrderSubstitutions', 'List of order substitutions', InfoModal::SIZE_XL);
echo $modal_orders->addBodyAttributes(['id' => 'bodyOrderSubstitutions'])->put();

?>
<style>
    .blockOrderBundles {
        border-radius: 5px;
        border: 1px solid #cccccc;
    }
</style>
<script>
    let loaded_subst = false;
    const substitutionTemplate = substitution => {
        const name = substitution[0].substitution_barcode || '';
        const items = substitution[0].items || 0;
        return `
                    <div class="m-2 p-3 blockOrderBundles">
                        <div class="row">
                            <div class="col">
                                <h5 class="title"><b>${html_escape(name)}</b></h5>
                            </div>
                            <div class="col-auto">${+items} Item${items > 1 ? 's' : ''}</div>
                        </div>
                        <table class="table table-hover mb-0 tableOrderBundles">
                        <colgroup><col width="40%"><col width="40%"><col width="20%"></colgroup>
                        <thead>
                        <tr>
                            <th>Barcode</th>
                            <th>Name</th>
                            <th>Quantity</th>
                        </tr>
                        </thead>
                            <tbody>
            ${((skus) => {
                return skus.reduce((temp, sku) => {
                    temp += `<tr>
                                 <td>${html_escape(sku.barcode)}</td>
                                 <td>${html_escape(sku.SKU_name)}</td>
                                 <td>${html_escape(sku.quantity)}</td>
                             </tr>`
                return temp;
            }, '');
        })(substitution)}
                            </tbody>
                        </table>
                    </div>`
    };

    $('.js-btn-order-substitutions').click(function (e) {
        if (!loaded_subst) {
            $("#bodyOrderSubstitutions").children().remove();
            $.ajax({
                url: "/orders/get_substitutions",
                method: "POST",
                data: {order_id: $(e.currentTarget).data('order_id')},
                dataType: "json",
                success: function (response) {
                    try {
                        if (response.result === 'success') {
                            if (response.records.length == 0)
                                $("#bodyOrderSubstitutions").append('<?= addslashes(Html::error('History of substitutions are not found')); ?>');
                            else
                                Object.values(response.records).forEach(substitution => {
                                    $("#bodyOrderSubstitutions").append(substitutionTemplate(substitution))
                                });
                            loaded = true
                        } else
                            throw new Error()
                    } catch (e) {
                        document.location.reload();
                    }
                }
            });
        }
        <?= $modal_orders->show(); ?>
    });
</script>