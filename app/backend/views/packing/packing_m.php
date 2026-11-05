<?php

use app\backend\models\SKUs;
use app\backend\models\Warehouses;
use app\core\entities\Order;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\Filter;
use app\core\statical\WithDate;

/**
 * @var User $authUser
 * @var array $orders
 * @var int $TPs
 * @var array $users
 * @var array $printers
 * @var string $printer_def
 * @var bool $is_mezzanine
 * @var array $floors
 * @var array $filters
 * @var array $sort_services
 * @var array $suppliers
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Packing <?= $_SESSION['packing_mode']; ?></h1>
    </div>
    <div class="card shadow mb-4">
            <div class="card-header py-3">
                <div class="row">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-tasks"></i>
                            Orders list (<?= count($orders); ?> item<?= count($orders) > 1 ? 's' : '' ?>)
                        </span>
                        </h6>
                    </div>
                    <div class="col-auto">
                        <h6 class="m-0 font-weight-bold text-primary">Select Retain Me printer</h6>
                        <?= Html::dropDownList('printer_def_select', $printers, $printer_def . "",
                            [
                                'class' => 'selectpicker form-control',
                                'onchange' => "document.getElementById('printer_def').value = this.value",
                            ]);
                        ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form action="/pack/packing_m" method="GET" name="filter">
                    <div class="form-row justify-content-center">
                        <?php if ($is_mezzanine) { ?>
                            <div class="form-group col-lg-3">
                                <div class="form-group-element">
                                    <?php
                                        $options = [
                                            'class' => 'form-control form-control-lg custom-select h-100',
                                            'id' => 'floor',
                                            'onchange' => 'filter.submit()'
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
                                        'onchange' => "action = ''; submit();",
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
                                    'onchange' => "action = ''; submit();",
                                    'data-empty-value' => Filter::NOT_SELECTED,
                                ];
                                echo Html::multiSelect('filter[clients][]', ['-1' => 'Select clients'] + $suppliers, $filters['clients'] ?? [],  $options);
                                ?>
                                <label for="clients">Clients</label>
                            </div>
                        </div>
                        <div class="form-group col-lg-3">
                            <div class="form-group-element">
                                <input
                                    type="date"
                                    class="form-control form-control-lg"
                                    id="despatch_date"
                                    name="filter[despatch_date]"
                                    value="<?= !empty($filters['despatch_date']) && $filters['despatch_date'] != Filter::NOT_SELECTED
                                        ? View::toHtml($filters['despatch_date'])
                                        : '' ?>"
                                    autocomplete="off"
                                    onblur="action = ''; submit();"
                                    onkeydown="if (event.key === 'Enter') { event.preventDefault(); action = ''; submit(); }"
                                >
                                <label for="despatch_date">Despatch date</label>
                            </div>
                        </div>
                    </div>
                </form>
                <form method="GET" action="/pack/process" class="js-form-submit-once" data-submit-key="scan_multi_pack">
                    <input type="hidden" id="printer_def" name="printer_def" value="<?= View::toHtml($printer_def) ?>">
                    <?php if ($filters['floor'] && $filters['floor'] != Filter::NOT_SELECTED) { ?>
                        <input type="hidden" name="floor" value="<?= View::toHtml($filters['floor']) ?>">
                    <?php } ?>
                <?php if (!empty($orders)) { ?>
                    <div class="row justify-content-md-center">
                        <div class='input-group col-lg-6'>
                            <?php (new Scan([
                                'name' => 'location_id',
                                'placeholder' => 'Scan Location here to start packing...',
                                'type' => Scan::TYPE__LOCATION,
                            ], ['id' => 'scan_btn']))->render(); ?>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <colgroup>
                                        <col width="10%">
                                        <col width="11%">
                                        <col width="11%">
                                        <col width="11%">
                                        <col width="14%">
                                        <col width="11%">
                                        <col width="11%">
                                        <col width="11%">
                                        <col width="10%">
                                    </colgroup>
                                    <thead>
                                    <tr>
                                        <th>Order ID/Reference</th>
                                        <th>Client Name</th>
                                        <th>Despatch date</th>
                                        <th>Service</th>
                                        <th>Location ID</th>
                                        <th>SKU/Units</th>
                                        <th>Status</th>
                                        <th>Packing by</th>
                                        <th>Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($orders as $order) {
                                        switch ($order['status']) {
                                            case Order::AWAITING_PACK_NUM:
                                            case Order::PACKING_NUM:
                                                $style = 'secondary';
                                                break;
                                            case Order::PRINTING_DOCUMENTS_NUM:
                                                $style = 'success';
                                                break;
                                            default:
                                                $style = 'danger';
                                        }
                                        ?>
                                        <tr<?= strtotime($order['despatch_date']) < strtotime(date('Y-m-d',time())) ? ' style="background-color: #feebef !important"' : ''; ?>>
                                            <td>
                                                <b>ID</b>: <?= View::toHtml($order['id']) ?>
                                                <br/>
                                                <b>Reference</b>: <?= View::toHtml($order['ref'] ?: 'N/A') ?>
                                            </td>
                                            <td>
                                                <?= !empty($order['name']) ? View::toHtml($order['name']) : '-' ?>
                                            </td>
                                            <td>
                                                <?php if (empty($order['despatch_date'])) { ?>
                                                    -
                                                <?php } else { ?>
                                                    <?= View::toHtml(date('jS M Y',
                                                        WithDate::dateToInt($order['despatch_date']))); ?>
                                                    <?php if (WithDate::dateToInt($order['despatch_date']) < time()) { ?>
                                                        <i class="fa fa-exclamation-triangle"></i>
                                                    <?php } ?>
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?= !empty($order['shipping_service']) ? View::toHtml($order['shipping_service']) : '-' ?>
                                            </td>
                                            <td<?= SKUs::viewPackingText($order); ?>><?= View::toHtml($order['location']); ?></td>
                                            <td><?= View::toHtml($order['SKUs']) . ' / ' . View::toHtml($order['units']); ?></td>
                                            <td>
                                                <h5>
                                                    <span class="badge badge-<?= View::toHtml($style) ?>">
                                                        <?= Order::getStateName($order['status']); ?>
                                                    </span>
                                                </h5>
                                            </td>
                                            <td>
                                                <?php if(!empty($order['packing_user_id']) && !empty($users[$order['packing_user_id']])) { ?>
                                                    <?= View::toHtml($users[$order['packing_user_id']]['login'])?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            </td>
                                            <td>
                                                <?php switch ($order['status']) {
                                                    case Order::AWAITING_PACK_NUM:
                                                    case Order::PACKING_NUM:
                                                        ?>
                                                        <button class="col btn btn-primary js-start-printing" id="prndoc_<?= (int)$order['id'] ?>" type="button">
                                                            <?= Order::isPacking($order['status']) ? 'Continue' : 'Start packing'; ?>
                                                        </button>
                                                        <?php break;
                                                    case Order::PRINTING_DOCUMENTS_NUM:
                                                        ?>
                                                        <button class="col btn btn-secondary js-printing-documents" id="prndoc_<?= (int)$order['id'] ?>" type="button">
                                                            Print documents
                                                        </button>
                                                        <?php break;
                                                    case Order::PRINTING_DOCUMENTS_ISSUE_NUM:
                                                        ?>
                                                        <button class="col btn btn-secondary js-printing-documents" id="prndoc_<?= (int)$order['id'] ?>" type="button">
                                                            Try again
                                                        </button>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php } else {
                    if ($TPs)
                        echo Html::warning("No orders waiting to be packed");
                    else
                        echo Html::error("No Pack Station for your account");
                } ?>
                </form>
            </div>
    </div>
</div>
<script>
    $('.js-start-printing').click(function (e) {
        var id = e.currentTarget.id.split('_');
        location = '/pack/process?order_id=' + id[1] + '&printer_def=' + $('#printer_def').val();
    });
    $('.js-printing-documents').click(function (e) {
        var id = e.currentTarget.id.split('_');
        location = '/pack/printing_documents?mode=m&id=' + id[1] + '&source_id=' + $('#printer_def').val();
    });
</script>
<?= View::include_js(BACKEND_JS, "multi_select.js"); ?>