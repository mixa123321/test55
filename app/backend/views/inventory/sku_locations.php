<?php

use app\backend\models\Locations;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\entities\SKU;
use app\core\helpers\Html;

/**
 * @var SKU $SKU
 * @var $SKU_barcode string
 * @var string $back
 * @var array $locations
 * @var array $batches
 * @var array $preff_locs
 * @var array $zones
 * @var array $booked_zones
 * @var array $types
 */

$location_assignment = function ($title, $type_id, $is_free, $is_booked) {
    if (!$is_free)
        $free_content = Html::warning('No free location');
    else
        $free_content = "
            <div class=\"row js-location-assignment\">
                <div class=\"col\">" .
                    (new Scan([
                        'id' => "location_$type_id",
                        'placeholder' => '',
                        'type' => Scan::TYPE__LOCATION,
                    ], [
                        'title' => 'add',
                        'type' => 'button',
                        'class' => "add-location-$type_id",
                        'icon' => 'fas fa-fw fa-plus',
                    ]))->getTemplate() . "
                </div>
            </div>
            <div id=\"error_$type_id\" class=\"alert alert-danger p-2 d-none\"></div>
            <p class=\"mt-3 mb-1\"><b>Free $title locations</b></p>
            <div class=\"row\">
                <div class=\"col-auto mt-2\">Zone</div>
                <div class=\"col\">" .
                    Html::dropDownList('', [], 0, [
                        'class' => "selectpicker form-control zone-location-$type_id",
                        'id' => "zone_$type_id",
                    ]) . "
                </div>
                <div class=\"col-auto mt-2\">Column</div>
                <div class=\"col\">" .
                    Html::dropDownList('', [], 0, [
                        'class' => "selectpicker form-control column-location-$type_id",
                        'id' => "column_$type_id",
                    ]) . "
                </div>
            </div>
            <div class=\"row\">
                <div class=\"col mt-3\" style=\"max-height: 300px; overflow-y: auto;\">
                    <div class=\"js-loader text-center d-none m-1\" id=\"loader_$type_id\" style=\"overflow: hidden;\">
                        <div class=\"spinner-border text-primary\" role=\"status\">
                            <span class=\"sr-only\">Loading...</span>
                        </div>
                    </div>
                    <table class=\"table table-hover mb-0\" id=\"free_$type_id\"></table>
                </div>
            </div>";
    $booked_content = '';
    if ($is_booked) {
        $booked_content = "
            <div class=\"col-auto\">
                <div class=\"form-check\">
                    <input type=\"checkbox\" class=\"form-check-input\" id=\"booked_$type_id\">
                    <label class=\"form-check-label\" for=\"booked_$type_id\">Booked</label>
                </div>
            </div>";
    }
    return "
        <div class=\"row mt-2\">
            <div class=\"col\">
                <p><b>$title locations</b></p>            
            </div>
            $booked_content
        </div>
        <div class=\"row\">
            <div class=\"col mt-0\">
                <table id=\"table_$type_id\" class=\"table table-hover\">
                    <colgroup>
                        <col width='60%'/>                    
                        <col width='20%'/>                    
                        <col width='*'/>                    
                    </colgroup>
                    <thead>
                        <tr>
                            <th></th>
                            <th>Priority</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id=\"preff_locs_$type_id\"></tbody>
                </table>
            </div>
        </div>
        $free_content";
}

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= View::toHtml($SKU->getName()); ?> (<?= View::toHtmlConvertWithPrimaryBarcode($SKU_barcode); ?>)</h1>
    </div>
    <form method="POST" action="/housekeeping/assignment">
        <input type="hidden" name="id" value="<?= $SKU->getId(); ?>" />
        <input type="hidden" name="back" value="<?= View::toHtml($back); ?>" />
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <div class="row">
                    <div class="col align-content-center">
                        <h6 class="m-0 font-weight-bold text-primary"><span><i class="fas fa-fw fa-cogs"></i>Placement management</span></h6>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" type="submit">
                            <span class="text">Done</span>
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8 col-lg-9">
                        <div class="row">
                            <div class="col">
                                <b>Location assignment for SKU</b>
                            </div>
                            <div class="col-auto">
                                <button id='historyBtn' onclick="showHistoryChanges()" class="btn btn-secondary btn-icon-split" type="button">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-history"></i>
                                    </span>
                                    <span class="text">History</span>
                                </button>
                            </div>
                        </div>
                        <div class="row">
                            <?php foreach ($types as $type => $title): ?>
                                <div class="col-md-6">
                                    <?= $location_assignment($title, $type, count($zones[$type]), count($booked_zones[$type])); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3 p-3 bg-light">
                        <p><b>Current placement of SKU by locations <?= count($locations) ? ('(' . count($locations) . '&nbsp;item' . (count($locations) > 1 ? 's' : '') . ')') : ''; ?></b></p>
                        <?php if (count($locations)) { ?>
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Location/Batches</th>
                                        <th>Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($locations as $location) { ?>
                                        <tr style="background-color: <?= Locations::$colorLocation[$location['type_id']]; ?>">
                                            <td class="m-0 py-1">
                                                <?= View::toHtml($location['name']); ?>
                                                <?php if(!empty($location['expiry'])) { ?>
                                                    <br/>
                                                    <span class="text-danger text-sm font-weight-bold">Contains an expired batch</span>
                                                <?php } ?>
                                                <br/>
                                                <?php if(!empty($batches[$location['id']])) { ?>
                                                    <?php foreach($batches[$location['id']] as $batch) { ?>
                                                        <b>Batch#:</b> <?= View::toHtml($batch['batch_number'])?>
                                                        (<?= View::toHtml($batch['quantity'])?> units)<br/>
                                                        <b>Exp#:</b>
                                                        <?php
                                                            echo View::toHtml(date('jS M Y', strtotime($batch['expired'])));
                                                            if ($batch != end($batches[$location['id']]))
                                                                echo '<hr class="my-1"/>';
                                                        ?>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    -
                                                <?php } ?>
                                            </td>
                                            <td class="text-right"><?= View::toHtml($location['summa']); ?></td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } else
                            echo Html::warning('No location found');
                        ?>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-secondary btn-icon-split" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-chevron-left"></i>
                            </span>
                            <span class="text">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" type="submit">
                            <span class="text">Done</span>
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<?php
    $modal_history = new InfoModal('historyChanges', 'Placement SKU history', InfoModal::SIZE_LG);
    echo $modal_history
        ->addContent('
             <div id="history-status"></div>
             <div id="history-table"></div>')
        ->put();
?>
<script>
    let loadedHistory = false;
    function showHistoryChanges()
    {
        const historyBtn = document.getElementById('historyBtn');
        historyBtn.disabled = true;
        const status = document.getElementById('history-status');
        if (!loadedHistory) {
            status.className = 'alert alert-primary mb-0';
            status.textContent = 'Loading...';
            $.ajax({
                url: "/housekeeping/get_placement_history",
                method: "POST",
                data: { SKU_id: parseInt('<?= $SKU->getId(); ?>') },
                dataType: "json",
                success: function (response) {
                    loadedHistory = true;
                    historyBtn.disabled = false;
                    const records = response.records;
                    let templateTable = '';
                    if (records.length > 0) {
                        templateTable = `
                            <table class="table table-hover mb-0">
                                <thead>
                                    <th>Event date</th><th>User</th><th>Operation</th><th>Locations</th>
                                </thead>
                                <tbody>${records.reduce((temp, rec)=> {
                                    temp += `
                                        <tr>
                                            <td>${rec.date}</td>
                                            <td>${rec.user}</td>
                                            <td>${rec.operation}</td>
                                            <td>${rec.locations}</td>
                                        </tr>`;
                                    return temp;
                                }, '')}
                                </tbody>
                            </table>`;
                        status.className = '';
                        status.textContent = '';
                    } else {
                        status.className = 'alert alert-warning mb-0';
                        status.textContent = 'Not found data';
                    }
                    document.getElementById('history-table').insertAdjacentHTML('afterbegin', templateTable);
                },
                error: function() {
                    status.className = 'alert alert-danger mb-0';
                    status.textContent = 'Error';
                }
            });
        }
        historyBtn.disabled = false;
        <?= $modal_history->show(); ?>
    }

    $(document).submit(function (e) {
        if (this.activeElement.id === 'location_PF') {
            $('.add-location-PF').click();
            return false;
        }
        if (this.activeElement.id === 'location_ST') {
            $('.add-location-ST').click();
            return false;
        }
        return true;
    });

    $(document).ready(function () {
        class LocationAssignment {
            constructor(locations, zones, booked_zones, type) {
                this.free_locations = [];
                this.locations = locations;
                this.zones = zones;
                this.booked_zones = {};
                // fix for whole zone booked
                for (var zone in booked_zones) {
                    var columns = booked_zones[zone];
                    if (columns.length == 1 && columns[0] === null
                        || columns.length == 0
                    ) {
                        if (zone in this.zones)
                            this.booked_zones[zone] = this.zones[zone];
                        continue;
                    }
                    this.booked_zones[zone] = columns;
                }
                this.setBooked(false);
                this.type = type;
            }
            addEvents() {
                let self = this;
                $(document).on("click", '.remove-location-' + this.type, function (e) {
                    self.locations = self.locations.filter(el => el.location_id != $(this).data('id'));
                    self.reFillAssign();
                });
                $(document).on("click", '.add-location-' + this.type, function () {
                    let button = $(this);
                    let location = $('#location_' + self.type);
                    let search = location.val().trim().toUpperCase();
                    location.val('');
                    if (search == '') {
                        showError('error_' + self.type, 'The location name is missing', 3000);
                        location.focus();
                        return false;
                    }
                    let exist_locations = self.free_locations.filter(el => (el.name == search) || (el.alias == search));
                    if (exist_locations.length !== 1) {
                        if (exist_locations.length == 0) {
                            location.attr('disabled', true);
                            button.attr('disabled', true);
                            self.loadFreeLocations(search).done(function (response) {
                                location.attr('disabled', false);
                                if (response.error) {
                                    showError('error_' + self.type, response.error, 3000);
                                    location.focus();
                                    return;
                                }
                                if (response.locations.length == 0) {
                                    showError('error_' + self.type, 'The location is not found', 3000);
                                    location.focus();
                                    return;
                                }
                                $('#zone_' + self.type).val(response.zone);
                                $('#column_' + self.type).val(response.column);
                                self.reFillFree(response.zone, response.column);
                                // simulate search again
                                location.val(search);
                                button.attr('disabled', false);
                                button.click();
                            }).always(function () {
                                location.attr('disabled', false);
                                button.attr('disabled', false);
                            });
                            return;
                        }
                        showError('error_' + self.type, 'The location name is wrong', 3000);
                        location.focus();
                        return false;
                    }
                    let booked = self.locations.filter(el => el.location_id == exist_locations[0].location_id);
                    if (booked.length !== 0) {
                        showError('error_' + self.type, 'The location is already booked', 3000);
                        location.focus();
                        return;
                    }
                    exist_locations[0].priority = self.getNextPriority(self.locations);
                    self.locations.push(exist_locations[0]);
                    self.reFillAssign();
                    location.focus();
                });
                $(document).on("change", '#booked_' + this.type, function () {
                    self.setBooked($(this).is(':checked'));
                    self.reFillRanges();
                    self.reFillFree($('#zone_' + this.type).val(), $('#column_' + this.type).val())
                });
                $(document).on("change", '.priority-location-' + this.type, function () {
                    let current = self.locations.find(el => el.location_id == $(this).data('id'));
                    current.priority = $(this).val();
                });
                $(document).on("change", '.zone-location-' + this.type, function () {
                    self.setColumn($(this).val());
                    self.reFillFree($(this).val());
                });
                $(document).on("change", '.column-location-' + this.type, function () {
                    self.reFillFree('', $(this).val());
                });
                $(document).on("click", '.select-location-' + this.type, function () {
                    let id = $(this).data('id');
                    let exist_locations = self.free_locations.filter(el => el.location_id == id);
                    if (exist_locations.length !== 1) {
                        showError('error_' + self.type, 'The location name is wrong', 3000);
                        return;
                    }
                    let booked = self.locations.filter(el => el.location_id == id);
                    if (booked.length !== 0) {
                        showError('error_' + self.type, 'The location is already booked', 3000);
                        return;
                    }
                    exist_locations[0].priority = self.getNextPriority(self.locations);
                    self.locations.push(exist_locations[0]);
                    self.reFillAssign();
                });
                return this;
            }
            getNextPriority(locations) {
                let maxPriority = 0;
                if (locations.length > 0) {
                    let listPriority = locations.map(a => parseInt(a.priority));
                    maxPriority = Math.max.apply(Math, listPriority);
                }

                return parseInt(maxPriority) + 1;
            }
            setZone() {
                let $zone = $('#zone_' + this.type);
                $zone.children().remove();
                for (const zone in this.show_zones)
                    $zone.append('<option value="' + zone + '">' + zone + '</option>');
            }
            setColumn(zone) {
                let $column = $('#column_' + this.type);
                $column.children().remove();
                if (this.show_zones.length == 0 || this.show_zones[zone] == undefined)
                    return;
                for (const column of this.show_zones[zone])
                    $column.append('<option value="' + column + '">' + column + '</option>');
            }
            setBooked(booked) {
                this.booked = booked;
                if (!this.booked) {
                    this.show_locations = this.free_locations;
                    this.show_zones = this.zones;
                } else {
                    this.show_locations = this.free_locations.filter(el => {
                        return el.book == this.booked;
                    });
                    this.show_zones = this.booked_zones;
                }
            }
            reFillAssign() {
                let table_location = $('#preff_locs_' + this.type);
                table_location.children().remove();
                this.showTable();
                for (const location of this.locations)
                    this.addRow(location);
            }
            reFillRanges(zone = '') {
                this.setZone();
                if (zone == '')
                    zone = $('#zone_' + this.type).val();
                this.setColumn(zone);
            }
            reFillFree(zone = '', column = '', load = true) {
                if (zone == '')
                    zone = $('#zone_' + this.type).val();
                if (column == '')
                    column = $('#column_' + this.type).val();
                let table_free = $('#free_' + this.type);
                table_free.children().remove();
                let show_locations = this.free_locations.filter(el => {
                    return el.zone == zone
                        && el.column == column
                        && (!this.booked || el.book);
                });
                if (load && show_locations.length == 0) {
                    const self = this;
                    return this.loadFreeLocations(zone, column).done(function (response) {
                        self.reFillFree(zone, column, false);
                    });
                }
                for (const location of show_locations)
                    table_free.append(
                        '<tr class="select-location-' + this.type + '" style="cursor: pointer" data-id="' + location.location_id + '">' +
                            '<td>' + location.name + '</td>' +
                        '</tr>');
            }
            reFill() {
                this.reFillAssign();
                this.reFillRanges();
                this.reFillFree();
            }
            showTable() {
                let table = $('#table_' + this.type);
                if (this.locations.length === 0) {
                    if (!table.prop('classList').contains('d-none'))
                        table.prop('classList').add('d-none');
                } else {
                    if (table.prop('classList').contains('d-none'))
                        table.prop('classList').remove('d-none');
                }
            }
            addRow(location) {

                let table_location = $('#preff_locs_' + this.type);
                table_location.append(
                    '<tr id="' + this.type + '_preff' + location.location_id + '">' +
                        '<td><input type="hidden" name="' + this.type + '_name[' + location.location_id + ']" value="' + location.name + '"/>' + location.name + '</td>' +
                        '<td><input class="form-control priority-location-' + this.type + '" type="number" data-id="' + location.location_id + '" name="' + this.type + '_preff[' + location.location_id + ']" value="' + location.priority + '" min="1"/></td>' +
                        '<td>' + (location.nums != 0 ?
                            '' :
                            '<button class="btn btn-danger remove-location-' + this.type + '" type="button" data-id="' + location.location_id + '">' +
                                '<span class="icon text-white"><i class="fas fa-fw fa-times"></i></span>' +
                            '</button>') +
                        '</td>' +
                    '</tr>');
            }
            loadFreeLocations(zone, column) {
                let self = this,
                    data = {
                        type: this.type,
                        booked: this.booked,
                        SKU_id: <?= $SKU->getId(); ?>,
                    };
                if (column != undefined) {
                    data.zone = zone;
                    data.column = column;
                } else
                    data.scan = zone;
                $('#zone_' + this.type).prop('disabled', true);
                $('#column_' + this.type).prop('disabled', true);
                $('#loader_' + this.type).removeClass('d-none');
                return $.ajax({
                    url: "/housekeeping/sku_locations",
                    method: "GET",
                    data: data,
                    dataType: "json",
                }).done(function (response) {
                    if (response.error) {
                        showError('error_' + self.type, response.error, 3000);
                        return response;
                    }
                    let locations = [];
                    for (const location of response.locations) {
                        if (self.free_locations.filter(el => el.location_id == location.location_id).length != 0) {
                            continue;
                        }
                        locations.push(location);
                        self.free_locations.push(location);
                    }
                    response.locations = locations;
                    return response;
                }).fail(function () {
                    showError('error_' + self.type, 'Error loading free locations', 3000);
                }).always(function () {
                    $('#zone_' + self.type).prop('disabled', false);
                    $('#column_' + self.type).prop('disabled', false);
                    $('#loader_' + self.type).addClass('d-none');
                });
            }
        }

        <?php foreach ($types as $type => $title): ?>
            var location_assignment_<?= $type; ?> = new LocationAssignment(
                <?= View::toJs($preff_locs[$type]); ?>,
                <?= View::toJs($zones[$type]); ?>,
                <?= View::toJs($booked_zones[$type]); ?>,
                '<?= $type; ?>');
            location_assignment_<?= $type; ?>.addEvents();
            location_assignment_<?= $type; ?>.reFill();
        <?php endforeach; ?>
    });
</script>