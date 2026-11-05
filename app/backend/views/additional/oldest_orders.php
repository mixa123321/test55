<?php
/**
 * @var $authUser User
 * @var $orders Order[]
 * @var $num_orders int
 * @var $back string
 * @var $backorder bool
 */

use app\backend\models\Orders;
use app\core\entities\Order;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;

?>

<div class="container-fluid p-sm-4 p-2">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <form method="get" class="col">
                    <div class="row">
                        <div class="col-auto align-self-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-fw fa-th"></i>
                            </h6>
                        </div>
                        <div class="col-auto align-self-center">
                            <?php
                            echo Html::dropDownList('num_orders',
                                [
                                    5 => 5,
                                    10 => 10,
                                    20 => 20,
                                    50 => 50
                                ],
                                !empty($_GET['num_orders']) ? $_GET['num_orders'] : null,
                                [
                                    'class' => 'form-control form-control-lg custom-select col-auto',
                                    'onchange' => 'submit();',
                                ]);
                            ?>
                        </div>
                        <div class="col-auto align-self-center">
                            Oldest Orders<br/>
                            <div class="form-group mb-0">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input position-static" id="backorder" name="backorder" onchange="submit();"<?= $backorder ? ' checked' : ''; ?>>
                                    <label for="backorder">backorder</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                <div class="col-auto align-self-center">
                    <a class="btn btn-primary btn-icon-split" href="/additional/order_bin">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-th"></i>
                        </span>
                        <span class="text text-white">Order Bin</span>
                    </a>
                </div>
                <div class="col-auto align-self-center">
                    <a class="btn btn-primary btn-icon-split" href="/additional/table_pack">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-desktop"></i>
                        </span>
                        <span class="text text-white">Pack Stations</span>
                    </a>
                </div>
                <div class="col-auto align-self-center">
                    <a class="btn btn-primary btn-icon-split" href="/additional/large_pack">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-desktop"></i>
                        </span>
                        <span class="text text-white">Large Packs</span>
                    </a>
                </div>
                <div class="col-auto align-self-center">
                    <a class="btn btn-primary btn-icon-split" href="/additional/picking_history">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-inbox"></i>
                            </span>
                        <span class="text text-white">Picking History</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($orders)) { ?>
                <div class="row">
                    <div class="col ml-3">
                        <div class="row justify-content-center">
                            <h2>Orders</h2>
                        </div>
                        <div class="row justify-content-center">
                            <?php
                                $i = 1;
                                foreach ($orders as $order) {
                                    if ($i % 5 == 1)
                                        echo "<div class='col-1'></div>";
                            ?>
                                    <div class="col-2 text-md-center rounded border badge-primary" onclick="location = '/additional/view_order/?id=<?= $order->getId(); ?>&num_orders=<?= $num_orders; ?>';">
                                        <div class="row">
                                            <div class="col pb-1">
                                                <span class="font-weight-bold text-lg">#<?= View::toHtml($order->getId()); ?></span><br/>
                                                <?= empty($order->getRef()) ? '-' : View::toHtml($order->getRef()); ?><br/>
                                                <span class="badge<?= !$order->awaitingChannelServiceMapping() ? ' text-lg' : ''; ?> border border-secondary badge-<?= Order::colorStatus($order->getStatus()); ?> mb-0"><?= View::toHtml($order->getStatusName()); ?></span>
                                            </div>
                                        </div>
                                    </div>
                            <?php
                                    if ($i % 5 == 0)
                                        echo "<div class='col-1'></div>";
                                    $i++;
                                }
                            ?>
                            <div class="col-1"></div>
                        </div>
                    </div>
                </div>
            <?php } else {
                echo Html::warning("No orders found");
            } ?>
        </div>
    </div>
</div>
