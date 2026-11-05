<?php

use app\core\entities\ASN;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var ASN $ASN
 * @var array $SKUs
 * @var User $authUser
 * @var array $comments
 * @var string $back
 * @var string $month_year
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">View Manifest</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-sign-in-alt"></i> ASN #<?= $ASN->getId(); ?>
            </h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-md-10">
                    <form method="post" action="/goods_in/no_verified">
                        <input type="hidden" name="asn_id" value="<?= $ASN->getId(); ?>"/>
                        <div class="form-group row">
                            <div class='col'>
                                <input class="form-control" type="text" id="location" name="location" placeholder="Location in storage (empty to PA zone)" autofocus />
                            </div>
                            <div class='col-auto'>
                                <button class="btn btn-success btn-icon-split" type="submit">
                                <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-clipboard-check"></i>
                                </span>
                                    <span class="text">Accept</span>
                                </button>
                            </div>
                        </div>
                    </form>
                    <p class="form-control-static text-success"><b>Expected SKUs</b>:</p>
                    <div class="row">
                        <?php foreach ($SKUs as $SKU) { ?>
                            <div class="col-md-12 text-center border border-info" style="border-radius: 10px;">
                                <?= '<b>' . View::toHtml($SKU['name']) . '</b> (#' . View::toHtml($SKU['id']) . ')'; ?><br/>
                                <b><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></b><br/>
                                <?php if (!empty($SKU['batch_number'])) :?>Batch no.  <b><?= View::toHtml($SKU['batch_number']); ?></b> Expired <b><?= View::toHtml((new DateTime($SKU['expired']))->format('Y-m-d')); ?></b><br/><?php endif;?>
                                <?php if (!empty($SKU['photo'])) { ?>
                                    <img class="img-responsive" style="max-height: 300px; max-width: 250px" src="<?= SKU::showPhotoSKU(urlencode($SKU['photo'])); ?>" /><br/>
                                <?php } ?>
                                <b>Quantity</b> <?= View::toHtml($SKU['quantity']); ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                    <span class="icon text-white-50">
                        <i class="fas fa-fw fa-chevron-left"></i>
                    </span>
                        <span class="text">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>