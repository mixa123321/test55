<?php
/**
 * @var $shops array
 * @var $shops_daily array
 * @var $channels array
 * @var $suppliers array
 */

use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\models\Channels;

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Inventory synchronisation</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-sync"></i>
                            Shops (<?= count($shops); ?> item<?= count($shops) > 1 ? 's' : ''; ?>)
                        </span>
                    </h6>
                </div>
                <?php if (!empty($shops_daily)) { ?>
                    <div class="col-auto">
                        <button class="btn btn-success btn-block justify-content-start btn-icon-split js-btn-calendar">
                            <span class="icon text-white-50 align-self-center">
                                <i class="fas fa-fw fa-calendar"></i>
                            </span>
                            <span class="text flex-fill">Daily</span>
                        </button>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'id' => 'supplier_id',
                                    'class' => 'form-control form-control-lg custom-select',
                                ];
                                if (count($suppliers) < 2)
                                    $options['disabled'] = true;
                                echo Html::dropDownList('supplier_id', $suppliers,!empty($_GET['supplier_id']) ? $_GET['supplier_id'] : null, $options);
                            ?>
                            <label for="supplier_id">Client</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                                $options = [
                                    'id' => 'channel_id',
                                    'class' => 'form-control form-control-lg custom-select',
                                ];
                                if (count($channels) < 2)
                                    $options['disabled'] = true;
                                echo Html::dropDownList('channel_id', $channels,!empty($_GET['channel_id']) ? $_GET['channel_id'] : null, $options);
                            ?>
                            <label for="channel_id">Channel</label>
                        </div>
                    </div>
                </div>
                <div class="text-right mb-3">
                    <a href="/inventory_sync/sync" class="btn btn-lg btn-outline-orange">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                </div>
            </form>
            <?php if (count($shops)) { ?>
                <div class="row">
                    <div class="col table-responsive">
                        <table class="table table-hover m-0">
                            <colgroup>
                                <col width="35%"/>
                                <col width="35%"/>
                                <col width="*"/>
                                <col width="125px"/>
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Shop</th>
                                <th>Client</th>
                                <th>Channel</th>
                                <th>Sync</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($shops as $shop) { ?>
                                <tr<?= !$shop['is_active'] ? ' style="background-color: lightpink; color: black;"' : ''; ?>>
                                    <td><?= View::toHtml($shop['shop_url']); ?></td>
                                    <td><?= View::toHtml($shop['sname']) . ' (' . $shop['sid'] . ')'; ?></td>
                                    <td><?= View::toHtml($shop['cname']); ?></td>
                                    <td>
                                        <button class="btn btn-primary btn-block justify-content-start btn-icon-split mt-0 mb-1 js-btn-detail"<?= !$shop['is_active'] ? ' disabled' : ''; ?>
                                                data-shop_id='<?= $shop['id']; ?>'>
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-list"></i>
                                            </span>
                                            <span class="text flex-fill">SKUs</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php }?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="dailyInventory">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Daily Inventory</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="/inventory_sync/update_sync">
                <input type="hidden" name="back" value="<?= http_build_query($_GET); ?>" />
                <div class="modal-body">
                    <div class="col table-responsive">
                        <table class="table table-hover m-0">
                            <thead>
                            <tr>
                                <th>Shop</th>
                                <th>Sync</th>
                            </tr>
                            </thead>
                            <tbody id="tableInfo">
                                <?php foreach ($shops_daily as $shop) {
                                    if (in_array($shop['name'], Channels::$allowed_channels)) { ?>
                                    <tr>
                                        <td>
                                            <input type="hidden" name="shop[<?= $shop['id'] . '_' . (int)$shop['is_daily']; ?>]"/>
                                            <label for="daily[<?= $shop['id']; ?>]"><?= View::toHtml($shop['shop_url']); ?></label>
                                        </td>
                                        <td>
                                            <div class="form-check-inline">
                                                <input type="checkbox"
                                                       class="form-check-input"
                                                       id="daily[<?= $shop['id']; ?>]"
                                                       name="daily[<?= $shop['id']; ?>]"
                                                       <?= $shop['is_daily'] ? ' checked' : ''; ?>
                                                >
                                            </div>
                                        </td>
                                    </tr>
                                <?php
                                    }
                                } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-success">Done</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    $('.js-btn-detail').click(function () {
        location = '/inventory_sync/sync_shop?id=' + $(this).data('shop_id') + '&<?= http_build_query($_GET); ?>';
    });

    $('.js-btn-calendar').click(function () {
        $('#dailyInventory').modal('show');
    });
</script>
