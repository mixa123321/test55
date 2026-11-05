<?php
/**
 * @var $package Package
 * @var $package_warehouses array
 * @var $package_consumables array,
 * @var $packagings array
 * @var $consumables array
 * @var $supplier Supplier
 * @var $back string
 * @var $in_package bool
 */

use app\core\entities\Package;
use app\core\entities\Supplier;
use app\core\helpers\Arr;
use app\core\helpers\View;
use app\core\helpers\HTTP;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Package (<?= View::toHtml($supplier->getName()); ?>)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-box"></i> <?= $package->getId() == 0 ? "New package" : View::toHtml($package->getName()); ?>
            </h6>
        </div>
        <form method="post" action="/packages/adding_package">
            <input type="hidden" name="package[id]" value="<?= $package->getId(); ?>">
            <input type="hidden" name="package[supplier_id]" value="<?= $package->getSupplierId(); ?>">
            <input type="hidden" name="package[state]" value="<?= $package->getState(); ?>">
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <div class="form-row">
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <input type="text" name="package[name]" id="name" class="form-control form-control-lg" placeholder=" " value="<?= View::toHtml($package->getName()); ?>">
                                    <label for="name">Name <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <input type="text" name="package[barcode]" id="barcode" class="form-control form-control-lg" placeholder=" " value="<?= View::toHtml($package->getBarcode()); ?>">
                                    <label for="barcode">Barcode</label>
                                </div>
                            </div>
                            <div class="form-group col-6">
                                <div class="form-group-element">
                                    <input type="text" name="package[instruction]" id="instruction" class="form-control form-control-lg" placeholder=" " value="<?= View::toHtml($package->getInstruction()); ?>">
                                    <label for="instruction">Instruction</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-5">
                                <div class="form-group-element mb-2">
                                    <select name="package[packaging_id]" id="packaging_id" class="form-control form-control-lg custom-select">
                                        <?php foreach ($packagings as $packaging_id => $packaging) { ?>
                                            <option value="<?= $packaging_id; ?>"<?= $packaging_id == $package->getPackagingId() ? " selected" : ""; ?>>
                                                <?= View::toHtml($packaging); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <label for="packaging_id">Packaging</label>
                                </div>
                                <label>Warehouses (<span class="text-lowercase">Must be selected at least one warehouse</span>)<span style="color:red">*</span></label><br>
                                <?php foreach ($warehouses as $warehouse) {
                                    if (!in_array($warehouse['id'], $supplier->getWarehouses()) || $warehouse['is_virtual'])
                                        continue;
                                ?>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" id="warehouse_<?= $warehouse['id']; ?>" name="warehouses[<?= $warehouse['id']; ?>]" <?= !empty($package_warehouses[$warehouse['id']]) ? 'checked' : '' ?> value="1">
                                        <label class="form-check-label" for="warehouse_<?= $warehouse['id']; ?>"><?= View::toHtml($warehouse['name']); ?></label>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="form-group col-7 pl-4 pr-3">
                                <div class="row">
                                    <div class="col-12 p-3 border">
                                        <h5 class="text-primary mb-3">Consumables</h5>
                                        <?php foreach ($consumables as $consumable_id => $consumable_name) { ?>
                                            <div class="row mb-1">
                                                <div class="col-9 align-self-center"><?= View::toHtml($consumable_name); ?></div>
                                                <div class="col-3 align-self-center">
                                                    <input class="form-control" name="consumables[<?= $consumable_id; ?>]" type="number" min="0" value="<?= $package_consumables[$consumable_id] ?? ''; ?>"/>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?php if (count($packagings) > 1) { ?>
                                    <div class="form-check my-2">
                                        <input type="checkbox" class="form-check-input" id="in_package" name="in_package"<?= $in_package ? ' checked' : ''; ?>>
                                        <label class="form-check-label" for="in_package">In package</label>
                                    </div>
                                    <div class="row<?= $in_package ? '' : ' d-none'; ?>" id="add_packaging">
                                        <div class="col-12 p-3 border mb-2">
                                            <h5 class="text-primary mb-3">Packaging</h5>
                                                <?php foreach ($packagings as $packaging_id => $packaging) { ?>
                                                    <div class="row mb-1" id="row_<?= $packaging_id; ?>">
                                                        <div class="col-9 align-self-center"><?= View::toHtml($packaging); ?></div>
                                                        <div class="col-3 align-self-center">
                                                            <input class="form-control" name="in_packages[<?= $packaging_id; ?>]" type="number" min="0" value="<?= $package_consumables[$packaging_id] ?? ''; ?>"/>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-orange btn-icon-split" type="submit">
                            <span class="text text-white">Done</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    var packaging_id = $('#packaging_id').val();
    hidePackaging(packaging_id);

    $('#in_package').change(() => {
        if ($('#in_package').is(':checked'))
            $('#add_packaging').prop('classList').remove('d-none');
        else
            $('#add_packaging').prop('classList').add('d-none');
    });

    $('#packaging_id').change(() => {
        showPackaging(packaging_id);
        packaging_id = $('#packaging_id').val();
        hidePackaging(packaging_id);
    });

    function showPackaging(key)
    {
        $('#row_' + key).prop('classList').remove('d-none');
    }

    function hidePackaging(key)
    {
        $('#row_' + key).prop('classList').add('d-none');
    }
</script>