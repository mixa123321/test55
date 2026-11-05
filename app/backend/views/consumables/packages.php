<?php

use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var $authUser User
 * @var $supplier_id int
 * @var $supplier_name string
 * @var $packages array
 * @var $back string
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= View::toHtml($supplier_name); ?></h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-box"></i>
                        Package list (<?= count($packages); ?> item<?= count($packages) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <?php if ($authUser->hasUsersAccess()) { ?>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split" href="/packages/add_package/?id=<?= $supplier_id ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-plus"></i>
                            </span>
                            <span class="text">Create new package</span>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($packages)) { ?>
                <table class="table table-hover last-div">
                    <colgroup>
                        <col width="*">
                        <col width="*">
                        <col width="20%">
                        <col width="20%">
                        <col width="12%">
                        <col width="8%">
                        <col width="8%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Packaging</th>
                            <th>Consumables</th>
                            <th>In package</th>
                            <th>Warehouses</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($packages as $package_id => $package) { ?>
                            <tr<?= ($package['default'] ?? 0) ? ' class="alert-success"' : ''; ?>>
                                <td>
                                    <?php
                                        echo '<b>' . View::toHtml($package['name']) . '</b>';
                                        if (!empty($package['barcode']))
                                            echo '<br/>' . View::toHtml($package['barcode']);
                                        if (!empty($package['instruction']))
                                            echo '<br/><span class="text-xs">' . View::toHtml($package['instruction']) . '</span>'
                                    ?>
                                </td>
                                <td><?= View::toHtml($package['pname']) . " (" . (float)$package['weight'] . " kg)<br/>{$package['sizes']} cm<sup>3</sup><br/>Max {$package['max_weight']} kg"; ?></td>
                                <td>
                                    <?php if (!empty($package['consumables']))
                                        echo implode('<br/>', array_map([View::class, 'toHtml'], $package['consumables']));
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($package['in_package']))
                                        echo implode('<br/>', array_map([View::class, 'toHtml'], $package['in_package']));
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($package['warehouses']))
                                        echo implode('<br/>', $package['warehouses']);
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?= $package['state'] ? 'success' : 'danger'; ?>">
                                        <?= View::toHtml(!$package['state'] ? 'Locked' : 'Active') ?>
                                    </span>
                                </td>
                                <td>
                                    <a class="btn btn-success btn-icon-split btn-block justify-content-start mt-0 mb-1" href="/packages/edit_package/?supplier_id=<?= urlencode($supplier_id) ?>&id=<?= urlencode($package_id) ?>">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-pen"></i>
                                        </span>
                                        <span class="text">Edit</span>
                                    </a>
                                    <button class="btn btn-<?= !$package['state'] ? "success" : "danger"; ?> btn-icon-split btn-block justify-content-start mt-0 mb-1 js-btn-lock"
                                            data-package_id="<?= $package_id; ?>"
                                            data-state="<?= (int)(!$package['state']); ?>">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-fw fa-lock<?= !$package['state'] ? "-open" : "" ?>"></i>
                                        </span>
                                        <span class="text text-white"><?= !$package['state'] ? "Unlock" : "Lock"; ?></span>
                                    </button>
                                    <?php if (!($package['default'] ?? 0) && $package['state']) { ?>
                                        <button class="btn btn-primary btn-icon-split btn-block justify-content-start mt-0 mb-1 js-btn-default"
                                                data-package_id="<?= $package_id; ?>">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-check"></i>
                                            </span>
                                            <span class="text">Default</span>
                                        </button>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else
                echo Html::warning('No records found');
            ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>/?id=<?= (int)$supplier_id ?>">
                        <i class="fas fa-fw fa-chevron-left mr-2"></i>
                        <span class="ml-1">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    $modal_lock = new ConfirmModal('lockPackage', 'Change status package');
    echo $modal_lock->addContent('Would you like to change status a package?')
        ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['id' => 'lockConf'])
        ->addCustomButtons('<input type="hidden" id="state" /><input type="hidden" id="package_id" />' . $modal_lock->getButtons())
        ->put();
?>
<script>
    $('.js-btn-lock').click((e) => {
        var btn_lock = $(e.target).parents('button');
        $('#package_id').val(btn_lock.data('package_id'));
        $('#state').val(btn_lock.data('state'));
        <?= $modal_lock->show(); ?>
    });

    $('#lockConf').click(() => {
        location = '/packages/lock_package/?id=' + $('#package_id').val() + '&lock=' +  $('#state').val();
    });

    $('.js-btn-default').click((e) => {
        var btn_lock = $(e.target).parents('button');
        location = '/packages/default_package/?id=' + btn_lock.data('package_id') + '&supplier_id=<?= $supplier_id; ?>';
    });
</script>
