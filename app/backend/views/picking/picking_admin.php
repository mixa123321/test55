<?php

use app\backend\models\Pick;
use app\backend\models\Warehouses;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\backend\models\SKUs;

/**
 * @var User $authUser
 * @var string $mode
 * @var array $SKUs
 * @var bool $is_mezzanine
 * @var int $total_count
 * @var int $total_pages
 * @var int $current_page
 * @var array $sort_services
 * @var array $filters
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Picking (<?= View::toHtml($mode); ?>)</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <span>
                    <i class="fas fa-fw fa-tasks"></i>
                    SKUs list (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                </span>
            </h6>
        </div>
        <div class="card-body">
            <form action="/pick/picking_<?= $mode[0]; ?>" method="GET" name="filter">
                <div class="form-row justify-content-center">
                    <?php if ($is_mezzanine) { ?>
                        <div class="form-group col-lg-3">
                            <div class="form-group-element">
                                <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
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
                    <div class="form-group col-lg-5">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'services',
                                    'onchange' => 'filter.submit()',
                                    'data-empty-value' => '-1',
                                ];
                                if (count($sort_services) == 1)
                                    $options += ['disabled' => 'disabled'];
                                echo Html::multiSelect('filter[services][]', $sort_services, $filters['services'] ?? [], $options);
                            ?>
                            <label for="services">Services</label>
                        </div>
                    </div>
                </div>
            </form>
            <?php if (count($SKUs)) { ?>
                <div class="row">
                    <div class="col-12">
                        <?php View::pagination("/pick/picking_{$mode[0]}?" . http_build_query([
                            'filter' => $filters,
                        ]) . '&', 1, $total_pages, $current_page,
                            Pick::LIMIT_PER_PAGE); ?>
                        <div class="row">
                            <table class="table table-hover">
                                <colgroup>
                                    <col width="12%"/>
                                    <col width="12%"/>
                                    <col width="40%"/>
                                    <col width="*"/>
                                    <col width="5%"/>
                                    <col width="12%"/>
                                </colgroup>
                                <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>Barcode</th>
                                    <th>Title</th>
                                    <th>Batches</th>
                                    <th>Qty</th>
                                    <th>Priority</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($SKUs as $SKU) { ?>
                                    <tr<?= strtotime($SKU['despatch_date']) < strtotime(date('Y-m-d',time())) ? ' style="background-color: #feebef"' : ''; ?>>
                                        <td><?= View::toHtml("{$SKU['warehouse']}-{$SKU['type_id']}-{$SKU['zone']}-{$SKU['column']}-{$SKU['row']}"); ?></td>
                                        <td><?= View::toHtmlConvertWithPrimaryBarcode($SKU['barcode']); ?></td>
                                        <td><?= View::toHtml($SKU['name']); ?> (<?= View::toHtml($SKU['id']); ?>)</td>
                                        <td><?= empty($SKU['batches']) ? '-' : implode('<br/>', $SKU['batches']); ?></td>
                                        <td><?= View::toHtml($SKU['quantity']); ?></td>
                                        <td>
                                            <?= SKUs::viewProcessingStatus($SKU); ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php View::pagination("/pick/picking_{$mode[0]}?" . http_build_query([
                            'floor' => $_GET['floor'] ?? '',
                            'services' => $_GET['services'] ?? -1,
                        ]) . '&', 1, $total_pages, $current_page,
                            Pick::LIMIT_PER_PAGE); ?>
                    </div>
                </div>
            <?php } else {
                echo Html::warning("No SKUs found");
            } ?>
        </div>
    </div>
</div>
<?= View::include_js(BACKEND_JS, "multi_select.js"); ?>