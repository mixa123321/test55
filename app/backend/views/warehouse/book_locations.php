<?php

use app\core\entities\Location;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\modal\ConfirmModal;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;

/**
 * @var User $authUser
 * @var array $suppliers
 * @var array $locations
 * @var array $ranges
 * @var string $warehouse_label
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Book locations</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-fw fa-book-open"></i> Booked locations
                    </h6>
                </div>
                <form method="get">
                    <?= Html::hidden('back', $_GET['back']); ?>
                    <div class="col-auto form-inline">
                        <?= Html::dropDownList('type_id', array_combine(Location::STORAGE_TYPES, Location::STORAGE_TYPES), $_GET['type_id'], [
                            'id' => 'type_id',
                            'class' => 'selectpicker form-control mr-3',
                            'onchange' => 'submit();']); ?>
                        <?php if (count($suppliers) == 1)
                            echo Html::tag('h6', View::toHtml($suppliers[$_GET['supplier_id']]), ['class' => 'm-0 font-weight-bold text-primary float-left']) .
                                 Html::hidden('supplier_id', $_GET['supplier_id'], true);
                        else
                            echo Html::dropDownList('supplier_id', $suppliers, $_GET['supplier_id'], [
                                'id' => 'supplier_id',
                                'class' => 'selectpicker form-control',
                                'onchange' => 'submit();']);
                        ?>
                    </div>
                </form>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div id="msg_err" class="alert alert-danger d-none"></div>
                    <div id="msg_suc" class="alert alert-success d-none"></div>
                    <div class="row mb-2">
                        <h4 class="col">Ranges</h4>
                        <div class="col-auto">
                            <button class="btn btn-success btn-icon-split js-btn-add-range">
                                <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-plus"></i>
                                </span>
                                <span class="text">Create new range</span>
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-warning d-none" id="ranges_empty">No records found</div>
                    <div class="col-12" id="ranges"></div>
                    <div class="ranges-content d-none"></div>
                    <div class="row mb-2 mt-3">
                        <h4 class="col">Locations</h4>
                        <div class="col-auto">
                            <button class="btn btn-success btn-icon-split js-btn-import-locations">
                                <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-file-import"></i>
                                </span>
                                <span class="text">Import locations</span>
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-warning d-none" id="locations_empty">No records found</div>
                    <div class="col-12" id="locations"></div>
                    <div class="locations-content d-none"></div>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/locations/?<?= View::toHtml($_GET['back'] ?? ''); ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-chevron-left"></i>
                        </span>
                        <span class="text">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
    $modal_delete_location = new ConfirmModal('deleteLocation', View::getTranslate('Delete'));
    echo $modal_delete_location
        ->addContent(View::getTranslate('Would you like to unbook') . ' <span id="name_location"></span>?')
        ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['class' => 'js-btn-conf'])
        ->addCustomButtons(Html::hiddens([['type_book', '', false], ['book_item', '', false]]) . $modal_delete_location->getButtons())
        ->put();

    $modal_add_range_content = '
        <div id="msg_err_range" class="alert alert-danger d-none"></div>
        <div class="row">
            <div class="form-group col-6 mb-0">
                <div class="form-group-element">' .
                    Html::dropDownList('zone', [], null, ['id' => 'zone', 'onchange' => 'changeRange()']) . '
                    <label for="zone">' . View::getTranslate('Zone') . '</label>
                </div>
            </div>
            <div class="form-group col-6 mb-0">
                <div class="form-group-element">' .
                    Html::dropDownList('column', [], null, ['id' => 'column']) . '
                    <label for="column">' . View::getTranslate('Column') . '</label>
                </div>
            </div>
        </div>';
    $modal_add_range = new SwitchModal('addRange', View::getTranslate('Add range'));
    echo $modal_add_range
        ->addContent($modal_add_range_content)
        ->addButton('Add', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['class' => 'js-btn-add-range-conf'])
        ->put();

    $modal_import_locations_content = '
        <div id="msg_err_import" class="alert alert-danger d-none"></div>
        <div class="row">
            <div class="col">
                <p class="form-control-static text-lg"><b>Guidance before uploading</b></p>
                <p class="form-control-static text-justify">You must use the template below, and remember to leave the required column headers in your CSV file (as in the first line of the example file below), but remove the 2<sup>nd</sup> and 3<sup>rd</sup> lines of the file as these lines are purely for demonstration purposes only.</p>
                <p class="form-control-static"><a href="/upload/demo/?csv=import_locations_example"><b>Download example CSV file</b></a></p>
                <p class="form-control-static mt-2 mb-1"><b>Select CSV to upload</b></p>
                <input type="hidden" name="MAX_FILE_SIZE" value="2000000" />
                <div class="form-group mb-0">
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" name="upload_file" id="upload_file">
                        <label class="custom-file-label" for="upload_file" id="name_file">Choose file</label>
                    </div>
                </div>
            </div>
        </div>';
    $modal_import_locations = new SwitchModal('importLocations', View::getTranslate('Import locations'));
    echo $modal_import_locations
        ->addContent($modal_import_locations_content)
        ->addButton('Upload', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['class' => 'js-btn-import-location-conf', 'disabled' => 'disabled'])
        ->put();
?>
<script type="text/javascript" src="<?= BACKEND_JS . '/BadgeItems.js'; ?>"></script>
<script type="text/javascript" src="<?= BACKEND_JS . '/BookLocations.js'; ?>"></script>
<script>
    let range_zones = null;

    $('.js-btn-add-range').click(function () {
        $.get('/locations/get_ranges', {type_id: $('#type_id').val()}, res => {
            if (res.error !== undefined) {
                showError('msg_err', res.error, 3000);
                return false;
            }
            range_zones = res.success;
            let first = false;
            $('#zone').children().remove().end();
            $.each(range_zones, function (key, item) {
                if (!first)
                    first = key;
                $('#zone').append($('<option>', { value : key }).text(key));
            });
            setColumns(first);
            <?= $modal_add_range->show(); ?>
        }, 'json').fail(function () {
            showError('msg_err', '<?= View::getTranslate('Access denied') ; ?>', 3000);
        });
    });

    function setColumns(zone)
    {
        $('#column').children().remove().end();
        $('#column').append($('<option>', { value : 0 }).text('all'));
        if (range_zones[zone].length === 1)
            return;
        range_zones[zone].forEach(function (value) {
            $('#column').append($('<option>', { value : value }).text(value));
        });
    }

    function changeRange()
    {
        setColumns($('#zone').val());
    }

    $('.js-btn-add-range-conf').click(function () {
        $.ajax({
            url: '/locations/add_range',
            method: 'POST',
            data: {
                type_id: $('#type_id').val(),
                supplier_id: $('#supplier_id').val(),
                zone: $('#zone').val(),
                column: $('#column').val(),
            },
            dataType: 'json',
            success: function (res) {
                if (res.error !== undefined) {
                    showError('msg_err_range', res.error, 3000);
                    return false;
                }
                <?= $modal_add_range->hide(); ?>
                location.reload();
            },
            error: function (res) {
                showError('msg_err_range', '<?= View::getTranslate('Access denied') ; ?>', 3000);
            }
        });
    });

    $('.js-btn-import-locations').click(function () {
        changeImport('');
        $('#upload_file').val('');
        <?= $modal_import_locations->show(); ?>
    });

    function changeImport(filename)
    {
        $('.js-btn-import-location-conf').prop('disabled', filename === '');
        $('#name_file').text(filename === '' ? 'Choose file' : filename);
    }

    $('#upload_file').change(function() {
        changeImport(this.files[0].name);
    });

    function removeElement(item, type)
    {
        $('#name_location').text(type + ' ' + item.innerText);
        $('#type_book').val(type);
        $('#book_item').val(item.innerText);
        <?= $modal_delete_location->show(); ?>
    }

    $(document).ready(function () {
        var book_locations = (new BookLocations(
            new RangeItems(<?= View::toJs($ranges) ?>, removeElement),
            new LocationItems(<?= View::toJs($locations) ?>, removeElement)
        )).addEvents();
        book_locations.reFill();

        $('.js-btn-conf').click(function () {
            let type = $('#type_book').val();
            let item = $('#book_item').val();
            if (!removeFromDB(item, type))
                return;
            if (type === 'range')
                book_locations.removeRange(item);
            else if (type === 'location')
                book_locations.removeLocation(item);
        });

        function removeFromDB(item, type)
        {
            let data = null, url = '', data_post = null;
            if (type === 'range') {
                data = book_locations.ranges.getItem(item);
                url = '/locations/remove_range';
                data_post = {
                    supplier_id: $('#supplier_id').val(),
                    type_id: $('#type_id').val(),
                    zone: data.zone,
                    column: data.column
                };
            } else if (type === 'location') {
                data = book_locations.locations.getItem(item);
                url = '/locations/remove_location';
                data_post = {
                    supplier_id: $('#supplier_id').val(),
                    type_id: $('#type_id').val(),
                    location_id: data.id
                };
            } else
                return false;
            let result = false;
            $.ajax({
                url: url,
                method: 'POST',
                data: data_post,
                async: false,
                dataType: 'json',
                success: function (res) {
                    if (res.error !== undefined) {
                        showError('msg_err', res.error, 3000);
                        return;
                    }
                    $('#main-flash-errors').hide();
                    showError('msg_suc', res.success, 7000);
                    result = true;
                },
                error: function (res) {
                    showError('msg_err', '<?= View::getTranslate('Access denied') ; ?>', 3000);
                }
            });
            return result;
        }

        $('.js-btn-import-location-conf').click(function () {
            let formData = new FormData();
            formData.append('filename', $('#upload_file').prop("files")[0]);
            formData.append('type_id', $('#type_id').val());
            formData.append('supplier_id', $('#supplier_id').val());
            $.ajax({
                url: '/locations/import_locations',
                method: 'POST',
                data : formData,
                dataType: 'json',
                processData: false,
                contentType: false,
                success: function (res) {
                    if (res.error !== undefined) {
                        showError('msg_err_import', res.error, 3000);
                        return false;
                    }
                    <?= $modal_import_locations->hide(); ?>
                    if (res.success[0].length > 0)
                        showError('msg_suc', res.success[0].join('; '), 7000);
                    if (res.success[1].length > 0)
                        showError('msg_err', res.success[1].join('; '), 7000);
                    book_locations.refresh(res.ranges, res.locations);
                },
                error: function (res) {
                    showError('msg_err_import', '<?= View::getTranslate('Access denied') ; ?>', 3000);
                }
            });
        });
    });
</script>
