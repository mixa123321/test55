<?php

use app\backend\models\Movements;
use app\core\entities\Movement;
use app\core\entities\User;
use app\core\helpers\actions\Actions;
use app\core\helpers\Arr;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\statical\TypeLocation;
use app\core\statical\WithDate;
use app\core\helpers\modal\ConfirmModal;
use app\core\models\SKUs;

/**
 * @var User $authUser
 * @var array $movements
 * @var array $suppliers
 * @var array $warehouses
 * @var int $warehouse_id
 * @var int $total_count
 * @var int $total_pages
 * @var int $current_page
 * @var string $movements_filters
 * @var array $filters
 */

$actions = new Actions('movements');
$actions
    ->addButton(Actions::ANCHOR, 'Create', 'plus', '/movement/create_movement');
if (Movements::canUploadMovements($authUser)) {
    $upload_modal = new ConfirmModal('uploadModal', 'Upload Movements', ConfirmModal::SIZE_NORMAL);
    $upload_modal->includeFile('upload/modals/upload_file.php', [
        'type' => 'CSV',
        'mime' => 'text/csv',
        'title' => 'Add movements from a CSV file',
        'example' => 'movements_upload_example',
        'handler' => 'Movements',
        'suppliers' => $suppliers,
        'require_supplier' => true,
    ]);
    echo $upload_modal->safePut();
    $actions->addButton(Actions::ANCHOR, 'Upload', 'upload', '#', ['onclick' => 'event.preventDefault(); ' . View::toHtml($upload_modal->showWithParameteres(['form'=> json_encode([])]))]);
  }
$actions
    ->addButton(Actions::ANCHOR, 'Restore', 'trash-restore', '/movement/restore_manually_removed_list')
    ->addDivider()
    ->addButton(Actions::ANCHOR, 'Requests', 'question', '/movement/requests');

if (Movements::canUploadMovements($authUser)) {
    $upload_movements_modal = new ConfirmModal('uploadModal2', 'Upload to stock', ConfirmModal::SIZE_NORMAL);
    $upload_movements_modal->includeFile('upload/modals/upload_file.php', [
        'type' => 'CSV',
        'mime' => 'text/csv',
        'title' => 'Add movements from a CSV file',
        'example' => 'movements_upload_stock_example',
        'handler' => 'BulkMovements',
        'suppliers' => $suppliers,
        'require_supplier' => true,
    ]);
    echo $upload_movements_modal->safePut();
    $actions->addDivider()->addButton(Actions::ANCHOR, 'Upload to stock', 'upload', '#', ['onclick' => 'event.preventDefault(); ' . View::toHtml($upload_movements_modal->showWithParameteres(['form'=> json_encode([])]))]);
}


?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4 bg-">
        <h1 class="h3 mb-0 text-gray-800">Movements</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-tasks"></i> Movements list (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <?= $actions->get(); ?>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER; ?>">
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="text" id="barcode" name="barcode" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['barcode']) ? $_GET['barcode'] : '') ?>"/>
                            <label for="barcode">SKU barcode</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_created_from" name="filter[date_created_from]"
                                   value="<?= View::toHtml(!empty($filters['date_created_from']) ? $filters['date_created_from'] : '') ?>"
                                   min="<?= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?>"/>
                            <label for="date_created_from">Date created from</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_created_to" name="filter[date_created_to]"
                                   min="<?= View::toHtml(!empty($filters['date_created_from']) ? $filters['date_created_from'] : '') ?>"
                                   value="<?= View::toHtml(!empty($filters['date_created_to']) ? $filters['date_created_to'] : '') ?>"/>
                            <label for="date_created_to">Date created to</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'filter[order_by]',
                                'onchange' => 'submit()',
                            ];
                            echo Html::dropDownList('filter[order_by]', Movements::$order_by, !empty($filters['order_by']) ? $filters['order_by'] : '0', $options);
                            ?>
                            <label for="order_by">Order by</label>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'type_from',
                                'data-empty-value' => Filter::NOT_SELECTED,
                            ];
                            echo Html::multiSelect('filter[type_from][]', 
                                [Filter::NOT_SELECTED => 'Select origin'] + TypeLocation::getAll(),
                                !empty($filters['type_from']) ? $filters['type_from'] : [],
                                $options
                            );
                            ?>
                            <label for="type_from">Type of origin location</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'type_to',
                                'data-empty-value' => Filter::NOT_SELECTED,
                            ];
                            echo Html::multiSelect('filter[type_to][]', 
                                [Filter::NOT_SELECTED => 'Select destination'] + TypeLocation::getAll(),
                                !empty($filters['type_to']) ? $filters['type_to'] : [],
                                $options
                            );
                            ?>
                            <label for="type_to">Type of destination location</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'status',
                                'data-empty-value' => Filter::NOT_SELECTED,
                            ];
                            echo Html::multiSelect('filter[status][]', 
                                [Filter::NOT_SELECTED => 'Select status'] + Movement::getStatusAll(),
                                !empty($filters['status']) ? $filters['status'] : [],
                                $options
                            );
                            ?>
                            <label for="status">Status</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'type',
                                'data-empty-value' => Filter::NOT_SELECTED,
                            ];
                            echo Html::multiSelect('filter[type][]', 
                                [Filter::NOT_SELECTED => 'Select type'] + Movement::$type_name,
                                !empty($filters['type']) ? $filters['type'] : [],
                                $options
                            );
                            ?>
                            <label for="type">Type</label>
                        </div>
                    </div>
                </div>
                <div class="text-right mb-3">
                    <a href="/movement?clear=1" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                </div>
            </form>
            <?php if (count($movements)) { ?>
                <?php View::pagination("/movement/?" . $movements_filters . '&', 1, $total_pages, $current_page, Movements::LIMIT_PER_PAGE); ?>
                <div class="row">
                    <table class="table table-hover">
                        <colgroup>
                            <col width="10%">
                            <col width="10%">
                            <col width="20%">
                            <col width="7%">
                            <col width="12%">
                            <col width="*">
                            <col width="12%">
                            <col width="12%">
                            <col width="5%">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>Origin location</th>
                            <th>Destination location</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Created</th>
                            <th>Comment</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movements as $movement) { ?>
                                <?php $movement_class = Movements::getAdminMovementStyle($movement); ?>
                                <tr <?= $movement_class ? 'class="' . $movement_class .'"' : ''?>>
                                    <td><?= View::toHtml($movement['lf_name']); ?></td>
                                    <td><?= View::toHtml($movement['lt_name']); ?></td>
                                    <td><?= View::toHtml("{$movement['name']} (#{$movement['SKU_id']})") . '<br/><b>' . View::toHtmlConvertWithPrimaryBarcode($movement['barcode']) . '</b>'; ?></td>
                                    <td><?= View::toHtml($movement['quantity']); ?></td>
                                    <td><?= View::toHtml(WithDate::toFrontFormat($movement['create_date'])) .  '<br/>by <b>' .
                                        (empty($movement['create_user_id']) ? 'system</b>' : (View::toHtml($movement['clogin']) . '</b> (#' . View::toHtml($movement['create_user_id']) . ')')); ?>
                                    </td>
                                    <td><?= str_replace(';' . PHP_EOL, ';<br/>', View::jsonToHtml($movement['comment'], ';' . PHP_EOL)); ?></td>
                                    <td><span class="badge badge-<?= View::toHtml(Movement::getTypeColor($movement['type'])); ?>"><?= View::toHtml(Movement::getTypeName($movement['type'])); ?></span></td>
                                    <td class="text-center">
                                        <span class="badge badge-<?= View::toHtml(Movement::getStatusColor($movement['status'])); ?>"><?= View::toHtml(Movement::getStatusName($movement['status'])); ?></span>
                                        <?php if (!empty($movement['reason'])) { ?>
                                            <br/><div class="alert alert-dark text-xs text-center p-1 m-0"><?= View::toHtml($movement['reason']); ?></div>
                                        <?php } else { ?>
                                            <?= $movement['user_id'] > 0 ? ('<br/>by <b>' . View::toHtml($movement['login']) . '</b> (#' . View::toHtml($movement['user_id']) . ')') : ''; ?>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-danger p-2 js-btn-delete" data-id="<?= (int) $movement['id']; ?>">
                                            <span class="icon text-white"><i class="fas fa-fw fa-times"></i></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php View::pagination("/movement/?" . $movements_filters . '&', 1, $total_pages, $current_page, Movements::LIMIT_PER_PAGE); ?>
            <?php } else {
                echo Html::warning("No movements found");
            } ?>
        </div>
    </div>
</div>

<?php
$modal_delete_movement = new ConfirmModal('deleteMovement', 'Delete Movement');
echo $modal_delete_movement->addContent('Are you sure?')
    ->addForm('/movement/delete_movement', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="id" id="movement_id">' . $modal_delete_movement->getButtons())
    ->put();
?>

<?= View::include_js(BACKEND_JS, 'movement.js')?>
<?= View::include_js(BACKEND_JS, 'multi_select.js')?>

<?php if (!empty($upload_modal)): ?>
    <script>
        $('<?= $upload_modal->getSelector(); ?>').on('upload.success', function() {
            $('<?= $upload_modal->getSelector(); ?>').on('hidden.bs.modal', function () {
                location.reload();
            });
        });
    </script>
<?php endif; ?>