<?php
/**
 * @var $authUser User
 * @var $statuses array
 * @var $group_by_list array
 * @var $statistics array
 * @var $suppliers array
 * @var $countries array
 * @var $date_types array
 * @var $data array
 * @var $date_to string
 * @var $date_from string
 * @var $warehouse_lang string
 */

use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\models\TokensTranslations;

View::addTokens(TokensTranslations::getViewTokens('dashboard', $warehouse_lang));

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-chart-line"></i> <?= View::getTranslate('Orders'); ?>
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_from" name="date_from"
                                   value="<?= View::toHtml($date_from) ?>"
                                   min="<?= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?>"/>
                            <label for="date_from"><?= View::getTranslate('Date from'); ?></label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_to" name="date_to"
                                   min="<?= View::toHtml($date_from) ?>"
                                   value="<?= View::toHtml(!empty($_GET['date_to']) ?  $date_to : '') ?>"/>
                            <label for="date_to"><?= View::getTranslate('Date to'); ?></label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'date_type',
                            ];
                            echo Html::dropDownList('date_type', $date_types,
                                !empty($_GET['date_type']) ? $_GET['date_type'] : null, $options);
                            ?>
                            <label for="date_type"><?= View::getTranslate('Date type'); ?></label>
                        </div>
                    </div>
                    <?php if (!$authUser->isRoleSupplierUser()) { ?>
                        <div class="form-group col-3">
                            <div class="form-group-element">
                                <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'supplier_id',
                                ];
                                if (empty($suppliers))
                                    $options['disabled'] = true;
                                echo Html::dropDownList('supplier_id', $suppliers,
                                    !empty($_GET['supplier_id']) ? $_GET['supplier_id'] : null, $options);
                                ?>
                                <label for="supplier_id"><?= View::getTranslate('Client'); ?></label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'country_id',
                            ];
                            echo Html::dropDownList('destination_country', $countries,
                                !empty($_GET['destination_country']) ? $_GET['destination_country'] : null, $options);
                            ?>
                            <label for="country_id"><?= View::getTranslate('Destination country'); ?></label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'status',
                                ];
                                if (empty($statuses))
                                    $options['disabled'] = true;
                                echo Html::dropDownList('status', $statuses,
                                    !empty($_GET['status']) ? $_GET['status'] : null, $options);
                            ?>
                            <label for="status"><?= View::getTranslate('Status'); ?></label>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-center mt-3">
                    <label class="mt-2"><?= View::getTranslate('Group by'); ?></label>
                    <div class="btn-group btn-group-toggle ml-3" data-toggle="buttons">
                        <?php foreach ($group_by_list as $key => $value) {
                            $is_active = $key === 'create_date_group'; //by default
                            if (!empty($_GET['group_by'])) {
                                $is_active = $_GET['group_by'] === $key;
                            }
                            ?>
                            <label class="btn btn-<?= $is_active ? 'primary' : 'secondary'?> mr-1 js-group-by-btn" style="cursor: pointer">
                                <input type="radio" name="group_by" value="<?= View::toHtml($key)?>"
                                       autocomplete="off" <?= $is_active ? 'checked' : ''?>>
                                <span class="text"><?= View::toHtml($value)?></span>
                            </label>
                        <?php } ?>
                    </div>
                </div>
                <div class="text-right mt-3">
                    <a href="/dashboard" class="btn btn-lg btn-outline-orange"><?= View::getTranslate('Clear'); ?></a>
                    <button type="submit" class="btn btn-lg btn-orange"><?= View::getTranslate('Filter'); ?></button>
                </div>
            </form>
            <div class="row mt-3">
                <h2 class="ml-3"><?= View::getTranslate('Total orders found'); ?>: <?= (int) $data['total']?></h2>
                <div id="chart-container"></div>
            </div>
        </div>
    </div>
</div>
<script src="<?= VENDOR_PATH . '/js/google_chart.js'; ?>"></script>
<script>
    var global_chart_data = <?= View::toJs($data['chart_data']) ?>;
    var total_orders = <?= (int) $data['total']; ?>;
    var fig_count = <?= (int) $data['fig_count']; ?>;
</script>
<?= View::include_js(BACKEND_JS, "dashboard.js")?>