<?php

use app\backend\models\Reports as ReportsModel;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\models\perms\Roles;
use app\core\statical\WithDate;

/**
 * @var User $authUser
 * @var array $suppliers
 * @var array $workers
 * @var string $report_by
 * @var array $reports
 * @var array $intervals
 * @var string $in_date
 * @var bool $warehouse_virtual
 * @var SKU|null $sku
 * @var array $history
 * @var string $barcode
 * @var bool $has_location_history_report_permission
 * @var array $sku_event_categories
 * @var array $all_event_names
 */

?>
<style>
    .reports-container .card:not(:last-child) {
        margin-bottom: 1rem;
    }
    .card-header .custom-control-label {
        cursor: pointer;
    }
    .event-category .card-header {
        background-color: #f8f9fc;
        transition: background-color 0.2s;
    }
    .event-category .card-header:hover {
        background-color: #eaecf4;
    }
    .event-category .card-body {
        padding: 0.75rem 1.25rem;
    }
</style>
<div class="container-fluid p-sm-4 p-2 reports-container">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reports</h1>
    </div>
    <?php if (!$warehouse_virtual && in_array($authUser->getRoleId(), Roles::MANAGERS)) { ?>
        <div class="card shadow">
            <div class="card-header py-3" data-toggle="collapse" data-target="#collapseOrderPacked" aria-controls="collapseOrderPacked" aria-expanded="true">
                <div class="row">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-chart-area"></i>
                            Picking/Packing
                        </span>
                        </h6>
                    </div>
                </div>
            </div>
            <div class="collapse show" id="collapseOrderPacked">
                <div class="card-body">
                    <form action="/reports" method="GET" name="form" id="form" disabled>
                        <div class="form-row">
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
                                        'id' => 'supplier_id'
                                    ];
                                    if (empty($suppliers)) {
                                        $options['disabled'] = true;
                                    }
                                    echo Html::dropDownList('supplier_id', ['all'] + $suppliers,
                                        !empty($_GET['supplier_id']) ? $_GET['supplier_id'] : null, $options);
                                    ?>
                                    <label for="supplier_id">Client</label>
                                </div>
                            </div>
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <input class="form-control form-control-lg" type="date" id="date_from"
                                           name="date_from"
                                           max="<?= View::toHtml(!empty($_GET['date_to']) ? $_GET['date_to'] : $in_date) ?>"
                                           value="<?= View::toHtml(!empty($_GET['date_from']) ? $_GET['date_from'] : date(WithDate::DATE_SIMPLE_FORMAT, strtotime("-1 week"))) ?>"
                                    />
                                    <label for="date_from">Date from</label>
                                </div>
                            </div>
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <input class="form-control form-control-lg" type="date" id="date_to"
                                           name="date_to"
                                           min="<?= View::toHtml(!empty($_GET['date_from']) ? $_GET['date_from'] : date(WithDate::DATE_SIMPLE_FORMAT, strtotime("-1 week"))) ?>"
                                           max="<?= $in_date ?>"
                                           value="<?= View::toHtml(!empty($_GET['date_to']) ? $_GET['date_to'] : '') ?>"
                                    />
                                    <label for="date_to">Date to</label>
                                </div>
                            </div>
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
                                        'id' => 'interval'
                                    ];
                                    echo Html::dropDownList('interval', $intervals,
                                        !empty($_GET['interval']) ? $_GET['interval'] : (string) array_key_last($intervals),
                                        $options);
                                    ?>
                                    <label for="interval">Interval</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <input class="form-control form-control-lg" type="text" id="SKU_id"
                                           name="SKU_id"
                                           value="<?= View::toHtml(!empty($_GET['SKU_id']) ? $_GET['SKU_id'] : '') ?>"
                                    />
                                    <label for="date_from">SKU id</label>
                                </div>
                            </div>
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <input class="form-control form-control-lg" type="text" id="barcode"
                                           name="SKU_barcode"
                                           value="<?= View::toHtml(!empty($_GET['SKU_barcode']) ? $_GET['SKU_barcode'] : '') ?>"
                                    />
                                    <label for="barcode">SKU Barcode</label>
                                </div>
                            </div>
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
                                        'id' => 'worker_id'
                                    ];
                                    if (empty($workers)) {
                                        $options['disabled'] = true;
                                    }
                                    echo Html::dropDownList('worker_id', $workers,
                                        !empty($_GET['worker_id']) ? $_GET['worker_id'] : null, $options);
                                    ?>
                                    <label for="supplier_id">Worker</label>
                                </div>
                            </div>
                            <div class="form-group col-auto mr-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="empty"
                                           name="empty" <?= !empty($_GET['empty']) ? 'checked' : '' ?> >
                                    <label class="form-check-label" for="empty">Empty</label>
                                </div>
                            </div>
                        </div>
                        <div class="row justify-content-center mt-3">
                            <label class="mt-2">Group by</label>
                            <?php foreach ($reports as $key => $report) { ?>
                                <div class="btn-group btn-group-toggle ml-3" data-toggle="buttons">
                                    <label class="btn btn-<?= $report_by === $key ? 'primary' : 'secondary' ?> mr-1 js-report-by"
                                           style="cursor: pointer">
                                        <input type="radio" name="report_by" value="<?= $key ?>"
                                               autocomplete="off" <?= $report_by === $key ? 'checked' : '' ?>>
                                        <span class="text"><?= $report ?></span>
                                    </label>
                                </div>
                            <?php } ?>
                        </div>
                        <div class="row justify-content-end m-0 my-3">
                            <a href="/reports"
                               class="btn btn-lg btn-outline-orange ml-1" <?= empty($_GET) ? '' : 'disabled' ?> >Clear</a>
                            <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                        </div>
                    </form>
                    <div id="load-container" class="text-center d-none">
                        <img src="<?= COMMON_IMG . DIRECTORY_SEPARATOR . 'tenor.gif'; ?>" style="width: 150px"/>
                    </div>
                    <div id="head-container" class="d-none">
                        <div class="row mr-3">
                            <div class="col">
                                <h2 class="ml-3">Total items found: <span id="total-items"></span></h2>
                            </div>
                            <div class="col-auto">
                                <a id="csv-report" class="btn btn-outline-primary btn-rounded float-md-right" href="/">
                                    <i class="fa fa-download"></i> Download report as CSV
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="chart-container" class="mt-2 p-1 d-none"></div>
                    <div id="error-container" class="d-none">
                        <div id="error-response-list"></div>
                        <?= Html::warning('No records found'); ?>
                    </div>
                </div>
            </div>
        </div>
        <script src="<?= VENDOR_PATH . '/js/google_chart.js'; ?>"></script>
        <script>
            const loader = document.getElementById('load-container');
            const head_container = document.getElementById('head-container');
            const chart_container = document.getElementById('chart-container');
            const error_container = document.getElementById('error-container');
            const total_items_el = document.getElementById('total-items');
            const csv_report_link = document.getElementById('csv-report');
            const error_response = document.getElementById('error-response-list');
            let records = [];

            $( document ).ready(function() {
                google.charts.load('current', {packages: ['corechart', 'bar']});
                $('#form').submit();
            });

            function addError(error) {
                error_response.insertAdjacentHTML('beforeend', `<li class="alert-danger p-2">${html_escape(error)}</li>`)
            }

            let queue = true;
            $('#form').submit(function(e){
                e.preventDefault();
                if (queue) {
                    const submit_btn = $(this).find(':submit')[0];
                    submit_btn.disabled = true;
                    queue = false;
                    loader.classList.remove('d-none');
                    if (!head_container.classList.contains('d-none'))
                        head_container.classList.add('d-none');
                    if (!chart_container.classList.contains('d-none'))
                        chart_container.classList.add('d-none');
                    if (!error_container.classList.contains('d-none'))
                        error_container.classList.add('d-none');
                    error_response.innerHTML = '';
                    $.ajax({
                        url: "/reports/get_async_data",
                        method: "GET",
                        dataType: 'json',
                        data: $(this).serialize(),
                        success: response => {
                            if (response.errors.length > 0)
                                response.errors.forEach(error => addError(error))
                            if (response.records.length > 1) {
                                records = response.records;
                                total_items_el.innerText = response.total;
                                csv_report_link.href = '/reports/csv_report?filename=' + response.filename;
                                head_container.classList.remove('d-none');
                                chart_container.classList.remove('d-none');
                                if (!error_container.classList.contains('d-none'))
                                    error_container.classList.add('d-none');
                                google.charts.setOnLoadCallback(drawBarChart);
                            } else {
                                if (!head_container.classList.contains('d-none'))
                                    head_container.classList.add('d-none');
                                if (!chart_container.classList.contains('d-none'))
                                    chart_container.classList.add('d-none');
                                error_container.classList.remove('d-none');
                            }
                            loader.classList.add('d-none');
                            queue = true;
                            submit_btn.disabled = false;
                        },
                        error: () => addError('Server error. Please reload page')
                    })
                }
            });

            $('.js-report-by').click(function () {
                $(this).find('input').prop("checked", true);
                $('.js-report-by').each(function(){
                    if ($(this).find('input')[0].checked) {
                        if (!this.classList.contains('btn-primary'))
                            this.classList.add('btn-primary')
                        this.classList.remove('btn-secondary')
                    } else {
                        if (!this.classList.contains('btn-secondary'))
                            this.classList.add('btn-secondary')
                        this.classList.remove('btn-primary')
                    }
                });
                $(this).closest('form').submit();
            });

            document.getElementById('date_from').addEventListener('change', ({target}) => {
                document.getElementById('date_to').min = target.value;
            });
            document.getElementById('date_to').addEventListener('change', ({target}) => {
                document.getElementById('date_from').max = target.value;
            });

            function drawBarChart() {
                const height = (records.length - 1) * 75 + 50;
                const percent = (height - 50)/height * 100;
                const data = google.visualization.arrayToDataTable(records);
                const options = {
                    height: height,
                    legend: { position: 'none' },
                    chartArea:{left:'10%',top:'0%',width:'80%',height:percent + '%'},
                    hAxis: {
                        minValue: 0,
                        format: '#'
                    },
                    vAxis: {
                        minValue: 0,
                    },
                    bar: {
                        'groupWidth': '60%',
                    },
                    colors: ['#1a183c'],
                    fontSize: 18
                };
                const chart = new google.visualization.BarChart(chart_container);
                chart.draw(data, options);
            }
        </script>
    <?php } ?>
    <?php if (in_array($authUser->getRoleId(), Roles::MANAGERS)) { ?>
        <div class="card shadow">
            <div class="card-header py-3" data-toggle="collapse" data-target="#collapseLocationsHeld" aria-controls="collapseLocationsHeld" aria-expanded="true">
                <div class="row">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary"><span><i class="fas fa-fw fa-map-marked-alt"></i> Locations</span></h6>
                    </div>
                </div>
            </div>
            <div class="collapse show" id="collapseLocationsHeld">
                <div class="card-body">
                    <div class="row justify-content-between">
                        <div class="form-group col-4 mb-0">
                            <div class="form-group-element">
                                <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'locs_supplier_id'
                                ];
                                if (empty($suppliers)) {
                                    $options['disabled'] = true;
                                }
                                echo Html::dropDownList(
                                    'locs_supplier_id',
                                    $suppliers + [ReportsModel::LOCATIONS_REPORT_ALL => 'All'],
                                    null,
                                    $options
                                );
                                ?>
                                <label for="supplier_id">Client</label>
                            </div>
                        </div>
                        <div class="col-auto align-self-center mr-auto">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="locs_empty" disabled>
                                <label class="form-check-label" for="locs_empty">Empty locations</label>
                            </div>
                        </div>
                        <div class="col-auto align-self-center">
                            <a id="csv-report-locations" class="btn btn-outline-primary btn-rounded float-md-right" href="">
                                <i class="fa fa-download"></i> Download report as CSV
                            </a>
                        </div>
                    </div>
                    <?php if ($has_location_history_report_permission) { ?>
                        <hr>
                        <div class="row mb-2">
                            <div class="col-12">
                                <h5 class="mb-0 text-gray-800">Location History</h5>
                            </div>
                        </div>
                        <div class="row justify-content-between">
                            <div class="form-group col-6 mb-0">
                                <div class="form-group-element">
                                    <input type="text" class="form-control form-control-lg" id="location_history_location_search" placeholder=" ">
                                    <input type="hidden" id="location_history_location_id">
                                    <label for="location_history_location_search">Location</label>
                                    <div class="input-group-append position-absolute" style="right: 0; top: 0; height: 100%;">
                                        <button class="btn btn-primary h-100" type="button" id="btn_search_location_history_location">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto align-self-center">
                                <a id="csv-report-location-history" class="btn btn-outline-primary btn-rounded float-md-right disabled" href="">
                                    <i class="fa fa-download"></i> Download report as CSV
                                </a>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <script>
            // "Empty locations" is available for "All" only
            function syncLocationsEmpty() {
                const isAll = $('#locs_supplier_id').val() === '<?= ReportsModel::LOCATIONS_REPORT_ALL ?>';
                $('#locs_empty').prop('disabled', !isAll);
                if (!isAll)
                    $('#locs_empty').prop('checked', false);
            }
            $('#locs_supplier_id').change(syncLocationsEmpty);
            syncLocationsEmpty();
            $('#csv-report-locations').click(() => {
                location = '/reports/csv_report_locations?supplier_id=' + $('#locs_supplier_id').val()
                    + ($('#locs_empty').is(':checked') ? '&empty=1' : '');
                return false;
            });
            $(document).ready(function() {
                let lastLocationHistoryResponse = [];

                function updateLocationHistoryButton() {
                    const btn = $('#csv-report-location-history');
                    if ($('#location_history_location_id').val()) {
                        btn.removeClass('disabled');
                    } else {
                        btn.addClass('disabled');
                    }
                }

                function selectLocationHistoryLocation(item) {
                    $('#location_history_location_id').val(item.id);
                    $('#location_history_location_search').val(item.label);
                    updateLocationHistoryButton();
                }

                function loadLocationHistoryLocations(term, callback) {
                    $.getJSON('/reports/get_locations_for_search', {
                        term: term,
                    }, (data) => {
                        lastLocationHistoryResponse = data.data;
                        callback(data.data);
                    });
                }

                function searchLocationHistoryLocation() {
                    const searchTerm = $('#location_history_location_search').val().trim();
                    if (!searchTerm) {
                        return;
                    }
                    loadLocationHistoryLocations(searchTerm, function(locations) {
                        if (locations.length === 1) {
                            selectLocationHistoryLocation(locations[0]);
                        } else if ($.fn.autocomplete) {
                            $('#location_history_location_search').autocomplete('search', searchTerm);
                        }
                    });
                }

                if ($.fn.autocomplete) {
                    $('#location_history_location_search').autocomplete({
                        source: function (request, response) {
                            loadLocationHistoryLocations(request.term, response);
                        },
                        minLength: 1,
                        select: function(event, ui) {
                            selectLocationHistoryLocation(ui.item);
                            return false;
                        }
                    });
                }

                $('#location_history_location_search').on('input', function() {
                    $('#location_history_location_id').val('');
                    updateLocationHistoryButton();
                });

                $('#location_history_location_search').keypress(function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        searchLocationHistoryLocation();
                    }
                });

                $('#btn_search_location_history_location').click(function() {
                    searchLocationHistoryLocation();
                });

                $('#csv-report-location-history').click(function() {
                    if ($(this).hasClass('disabled')) {
                        return false;
                    }
                    location = '/reports/csv_report_location_history?location_id=' + $('#location_history_location_id').val();
                    return false;
                });
            });
        </script>
    <?php } ?>
    <?php if (!$authUser->isRoleWorker()) { ?>
        <div class="card shadow">
            <div class="card-header py-3" data-toggle="collapse" data-target="#collapseCollectionTime" aria-controls="collapseCollectionTime" aria-expanded="true">
                <div class="row">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary"><span><i class="far fa-fw fa-calendar-alt"></i> Collection Time</span></h6>
                    </div>
                </div>
            </div>
            <div class="collapse show" id="collapseCollectionTime">
                <div class="card-body">
                    <?php if (in_array($authUser->getRoleId(), Roles::MANAGERS)) { ?>
                        <div class="row mb-3">
                            <div class="col-12 mb-2">
                                <h5 class="mb-0 text-gray-800">Daily view</h5>
                            </div>
                            <div class="col">
                                <div class="form-row">
                                    <div class="form-group col-3 mb-0">
                                        <div class="form-group-element">
                                            <input class="form-control form-control-lg" type="date" id="date_for" name="date_for"
                                                   max="<?= View::toHtml($in_date); ?>" value="<?= View::toHtml($in_date); ?>" required/>
                                            <label for="date_for">Date</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto align-self-center">
                                <a id="csv-report-CT-daily" class="btn btn-outline-primary btn-rounded float-md-right" href="">
                                    <i class="fa fa-download"></i> Download report as CSV
                                </a>
                            </div>
                        </div>
                        <script>
                            $('#csv-report-CT-daily').click(() => {
                                if ($('#date_for').val().trim() == '') {
                                    showError('report_error', 'Date must be entered', 3000);
                                    return false;
                                }
                                location = '/reports/csv_report_CT_daily?date_for=' + $('#date_for').val();
                                return false;
                            });
                        </script>
                    <?php } ?>
                    <div class="row">
                        <div class="col-12 mb-2">
                            <h5 class="mb-0 text-gray-800">View between date ranges</h5>
                        </div>
                        <div class="col">
                            <div class="form-row">
                                <div class="form-group col-3 mb-0">
                                    <div class="form-group-element">
                                        <input class="form-control form-control-lg" type="date" id="date_from_CT" name="date_from_CT"
                                               max="<?= View::toHtml($in_date); ?>" value="<?= View::toHtml($in_date); ?>" required/>
                                        <label for="date_from_CT">Date From</label>
                                    </div>
                                </div>
                                <div class="form-group col-3 mb-0">
                                    <div class="form-group-element">
                                        <input class="form-control form-control-lg" type="date" id="date_to_CT" name="date_to_CT"
                                               min="" max="<?= View::toHtml($in_date); ?>" value="<?= View::toHtml($in_date); ?>" required/>
                                        <label for="date_to_CT">Date To</label>
                                    </div>
                                </div>
                                <div class="form-group col-5 mb-0">
                                    <div class="form-group-element">
                                        <?php
                                            $options = [
                                                'class' => 'form-control form-control-lg custom-select',
                                                'id' => 'CT_supplier_id'
                                            ];
                                            if (in_array($authUser->getRoleId(), Roles::MANAGERS))
                                                $suppliers = ['all'] + $suppliers;
                                            if (empty($suppliers) || count($suppliers) == 1)
                                                $options['disabled'] = true;
                                            echo Html::dropDownList('CT_supplier_id', $suppliers, null, $options);
                                        ?>
                                        <label for="CT_supplier_id">Client</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-auto align-self-center">
                            <a id="csv-report-CT-interval" class="btn btn-outline-primary btn-rounded float-md-right" href="">
                                <i class="fa fa-download"></i> Download report as CSV
                            </a>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col">
                            <div class="alert alert-danger mt-3 mb-0 d-none" id="report_error">No records found</div>
                        </div>
                    </div>
                    <script>
                        $('#csv-report-CT-interval').click(() => {
                            if ($('#date_from_CT').val().trim() == '' || $('#date_to_CT').val().trim() == '') {
                                showError('report_error', 'Both dates must be entered', 3000);
                                return false;
                            }
                            if ($('#date_from_CT').val() > $('#date_to_CT').val()) {
                                showError('report_error', 'The interval start date cannot be greater than the end date', 3000);
                                return false;
                            }
                            location = '/reports/csv_report_CT_interval?date_from=' + $('#date_from_CT').val() + '&date_to=' + $('#date_to_CT').val() + '&supplier_id=' + $('#CT_supplier_id').val();
                            return false;
                        });

                        document.getElementById('date_from_CT').addEventListener('change', ({target}) => {
                            document.getElementById('date_to_CT').min = target.value;
                        });
                        document.getElementById('date_to_CT').addEventListener('change', ({target}) => {
                            document.getElementById('date_from_CT').max = target.value;
                        });
                    </script>
                </div>
            </div>
        </div>
    <?php } ?>
    <?php if ($has_sku_report_permission) { ?>
    <div class="card shadow">
        <div class="card-header py-3" data-toggle="collapse" data-target="#collapseSKUHistory" aria-controls="collapseSKUHistory" aria-expanded="true">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary"><span><i class="fas fa-fw fa-history"></i> SKU History Report</span></h6>
                </div>
            </div>
        </div>
        <div id="collapseSKUHistory" class="collapse show" aria-labelledby="headingSKUHistory">
            <div class="card-body">
                <div class="alert alert-danger" id="sku_report_error" style="display: none;"></div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <div class="form-group-element">
                                <input type="text" class="form-control form-control-lg" id="sku_search" placeholder=" ">
                                <label for="sku_search">SKU</label>
                                <div class="input-group-append position-absolute" style="right: 0; top: 0; height: 100%;">
                                    <button class="btn btn-primary h-100" type="button" id="btn_search_sku">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0 text-gray-800">Events</h5>
                                <button type="button" class="btn btn-sm btn-secondary" id="btn_clear_sku_events">
                                    Clear All
                                </button>
                            </div>
                            <?php foreach ($sku_event_categories as $categoryName => $categoryEvents): ?>
                                <div class="card event-category">
                                    <div class="card-header py-1 px-2">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input category-checkbox"
                                                   id="category_<?= View::toHtml(str_replace(' ', '_', $categoryName)) ?>"
                                                   checked>
                                            <label class="custom-control-label font-weight-bold" for="category_<?= View::toHtml(str_replace(' ', '_', $categoryName)) ?>">
                                                <?= View::toHtml($categoryName) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="card-body py-1 px-2">
                                        <div class="row">
                                            <?php foreach ($categoryEvents as $event): ?>
                                                <div class="col-md-6 col-lg-4">
                                                    <div class="custom-control custom-checkbox mb-0">
                                                        <input type="checkbox" class="custom-control-input event-checkbox"
                                                                id="event_<?= View::toHtml($event) ?>"
                                                                name="events[]"
                                                                value="<?= View::toHtml($event) ?>"
                                                                data-category="<?= View::toHtml(str_replace(' ', '_', $categoryName)) ?>"
                                                                checked>
                                                        <label class="custom-control-label" for="event_<?= View::toHtml($event) ?>">
                                                            <?= View::toHtml($all_event_names[$event]) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-md">
                        <div id="sku_card" style="display: none;">
                            <div class="card">
                                <div class="card-body p-2">
                                    <div class="row">
                                        <div id="sku_photo_container" class="col-auto text-center" style="min-width: 150px; display: flex; align-items: center; justify-content: center;">
                                            <img id="sku_photo" class="img-fluid rounded" style="max-width: 150px;" src="" alt="SKU Photo" />
                                            <div id="sku_photo_loading" class="text-center">
                                                <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                            </div>
                                        </div>
                                        <div class="col">
                                            <h6 class="card-title mb-0 text-lg" id="sku_name"></h6>
                                            <p class="card-text mb-0">
                                                <b class="scan-barcode" id="sku_barcode"></b>
                                            </p>
                                            <p class="card-text mb-0">
                                                SKU ID: <span id="sku_id"></span> | <span id="sku_supplier"></span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="no_sku_selected" class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Search for a SKU to view details</h5>
                        </div>
                    </div>

                    <div class="col-md-auto">
                        <div class="text-right">
                            <a id="btn_download_sku_report" class="btn btn-outline-primary btn-rounded disabled" href="#" disabled>
                                <i class="fa fa-download"></i> Download Report as CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        var selectedSKU = null;
        var selectedSupplier = null;
        var $errorElement = $("#sku_report_error");
        var skuEventsStorageKey = "reports_sku_history_selected_events";
        let _last_response = null;
        let _suppliers = <?= View::toJs($suppliers) ?>;

        $("#no_sku_selected").show();
        $("#sku_card").hide();
        restoreSelectedEvents();
        updateDownloadButton();

        function getSelectedEvents() {
            var events = [];
            $("input[name='events[]']:checked").each(function() {
                events.push($(this).val());
            });
            return events;
        }

        function saveSelectedEvents() {
            localStorage.setItem(skuEventsStorageKey, JSON.stringify(getSelectedEvents()));
        }

        function syncCategoryCheckboxes() {
            $(".category-checkbox").each(function() {
                var categoryId = $(this).attr("id").replace("category_", "");
                var hasCheckedEvents = $(".event-checkbox[data-category='" + categoryId + "']:checked").length > 0;
                $(this).prop("checked", hasCheckedEvents);
            });
        }

        function restoreSelectedEvents() {
            var storedEvents = localStorage.getItem(skuEventsStorageKey);
            if (storedEvents === null) {
                return;
            }

            try {
                storedEvents = JSON.parse(storedEvents);
            } catch (e) {
                localStorage.removeItem(skuEventsStorageKey);
                return;
            }

            if (!Array.isArray(storedEvents)) {
                localStorage.removeItem(skuEventsStorageKey);
                return;
            }

            $(".event-checkbox").prop("checked", false);
            storedEvents.forEach(function(event) {
                $(".event-checkbox").filter(function() {
                    return $(this).val() === event;
                }).prop("checked", true);
            });
            syncCategoryCheckboxes();
        }

        // Event Category handling
        $(".category-checkbox").change(function() {
            var categoryId = $(this).attr("id").replace("category_", "");
            var isChecked = $(this).prop("checked");

            // Toggle all checkboxes in this category
            $(".event-checkbox[data-category='" + categoryId + "']").prop("checked", isChecked);

            saveSelectedEvents();
            hideError();
            updateDownloadButton();
        });

        // Individual event checkbox handling
        $(".event-checkbox").change(function() {
            var category = $(this).data("category");

            // If all events in category are unchecked, uncheck the category
            var allUnchecked = $(".event-checkbox[data-category='" + category + "']:checked").length === 0;
            if (allUnchecked) {
                $("#category_" + category).prop("checked", false);
            }

            // If any event in an unchecked category is checked, check the category
            var anychecked = $(".event-checkbox[data-category='" + category + "']:checked").length > 0;
            if (anychecked && !$("#category_" + category).prop("checked")) {
                $("#category_" + category).prop("checked", true);
            }

            hideError();
            saveSelectedEvents();
            updateDownloadButton();
        });

        $("#btn_clear_sku_events").click(function() {
            $(".category-checkbox, .event-checkbox").prop("checked", false);
            saveSelectedEvents();
            hideError();
            updateDownloadButton();
        });

        function showError(message) {
            $errorElement.text(message);
            $errorElement.show();
        }

        function hideError() {
            $errorElement.hide();
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        $("#sku_search").autocomplete({
            source: function (request, response) {
                $.getJSON('/reports/get_SKUs_for_search', {
                    supplier_id: 0,
                    term: request.term,
                }, (data) => {
                    _last_response = data.data;
                    response(data.data);
                })
            },
            minLength: 1,
            select: function(event, ui) {
                selectedSKU = ui.item.id;
                selectedSupplier = ui.item.supplier_id;
                $(this).val("");
                hideError();
                updateDownloadButton();
                updateSKUCard();
                return false;
            }
        }).autocomplete("instance")._renderItem = function(ul, item) {
            let label = item.barcodes.join(', ') + ' / ' + item.name + ' / ' + item.customer_ref;
            let supplier_name = _suppliers[item.supplier_id];
            return $("<li>")
                .append("<div>" + escapeHtml(label) + " <small class='text-muted'>(" + escapeHtml(supplier_name) + ")</small></div>")
                .appendTo(ul);
        };

        $("#sku_search").on("input", function() {
            selectedSKU = null;
            selectedSupplier = null;
            hideError();
            updateSKUCard();

            var btn = $("#btn_download_sku_report");
            btn.attr("disabled", "disabled");
            btn.addClass("disabled");
        });

        $("#sku_search").keypress(function(e) {
            if (e.which === 13) {
                e.preventDefault();
                searchSku(_last_response);
            }
        });

        $("#btn_search_sku").click(function() {
            searchSku(_last_response);
        });

        function searchSku(last_response) {
            hideError();
            var searchTerm = $("#sku_search").val().trim();

            if (!searchTerm) {
                showError("Please enter a SKU barcode or name to search");
                return;
            }
            let available_SKUs = Object.values(last_response);
            let exactMatches = available_SKUs.find(function (element) {
                const found = element.barcodes.find((barcode) => barcode === searchTerm);
                if (found)
                    return element['id'];
                return false
            });

            if (exactMatches !== undefined) {
                selectedSKU = exactMatches.id;
                selectedSupplier = exactMatches.supplier_id;
                $("#sku_search").val("");
                updateDownloadButton();
                updateSKUCard();
            } else {
                var matches = available_SKUs.filter(function(item) {
                    let label = item.barcodes.join(', ') + ' / ' + item.name + ' / ' + item.customer_ref;
                    return label.toLowerCase().indexOf(searchTerm.toLowerCase()) !== -1;
                });
                if (matches.length === 1) {
                    selectedSKU = matches[0].id;
                    selectedSupplier = matches[0].supplier_id;
                    $("#sku_search").val("");
                    updateDownloadButton();
                    updateSKUCard();
                } else if (matches.length > 1) {
                    $("#sku_search").autocomplete("search", searchTerm);
                } else {
                    showError("No matching SKUs found");
                }
            }
        }

        function updateDownloadButton() {
            var hasEvents = $("input[name='events[]']:checked").length > 0;
            var btn = $("#btn_download_sku_report");

            if (!selectedSKU || !hasEvents) {
                btn.attr("disabled", "disabled");
                btn.addClass("disabled");
            } else {
                btn.removeAttr("disabled");
                btn.removeClass("disabled");
            }

            if (!hasEvents) {
                showError("Please select at least one event type");
            } else if (selectedSKU) {
                hideError();
            }
        }

        function updateSKUCard() {
            if (selectedSKU) {
                let skuInfo = _last_response[selectedSKU];
                if (skuInfo) {
                    var name = skuInfo['name'];
                    let supplier_name = _suppliers[skuInfo['supplier_id']];

                    $("#sku_name").text(name);
                    $("#sku_barcode").html(convertBarcodeWithPrimaryBarcode(skuInfo.barcodes.join(', ')));
                    $("#sku_id").text(skuInfo.id);
                    $("#sku_supplier").text(supplier_name || "Unknown");

                    $("#sku_photo_container").show();
                    $("#sku_photo_loading").show();
                    $("#sku_photo").hide();

                    if (skuInfo.photo) {
                        $.ajax({
                            url: "/SKUs/get_photo",
                            method: "GET",
                            dataType: "json",
                            data: { id: skuInfo.id },
                            success: function(response) {
                                $("#sku_photo_loading").hide();

                                if (response.error) {
                                    $("#sku_photo_container").hide();
                                } else {
                                    $("#sku_photo_container").show();
                                    $("#sku_photo").attr("src", response.photo).show();
                                }
                            },
                            error: function() {
                                $("#sku_photo_loading").hide();
                                $("#sku_photo_container").hide();
                            }
                        });
                    } else {
                        $("#sku_photo_loading").hide();
                        $("#sku_photo_container").hide();
                    }

                    $("#sku_card").show();
                    $("#no_sku_selected").hide();
                }
            } else {
                $("#sku_card").hide();
                $("#no_sku_selected").show();
            }
        }

        $("#btn_download_sku_report").click(function(e) {
            if ($(this).attr("disabled") || $(this).hasClass("disabled")) {
                e.preventDefault();
                return false;
            }

            hideError();

            if (!selectedSKU) {
                showError("Please select a SKU first");
                return false;
            }

            var events = getSelectedEvents();

            if (events.length === 0) {
                showError("Please select at least one event type");
                return false;
            }

            var url = "/reports/csv_report_skus?sku_id=" + encodeURIComponent(selectedSKU) +
                     "&supplier_id=" + encodeURIComponent(selectedSupplier) +
                     "&events=" + encodeURIComponent(JSON.stringify(events));

            window.location.href = url;
            return false;
        });
    });
    </script>
    <?php } ?>
</div>
