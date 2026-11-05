<?php

use app\core\entities\User;
use app\core\helpers\billet\PickingBillet;
use app\core\helpers\View;
use app\core\entities\Location;
use app\core\helpers\modal\ConfirmModal;

/**
 * @var User $authUser
 * @var array $SKUs
 * @var float $weight
 * @var Location $cart
 * @var string $mode
 * @var string $warehouse_label
 * @var Location $location
 * @var bool $leave
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Dropping (<?= View::toHtml($mode); ?>)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        SKUs list (<?= count($SKUs); ?> item<?= count($SKUs) > 1 ? 's' : '' ?>).
                        <?= View::include('utility/cart_widget.php', [
                            'cart' => $cart,
                            'warehouse_label' => $warehouse_label,
                            'weight' => $weight,
                        ]); ?>
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <p class="col-12 form-control-static text-lg"><b>Unload on the table <span class="text-success"><?= View::toHtml($location->getLocation($warehouse_label)); ?></span></b></p>
                <?php foreach ($SKUs as $SKU)
                    echo (new PickingBillet($SKU, false))
                        ->setColor('info')
                        ->put();
                ?>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-danger btn-icon-split" href="/drop/dropping_<?= substr($mode, 0, 1); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-times"></i>
                            </span>
                        <span class="text d-none d-md-block">Cancel</span>
                    </a>
                </div>
                <div class="col-auto">
                    <a class="btn btn-success btn-icon-split" data-toggle="modal" id="unloadCart">
                        <span class="text text-white d-none d-md-block">Unload</span>
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-angle-down"></i>
                        </span>
                    </a>
                </div>
                <?php if ($leave) { ?>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split" data-toggle="modal" id="leaveCart">
                            <span class="text text-white d-none d-md-block">Leave</span>
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-shopping-cart"></i>
                            </span>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php
    $services = $_GET['services'] ?? null;
    $service_inputs = '';
    if ($services)
        if (is_array($services))
            foreach ($services as $service)
                $service_inputs .= '<input type="hidden" name="services[]" value="' . View::toHtml($service) . '">';
        else
            $service_inputs .= '<input type="hidden" name="services" value="' . View::toHtml($services) . '">';
    $modal_unload_cart = new ConfirmModal('unloadCart', 'Unload trolley');
    echo $modal_unload_cart->addContent("Would you like to unload this trolley?")
        ->addForm('/drop/dropping_unload', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons(' <input type="hidden" name="id" value="' . $location->getId() . '">' .
            '<input type="hidden" name="mode" value="'. $mode . '">' .
            $service_inputs .
            $modal_unload_cart->getButtons())
        ->put();

    $modal_leave_cart = new ConfirmModal('leaveCart', 'Leave trolley');
    echo $modal_leave_cart->addContent("Would you like to leave this trolley?")
        ->addForm('/drop/dropping_leave', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons(' <input type="hidden" name="id" value="' . $location->getId() . '">' .
            '<input type="hidden" name="mode" value="'. $mode . '">' .
            $modal_leave_cart->getButtons())
        ->put();
?>

<script>
    $('#unloadCart').click(function () {
        <?= $modal_unload_cart->show(); ?>
    });
    $('#leaveCart').click(function () {
        <?= $modal_leave_cart->show(); ?>
    });
</script>
