<?php

use app\core\helpers\modal\InfoModal;

?>

<style>
    .blockOrderBundles {
        border-radius: 5px;
        border: 1px solid #cccccc;
    }
</style>

<?php
$modal = new InfoModal('listOrderBundles', 'List of order bundles', InfoModal::SIZE_LG);
echo $modal->addBodyAttributes(['id' => 'bodyOrderBundles'])->put();
?>

<script>

    let loaded = false;

    const bundleTemplate = bundle => {
        const name = bundle[0].bundle_name || '';
        const items = bundle[0].items || 0;
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
                temp += `<tr class="${(sku.state !== undefined && sku.state == '0') ? 'text-danger' : ''}">
                                                        <td>${html_escape(sku.barcode)}</td>
                                                        <td>${html_escape(sku.SKU_name)}</td>
                                                        <td>${html_escape(sku.quantity)}</td>
                                                    </tr>`
                return temp;
            }, '')
        })(bundle)}
                            </tbody>
                        </table>
                    </div>`
    }

    $('.js-btn-order-bundles').click(function (e) {
        if (!loaded)
            $.ajax({
                url: "/orders/get_bundles",
                method: "POST",
                data: {order_id: $(e.currentTarget).data('order_id')},
                dataType: "json",
                success: function (response) {
                    try {
                        if (response.result === 'success') {
                            Object.values(response.records).forEach(bundle => {
                                $("#bodyOrderBundles").append(bundleTemplate(bundle))
                            });
                            loaded = true
                        } else
                            throw new Error()
                    } catch (e) {
                        document.location.reload();
                    }
                }
            });
        <?= $modal->show(); ?>
    })
</script>