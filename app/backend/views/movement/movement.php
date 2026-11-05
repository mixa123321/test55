<?php

use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var string $back
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Movements</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-sign-in-alt"></i>
                            SKU selection
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <form class="col-md-10" method="post" id="frmSKU" action="/movement/movement_SKU">
                    <input type="hidden" id="supplier_id" name="supplier_id"/>
                    <div class="row justify-content-md-center mb-2">
                        <div class='input-group'>
                            <?php (new Scan([
                                'id' => 'search',
                                'name' => 'barcode',
                                'placeholder' => 'Scan SKU barcode',
                                'type' => Scan::TYPE__SKU,
                            ], ['class' => 'js-btn-scan', 'data' => [
                                'send' => 'frmSKU',
                                'empty' => 'false',
                                'suppliers' => '[]',
                            ]]))->render(); ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back ?? '/movement')) ?>">
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-left"></i>
                            </span>
                        <span class="text">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_supplier.php'; ?>
