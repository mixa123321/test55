<?php

use app\core\entities\Supplier;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\entities\Consumable;
use app\backend\models\Consumables;

/**
 * @var $consumable Consumable
 * @var $suppliers array
 * @var $supplier Supplier
 * @var $back string
 * @var $min_stock_level_data array
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <?= View::toHtml((!empty($consumable->getId()) ? "Edit " : "Create ") . Consumable::TYPES[$consumable->getType()] . (!empty($consumable->getId()) ? " #{$consumable->getId()}" : '')); ?>
        </h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <div class="col pl-0">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-fw fa-archive"></i>
                    <?= View::toHtml($consumable->getName() ?: ('New ' . Consumable::TYPES[$consumable->getType()])); ?>
                </h6>
            </div>
        </div>
        <form method="post" action="/consumables/adding_consumable" class="m-0">
            <div class="card-body">
                <input type="hidden" name="consumable[id]" value="<?= $consumable->getId(); ?>">
                <input type="hidden" name="consumable[type]" value="<?= $consumable->getType(); ?>">
                <input type="hidden" name="consumable[status]" value="<?= (int)$consumable->getStatus(); ?>">
                <div class="row justify-content-center">
                    <div class="col-md-11">
                        <div class="row">
                            <div class="col">
                                <?php if (!empty($suppliers)) { ?>
                                    <?php if (count($suppliers) != 1) { ?>
                                        <div class="form-row">
                                            <div class="form-group col-5">
                                                <div class="form-group-element">
                                                    <select name="consumable[supplier_id]" id="supplier_id" class="form-control form-control-lg custom-select">
                                                        <?php foreach ($suppliers as $row) { ?>
                                                            <option value="<?= $row['id'] ?>"<?= ($row['id'] == $consumable->getSupplierId()) ? " selected" : "" ?>><?= View::toHtml($row['name']) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                    <label for="supplier_id">Company</label>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } else { ?>
                                        <input type="hidden" name="consumable[supplier_id]" id="supplier_id" value="<?= $suppliers[array_keys($suppliers)[0]]['id']; ?>">
                                        <p class="form-control-static font-weight-bold text-primary pl-2">
                                            <i class="fa fa-user"></i> <?= View::toHtml($suppliers[array_keys($suppliers)[0]]['name']); ?>
                                        </p>
                                    <?php } ?>
                                <?php } else { ?>
                                    <input type="hidden" name="consumable[supplier_id]" id="supplier_id" value="<?= $consumable->getSupplierId(); ?>">
                                    <p class="form-control-static font-weight-bold text-primary pl-2">
                                        <i class="fa fa-user<?= $supplier->getId() ? '' : 's'; ?>"></i> <?= View::toHtml($supplier->getId() ? $supplier->getName() : 'All'); ?>
                                    </p>
                                <?php } ?>
                                <div class="form-row">
                                    <div class="col-4">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input name="consumable[name]" id="name" type="text" class="form-control form-control-lg" placeholder=" "
                                                       value="<?= View::toHtml($consumable->getName()); ?>">
                                                <label for="name">Name <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-1">
                                        <div class="form-group">
                                            <label for="reuse">Re-use</label>
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="reuse" name="consumable[reuse]"<?= $consumable->getReuse() ? ' checked' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-7">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input name="consumable[customer_ref]" id="customer_ref" type="text" class="form-control form-control-lg" placeholder=" "
                                                       value="<?= View::toHtml($consumable->getCustomerRef()); ?>">
                                                <label for="customer_ref">Customer Reference</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="col-4">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input name="consumable[length]" id="length" type="number" step="any" min="0" class="form-control form-control-lg" placeholder=" "
                                                       value="<?= View::toHtml($consumable->getLength()); ?>">
                                                <label for="length">Length <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input name="consumable[width]" id="width" type="number" step="any" min="0" class="form-control form-control-lg" placeholder=" "
                                                       value="<?= View::toHtml($consumable->getWidth()); ?>">
                                                <label for="width">Width <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input name="consumable[height]" id="height" type="number" step="any" min="0" class="form-control form-control-lg" placeholder=" "
                                                       value="<?= View::toHtml($consumable->getHeight()); ?>">
                                                <label for="height">Height <span class="text-danger">*</span></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($consumable->getType() == Consumable::TYPE_PACKAGING) { ?>
                                    <div class="form-row">
                                        <div class="col-4">
                                            <div class="form-group">
                                                <div class="form-group-element">
                                                    <input name="consumable[weight]" id="weight" type="number" step="any" min="0" class="form-control form-control-lg" placeholder=" "
                                                           value="<?= View::toHtml($consumable->getWeight()); ?>">
                                                    <label for="weight">Weight</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="form-group">
                                                <div class="form-group-element">
                                                    <input name="consumable[max_weight]" id="max_weight" type="number" step="any" min="0" class="form-control form-control-lg" placeholder=" "
                                                           value="<?= View::toHtml($consumable->getMaxWeight()); ?>">
                                                    <label for="max_weight">Max weight <span class="text-danger">*</span></label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                        <?php if (!empty($min_stock_level_data)) { ?>
                            <h4 class="mt-2">Inventory</h4>
                            <div class="row">
                                <div class="col-6">
                                    <table id="min_stock_level_data" class="table table-hover mt-2 mb-0">
                                        <colgroup>
                                            <col width="30%">
                                            <col width="70%">
                                        </colgroup>
                                        <thead>
                                            <th>Warehouse</th>
                                            <th>Min Stock Level</th>
                                        </thead>
                                        <tbody>
                                        </tbody>
                                    </table>
                                    <script>
                                        const min_stock_level_data = <?= View::toJs(json_decode($min_stock_level_data)); ?>;
                                        const supplier_id_el = document.getElementById('supplier_id');
                                        let supplier_id = supplier_id_el.value;
                                        if (min_stock_level_data[supplier_id] !== undefined)
                                            setMinStockLevelList(min_stock_level_data[supplier_id]);
                                        else
                                            emptyMinStockLevelList();
                                        if (supplier_id_el.type == 'select-one')
                                            supplier_id_el.addEventListener('change', ({target}) => {
                                                if (min_stock_level_data[+target.value] !== undefined)
                                                    setMinStockLevelList(min_stock_level_data[+target.value]);
                                                else
                                                    emptyMinStockLevelList();
                                            });

                                        function setMinStockLevelList(min_stock_level_Arr) {
                                            $('#min_stock_level_data').find('tbody').html(min_stock_level_Arr.reduce((t, stock_level_data) => {
                                                t += `<tr>
                                                          <td>${html_escape(stock_level_data.warehouse_name)}</td>
                                                          <td>
                                                              <input type="number"
                                                                     min="0"
                                                                     name="min_stock_level[${+stock_level_data.warehouse_id}]"
                                                                     value="${stock_level_data.value ? +stock_level_data.value : ''}"
                                                                     step="1"
                                                                     class="form-control"
                                                              >
                                                          </td>
                                                      </tr>`;
                                                return t;
                                            }, ''));
                                        }

                                        function emptyMinStockLevelList() {
                                            $('#min_stock_level_data').find('tbody').html(`<tr><td colspan="2"><?= Html::warning('Warehouses are not found for this company'); ?></td></tr>`);
                                        }
                                    </script>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-orange pr-3" type="submit">
                            <span class="mr-1">Done</span>
                            <i class="fas fa-fw fa-chevron-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
