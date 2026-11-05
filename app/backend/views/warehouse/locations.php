<?php

use app\core\entities\Location;
use app\core\entities\User;
use app\core\helpers\View;
use app\backend\models\Locations;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\Html;

/**
 * @var User $authUser
 * @var int $total_records
 * @var array $types
 * @var array $zones
 * @var array $columns
 * @var array $rows
 * @var array $floors
 * @var array<Location> $locations
 * @var int $total_pages
 * @var int $current_page
 * @var array $sizes
 * @var array $SKUs
 * @var array $not_free_locations
 * @var array $none_lock_locs
 * @var array $none_edit_locs
 * @var bool $warehouse_label
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Locations</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-map-marker-alt"></i>
                        Locations list (<?= (int) $total_records; ?> item<?= $total_records > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <?php if ($authUser->hasWorkersAccess()) { ?>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split" href="/locations/add_location?<?= http_build_query($_GET); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-plus"></i>
                            </span>
                            <span class="text">Create new location</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <a class="btn btn-primary btn-icon-split" href="/locations/book_locations?back=<?= urlencode(http_build_query($_GET)); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-book-open"></i>
                            </span>
                            <span class="text">Booking locations</span>
                        </a>
                    </div>
                <?php } ?>
                <div class="col-auto">
                    <button class="btn btn-primary btn-icon-split" type="button" onclick="return LabelPrint();" target="_blank">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-print"></i>
                        </span>
                        <span class="text">Print barcodes</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-12">
                    <form method="GET" action="/locations" name="frmSearch">
                        <div class="form-row">
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <input type="text" name="search_name" id="search_name" class="form-control form-control-lg" placeholder=" "
                                           value="<?= !empty($_GET['search_name']) ? View::toHtml($_GET['search_name']) : ''?>">
                                    <label for="search_name">Location ID/Alias</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-3 col-lg-2">
                                <div class="form-group-element">
                                    <select id="type_id" name="type_id" class="form-control form-control-lg custom-select">
                                        <option value="0">Any</option>
                                        <?php foreach ($types as $id => $type) { ?>
                                            <option value="<?= View::toHtml($id) ?>" <?= (!empty($_GET['type_id']) && $id === $_GET['type_id']) ? " selected" : "" ?>><?= View::toHtml($type) ?></option>
                                        <?php } ?>
                                    </select>
                                    <label for="type_id">Type</label>
                                </div>
                            </div>
                            <div class="form-group col-3 col-lg-2">
                                <div class="form-group-element">
                                    <select id="zone" name="zone" class="form-control form-control-lg custom-select">
                                        <option value="0">Any</option>
                                        <?php foreach ($zones as $zone) { ?>
                                            <option<?= (!empty($_GET['zone']) && $zone === $_GET['zone']) ? " selected" : "" ?>><?= View::toHtml($zone) ?></option>
                                        <?php } ?>
                                    </select>
                                    <label for="zone">Zone</label>
                                </div>
                            </div>
                            <div class="form-group col-3 col-lg-2">
                                <div class="form-group-element">
                                    <select id="column" name="column" class="form-control form-control-lg custom-select">
                                        <option value="0">Any</option>
                                        <?php foreach ($columns as $column) { ?>
                                            <option<?= (!empty($_GET['column']) && $column === $_GET['column']) ? " selected" : "" ?>><?= View::toHtml($column) ?></option>
                                        <?php } ?>
                                    </select>
                                    <label for="column">Column</label>
                                </div>
                            </div>
                            <div class="form-group col-3 col-lg-2">
                                <div class="form-group-element">
                                    <select id="row" name="row" class="form-control form-control-lg custom-select">
                                        <option value="0">Any</option>
                                        <?php foreach ($rows as $row) { ?>
                                            <option<?= (!empty($_GET['row']) && $row === $_GET['row']) ? " selected" : "" ?>><?= View::toHtml($row) ?></option>
                                        <?php } ?>
                                    </select>&nbsp;
                                    <label for="row">Row</label>
                                </div>
                            </div>
                            <?php if (!empty($floors)) { ?>
                                <div class="form-group col-3 col-lg-2">
                                    <div class="form-group-element">
                                        <select id="floor" name="floor" class="form-control form-control-lg custom-select">
                                            <option value="0">Any</option>
                                            <?php foreach ($floors as $key => $floor) { ?>
                                                <option value="<?= $key; ?>"<?= (!empty($_GET['floor']) && $key == $_GET['floor']) ? " selected" : "" ?>><?= View::toHtml($floor) ?></option>
                                            <?php } ?>
                                        </select>&nbsp;
                                        <label for="floor">Floor</label>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="text-right">
                            <a href="/locations" class="btn btn-lg btn-outline-orange">Clear</a>
                            <button type="submit" id="search" class="btn btn-lg btn-orange">Filter</button>
                        </div>
                    </form>
                </div>
                <div class="col-12 mt-4">
                    <?php if(!empty($locations)) { ?>
                        <?php View::pagination("/locations/?" . http_build_query($_GET) . '&', 1, $total_pages, $current_page, Locations::LIMIT_PER_PAGE); ?>
                        <table class="table table-hover">
                            <colgroup>
                                <col width="25%">
                                <col width="15%">
                                <col width="12%">
                                <col width="25%">
                                <col width="15%">
                                <col width="*">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Location</th>
                                <th>Alias</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <form method="get" action="/locations/edit_location" name="frmDel">
                                <input type="hidden" name="id" value="">
                                <input type="hidden" name="lock" value="">
                                <input type="hidden" name="type_id" value="<?= View::toHtml($_GET['type_id'] ?? ''); ?>">
                                <input type="hidden" name="zone" value="<?= View::toHtml($_GET['zone'] ?? ''); ?>">
                                <input type="hidden" name="column" value="<?= View::toHtml($_GET['column'] ?? ''); ?>">
                                <input type="hidden" name="row" value="<?= View::toHtml($_GET['row'] ?? ''); ?>">
                                <input type="hidden" name="floor" value="<?= View::toHtml($_GET['floor'] ?? 0); ?>">
                                <input type="hidden" name="c" value="<?= View::toHtml($current_page ?? 1); ?>">
                                <input type="hidden" name="non_picking" value="">
                                <tbody>
                                <?php foreach ($locations as $location) { ?>
                                    <tr>
                                        <td>
                                            <?php $view = Locations::getLocationView($location, $sizes, $warehouse_label); ?>
                                            <?= View::toHtml($location->getLocation($warehouse_label)); ?>
                                            <?php foreach ($view['badges'] as $badge) { ?>
                                                    <span class="badge badge-<?= $badge['color']; ?>"><?= $badge['text']; ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?= View::toHtml($view['alias']); ?>
                                        </td>
                                        <td><?= View::toHtml($view['type']) ?></td>
                                        <td><?= View::toHtml($view['size']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $view['state_badge'] ?>">
                                                <?= View::toHtml($view['state']) ?>
                                            </span>

                                            <?php if($location->getIsNonPicking()): ?>
                                                <br><span class="badge badge-danger">Non-picking</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $show_lock = true;
                                                $change_size = true;
                                                $action = null;
                                                if (!$location->isLocked()) {
                                                    if ($SKUs[$location->getId()]) {
                                                        $title = "View SKUs";
                                                        $iconTitle = "eye";
                                                        $btnAction = "primary";
                                                        $action = "/housekeeping/locations/?loc={$location->getId()}&" . http_build_query($_GET) . "&c={$current_page}&back=/locations";
                                                        $change_size = false;
                                                    } elseif (!$location->isDetached()) {
                                                        $title = "Lock";
                                                        $iconTitle = "lock";
                                                        $btnAction = "danger";
                                                        if (in_array($location->getId(), $none_lock_locs))
                                                            $show_lock = false;
                                                    } else {
                                                        $title = "Attach back";
                                                        $iconTitle = "link";
                                                        $btnAction = "info";
                                                    }
                                                } else {
                                                    $title = "Unlock";
                                                    $iconTitle = "lock-open";
                                                    $btnAction = "success";
                                                }
                                                $titleEdit = "Edit";
                                                $iconTitleEdit = "edit";
                                                $btnActionEdit = "success";
                                                $actionEdit= "/locations/edit_locations?id={$location->getId()}&change_size=$change_size&c=$current_page";
                                                if (!in_array($location->getId(), $none_edit_locs)) {?>
                                                    <a class="btn btn-<?= $btnActionEdit ?> btn-icon-split btn-block d-flex justify-content-start" href="<?= View::toHtml($actionEdit) ?>">
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-<?= $iconTitleEdit ?>"></i>
                                                    </span>
                                                        <span class="text flex-fill"><?= $titleEdit ?></span>
                                                    </a>
                                                <?php } ?>
                                                <?php if ($show_lock) {?>
                                                <a class="btn btn-<?= $btnAction ?> btn-icon-split btn-block d-flex justify-content-start text-nowrap"
                                                   <?php if ($action !== null) { ?>
                                                   href="<?= View::toHtml($action) ?>"
                                                   <?php } else { ?>
                                                   href="" data-toggle="modal" data-target="#lockLocationModalConfirm"
                                                   onclick="frmDel.id.value=<?= (int)$location->getId() ?>; frmDel.lock.value=<?= (int)(!$location->isActive()) ?>"
                                                   <?php } ?>>
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-<?= $iconTitle ?>"></i>
                                                    </span>
                                                    <span class="text flex-fill"><?= $title ?></span>
                                                </a>
                                                <?php }
                                                if ($location->showNonPicking() && !in_array($location->getId(), $not_free_locations)) { ?>
                                                    <a class="btn <?=!$location->getIsNonPicking() ? 'btn-danger' : 'btn-success'?> btn-icon-split btn-block d-flex justify-content-start text-nowrap"
                                                       href="" data-toggle="modal" data-target="#nonPickingLocationModalConfirm"
                                                       onclick="frmDel.id.value=<?=$location->getId()?>; frmDel.non_picking.value=<?=(int)(!$location->getIsNonPicking())?>">
                                                    <span class="icon text-white-50">
                                                        <i class="fas fa-fw fa-lock"></i>
                                                    </span>
                                                        <span class="text flex-fill"><?=!$location->getIsNonPicking() ? 'Non-Picking' : 'Picking'?> </span>
                                                    </a>
                                                <?php }
                                                if (Location::isStorage($location->getTypeId())) { ?>
                                                    <button type="button"
                                                            class="btn btn-primary btn-icon-split btn-block d-flex justify-content-start text-nowrap js-assignment"
                                                            id="loc_id_<?= $location->getId(); ?>">
                                                        <span class="icon text-white-50">
                                                            <i class="fas fa-fw fa-boxes"></i>
                                                        </span>
                                                        <span class="text flex-fill">Assignment</span>
                                                    </button>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                                <?php
                                echo (new ConfirmModal('lockLocation', 'Location state change'))->addContent('Please confirm this action?')
                                    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
                                    ->put();

                                echo (new ConfirmModal('nonPickingLocation', 'Non-picking location'))->addContent('Please confirm this action?')
                                    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
                                    ->put();
                                ?>
                            </form>
                        </table>
                        <?php View::pagination("/locations/?" . http_build_query($_GET) . '&', 1, $total_pages, $current_page, Locations::LIMIT_PER_PAGE); ?>
                    <?php } else { echo Html::warning("No records found"); } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="labelPrint">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Print barcodes</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col">
                        <span><b>Start point (mm)</b></span>
                        <div class="form-group form-inline mt-2 mb-3">
                            <label for="top-page" class="control-label col-3 pl-0">Top</label>
                            <input id="top-page" class="form-control col-3" type="number" min="0" value="12.9"/>
                            <label for="left-page" class="control-label col-3">Left</label>
                            <input id="left-page" class="form-control col-3" type="number" min="0" value="7"/>
                        </div>
                        <span><b>Cell spacing (mm)</b></span>
                        <div class="form-group form-inline mt-2 mb-3">
                            <label for="right-barcode" class="control-label col-3 pl-0">Horizontal</label>
                            <input id="right-barcode" class="form-control col-3" type="number" min="0" value="2.6"/>
                            <label for="down-barcode" class="control-label col-3">Vertical</label>
                            <input id="down-barcode" class="form-control col-3" type="number" min="0" value="0.0"/>
                        </div>
                        <span><b>Barcodes</b></span>
                        <div class="form-group form-inline mt-2 mb-1">
                            <label for="in-row" class="control-label col-3 pl-0">In row</label>
                            <input id="in-row" class="form-control col-3" type="number" min="1" value="3"/>
                            <label for="in-col" class="control-label col-3">In col</label>
                            <input id="in-col" class="form-control col-3" type="number" min="1" value="8"/>
                        </div>
                    </div>
                </div>
                <div id='prnError' class='col bg-danger text-white p-1 rounded text-center mt-1 mb-0 d-none'>
                    Information invalid
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-orange" data-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-orange js-btn-conf pr-3" type="button">
                    Generate
                    <i class="fas fa-fw fa-chevron-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<?php
$modal_content = '<div class="text-center d-none" id="icon_update">
                      <img src="' . COMMON_IMG . DIRECTORY_SEPARATOR . 'tenor.gif' . '" style="width: 150px"/>
                  </div>
                  <div class="alert alert-danger d-none mt-0 mb-0" id="errAssignment"></div>
                  <table class="table table-hover mb-0 d-none" id="tableSKUs">
                      <colgroup>
                          <col width="*"/>
                          <col width="*"/>
                          <col width="10%"/>
                      </colgroup>
                      <thead>
                      <tr>
                          <th>SKU Id</th>
                          <th>Barcode</th>
                          <th>Priority</th>
                      </tr>
                      </thead>
                      <tbody></tbody>
                  </table>';
$modal_assignment_request = new InfoModal('assignmentRequest', 'SKUs are assigned to location', InfoModal::SIZE_NORMAL);
echo $modal_assignment_request->addContent($modal_content)->put();
?>
<script>
    $('.js-btn-conf').click(function() {
        var top = $('#top-page').val()*1;
        var left = $('#left-page').val()*1;
        var right = $('#right-barcode').val()*1;
        var down = $('#down-barcode').val()*1;
        var m = $('#in-row').val()*1;
        var n = $('#in-col').val()*1;
        if (top < 0 || left < 0 || right < 0 || down < 0 || m < 1 || n < 1 ||
            (left + (m - 1))*right > 210 || (top + (n - 1)*down) > 297 || m > 5 || n > 20) {
            $('#prnError').prop('classList').remove('d-none');
            setTimeout(() => {
                $('#prnError').prop('classList').add('d-none');
            }, 3000);
            return false;
        }
        $('#labelPrint').modal('hide');
        window.open('/locations/print_bar_codes/?T=' + $('#type_id').val() +
                                               '&Z=' + $('#zone').val() +
                                               '&C=' + $('#column').val() +
                                               '&R=' + $('#row').val() +
                                               '&top=' + top +
                                               '&left=' + left +
                                               '&right=' + right +
                                               '&down=' + down +
                                               '&in_row=' + m +
                                               '&in_col=' + n);
    });

    $('.js-assignment').click(function(e) {
        var location_id = e.currentTarget.id.replace('loc_id_', '');
        $('#icon_update').prop('classList').remove('d-none');
        $('#tableSKUs').prop('classList').add('d-none');
        $('#errAssignment').prop('classList').add('d-none');
        <?= $modal_assignment_request->show(); ?>
        $.ajax({
            url: "/locations/get_assignment",
            method: "POST",
            data: { location_id: location_id },
            dataType: "json",
            success: function (json) {
                $('#icon_update').prop('classList').add('d-none');
                if ('error' in json) {
                    $('#errAssignment').html(json.error);
                    $('#errAssignment').prop('classList').remove('d-none');
                } else {
                    $('#tableSKUs > tbody').children().remove().end();
                    $.each(JSON.parse(json.success), function (key, item) {
                        $('#tableSKUs > tbody:last-child').append('<tr><td>' + item.SKU_id + '</td><td>' + convertBarcodeWithPrimaryBarcode(item.barcode) + '</td><td>' + item.priority + '</td></tr>');
                    });
                    $('#tableSKUs').prop('classList').remove('d-none');
                }
            },
            error: function () {
                $('#icon_update').prop('classList').add('d-none');
                $('#errAssignment').html('Connection is not found');
                $('#errAssignment').prop('classList').remove('d-none');
            }
        });
    });

    function LabelPrint()
    {
        $('#labelPrint').modal('show');
        return false;
    }
</script>
