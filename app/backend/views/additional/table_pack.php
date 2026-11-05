<?php
/**
 * @var $authUser User
 * @var $tables array
 * @var $orders array
 * @var $name string
 * @var $warehouse int
 */

use app\backend\models\Orders;
use app\core\entities\User;
use app\core\entities\Order;
use app\core\entities\Location;
use app\core\helpers\View;

$back = $name === 'Pack Stations' ? 'table' : 'large';

?>

<div class="container-fluid p-sm-4 p-2">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-desktop"></i>
                        <?= $name; ?>
                    </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/additional/order_bin">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-th"></i>
                            </span>
                        <span class="text text-white">Order Bins</span>
                    </a>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/additional/<?= $name === 'Pack Stations' ? 'large' : 'table'; ?>_pack">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-desktop"></i>
                            </span>
                        <span class="text text-white"><?= ($name === 'Pack Stations' ? 'Large Packs' : 'Pack Stations'); ?></span>
                    </a>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/additional/oldest_orders">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-clock"></i>
                            </span>
                        <span class="text text-white">Oldest Orders</span>
                    </a>
                </div>
                <div class="col-auto">
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
                            <a class="text-decoration-none h2" style="color: black" href="/additional/oldest_orders/?back=<?= urlencode($back); ?>_pack">The Oldest Orders</a>
                        </div>
                        <div class="row justify-content-center">
                            <div class="col-1"></div>
                            <?php
                            foreach ($orders as $order) { ?>
                                <div class="col-2 text-md-center rounded border badge-primary"
                                     onclick="location = '/additional/view_order/?id=<?= $order['order']->getId(); ?>&back=<?= urlencode($back); ?>_pack';">
                                    <div class="row">
                                        <div class="col pb-1">
                                            <span class="font-weight-bold text-lg">#<?= View::toHtml($order['order']->getId()); ?></span><br/>
                                            <?= empty($order['order']->getRef()) ? '-' : View::toHtml($order['order']->getRef()); ?><br/>
                                            <span class="badge<?= !$order['order']->awaitingChannelServiceMapping() ? ' text-lg' : ''; ?> border border-secondary badge-<?= Order::colorStatus($order['order']->getStatus()); ?> mb-0"><?= View::toHtml($order['order']->getStatusName()); ?></span><hr class="bg-light my-1"/>
                                            <span class="font-weight-bold text-lg">
                                                <?php foreach ($order['location'] as $loc)
                                                    echo "$warehouse$loc<br/>";
                                                ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="col-1"></div>
                        </div>
                    </div>
                </div>
            <?php } ?>
            <div class="row mt-3">
                <div class="col ml-3">
                    <div class="row justify-content-center">
                        <h2><?= $name; ?></h2>
                    </div>
                    <div class="row justify-content-center">
                        <?php
                        $i = 1;
                        foreach ($tables as $num => $table) {
                            if (!empty($table['minus']))
                                $color = 'danger';
                            elseif (empty($table['state']))
                                $color = 'secondary';
                            elseif ($table['exist'])
                                $color = 'warning';
                            else
                                $color = 'success';
                            if ($i % 5 == 1)
                                echo "<div class='col-1'></div>";
                            ?>
                            <div class="col-2 text-md-center rounded border bg-<?= $color; ?>">
                                <div class="row">
                                    <div class="col-12 border-bottom pb-1 border-secondary">
                                        <span class="font-weight-bold text-lg"><?= $num; ?></span>
                                    </div>
                                    <div class="col-12 ">
                                        <div class="row" style="max-height: 300px; min-height: 50px; overflow-y: auto;">
                                            <?php foreach ($table['data'] as $key => $orders) { ?>
                                                <div class="col-12 justify-content-center">
                                                    <?= $orders['orders'] ?: '&nbsp;'; ?><br/>
                                                    <span class="text-xs"><?= $orders['consist'] ?: '&nbsp;'; ?></span>
                                                    <?php if ($key + 1 != count($table['data'])) { ?>
                                                        <hr class="bg-secondary mt-1 mb-1"/>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
                            if ($i % 5 == 0)
                                echo "<div class='col-1'></div>";
                            $i++;
                        }
                        if (count($tables) % 5 !== 0)
                            echo "<div class='col-1'></div>";
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
