<?php

use app\core\entities\User;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\backend\models\SKUs;
use app\core\statical\WithDate;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\Filter;

/**
 * @var User $authUser
 * @var string $warehouse_label
 * @var array $orders
 * @var array $users
 * @var array $orders_skus
 * @var array $tables
 * @var int $table_id
 * @var int $print_limit
 * @var array $SKUs_filter
 * @var null|SKU $SKU
 * @var float $max_weight
 * @var array $packages
 * @var bool $hide
 * @var array $filters
 */

$order_ids = array_keys($orders);
$scan = new Scan([
    'id' => 'sku_count',
    'col' => 6,
    'name' => 'sku_count',
    'placeholder' => 'SKUs count...',
    'value' => 1,
    'allow_copy_paste' => true,
], ['id' => 'count_btn', 'icon' => 'fa fa-print', 'type' => '', 'title' => 'Print']);

$printing_documents_issue = '
<div class="col-auto">
    <h6 class="mb-1">Hide Printing Documents Issue</h6>
    <div class="custom-control custom-checkbox">
        <input class="custom-control-input" id="printing_documents_issue" name="hide_errors" value="on" type="checkbox" ' . ($hide ? 'checked' : '') . '>
        <label for="printing_documents_issue" class="custom-control-label w-100"></label>
    </div>
</div>';

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

                <?php if (false && !empty($orders)) { ?>
                    <div class="col-auto" style="align-content: end;">
                        <button type="button" class="btn btn-block btn-success mt-0 mb-0 js-conf-btn">Packing
                            configuration
                        </button>
                    </div>
                <?php } ?>
                <?= $printing_documents_issue ?>
                <?php if (count($SKUs_filter)) { ?>
                    <div class="col-auto">
                        <h6 class="m-0 font-weight-bold text-primary">Select SKU
                            <?php
                            echo Html::dropDownList('SKU_id', $SKUs_filter, (string)$SKU->getId(),
                                [
                                    'class' => 'selectpicker form-control',
                                    'id' => 'SKUs',
                                ]);
                            ?>
                        </h6>
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
                                ]);
                            ?>
                        </h6>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <form action="/pack/packing_b" method="GET">
                <input type="hidden" name="table_id" value="<?= (int)$table_id ?>">
                <input type="hidden" name="SKU_id" value="<?= (int)$SKU->getId() ?>">
                <input type="hidden" name="hide" value="<?= $hide ? 1 : 0 ?>">

                <div class="form-row justify-content-center">
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
                                onblur="this.form.submit();"
                                autocomplete="off"
                            >
                            <label for="despatch_date">Despatch date</label>
                        </div>
                    </div>
                </div>
            </form>
            <?php if (!empty($orders)) { ?>
                <div class="row justify-content-md-center">
                    <div class="col-6 mb-2">
                        <?= Html::info('The print limit is ' . min($print_limit, count($orders))); ?>
                    </div>
                </div>
                <div class="row justify-content-md-center">
                    <div class="input-group col-lg-6">
                        <?php $scan->render(); ?>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col width="11%">
                                <col width="12%">
                                <col width="11%">
                                <col width="11%">
                                <col width="11%">
                                <col width="11%">
                                <col width="8%">
                                <col width="10%">
                                <col width="5%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Order ID/Reference</th>
                                <th>Client Name</th>
                                <th>Despatch date</th>
                                <th>Service</th>
                                <th>Location</th>
                                <th>SKU</th>
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
                                <tr<?= strtotime($order['despatch_date']) < strtotime(date('Y-m-d', time())) ? ' style="background-color: #feebef !important"' : ''; ?>>
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
                                    </td>
                                    <td><?= View::toHtmlConvertWithPrimaryBarcode($orders_skus[$order['id']]['barcode']); ?></td>
                                    <td>
                                        <h5>
                                        <span class="badge badge-<?= View::toHtml($style) ?>">
                                            <?= Order::getStateName($order['status']); ?>
                                        </span>
                                        </h5>
                                    </td>
                                    <td>
                                        <?php if (!empty($order['packing_user_id']) && !empty($users[$order['packing_user_id']])) { ?>
                                            <?= View::toHtml($users[$order['packing_user_id']]['login']) ?>
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    </td>
                                    <td>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } else {
                echo Html::warning("No orders waiting to be packed");
            } ?>
        </div>
    </div>
</div>
<?php
$options = '';
foreach ($packages as $package)
    $options .= '<option value="' . $package['id'] . '" ' . ($package['is_default'] ? 'selected' : '') . '>' . $package['name'] . '</option>';

$content = '
<div>
    <div class="alert alert-danger p-2 d-none" id="msg_error"></div>
    <div class="row">
        <div class="col-md-12 mb-3">
            Apply only for orders in status awaiting pack
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-12">
            <select class="form-control form-control-sm box-type-select" id="package_type_id">
                ' . $options . '
            </select>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="row">
                <div class="col-4 form-group-element">
                    <input type="number" class="form-control form-control-sm package_dims_length" id="length" value="0" min="0.01" step="0.01" style="padding-top: 1.2rem;">     
                    <label for="length" style="top: 0.4rem;">Length (cm)<span class="text-danger">*</span></label>           
                </div>
                <div class="col-4 form-group-element">
                    <input type="number" class="form-control form-control-sm package_dims_width" id="width" value="0" min="0.01" step="0.01" style="padding-top: 1.2rem;">
                    <label for="width" style="top: 0.4rem;">Width (cm)<span class="text-danger">*</span></label>  
                </div>
                <div class="col-4 form-group-element">
                    <input type="number" class="form-control form-control-sm package_dims_height" id="height" value="0" min="0.01" step="0.01" style="padding-top: 1.2rem;">
                    <label for="height" style="top: 0.4rem;">Height (cm)<span class="text-danger">*</span></label>   
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="row">
                <div class="col-12 form-group-element">
                    <input type="number" class="form-control form-control-sm weight" id="weight" value="" min="0" step="any" data-original-title="" title="" style="padding-top: 1.2rem;">
                    <label for="weight" style="top: 0.4rem;">Weight, kg<span class="text-danger">*</span></label>  
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="text-center pt-0" style="font-size: 0.85rem; padding-top: 0.5rem; margin-bottom: 0;float: left">Max weight <span id="max_weight" class="text-danger font-weight-bold">10</span></div>
        </div>
        <div class="col-md-6">
            <div class="text-center pt-0" style="font-size: 0.85rem; padding-top: 0.5rem; margin-bottom: 0;float: right">Expected weight <span id="box_weight" class="text-danger font-weight-bold">5</span></div>
        </div>
    </div>
</div>';

$modal_packing = new SwitchModal('packing', 'Packing configuration', SwitchModal::SIZE_NORMAL);
echo $modal_packing->addContent($content)
    ->addButton('Pack', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE, ['id' => 'btn_packing_conf'])
    ->put();
?>

<script>
    $(document).ready(function () {

        $('#sku_count').keyup(function () {
            let prevValue = $(this).data("prevValue");
            if (prevValue === undefined) {
                prevValue = 1;
            }
            if (!/^\d{0,1000}$/i.test(this.value) || this.value > <?= min($print_limit, count($orders)); ?>) {
                $(this).val(prevValue)
                return;
            }
            $(this).data("prevValue", this.value);
        });

        function getFilters(skip_SKU_id = false) {
            return "?table_id=" + $('#tables').val() + (skip_SKU_id ? '' : '&SKU_id=' + $('#SKUs').val()) + "&hide=" + ($('#printing_documents_issue').is(':checked') ? 1 : 0);
        }

        $('#SKUs').change(function () {
            window.location.href = '/pack/packing_b' + getFilters();
        });

        $('#tables').change(function () {
            window.location.href = '/pack/packing_b' + getFilters(true);
        });


        $('#printing_documents_issue').change(function () {
            window.location.href = '/pack/packing_b' + getFilters();
        });

        class Packing {
            constructor(packages, max_package_weight, SKU_weight) {
                this.packages = packages;
                this.max_package_weight = max_package_weight;
                this.box_weight = 0;
                this.max_weight = 0;
                this.current_weight = 0;
                this.SKU_weight = SKU_weight;
            }

            getCommonMaxWeight() {
                let common_max = Math.min(this.max_package_weight, this.max_weight);
                if (common_max === 0)
                    common_max = Math.max(this.max_package_weight, this.max_weight);
                return common_max;
            }

            setPackView(length, width, height, weight) {
                $('#length').val(length);
                $('#width').val(width);
                $('#height').val(height);
               // $('#weight').val(weight);
            }

            showModal() {
                <?= $modal_packing->show(); ?>;
                return this;
            }

            setButtonSave() {
                // $('#btn_packing_conf').html('Save');
                return this;
            }

            setButtonPack() {
                // $('#btn_packing_conf').html('Pack');
                return this;
            }

            sendBulk() {
                let count = parseInt(document.getElementById('sku_count').value);
                if (1 > count || $('#count_btn').prop('disabled'))
                   return;
                let length = +$('#length').val();
                let width = +$('#width').val();
                let height = +$('#height').val();
                let weight = +$('#weight').val();
                let package_type_id = $('#package_type_id').val();
                if (!length || !width || !height || !weight || !package_type_id) {
                   showError('msg_error', 'Please fill all fields', 3000);
                   this.setButtonPack().showModal();
                   return;
                }
                let order_ids = <?= View::toJs($order_ids) ?>;
                let min = Math.min(count, order_ids.length);
                sendPostForm({'order_ids': JSON.stringify(order_ids.slice(0, min)), 'length': length, 'width': width, 'height': height, 'weight': weight, 'package_type_id': package_type_id}, '/pack/add_bulk_queue' + getFilters());
            }

            getDefaultPackageId() {
                let pack_id = null;
                for (const p of this.packages) {
                    if (pack_id === null)
                        pack_id = +p.id;
                    if (p.is_default) {
                        pack_id = +p.id
                        break;
                    }
                }
                return pack_id;
            }

            setPackData(pack_id) {
                let pack = this.getPackageById(pack_id);
                this.box_weight = pack.box_weight;
                this.max_weight = pack.weight;
                this.current_weight = Math.min(this.box_weight,  this.max_weight);
                this.setPackView(pack.length, pack.width, pack.height, this.current_weight);
                $('#box_weight').html(this.box_weight + this.SKU_weight);
                $('#max_weight').html(this.getCommonMaxWeight());
            }

            getPackageById(package_id) {
                return this.packages.find(p => +p.id === +package_id);
            }

            initPackage() {
                const pack_id = this.getDefaultPackageId();
                if (pack_id)
                    this.setPackData(pack_id);
                return this;
            }

            addEvents() {
                let self = this;
                $('.js-conf-btn').click(function () {
                    self.setButtonSave().showModal();
                });
                $('#weight').change(function () {
                    let weight = +$(this).val();
                    const common_max_weight = self.getCommonMaxWeight();
                    if (common_max_weight !== 0 && weight > common_max_weight) {
                        $('#weight').val(self.current_weight);
                    } else if (weight < 0.01) {
                        $('#weight').val(self.current_weight);
                    } else
                        self.current_weight = weight;
                });
                $('#package_type_id').change(function () {
                    self.setPackData(+this.value);
                });
                $('#btn_packing_conf').click(function () {
                    if ($('#btn_packing_conf').html() === 'Pack')
                        self.sendBulk();
                    else {
                        <?= $modal_packing->hide(); ?>;
                    }
                });
                $('#count_btn').click(function () {
                    // self.sendBulk(pack);
                    <?= $modal_packing->show(); ?>;
                });
                $('#sku_count').keydown(function (e) {
                    let keyCode = e.keyCode;
                    if (keyCode !== 13) {
                        return;
                    }
                    self.sendBulk(pack);
                });
                return this;
            }
        }

        let pack = new Packing(<?= View::toJs($packages) ?>, <?= View::toJs($max_weight) ?>, <?= View::toJs($SKU->getWeight()) ?>).addEvents().initPackage();
    });
</script>