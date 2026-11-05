<?php

use app\core\entities\Order;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

/**
 * @var Order $order
 * @var string $available_skus JSON
 * @var string $skus_info JSON
 * @var string $order_skus_info JSON
 */

?>
<style>
    .ui-widget.ui-widget-content {
        z-index: 2000;
    }
    .ui-autocomplete {
        max-height: 400px;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 20px;
    }
</style>
<div class="modal" tabindex="-1" role="dialog" id="editSkus">
    <div class="modal-dialog modal-lg" role="document">
        <form id="edit_skus_form">
            <input type="hidden" name="order_id" id="order_id" value="<?= $order->getId(); ?>">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit SKUs</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger" id="add_sku_error" style="display: none"></div>
                    <p class="form-control-static text-success"><b><a name="skus">Add SKUs using the search field below</a></b></p>
                    <div class="input-group col-sm-12">
                        <?php (new Scan(['id' => 'search_skus', 'type' => Scan::TYPE__SKU], [
                            'title' => 'Add',
                            'type' => 'button',
                            'id' => 'btn_add_sku',
                            'icon' => 'fas fa-fw fa-plus',
                        ]))->render(); ?>
                    </div>
                    <table class="table table-hover mt-4">
                        <thead>
                        <tr>
                            <th>Barcode</th>
                            <th>Product Name</th>
                            <th>Available in stock</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody id="skus_tbody" style="word-break: break-word;">
                        </tbody>
                    </table>
                    <div id='skusError' class='col bg-danger text-white p-1 rounded text-center mt-1 mb-0 d-none'>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-success" id="updateSkus" type="button">Update</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script type="text/template" id="sku_tr_template">
    <tr>
        <td>{{barcode_html}}</td>
        <td>{{name}}</td>
        <td>
            <span>{{left}}</span>
            <input name="left[{{id}}]" type="hidden" class="form-control" value="{{left}}">
        </td>
        <td><input name="quantity[{{id}}]" type="number" class="form-control sku-qty" value="{{qty}}" data-first-val="{{qty}}" min="1" step="1"></td>
        <td><input name="price[{{id}}]" type="number" class="form-control" value="{{price}}" min="0" step="any"></td>
        <td>
            <button type="button" class="btn btn-danger js-btn-remove-sku" data-sku="{{barcode}}">
                <i class="fas fa-fw fa-trash"></i>
            </button>
        </td>
    </tr>
</script>

<script>
    var global_skus_to_search = <?= $available_skus?>;
    var global_skus_info = <?= $skus_info?>;
    var order_skus = <?= $order_skus_info?>;
</script>
<?= View::include_js(BACKEND_JS, "add_sku.js")?>
<script>

    let order_status = <?= (int)$order->getStatus(); ?>;
    let query_status = <?= (int)Order::QUERY_NUM; ?>;

    if (order_status === query_status) {
        $('#search_skus').prop("disabled", true);
        $('#btn_add_sku').prop("disabled", true);
        $('input').on('focusin', function(){
            $(this).data('val', $(this).val());
        });
        $("#edit_skus_form").on("change", ".sku-qty", function () {
            var first = $(this).data('first-val');
            var current = $(this).val();
            if (current > first) {
                $(this).val(first);
                let skus_error = $('#skusError');
                skus_error.text('It is prohibited to add new products');
                skus_error.prop('classList').remove('d-none');
                setTimeout(() => {
                    skus_error.prop('classList').add('d-none');
                }, 3000);
            }
        });
    }

    $('.js-btn-edit-skus').click(function () {
        $('#editSkus').modal('show');
        $('#skus_tbody').html('');
        added_skus = {}
        renderOrderSKUs();
        // $('#order_id').val($(this).data('order_id'));
    });

    $('#updateSkus').click(function () {
        $.ajax({
            url: "/orders/save_skus",
            method: "POST",
            data: $('#edit_skus_form').serialize(),
            dataType: 'json',
            success :function (json) {
                if ('error' in json) {
                    $('#skusError').text(json['error']);
                    $('#skusError').prop('classList').remove('d-none');
                    setTimeout(() => {
                        $('#skusError').prop('classList').add('d-none');
                    }, 3000);
                } else {
                    location = '/orders/view_order/?id=' + $('.js-btn-edit-skus').data('order_id');
                }
            }
        });
    });
</script>
