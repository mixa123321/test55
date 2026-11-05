<?php

use app\core\entities\SubstitutionCondition;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var int $substitution_id
 * @var array $available_SKUs JSON
 * @var array $SKUs_info JSON
 * @var string $back
 * @var int $supplier_id
 * @var array $substitution_SKUs JSON
 * @var bool $edit_substitution
 * @var bool $has_orders
 * @var string $supplier_name
 * @var string $barcode
 * @var SubstitutionCondition $conditions
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= empty($edit_substitution) ? 'Create' : 'Edit' ?> rules</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-fw fa-<?= empty($edit_substitution) ? 'plus' : 'edit' ?>"></i> <?= empty($edit_substitution) ? 'Add' : 'Edit' ?> SKUs and conditions</h6>
                </div>
                <div class="col-auto">
                    <h6 class="m-0 font-weight-bold text-primary"><?= View::toHtml($supplier_name); ?></h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="/substitutions/substitutions_process" id="substitution_form">
                <?php
                    if (!empty($edit_substitution) && $has_orders)
                        echo "<div class='mb-3'>" . Html::warning('Rule is used in orders') . "</div>";
                ?>
                <input type="hidden" name="edit_substitution" value="<?= (int)!empty($edit_substitution); ?>"/>
                <input type="hidden" name="supplier_id" value="<?= $supplier_id; ?>"/>
                <input type="hidden" name="substitution_id" id="substitution_id" value="<?= (int)($_GET['id'] ?? 0); ?>"/>
                <input type="hidden" name="conditions[status]" value="<?= $conditions->getStatus(); ?>"/>
                <div class="row justify-content-between">
                    <div class="col-lg-5 order-lg-7">
                        <div class="row border border-secondary mx-1 p-3 mb-2">
                            <p class="form-control-static text-success mb-2"><b>Conditions</b></p>
                            <div class="col-12 mb-1">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="is_quantity" name="conditions[is_quantity]" <?= $conditions->isQuantity() ? ' checked' : ''; ?>>
                                    <label class="form-check-label" for="is_quantity">Quantity</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="form-group form-inline mb-0">
                                    <label class="px-2" for="quantity_from">From</label>
                                    <input id="quantity_from" name="conditions[quantity_from]" class="form-control" min="1" type="number" value="<?= $conditions->getQuantityFrom() ?: ''; ?>"/>
                                    <label class="px-2" for="quantity_to">To</label>
                                    <input id="quantity_to" name="conditions[quantity_to]" class="form-control" min="1" type="number" value="<?= $conditions->getQuantityTo() ?: ''; ?>"/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 order-lg-0">
                        <p class="form-control-static text-success mb-1"><b>Original SKU</b></p>
                        <input class="form-control mb-3" id="substitution" <?= !empty($barcode) ? 'value="' . View::toHtml($barcode) . '" readonly' : ''; ?> />
                        <div class="alert alert-danger" id="add_sku_error" style="display: none"></div>
                        <p class="form-control-static text-success mt-3"><b><a name="skus">Add SKUs using the search field below</a></b></p>
                        <?php (new Scan(['id' => 'search_skus', 'type' => Scan::TYPE__SKU], [
                            'id' => 'btn_add_sku',
                            'title' => 'add',
                            'icon' => 'fas fa-fw fa-plus',
                            'type' => 'button',
                        ]))->render();?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <table class="table table-hover mt-4">
                            <thead>
                            <tr>
                                <th>Barcode</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody id="skus_tbody">
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                    <span class="icon text-white-50">
                        <i class="fas fa-fw fa-chevron-left"></i>
                    </span>
                            <span class="text">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" type="submit">
                            <span class="text text-white">Done</span>
                            <span class="icon text-white">
                            <i class="fas fa-fw fa-chevron-right"></i>
                        </span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script type="text/template" id="sku_tr_template">
    <tr>
        <td>{{barcode_html}}</td>
        <td>{{name}}</td>
        <td><input name="quantity[{{id}}]" id="{{id}}" type="text" class="form-control quantity" value="{{qty}}"></td>
        <td>
            <button type="button" class="btn btn-danger js-btn-remove-sku" data-sku="{{barcode}}">Remove</button>
        </td>
    </tr>
</script>
<script>
    var global_skus_to_search = <?= $available_SKUs; ?>;
    var global_skus_info = <?= $SKUs_info; ?>;

    const form = document.getElementById('substitution_form');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        $('#add_sku_error').hide();
        let barcode = $('#substitution').val().trim();
        if (barcode === '') {
            $('#add_sku_error').html('Original SKU is empty');
            $('#add_sku_error').show();
            return;
        }

        let exist_barcode = false;
        for (const item in global_skus_info) {
            let barcodes = global_skus_info[item].barcodes;
            if (barcodes.includes(barcode) || barcode === item) {
                exist_barcode = true;
                barcode = global_skus_info[item].barcode;
                break;
            }
        }
        if (exist_barcode === false) {
            $('#add_sku_error').html('Original SKU is not found');
            $('#add_sku_error').show();
            return;
        }
        let id = global_skus_info[barcode].id;
        let qty = $('#skus_tbody').children().children().children('.quantity');
        if (!qty.length) {
            $('#add_sku_error').html('SKUs are empty');
            $('#add_sku_error').show();
            return;
        }
        let exist = false;
        $.each(qty, function(key, item) {
            if (item.id == id) {
                $('#add_sku_error').html('SKUs contains original SKU');
                $('#add_sku_error').show();
                exist = true;
            }
        });
        if (!exist) {
            $('#substitution_id').val(id);
            this.submit();
        }
    });
</script>
<?= View::include_js(BACKEND_JS, "add_sku.js") ?>
<script>
    var $substitution = $('#substitution');
    $(function () {
        $substitution.autocomplete({source: global_skus_to_search});
    });

    <?php if (!empty($substitution_SKUs)) { ?>
        var substitution_SKUs = <?= $substitution_SKUs; ?>;
        for(key in substitution_SKUs)
            add_sku(key, substitution_SKUs[key].quantity, '', '')
    <?php } ?>
</script>
