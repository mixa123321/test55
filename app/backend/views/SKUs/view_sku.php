<?php

use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var $authUser User
 * @var $SKU_barcode string
 * @var $SKU SKU
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">View SKU</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <div class="col pl-0">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-fw fa-archive"></i> <?= View::toHtml($SKU->getName()) ?>
                </h6>
            </div>
            <?php if ($SKU->getName() && !$authUser->isClientSide() && $warehouse_virtual) { ?>
                <input type="hidden" id="SKU_id" value="<?= (int)$SKU->getId(); ?>">
                <div class="col-auto pr-0">
                    <button class="btn btn-success btn-icon-split js-in-reality">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-warehouse"></i>
                        </span>
                        <span class="text">In reality</span>
                    </button>
                </div>
            <?php } ?>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-md-10">
                    <div class="row">
                        <div class="col-10">
                            <div class="row">
                                <div class="col-md-3">
                                    <label>Name:</label> <b><?= View::toHtml($SKU->getName()); ?></b>
                                </div>
                                <div class="col-md-6">
                                    <label>Barcode:</label> <b><?= View::toHtmlConvertWithPrimaryBarcode($SKU_barcode); ?></b>
                                </div>
                                <div class="col-md-2">
                                    <?php if ($SKU->getGs1()) { ?>
                                        <b>GS1</b>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <label>Market value:</label> <b><?= View::toHtml($SKU->getMarketValue()); ?></b>
                                </div>
                                <div class="col-md-3">
                                    <label>Market currency type:</label> <b><?= View::toHtml($SKU->getMarketCurrency()); ?></b>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <label>Length (cm):</label> <b><?= View::toHtml($SKU->getDepth()); ?></b>
                                </div>
                                <div class="col-md-3">
                                    <label>Width (cm):</label> <b><?= View::toHtml($SKU->getWidth()); ?></b>
                                </div>
                                <div class="col-md-3">
                                    <label>Height (cm):</label> <b><?= View::toHtml($SKU->getHeight()); ?></b>
                                </div>
                                <div class="col-md-3">
                                    <label>Weight (kg):</label> <b><?= View::toHtml($SKU->getWeight()); ?></b>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <label>HS Code:</label> <b><?= View::toHtml($SKU->getHsCode()); ?></b>
                                </div>
                            </div>
                        </div>
                        <div class='col-md-2'>
                            <?php if (!empty($SKU->getPhoto())) { ?>
                                <img class="img-responsive" id="SKU_photo" style="max-height: 300px; max-width: 250px" src="/image/public?path=<?= View::toHtml($SKU->getPhoto()); ?>" />
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
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'SKU_in_reality.php'; ?>
