<?php

use app\backend\models\Pagination;
use app\core\entities\Movement;
use app\core\helpers\Arr;
use app\core\helpers\Filter;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\View;
use app\core\models\SKUs;
use app\core\statical\TypeLocation;
use app\core\statical\WithDate;

/**
 * @var array $processings
 * @var int $total_count
 * @var int $total_pages
 * @var int $current_page
 * @var string $processings_filters
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Processing movements</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-arrows-alt"></i>
                            Movements list (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <div class='col-auto'>
                    <a class="btn btn-success btn-icon-split" href="/processing/create_movement">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Create movement</span>
                    </a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <input type="hidden" name="<?= Filter::getFilterTypeKey('barcode'); ?>" value="<?= SKUs::DEFAULT_BARCODE_FILTER; ?>">
                <div class="form-row">
                    <div class="form-group col">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="text" id="barcode" name="barcode" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['barcode']) ? $_GET['barcode'] : '') ?>"/>
                            <label for="barcode">SKU barcode</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'type_from',
                                ];
                                echo Html::dropDownList('type_from', ['all'] + TypeLocation::getAll(),
                                    !empty($_GET['type_from']) ? $_GET['type_from'] : '', $options);
                            ?>
                            <label for="type_from">Type of origin location</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'type_to',
                                ];
                                echo Html::dropDownList('type_to', ['all'] + TypeLocation::getAll(),
                                    !empty($_GET['type_to']) ? $_GET['type_to'] : '', $options);
                            ?>
                            <label for="type_to">Type of destination location</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_created_from" name="date_created_from"
                                   value="<?= View::toHtml(!empty($_GET['date_created_from']) ? $_GET['date_created_from'] : '') ?>"
                                   min="<?= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?>"/>
                            <label for="date_created_from">Date created from</label>
                        </div>
                    </div>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_created_to" name="date_created_to"
                                   min="<?= View::toHtml(!empty($_GET['date_created_from']) ? $_GET['date_created_from'] : '') ?>"
                                   value="<?= View::toHtml(!empty($_GET['date_created_to']) ? $_GET['date_created_to'] : '') ?>"/>
                            <label for="date_created_to">Date created to</label>
                        </div>
                    </div>
                </div>
                <div class="text-right mb-3">
                    <a href="/processing" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                </div>
            </form>
            <?php if (count($processings)) { ?>
                <?php View::pagination("/processing/?" . $processings_filters . '&', 1, $total_pages, $current_page, Pagination::LIMIT_PER_PAGE_BIG); ?>
                <div class="row">
                    <table class="table table-hover">
                        <colgroup>
                            <col width="12%">
                            <col width="12%">
                            <col width="*">
                            <col width="10%">
                            <col width="15%">
                            <col width="15%">
                            <col width="5%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Origin location</th>
                                <th>Destination location</th>
                                <th>SKU</th>
                                <th>Quantity</th>
                                <th>Created</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($processings as $processing) { ?>
                                <tr>
                                    <td><?= View::toHtml($processing['lf_name']); ?></td>
                                    <td><?= View::toHtml($processing['lt_name']); ?></td>
                                    <td><?= View::toHtml("{$processing['name']} (#{$processing['SKU_id']})") . '<br/><b>' . View::toHtmlConvertWithPrimaryBarcode($processing['barcode']) . '</b>'; ?></td>
                                    <td><?= View::toHtml($processing['quantity']); ?></td>
                                    <td><?= View::toHtml(WithDate::toFrontFormat($processing['create_date'])) .  '<br/>by <b>' .
                                        (empty($processing['create_user_id']) ? 'system</b>' : (View::toHtml($processing['clogin']) . '</b> (#' . View::toHtml($processing['create_user_id']) . ')')); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-<?= View::toHtml(Movement::getStatusColor($processing['status'])); ?>"><?= View::toHtml(Movement::getStatusName($processing['status'])); ?></span>
                                        <?php if (!empty($processing['reason'])) { ?>
                                            <br/><div class="alert alert-dark text-xs text-center p-1 m-0"><?= View::toHtml($processing['reason']); ?></div>
                                        <?php } else { ?>
                                            <?= $processing['user_id'] > 0 ? ('<br/>by <b>' . View::toHtml($processing['login']) . '</b> (#' . View::toHtml($processing['user_id']) . ')') : ''; ?>
                                        <?php } ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-danger p-2 js-btn-delete" data-id="<?= (int)$processing['id']; ?>">
                                            <span class="icon text-white"><i class="fas fa-fw fa-times"></i></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php View::pagination("/processing/?" . $processings_filters . '&', 1, $total_pages, $current_page, Pagination::LIMIT_PER_PAGE_BIG); ?>
            <?php } else
                echo Html::warning("No movements found");
            ?>
        </div>
    </div>
</div>
<?php
    $modal_delete_movement = new ConfirmModal('deleteMovement', 'Delete Movement');
    echo $modal_delete_movement->addContent('Are you sure?')
        ->addForm('/processing/delete_movement', ConfirmModal::METHOD_POST)
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
        ->addCustomButtons('<input type="hidden" name="id" id="movement_id">' . $modal_delete_movement->getButtons())
        ->put();
    ?>
<?= View::include_js(BACKEND_JS, 'movement.js')?>
