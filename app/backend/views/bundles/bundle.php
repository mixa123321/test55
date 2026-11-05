<?php

use app\core\entities\Bundle;
use app\core\entities\User;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\helpers\Arr;
use app\core\statical\Currencies;

/**
 * @var User $authUser
 * @var Bundle $bundle
 * @var array $bundle_SKUs JSON
 * @var bool $has_orders
 * @var string $currency
 * @var array $available_skus JSON
 * @var array $skus_info JSON
 * @var string $back
 * @var array $warehouses
 * @var int $warehouse_id
 */

?>
    <div id="response"></div>
    <div class="container-fluid p-sm-4 p-2">
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= empty($bundle->getId()) ? 'Create' : 'Edit' ?> bundle</h1>
        </div>
        <form method="post" action="/bundles/bundles_process" id="bundle_form">
            <?php if (!empty($bundle->getId())) {?>
            <input type="hidden" name="bundle_id" value="<?= $bundle->getId() ?>" id="bundle_id">
            <?php } else { ?>
            <input type="hidden" name="supplier_id" value="<?= $bundle->getSupplierId() ?>" id="supplier_id">
            <?php } ?>
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <div class="row">
                        <div class="col">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-fw fa-plus"></i> Add SKUs</h6>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($bundle->getId()) && $has_orders) { ?>
                        <div class="row">
                            <div class="col alert alert-warning">
                                Bundle is used in orders
                            </div>
                        </div>
                    <?php } ?>
                    <?php if (empty($currency)) { ?>
                        <div class="row">
                            <div class="col alert alert-warning">
                                Currency not saved
                            </div>
                        </div>
                    <?php } ?>
                    <div class="row">
                        <div class="col-lg-5">
                            <div class="form-group">
                                <div class="form-group-element">
                                    <input type="text"
                                           id="bundle_name"
                                           name="bundle_name"
                                           required
                                           class="form-control form-control-lg"
                                           placeholder=" "
                                           value="<?= View::toHtml($bundle->getName()) ?>"
                                    >
                                    <label for="name">Bundle Name/SKU <span class="text-danger">*</span></label>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-1"></div>
                        <div class="col-lg-4">
                            <div class="row">
                                <div class="col-lg-9 form-group">
                                    <div class="form-group-element">
                                        <input name="cost_value" id="cost_value" type="number" step="any" min="0" class="form-control form-control-lg" placeholder=" " value="<?= View::toHtml($bundle->getCostValue()); ?>">
                                        <label for="cost_value">Market Value <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-lg-3 form-group">
                                    <div class="form-group-element">
                                        <input name="currency" id="currency" type="text" class="form-control form-control-lg" readonly value="<?= View::toHtml($currency); ?>">
                                        <label for="currency">Currency</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="alert alert-danger" id="add_sku_error" style="display: none"></div>
                            <p class="form-control-static text-success"><b><a name="skus">Add SKUs using the
                                        search field below</a></b></p>
                            <div class="input-group">
                                <?php (new Scan(['id' => 'search_skus', 'type' => Scan::TYPE__SKU], [
                                    'id' => 'btn_add_sku',
                                    'title' => 'Add',
                                    'icon' => 'fas fa-fw fa-plus',
                                    'type' => 'button',
                                ]))->render(); ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <table class="table table-hover mt-4">
                                <thead>
                                <tr>
                                    <th>Barcode</th>
                                    <th>Product Name</th>
                                    <th>Market currency type</th>
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
                </div>
            </div>
        </form>
    </div>

    <script type="text/template" id="sku_tr_template">
        <tr class="{{active}}">
            <td>{{barcode_html}}</td>
            <td>{{name}}</td>
            <td>{{market_currency}}</td>
            <td><input name="quantity[{{id}}]" type="text" class="form-control" value="{{qty}}"></td>
            <td>
                <button type="button" class="btn btn-danger js-btn-remove-sku" data-sku="{{barcode}}">Remove</button>
            </td>
        </tr>
    </script>
    <script>
        var global_skus_to_search = <?= $available_skus; ?>;
        var global_skus_info = <?= $skus_info; ?>;

        const form = document.getElementById('bundle_form');
        var bundle_SKUs =<?= $bundle_SKUs; ?>;

        function setCurrency() {
            let market_currency = null;
            let current_currency = <?= View::toJs($currency) ?>;
            for (let SKU in added_skus) {
                if (global_skus_info[SKU].market_currency && !market_currency) {
                    market_currency = global_skus_info[SKU].market_currency
                }
                if (current_currency && global_skus_info[SKU].market_currency === current_currency) {
                    market_currency = current_currency;
                }
            }
            if (!market_currency) {
                market_currency = <?= View::toJs(Currencies::getCurrency(Arr::map($warehouses, 'id', 'currency_id')[$warehouse_id])) ?>;
            }
            $('#currency').val(market_currency);
        }

        $('#skus_tbody').on('afterInitSKUs', function () {
            if (!$('#currency').val() && Object.keys(added_skus).length) {
                setCurrency();
            }
        });

        $('#skus_tbody').on('changeSKUsAfterInit', function () {
            if (Object.keys(added_skus).length) {
                setCurrency();
            }
        });

        $('#cost_value').change(function () {
            if (!this.value) {
                this.value = 0;
                return true;
            }
            this.value = parseFloat(this.value).toFixed(2).replace(/0$|\,00$|\.00$/gm,'');
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            const form = this;
            const response_block = document.getElementById('response');
            response_block.innerHTML = '';
            $.ajax({
                url: "/bundles/validate_name",
                method: "POST",
                data: {
                    <?php if (!empty($bundle->getId())) {?>
                    bundle_id: <?= $bundle->getId(); ?>,
                    <?php } else { ?>
                    supplier_id: parseInt('<?= $bundle->getSupplierId() ?>'),
                    <?php } ?>
                    bundle_name: document.getElementById('bundle_name').value,
                },
                dataType: "json",
                success: function (response) {
                    if (response.result === 'error' || response.errors.length > 0)
                        response.errors.forEach(error =>
                            response_block.insertAdjacentHTML('beforeend', `<div class="alert alert-danger">${html_escape(error)}</div>`)
                        );
                    else if (response.result === 'success')
                        form.submit();
                },
                error: function () {
                    location.reload();
                }
            });
        })
    </script>
<?= View::include_js(BACKEND_JS, "add_sku.js") ?>