<?php

use app\backend\models\Pack;
use app\backend\models\SKUs;
use app\backend\models\Warehouses;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\Filter;
use app\core\statical\WithDate;

/**
 * @var User $authUser
 * @var array $orders
 * @var int $status
 * @var bool $is_mezzanine
 * @var int $total_count
 * @var int $total_pages
 * @var int $current_page
 * @var array $sort_services
 * @var array $suppliers
 * @var array $filters
 * @var array $floors
 */

$packing = !is_array($status) && Order::isAwaitingPack($status);

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= $packing ? 'Packing' : 'Packed'; ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                        Orders list (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="get" id="filter" action="/pack/<?= $packing ? 'all' : 'packed'; ?>" onchange="submit();">
                <div class="form-row justify-content-center">
                    <div class="form-group col-lg-3">
                        <div class="form-group-element">
                            <select id="packing_type" name="packing_type" class="form-control form-control-lg custom-select" >
                                <option value="0">All</option>
                                <?php foreach (Pack::$type_of_packing as $key => $value) { ?>
                                    <option value="<?= $key; ?>"<?= !empty($_GET['packing_type']) && $key == $_GET['packing_type'] ? ' selected' : ''; ?>><?= ucwords($value); ?></option>
                                <?php } ?>
                            </select>
                            <label for="packing_type">Type of packing</label>
                        </div>
                    </div>
                    <?php if ($is_mezzanine) { ?>
                        <div class="form-group col-lg-3">
                            <div class="form-group-element">
                                <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'floor'
                                ];
                                echo Html::dropDownList('filter[floor]',
                                    $floors,
                                    $filters['floor'] ?? null, $options);
                                ?>
                                <label for="floor">Floor</label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-lg-3">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'services',
                                    'onchange' => 'filter.submit()',
                                    'data-empty-value' => Filter::NOT_SELECTED,
                                ];
                                if (count($sort_services) == 1)
                                    $options += ['disabled' => 'disabled'];
                                echo Html::multiSelect('filter[services][]', 
                                    $sort_services,
                                    $filters['services'] ?? [], 
                                    $options);
                            ?>
                            <label for="services">Services</label>
                        </div>
                    </div>
                    <div class="form-group col-lg-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'clients',
                                'onchange' => 'filter.submit()',
                                'data-empty-value' => Filter::NOT_SELECTED,
                            ];
                            echo Html::multiSelect('filter[clients][]', ['-1' => 'Select clients'] + $suppliers, $filters['clients'] ?? [],  $options);
                            ?>

                            <label for="clients">Clients</label>
                        </div>
                    </div>
                </div>
            </form>
            <?php if (!empty($orders)) { ?>
                <div class="row">
                    <div class="col-12">
                        <?php
                        $path = "/pack/" . ($packing ? 'all' : 'packed') . "?" . http_build_query([
                            'packing_type' => !empty($_GET['packing_type']) ? $_GET['packing_type'] : '',
                            'filter' => $filters,
                        ]) . '&';
                        View::pagination($path, 1, $total_pages, $current_page,
                            Pack::LIMIT_PER_PAGE); ?>
                        <div class="row">
                            <table class="table table-hover">
                                <colgroup>
                                    <col width="*">
                                    <col width="20%">
                                    <col width="*">
                                    <col width="*">
                                    <col width="*">
                                    <col width="*">
                                </colgroup>
                                <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Account</th>
                                    <th>Despatch</th>
                                    <th>Location</th>
                                    <th>SKUs / Units</th>
                                    <th>Priority</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($orders as $order) { ?>
                                    <tr<?= WithDate::dateToInt($order['despatch_date']) < WithDate::dateToInt(date('Y-m-d', time())) ? ' style="background-color: #feebef"' : ''; ?>>
                                        <td><?= View::toHtml($order['id']); ?></td>
                                        <td><?= View::toHtml("{$order['name']} ({$order['sid']})"); ?></td>
                                        <td><?= !empty($order['despatch_date']) ? View::toHtml(date('jS M Y', WithDate::dateToInt($order['despatch_date']))) : '-'; ?></td>
                                        <td><?= View::toHtml("{$order['warehouse']}-{$order['type_id']}-{$order['zone']}-{$order['column']}-{$order['row']}"); ?></td>
                                        <td><?= Location::isTP($order['type_id']) ? 'single' : (
                                                View::toHtml("{$order['SKUs']} / {$order['units']}") . (Location::isLP($order['type_id']) ? "<br/>large" : '')
                                        ); ?></td>
                                        <td>
                                            <?= SKUs::viewProcessingStatus($order); ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php View::pagination($path, 1, $total_pages, $current_page,
                            Pack::LIMIT_PER_PAGE); ?>
                    </div>
                </div>
            <?php } else {
                echo Html::warning("No orders found");
            } ?>
        </div>
    </div>
</div>
<?= View::include_js(BACKEND_JS, "multi_select.js"); ?>