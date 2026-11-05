<?php
/**
 * @var $authUser User
 * @var $OBs array
 * @var $orders array
 * @var $OB_info array
 * @var $warehouse_label
 */

use app\backend\models\Orders;
use app\core\entities\User;
use app\core\entities\Order;
use app\core\helpers\View;

?>

<div class="container-fluid p-sm-4 p-2">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-th"></i>
                        Order Bins
                    </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/additional/table_pack">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-desktop"></i>
                            </span>
                        <span class="text text-white">Pack Stations</span>
                    </a>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/additional/large_pack">
                        <span class="icon text-white-50">
                                <i class="fas fa-fw fa-desktop"></i>
                            </span>
                        <span class="text text-white">Large Packs</span>
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
                            <a class="text-decoration-none h2" style="color: black" href="/additional/oldest_orders/?back=order_bin">The Oldest Orders</a>
                        </div>
                        <div class="row justify-content-center">
                            <div class="col-1"></div>
                            <?php foreach ($orders as $order) { if($order['order'] === null) continue; ?>
                                <div class="col-2 text-md-center rounded border badge-primary" onclick="location = '/additional/view_order/?id=<?= $order['order']->getId(); ?>&back=order_bin';">
                                    <div class="row">
                                        <div class="col pb-1">
                                            <span class="font-weight-bold text-lg">#<?= View::toHtml($order['order']->getId()); ?></span><br/>
                                            <?= empty($order['order']->getRef()) ? '-' : View::toHtml($order['order']->getRef()); ?><br/>
                                            <span class="badge<?= !$order['order']->awaitingChannelServiceMapping() ? ' text-lg' : ''; ?> border border-secondary badge-<?= Order::colorStatus($order['order']->getStatus()); ?> mb-0"><?= View::toHtml($order['order']->getStatusName()); ?></span><hr class="bg-light my-1"/>
                                            <span class="font-weight-bold text-lg">
                                                <?php foreach ($order['location'] as $loc)
                                                    echo View::toHtml($loc->getLocation($warehouse_label)) . '<br/>';
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
                        <h2 class="mb-0">Main Storage</h2>
                    </div>
                    <div class="row justify-content-center">
                        <h5>(free - <span class="text-success font-weight-bold" id="id_free"></span>; warning - <span class="text-danger font-weight-bold" id="id_warn"></span>)</h5>
                    </div>
                    <div class="row justify-content-center">
                        <?php
                        $i = 1;
                        $free = 0;
                        $warn = 0;
                        $offset_before = 0;
                        $offset_after = 0;
                        $offset = false;
                        if (($OB_info['rows'] ?? 10) < 12) {
                            $offset = true;
                            $offset_sum = 12 - ($OB_info['rows'] ?? 10);
                            $offset_before = intval(ceil($offset_sum / 2));
                            $offset_after = intval($offset_sum - $offset_before);
                        }
                        if (!empty($OBs[0]))
                            foreach ($OBs[0] as $OB) {
                                if (!empty($OB['minus']))
                                    $color = 'danger';
                                elseif (empty($OB['state']))
                                    $color = 'secondary';
                                elseif (!empty($OB['consist']) && !empty($OB['orders']))
                                    $color = 'warning';
                                elseif (empty($OB['consist']) && empty($OB['orders']))
                                    $color = 'success';
                                else
                                    $color = 'danger';
                                if ($offset && ($i % ($OB_info['rows'] ?? 10) == 1))
                                    echo "<div class='col-" . $offset_before . "'></div>";
                                if ($color == 'success')
                                    $free++;
                                if ($color == 'danger')
                                    $warn++;
                            ?>
                            <div class="col-1 text-md-center rounded border bg-<?= $color; ?>"
                                <?php if (!empty($OB['orders'])) { ?>
                                    onclick="location = '/additional/view_order/?id=<?= $OB['order_id']; ?>&back=order_bin';"
                                <?php } ?>
                            >
                                <div class="row">
                                    <div class="col border-bottom pb-1 border-secondary">
                                        <span class="font-weight-bold text-lg"><?= $OB['row']; ?></span><br/>
                                        <?= $OB['orders'] ?: '&nbsp;'; ?>
                                    </div>
                                </div>
                                <div class="row" style="max-height: 100px; min-height: 30px; overflow-y: auto;">
                                    <div class="col text-xs">
                                        <?= $OB['consist'] ?: '&nbsp;'; ?>
                                    </div>
                                </div>
                            </div>
                        <?php
                            if ($offset && ($i % ($OB_info['rows'] ?? 10) == 0))
                                echo "<div class='col-" . $offset_after . "'></div>";
                            if ($i % (($OB_info['cols'] ?? 5)*($OB_info['rows'] ?? 10)) == 0)
                                echo "<div class='col-12' style='height: 5px;'></div>";
                            $i++;
                        } ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($OBs[1])) { ?>
                <div class="row mt-3">
                    <div class="col ml-3">
                        <div class="row justify-content-center">
                            <h2 class="mb-0">Mezzanine</h2>
                        </div>
                        <div class="row justify-content-center">
                            <h5>(free - <span class="text-success font-weight-bold" id="id_freeM"></span>; warning - <span class="text-danger font-weight-bold" id="id_warnM"></span>)</h5>
                        </div>
                        <div class="row justify-content-center">
                            <?php
                            $i = 1;
                            $freeM = 0;
                            $warnM = 0;
                            foreach ($OBs[1] as $OB) {
                                if (!empty($OB['minus']))
                                    $color = 'danger';
                                elseif (empty($OB['state']))
                                    $color = 'secondary';
                                elseif (!empty($OB['consist']) && !empty($OB['orders']))
                                    $color = 'warning';
                                elseif (empty($OB['consist']) && empty($OB['orders']))
                                    $color = 'success';
                                else
                                    $color = 'danger';
                                if ($color == 'success')
                                    $freeM++;
                                if ($color == 'danger')
                                    $warnM++;
                                if ($offset && ($i % ($OB_info['rows'] ?? 10) == 1))
                                    echo "<div class='col-".$offset_before."'></div>";
                                ?>
                                <div class="col-1 text-md-center rounded border bg-<?= $color; ?>"
                                    <?php if (!empty($OB['orders'])) { ?>
                                        onclick="location = '/additional/view_order/?id=<?= $OB['order_id']; ?>&back=order_bin';"
                                    <?php } ?>
                                >
                                    <div class="row">
                                        <div class="col border-bottom pb-1 border-secondary">
                                            <span class="font-weight-bold text-lg"><?= $OB['row']; ?></span><br/>
                                            <?= $OB['orders'] ?: '&nbsp;'; ?>
                                        </div>
                                    </div>
                                    <div class="row" style="max-height: 100px; min-height: 30px; overflow-y: auto;">
                                        <div class="col text-xs">
                                            <?= $OB['consist'] ?: '&nbsp;'; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php
                                if ($offset && ($i % ($OB_info['rows'] ?? 10) == 0))
                                    echo "<div class='col-" . $offset_after . "'></div>";
                                if ($i % (($OB_info['cols'] ?? 5)*($OB_info['rows'] ?? 10)) == 0)
                                    echo "<div class='col-12' style='height: 5px;'></div>";
                                $i++;
                            } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<script>
    $('#id_free').text(<?= empty($free) ? 0 : $free; ?>);
    $('#id_warn').text(<?= empty($warn) ? 0 : $warn; ?>);
    $('#id_freeM').text(<?= empty($freeM) ? 0 : $freeM; ?>);
    $('#id_warnM').text(<?= empty($warnM) ? 0 : $warnM; ?>);
</script>