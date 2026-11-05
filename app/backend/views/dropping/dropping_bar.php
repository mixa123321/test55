<?php

use app\core\entities\User;
use app\core\helpers\billet\PickingBillet;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var User $authUser
 * @var array $SKUs
 * @var string $mode
 * @var string $back
 */

$SKU = $SKUs[0];

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="row d-sm-flex align-items-center justify-content-between mb-4">
        <div class="col-12 h2 mb-0 text-gray-800">Dropping location for <?= View::toHtml($SKU['barcode']); ?></div>
        <?php if (!empty($SKU['batch_id'])) { ?>
            <div class="col-12 h5 mb-0 text-gray-800">(<?= str_replace("<br/>",'; ', $SKU['batch_number']); ?>)</div>
        <?php } ?>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        Locations list (<?= count($SKUs); ?> item<?= count($SKUs) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-md-10">
                    <form method="post" action="/drop/dropping_loc">
                        <input type="hidden" name="mode" value="<?= View::toHtml($mode); ?>"/>
                        <input type="hidden" name="SKU_id" value="<?= $SKU['id']; ?>"/>
                        <input type="hidden" name="batch_id" value="<?= $SKU['batch_id']; ?>"/>
                        <input type="hidden" name="back" value="<?= View::toHtml($back) ?>"/>
                        <div class="form-group row">
                            <div class="col">
                                <?php (new Scan([
                                    'id' => 'search',
                                    'name' => 'search',
                                    'placeholder' => 'Location',
                                    'type' => Scan::TYPE__LOCATION,
                                ], [
                                    'id' => 'btnLoc',
                                    'title' => 'Location',
                                    'icon' => 'fas fa-fw fa-box-open',
                                ]))->render(); ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="row justify-content-center">
                <?php foreach ($SKUs as $SKU)
                    echo (new PickingBillet($SKU, true, true))->put();
                ?>
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
<script>
    $('#btnLoc').click(function () {
        $('#search').focus();
        return $('#search').val().trim() != '';
    });
</script>