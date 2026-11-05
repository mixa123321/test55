<?php

use app\backend\models\Roles;
use app\core\entities\Consumable;
use app\core\helpers\Html;
use app\backend\models\Consumables;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\status\ConsumableStatus;
use app\core\helpers\View;
use app\core\helpers\modal\ConfirmModal;

/**
 * @var $total_count int
 * @var $consumables array
 * @var $current_filters array
 * @var $total_pages int
 * @var $current_page int
 * @var $suppliers array
 * @var $side string
 * @var $lc_id int
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Consumables</h1>
    </div>
    <div class="alert alert-danger p-2 d-none" id="msg_error"></div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-tasks"></i>
                            Consumables list (<?= $total_count; ?> item<?= $total_count > 1 ? 's' : '' ?>)
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <form class="form-inline" method="GET" action="/consumables/consumable_add">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control custom-select',
                                'id' => 'create_supplier_id'
                            ];
                            echo Html::dropDownList('type', Consumable::TYPES, null, $options);
                            ?>
                        </div>
                        <button type="submit" class="btn btn-success">Create new</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="get" action="/consumables/">
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="like_name" id="like_name" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['like_name']) ? $_GET['like_name'] : ''); ?>">
                            <label for="like_name">Name</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="like_customer_ref" id="like_customer_ref" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['like_customer_ref']) ? $_GET['like_customer_ref'] : ''); ?>">
                            <label for="like_customer_ref">Customer Reference</label>
                        </div>
                    </div>
                    <div class="form-group col-auto">
                        <div class="form-group-element">
                            <select id="type" name="type" class="form-control form-control-lg custom-select">
                                <?php foreach (['Any'] + Consumable::TYPES as $key => $type) { ?>
                                    <option value="<?= $key; ?>"<?= (isset($_GET['type']) && $key == $_GET['type']) ? ' selected' : ''; ?>>
                                        <?= View::toHtml($type) ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <label for="type">Type</label>
                        </div>
                    </div>
                    <?php if (!empty($suppliers)) { ?>
                        <div class="form-group col-3 align-self-center">
                            <div class="form-group-element">
                                <select id="supplier_id" name="supplier_id" class="form-control form-control-lg custom-select" onchange="setGlobalPackaging();">
                                    <?php
                                        if (count($suppliers) > 1)
                                            echo '<option value="-' . $lc_id . '" ' . ((isset($_GET['supplier_id']) && !$_GET['supplier_id']) ? ' selected' : '') . '>All</option>';
                                        foreach ($suppliers as $row) { ?>
                                            <option value="<?= $row['id'] ?>"<?= !empty($_GET['supplier_id']) && $row['id'] === $_GET['supplier_id'] ? " selected" : "" ?>><?= View::toHtml($row['name']) ?></option>
                                    <?php } ?>
                                </select>
                                <label for="supplier_id">Companies</label>
                            </div>
                        </div>
                    <?php } ?>
                    <?php if ($side == Roles::SIDE_WAREHOUSE) { ?>
                        <div class="form-group col-auto align-self-center">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="global_packaging"
                                       name="global_packaging"<?= !empty($_GET['global_packaging']) ? ' checked' : ''; ?> onclick="submit();"<?= ($_GET['supplier_id'] ?? true) ? '' : ' disabled'; ?>>
                                <label class="form-check-label" for="global_packaging">Global Packaging</label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-2">
                        <div class="form-group-element">
                            <input name="volume" id="volume" type="number" step="any" min="0" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['volume']) ? $_GET['volume'] : '') ?>">
                            <label for="volume">Volume</label>
                        </div>
                    </div>
                    <div class="form-group col-1">
                        <div class="form-group-element">
                            <select id="status" name="status" class="form-control form-control-lg custom-select">
                                <?php foreach ([-1 => 'Any'] + Consumable::STATUSES as $key => $status) { ?>
                                    <option value="<?= $key; ?>"<?= (isset($_GET['status']) && $key == $_GET['status']) ? ' selected' : ''; ?>>
                                        <?= View::toHtml($status) ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <label for="status">Status</label>
                        </div>
                    </div>
                </div>
                <div class="text-right mb-4">
                    <a href="/consumables" class="btn btn-lg btn-outline-orange">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                </div>
            </form>
            <?php if (!empty($consumables)) { ?>
                <?php View::pagination("/consumables/?" . $current_filters . '&', 1, $total_pages, $current_page,Consumables::LIMIT_PER_PAGE); ?>
                <table class="table table-hover">
                    <colgroup>
                            <col width="7%">
                            <col width="*">
                            <col width="10%">
                            <col width="*">
                            <col width="10%">
                            <col width="7%">
                            <col width="7%">
                            <col width="7%">
                            <col width="7%">
                            <col width="9%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="align-content-start">Id</th>
                            <th class="align-content-start">Name<br/>Customer Reference</th>
                            <th class="align-content-start">Type</th>
                            <th class="align-content-start">Company</th>
                            <th class="align-content-start">Sizes, cm<br/>(L x W x H)</th>
                            <th class="align-content-start">Weight, kg</th>
                            <th class="align-content-start">Max Weight, kg</th>
                            <th class="align-content-start">Quantity</th>
                            <th class="align-content-start">Status</th>
                            <th class="align-content-start">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consumables as $consumable) { ?>
                            <tr>
                                <td><?= View::toHtml($consumable['id']) ?></td>
                                <td><b><?= View::toHtml($consumable['name']) ?></b><br/><?= View::toHtml($consumable['customer_ref'] ?: '-') ?></td>
                                <td><b><?= Consumable::TYPES[$consumable['type']]; ?></b><br/><span class="badge badge-<?= $consumable['reuse'] ? 'success' : 'danger'; ?>">Re-use</span></td>
                                <td><?= View::toHtml($consumable['sup_name']) ?></td>
                                <td><?= View::toHtml("{$consumable['length']} x {$consumable['width']} x {$consumable['height']}") ?></td>
                                <td>
                                    <?php if ($consumable['type'] == Consumable::TYPE_PACKAGING)
                                        echo View::toHtml((float)$consumable['weight']);
                                    ?>
                                </td>
                                <td>
                                    <?php if ($consumable['type'] == Consumable::TYPE_PACKAGING)
                                        echo View::toHtml((float)$consumable['max_weight']);
                                    ?>
                                </td>
                                <td><?= View::toHtml($consumable['quantity']) ?></td>
                                <td><?= ConsumableStatus::get($consumable['status']); ?></td>
                                <td>
                                    <a class="btn btn-success btn-icon-split btn-block justify-content-start my-0" href="/consumables/consumable_add/?consumable_id=<?= $consumable['id']; ?>">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-pen"></i>
                                        </span>
                                        <span class="text">Edit</span>
                                    </a>
                                    <?php if ($side == Roles::SIDE_WAREHOUSE) { ?>
                                        <?php
                                            if ($consumable['status'] == Consumable::STATUS_ACTIVE) {
                                                $btn_col = 'danger';
                                                $btn_title = 'Lock';
                                            } else {
                                                $btn_col = 'success';
                                                $btn_title = 'Unlock';
                                            }
                                        ?>
                                        <button class="btn btn-<?= $btn_col; ?> btn-icon-split btn-block justify-content-start mt-1 mb-0 js-btn-lock"
                                                data-consumable_lock="<?= (int)($consumable['status'] != Consumable::STATUS_ACTIVE); ?>"
                                                data-consumable_type="<?= (int)($consumable['type'] == Consumable::TYPE_PACKAGING); ?>"
                                                data-consumable_id="<?= $consumable['id']; ?>">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-<?= strtolower($btn_title); ?>"></i>
                                            </span>
                                            <span class="text"><?= $btn_title; ?></span>
                                        </button>
                                        <button class="btn btn-success btn-icon-split btn-block justify-content-start mt-1 mb-0 js-btn-calc"
                                                data-quantity="<?= $consumable['quantity']; ?>"
                                                data-consumable_id="<?= $consumable['id']; ?>"
                                                data-title="<?= View::toHtml(Consumable::TYPES[$consumable['type']] . " '{$consumable['name']}'" . (!empty($consumable['customer_ref']) ? " ({$consumable['customer_ref']})" : '')); ?>">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-calculator"></i>
                                            </span>
                                            <span class="text">Count</span>
                                        </button>
                                    <?php } ?>
                                    <button class="btn btn-primary btn-icon-split btn-block justify-content-start mt-1 js-btn-history"
                                            data-consumable_id="<?= $consumable['id']; ?>">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-history"></i>
                                        </span>
                                        <span class="text">History</span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php View::pagination("/consumables/?" . $current_filters . '&', 1, $total_pages, $current_page,Consumables::LIMIT_PER_PAGE); ?>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
    </div>
</div>
<?php
    $modal_lock = new ConfirmModal('lockConsumable', 'Change status consumable');
    echo $modal_lock->addBodyAttributes(['id' => 'ansLock'])
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'lockConf'])
        ->addCustomButtons('<input type="hidden" id="is_lock" /><input type="hidden" id="consumable_id" />' . $modal_lock->getButtons())
        ->put();
    $modal_count = new SwitchModal('countConsumable', 'Update the current quantity');
    echo $modal_count->addContent('
                <input id="calc_id" type="hidden"/>
                <div class="row mb-2">
                    <div class="col text-lg text-center font-weight-bold" id="calc_name"></div>
                </div>
                <div class="row justify-content-center">
                    <div class="col col-6">
                        <div class="form-group mb-1">
                            <label for="current_count">Current Count</label>
                            <input id="current_count" class="form-control" type="number" disabled/>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <div class="col col-6">
                        <div class="form-group mt-2 mb-1">
                            <label for="new_count">New Count</label>
                            <input id="new_count" class="form-control" type="number" min="0" step="any"/>
                        </div>
                    </div>
                </div>')
        ->addButton('Update', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE | SwitchModal::BUTTON_CONFIRM, ['id' => 'calcConf', 'disabled' => 'disabled'])
        ->put();
    $modal_history = new InfoModal('historyConsumable', 'History (last 1 month)', InfoModal::SIZE_LG);
    echo $modal_history->addContent('
                <ul class="nav nav-tabs nav-pills mt-1" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="stock-tab" data-toggle="tab" data-target="#stock" type="button" role="tab" aria-controls="stock" aria-selected="true">Stock</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="count-tab" data-toggle="tab" data-target="#count" type="button" role="tab" aria-controls="count" aria-selected="false">Count</button>
                    </li>
                </ul>
                <div class="tab-content col-12">
                    <div class="tab-pane fade show active" id="stock" role="tabpanel" aria-labelledby="stock-tab"></div>
                    <div class="tab-pane fade show" id="count" role="tabpanel" aria-labelledby="count-tab"></div>
                </div>
        ')
        ->put();
?>
<script>
    setGlobalPackaging();

    function setGlobalPackaging()
    {
        if ($('#supplier_id').val() == 0) {
            $('#global_packaging').attr('disabled', true);
            $('#global_packaging').attr('checked', false);
        } else
        if ($('#global_packaging').is(':disabled'))
            $('#global_packaging').attr('disabled', false);
    }

    $('.js-btn-lock').click((e) => {
        var btn_lock = $(e.target).parents('button');
        var consumable_lock = btn_lock.data('consumable_lock');
        var consumable_type = btn_lock.data('consumable_type');
        $('#ansLock').html('<div class="alert alert-danger mb-0">Would you like to ' + (consumable_lock ? 'unlock consumable' : ('lock consumable' + (consumable_type ? ' (existing packages will be locked)' : ''))) + '?</div>');
        $('#consumable_id').val(btn_lock.data('consumable_id'));
        $('#is_lock').val(consumable_lock);
        <?= $modal_lock->show(); ?>
    });

    $('#lockConf').click(() => {
        location = '/consumables/consumable_lock/?id=' + $('#consumable_id').val() + '&lock=' +  $('#is_lock').val();
    });

    $('.js-btn-calc').click((e) => {
        var btn_calc = $(e.target).parents('button');
        $('#calc_id').val(btn_calc.data('consumable_id'));
        $('#calc_name').text(btn_calc.data('title'));
        $('#current_count').val(btn_calc.data('quantity'));
        $('#new_count').val(btn_calc.data('quantity'));
        $('#calcConf').attr('disabled', true);
        <?= $modal_count->show(); ?>
    });

    $('#new_count').keydown((e) => {
        if (e.keyCode == 188 || e.keyCode == 190 || e.keyCode == 69 || e.keyCode == 189)
            return false;
    });

    $('#new_count').keyup(() => {
        $('#calcConf').attr('disabled', +$('#current_count').val() == +$('#new_count').val());
    });

    $('#calcConf').click(() => {
        location = '/consumables/consumable_count/?id=' + $('#calc_id').val() + '&q=' + $('#new_count').val();
    });

    $('.js-btn-history').click((e) => {
        let btn_history = $(e.target).parents('button');
        let consumable_id = btn_history.data('consumable_id');
        $.ajax({
            url: "/consumables/get_history_stock",
            method: "POST",
            dataType: 'json',
            data: { consumable_id: consumable_id },
            success: function (json) {
                if (json.stock == '[]')
                    $('#stock').html('<div class="alert alert-warning mt-3 mb-0">No records found</div>');
                else {
                    let history_stock = '';
                    $.each(JSON.parse(json.stock), (key, item) => {
                        history_stock += '<tr><td>' + key + '</td><td>' + item + '</td></tr>';
                    });
                    $('#stock').html(`
                        <div class="col-12 mt-3">
                            <div class="table-responsive" style="max-height: 500px">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <th>Date</th>
                                        <th>Quantity</th>
                                    </thead>
                                    <tbody>` + history_stock + `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `);
                }
                if (json.count == '[]')
                    $('#count').html('<div class="alert alert-warning mt-3 mb-0">No records found</div>');
                else {
                    let history_count = '';
                    $.each(JSON.parse(json.count), (key, item) => {
                        history_count += '<tr><td>' + html_escape(item.event_date) + '</td><td>' + html_escape(item.login) + '</td><td>' + html_escape(item.addition) + '</td></tr>';
                    });
                    $('#count').html(`
                        <div class="col-12 mt-3">
                            <div class="table-responsive" style="max-height: 500px">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <th>Date</th>
                                        <th>User</th>
                                        <th>New count</th>
                                    </thead>
                                    <tbody>` + history_count + `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `);
                }
                <?= $modal_history->show(); ?>
            },
            error: function (json) {
                showError('msg_error', 'Database error', 3000);
            }
        });
    });
</script>
