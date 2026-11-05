<?php
/**
 * @var $authUser User
 * @var $months array
 * @var $years array
 * @var $mounth array
 * @var $week
 * @var $days
 * @var $today string
 * @var $users array
 * @var $ASNs array
 */

use app\core\entities\User;
use app\core\statical\Months;
use app\core\helpers\View;

?>
<style>
    .table-hover-cells td.cells-ASN:hover {
        background-color: #eee;
    }
    div.incoming-day {
        /*float: right;*/
        width: 1.7em;
        height: 1.7em;
        background-color: #4d148c;
        color: #fff;
        text-align: center;
        position: absolute;
        right: 0;
        top: 0;
    }
    td.cells-ASN {
        margin: 0px;
        padding: 0px;
        width: calc(100%/7);
    }
    td.cells-ASN-today {
        background-color: #eeffee;
    }
    td.cells-ASN-unscheduled {
        background-color: #f00;
    }
    td.cells-ASN-pass {
        background-color: #eee;
    }

</style>

<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Incoming ASNs</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        ASNs list (<?= count($ASNs); ?> item<?= count($ASNs) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <div class="form-inline">
                        <form method="post" action="/goods_in/" >
                            <select name="month" class="form-control custom-select" onchange="submit();">
                                <?php foreach (Months::getAll() as $num => $month) {
                                    if ($num < $months[0] || $num > $months[1])
                                        continue; ?>
                                    <option value="<?= $num ?>"<?= $num == $months[2] ? " selected" : "" ?>><?= $month ?></option>
                                <?php } ?>
                            </select>&nbsp;
                            <select name="year" class="form-control custom-select" onchange="submit();">
                                <?php for ($i = $years[0]; $i <= $years[1]; $i++) { ?>
                                    <option<?= $i == $years[2] ? " selected" : "" ?>><?= $i ?></option>
                                <?php } ?>
                            </select>&nbsp;
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <table class="table table-bordered table-hover-cells">
                        <thead>
                        <tr>
                            <th>Sunday</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                            <th>Saturday</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                            $day = 0;
                            $num = 0;
                            for ($i = 0; $i < 6; $i++) {
                                echo "<tr>";
                                for ($j = 0; $j < 7; $j++) {
                                    $day = $i*7 + $j + 1 - $week;
                                    if ($day > 0 && $day <= $days) {
                                        echo "<td class='cells-ASN" . ($today > 0 && $today == $day ? ' cells-ASN-today' : '') . "' style='position: relative'><div class='incoming-day'>$day</div><div class='text-xs' style='height: 10em; padding: 0.5em; margin-top: 2.4em; overflow-y: auto;'>";
                                        $nums = [0, 0, 0];
                                        while ($num < count($ASNs) && $day == $ASNs[$num]['day']) {
                                            echo "<b><a class='btn-link text-success text-decoration-none' href='/" . (empty($ASNs[$num]['oref']) ? 'goods_in/view_asn' : 'return_ASNs/view_rasn') .
                                                "/?id=" . (int)$ASNs[$num]['id'] . "'>" . View::toHtml($ASNs[$num]['name']) . "</a></b><br/>ASN: <b>" . (int)$ASNs[$num]['id'] . " / " . (empty($ASNs[$num]['ref']) ? '-' : View::toHtml($ASNs[$num]['ref'])) . "</b><br/>" .
                                                (!empty($ASNs[$num]['order_id']) ? "<span class='badge badge-secondary'>Return order</span> <b>" . (int)$ASNs[$num]['order_id'] . " / " . (empty($ASNs[$num]['oref']) ? '-' : View::toHtml($ASNs[$num]['oref'])) . "</b><br/>" : '') .
                                                "SKUs: <b>{$ASNs[$num]['skus']}</b> / Units: <b>{$ASNs[$num]['units']}</b><br/>";
                                            $nums[0]++;
                                            $nums[1] += $ASNs[$num]['skus'];
                                            $nums[2] += $ASNs[$num]['units'];
                                            $num++;
                                        }
                                        if ($nums[0] > 0) {
                                            echo "<br/><span class=\"text-lightgray\"><b>{$nums[0]} ASNs / {$nums[1]} SKUs / {$nums[2]} Units</b></span>";
                                        }
                                        echo "</div></td>";
                                    } else {
                                        echo '<td class="cells-ASN-pass"></td>';
                                    }
                                }
                                echo "</tr>";
                                if ($day >= $days)
                                    break;
                            }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
