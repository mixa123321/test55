<?php

use app\backend\models\SKUs;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\Filter;
use app\core\statical\WithDate;

/**
 * @var User $authUser
 * @var array $orders
 * @var array $tables
 * @var array $users
 * @var int $table_id
 * @var array $printers
 * @var string $printer_def
 * @var array $carts
 * @var Location $cart
 * @var bool $can_unhook_cart
 * @var array $sort_services
 * @var array $suppliers
 * @var array $filters
 */

$pallets_to_be_returned = [];

foreach ($carts as $cart)
    if ($cart['type_id'] == Location::ST && $cart['orders_count'] == 0)
        $pallets_to_be_returned[$cart['id']] = $cart;
$carts = array_diff_key($carts, $pallets_to_be_returned);

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Packing <?= $_SESSION['packing_mode']; ?></h1>
    </div>
    <?php foreach ($pallets_to_be_returned as $pallet) { ?>
        <?= Html::warning(<<<HTML
                <span>Pallet <b>{$pallet['name']}</b> is ready to be returned. 
                        If you would like to return this pallet, press <b>"Return"</b></span>
                <a class="btn btn-primary btn-icon-split btn-sm" 
                    href='/cart/unhook_trolley/?cart_id={$pallet['id']}&back=/pack/packing_l?table_id={$table_id}'
                >
                    <span class="icon text-white-50">
                        <i class="fas fa-fw fa-arrow-left"></i>
                    </span>
                    <span class="text d-none d-sm-block" style="width:150px;">Return</span>
                </a>
HTML, false); ?>
    <?php } ?>
    <div class="card shadow mb-4">
        <form method="GET" action="/pack/process">
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
                    <?php if (count($carts)) { ?>
                        <div class="col-auto">
                            <button class="btn btn-primary btn-icon-split mt-1" type="button" onclick="openCartModal()">
                                <span class="icon text-white">
                                    <i class="fas fa-fw fa-shopping-cart"></i>
                                </span>
                                <span class="text d-none d-md-block">Trolleys</span>
                            </button>
                        </div>
                    <?php } ?>
                    <?php if (count($tables)) { ?>
                        <div class="col-auto">
                            <h6 class="m-0 font-weight-bold text-primary">Select table
                                <?php
                                echo Html::dropDownList('table_id', $tables, $table_id,
                                    [
                                        'class' => 'selectpicker form-control',
                                        'id' => 'tables',
                                        'onchange' => "action = ''; submit();",
                                    ]);
                                ?>
                            </h6>
                        </div>
                    <?php } ?>
                    <div class="col-auto">
                        <h6 class="m-0 font-weight-bold text-primary">Select Retain Me printer
                            <?= Html::dropDownList('printer_def', $printers, $printer_def . "",
                                    [
                                        'class' => 'selectpicker form-control',
                                        'id' => 'printer_def',
                                    ]);
                            ?>
                        </h6>
                    </div>
                </div>
            </div>
            <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <div class="row justify-content-md-center">
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
                                            onblur="action = ''; submit();"
                                            onkeydown="if (event.key === 'Enter') { event.preventDefault(); action = ''; submit(); }"
                                            autocomplete="off"
                                        >
                                        <label for="despatch_date">Despatch date</label>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($orders)) { ?>
                            <div class="row justify-content-md-center">
                                <div class="input-group col-lg-6">
                                    <?php (new Scan([
                                        'name' => 'location_id',
                                        'placeholder' => 'Scan Order ID/Reference/SKU barcode here to start packing...',
                                        'type' => Scan::TYPE__ORDER,
                                    ], ['id' => 'scan_btn']))->render(); ?>
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <colgroup>
                                                <col width="10%">
                                                <col width="11%">
                                                <col width="11%">
                                                <col width="11%">
                                                <col width="16%">
                                                <col width="11%">
                                                <col width="10%">
                                                <col width="10%">
                                                <col width="10%">
                                            </colgroup>
                                            <thead>
                                            <tr>
                                                <th>Order ID/Reference</th>
                                                <th>Client Name</th>
                                                <th>Despatch date</th>
                                                <th>Service</th>
                                                <th>Locations</th>
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
                                                    <td<?= SKUs::viewPackingText($order); ?>>
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
                                                    <td>
                                                        <?= View::toHtml($order['location']); ?>
                                                        <?php if (!empty($order['cart'])) { ?>
                                                            <small><br><b>Trolley: </b><?= View::toHtml($order['cart']); ?></small>
                                                        <?php } else if (!empty($order['pallet'])) { ?>
                                                            <small><br><b>Pallet: </b><?= View::toHtml($order['pallet']); ?></small>
                                                        <?php } ?>
                                                    </td>
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
                                                                <button class="btn btn-primary js-start-printing" id="prndoc_<?= (int)$order['id'] ?>" type="button">
                                                                    <?= Order::isPacking($order['status']) ? 'Continue' : 'Start packing'; ?>
                                                                </button>
                                                                <?php break;
                                                            case Order::PRINTING_DOCUMENTS_NUM:
                                                                ?>
                                                                <button class="btn btn-secondary js-printing-documents" id="prndoc_<?= (int)$order['id'] ?>" type="button">
                                                                    Print documents
                                                                </button>
                                                                <?php break;
                                                            case Order::PRINTING_DOCUMENTS_ISSUE_NUM:
                                                                ?>
                                                                <button class="btn btn-secondary js-printing-documents" id="prndoc_<?= (int)$order['id'] ?>" type="button">
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
                                if (count($tables))
                                    echo Html::warning("No orders waiting to be packed");
                                else
                                    echo Html::error("No Pack Station for your account");
                            } ?>
                        </div>
                    </div>
                </div>
        </form>
            </div>
    </div>
<?php

$modal_carts = new InfoModal('cardList', 'Hooked trolleys');
echo $modal_carts->includeFile('/packing/modals/hooked_trolleys.php', [
        'carts' => $carts,
        'can_unhook_cart' => $can_unhook_cart,
        'table_id' => $table_id,
    ])
    ->put();

?>
<script>
    $('.js-start-printing').click(function (e) {
        var id = e.currentTarget.id.split('_');
        location = '/pack/process?order_id=' + id[1] + '&printer_def=' + $('#printer_def').val() + '&table_id=' + '<?= $table_id ?>';
    });
    $('.js-printing-documents').click(function (e) {
        var id = e.currentTarget.id.split('_');
        location = '/pack/printing_documents?mode=l&id=' + id[1] + '&source_id=' + $('#printer_def').val();
    });
    function openCartModal() {
        <?= $modal_carts->show(); ?>
    }
</script>
<?= View::include_js(BACKEND_JS, "multi_select.js"); ?>