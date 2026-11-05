<?php

use app\core\entities\Location;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\billet\PickingMultiBillet;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var User $authUser
 * @var array $SKUs
 * @var string $mode
 * @var Location $cart
 * @var string $back
 * @var bool $is_multi_dropping
 */

$SKU = $SKUs[0];

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <div class="col-12 h2 mb-0 text-gray-800">Dropping SKU for <?= View::toHtml("{$SKU['warehouse']}-{$SKU['type_id']}-{$SKU['zone']}-{$SKU['column']}-{$SKU['row']}"); ?></div>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        SKUs list (<?= count($SKUs); ?> item<?= count($SKUs) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <?php if($is_multi_dropping): ?>
                <div class="col text-right">
                    <button class="js_select_all">Select all</button>
                </div>
                <?php endif;?>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-md-10">
                    <form method="post" action="/drop/dropping_bar" id="formDropSKU">
                        <input type="hidden" name="mode" value="<?= View::toHtml($mode); ?>"/>
                        <input type="hidden" name="loc" value="<?= $SKU['location_id']; ?>"/>
                        <input type="hidden" id="batch_id" name="batch_id"/>
                        <input type="hidden" id="SKU_id" name="SKU_id"/>
                        <input type="hidden" name="back" value="<?= View::toHtml($back); ?>"/>
                        <div class="form-group row">
                            <div class="col">
                                <?php (new Scan([
                                    'id' => 'search',
                                    'name' => 'search',
                                    'placeholder' => 'SKU',
                                    'type' => Scan::TYPE__SKU,
                                ], [
                                    'class' => 'js-btn-scan',
                                    'title' => 'SKU',
                                    'icon' => 'fas fa-fw fa-box-open',
                                    'data' => [
                                        'send' => 'formDropSKU',
                                        'locations' => '["' . $cart->getId() . '"]',
                                        'suppliers' => json_encode(array_values(array_unique(Arr::column($SKUs, 'supplier_id')))),
                                        'batches' => json_encode(array_values(array_unique(Arr::column($SKUs, 'batch_id')))),
                                        'type' => '22'
                                    ],
                                ]))->render(); ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <form method="post" action="/drop/dropping_multi_bar" id="formDropMultiSKU">
                <input type="hidden" name="location_id" value="<?= $SKU['location_id']; ?>"/>
                <input type="hidden" name="mode" value="<?= View::toHtml($mode); ?>"/>
                <input type="hidden" name="back" value="<?= View::toHtml($back) ?>"/>
                <div class="row justify-content-center">
                        <?php foreach ($SKUs as $SKU)
                            echo (new PickingMultiBillet($SKU, false, false, $is_multi_dropping))->put();
                        ?>
                </div>
            </form>
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

                <div class="col text-right">
                    <button class="btn btn-success btn-icon-split js_multi_drop" style="display: none" >
                        <span class="text">Multi drop</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'utility' . DIRECTORY_SEPARATOR . 'get_SKU_batch.php'; ?>
<script>

    $('.js_select_all').click(function () {
        $('input.checkSKU').prop('checked', 'checked');
        $('.js_multi_drop').show();
    });

    $('.checkSKU').change(function () {
        let checkedCheckboxes = $('input.checkSKU:checked');
        checkedCheckboxes.length > 0 ? $('.js_multi_drop').show() : $('.js_multi_drop').hide();
    });

    $('.js_multi_drop').click(function (){
        $("#formDropMultiSKU").submit();
    })


</script>
