<?php
/**
 * @var $users array
 * @var $log_records \app\core\entities\CurlLogRecord[]
 * @var $current_page int
 * @var $http_codes array
 * @var $sources array
 * @var $request_methods array
 * @var $current_filters array
 * @var $next_page bool
 * @var $warehouse_id int
 * @var $warehouses array
 */

use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\View;

$min_date = Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id];

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Log records</h1>
    </div>
    <div class="card shadow mb-4">

        <div class="card-body">

            <form method="GET">
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="request_id" id="request_id" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['request_id']) ? $_GET['request_id'] : '') ?>">
                            <label for="request_id">Request ID</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="url" id="url" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['url']) ? $_GET['url'] : '') ?>">
                            <label for="url">url</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="get_params" id="get_params" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['get_params']) ? $_GET['get_params'] : '') ?>">
                            <label for="get_params">GET Params</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="number" name="response_time" id="response_time" class="form-control form-control-lg filled" min="0"
                                   value="<?= View::toHtml(!empty($_GET['response_time']) ? $_GET['response_time'] : 0) ?>">
                            <label for="response_time">Response Time</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_from" name="date_from"
                                   value="<?= View::toHtml(!empty($_GET['date_from']) ? $_GET['date_from'] : '') ?>"
                                   min="<?= $min_date; ?>"/>
                            <label for="date_from">Date from</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_to" name="date_to"
                                   min="<?= View::toHtml(!empty($_GET['date_from']) ? $_GET['date_from'] : $min_date); ?>"
                                   value="<?= View::toHtml(!empty($_GET['date_to']) ? $_GET['date_to'] : '') ?>"/>
                            <label for="date_to">Date to</label>
                        </div>
                    </div>

                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?= Html::dropDownListSelectpicker(
                                'http_code',
                                $http_codes,
                                !empty($_GET['http_code']) ? $_GET['http_code'] : null,
                                [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'http_code',
                                    'data-actions-box' => 'true',
                                    'data-selected-text-format' => 'count == 1',
                                ]
                            ) ?>
                            <label for="http_code">HTTP Code</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?= Html::dropDownListSelectpicker(
                                'source',
                                $sources,
                                !empty($_GET['source']) ? $_GET['source'] : null,
                                [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'response_http_code',
                                    'data-actions-box' => 'true',
                                    'data-selected-text-format' => 'count == 1',
                                ]
                            ) ?>
                            <label for="source">Source</label>
                        </div>
                    </div>

                </div>
                <div class="form-row">
                    <div class="form-group col-auto mr-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="result_error" name="result_error"
                                <?= View::toHtml(!empty($_GET['result_error']) ? 'checked' : '') ?>
                            >
                            <label class="form-check-label" for="result_error">Error</label>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <a href="/log/curl" class="btn btn-lg btn-outline-orange">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                </div>
            </form>
            <?php if (count($log_records)) { ?>
                <?php View::simplePagination("/log/curl/?" . $current_filters . (empty($current_filters) ? '' : '&'), $current_page, $next_page); ?>
                <div class="row mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Request date</th>
                                <th>URL</th>
                                <th>Source</th>
                                <th>Response Code</th>
                                <th>Exec time (s)</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($log_records as $log_record) { ?>
                                <tr>
                                    <td><?= View::toHtml($log_record->getRequestId()) ?></td>
                                    <td><?= View::toHtml($log_record->getCreatedAt()) ?></td>
                                    <td><?= View::toHtml($log_record->getUrl()) ?></td>
                                    <td><?= View::toHtml($log_record->getSource()) ?></td>
                                    <td><?= View::toHtml($log_record->getHttpCode()) ?></td>
                                    <td><?= View::toHtml($log_record->getResponseTime()) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-primary js-details-btn"
                                                data-record-id="<?= (int) $log_record->getId()?>"
                                        >Details</button>
                                    </td>
                                </tr>
                            <?php }?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php View::simplePagination("/log/curl/?" . $current_filters . (empty($current_filters) ? '' : '&'), $current_page, $next_page); ?>
            <?php } else { ?>
                <div class="alert alert-warning mt-3">
                    No records found
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="detailsModal">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="word-wrap: break-word;">
                <div class="container-fluid p-sm-4 p-2">
                    <div id="modal-info" style="border-bottom: 2px solid dimgrey;"></div>
                    <div class="row">
                        <div class="col-md-6" id="modal-request" style="border-right: 2px solid dimgrey;"></div>
                        <div class="col-md-6" id="modal-response"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= View::include_js(BACKEND_JS, 'curl_log.js')?>
