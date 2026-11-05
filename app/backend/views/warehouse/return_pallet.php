<?php

use app\core\entities\Location;
use app\core\helpers\billet\PickingBillet;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var Location $cart
 * @var string $back
 * @var string $warehouse_label
 * @var array $SKUs
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Return pallet</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-pallet"></i>
                            <?= View::toHtml($cart->getLocation($warehouse_label)); ?>
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <div class="alert alert-danger d-none" style="position: relative" id="err_msg"></div>
                    <form method="post" action="/cart/return_pallet">
                        <input type="hidden" name="back" value="<?= View::toHtml($back); ?>" />
                        <?php (new Scan([
                                'id' => 'scan',
                                'name' => 'scan',
                                'placeholder' => 'Scan pallet',
                                'value' => empty($search) ? '' : htmlentities($search),
                                'type' => Scan::TYPE__LOCATION,
                        ], ['title' => 'Scan']))->render(); ?>
                    </form>
                </div>
            </div>
            <?php if (!empty($SKUs)): ?>
                <div class="row pt-2">
                    <div class="col">
                        <p class="form-control-static text-lg text-center"><b>Pallet must contains followed SKUs (<?= count($SKUs); ?>)</b></p>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <?php foreach ($SKUs as $SKU)
                        echo (new PickingBillet($SKU, false))
                            ->setColor('info')
                            ->put();
                    ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    $(function() {
        var $scan = $('#scan'),
            $form = $scan.closest('form'),
            $error = $('#err_msg');
        $scan.focus();
        $form.on('submit', function(e) {
            e.preventDefault();
            $error.addClass('d-none');
            if ($scan.val() === '') {
                $error.text('Please scan pallet').removeClass('d-none');
                return false;
            }
            $.post('/cart/return_pallet', $form.serialize(), function(data) {
                if (!data.success) {
                    $error.text(data.message || 'Error').removeClass('d-none');
                    return;
                }
                window.location.href = <?= View::toJs(HTTP::safeLinkUrl($back)) ?>;
            });
            return false;
        });
    })
</script>