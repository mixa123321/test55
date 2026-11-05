<?php

use app\core\entities\LogRecord;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\statical\WithDate;

/**
 * @var $users array
 * @var $log_records LogRecord[]
 * @var $current_page int
 * @var $next_page bool
 * @var $http_codes array
 * @var $request_methods array
 * @var $current_filters array
 * @var $warehouse_id int
 * @var $warehouses array
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Log records</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-book"></i>
                        Logs
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
                                   value="<?= View::toHtml(!empty($_GET['date_from']) ? $_GET['date_from'] : '') ?>"
                                   min="<?= Arr::map($warehouses, 'id', 'start_warehouse')[$warehouse_id]; ?>"/>
                            <label for="date_from">Date from</label>
                        </div>
                    </div>
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="date_to" name="date_to"
                                   min="<?= View::toHtml(!empty($_GET['date_from']) ? $_GET['date_from'] : '') ?>"
                                   value="<?= View::toHtml(!empty($_GET['date_to']) ? $_GET['date_to'] : '') ?>"/>
                            <label for="date_to">Date to</label>
                        </div>
                    </div>

                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                'id' => 'user_id',
                                'data-actions-box' => 'true',
                                'data-selected-text-format' => 'count == 1',
                            ];
                            if (count($users) == 1) {
                                $options['disabled'] = 'disabled';
                            }
                            echo Html::dropDownListSelectpicker('user_id', $users,
                                !empty($_GET['user_id']) ? $_GET['user_id'] : null, $options);
                            ?>
                            <label for="user_id">User</label>
                        </div>
                    </div>

                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?= Html::dropDownListSelectpicker(
                                'response_http_code',
                                $http_codes,
                                !empty($_GET['response_http_code']) ? $_GET['response_http_code'] : null,
                                [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'response_http_code',
                                    'data-actions-box' => 'true',
                                    'data-selected-text-format' => 'count == 1',
                                ]
                            ) ?>
                            <label for="response_http_code">HTTP Code</label>
                        </div>
                    </div>

                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="request_id" id="request_id" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['request_id']) ? $_GET['request_id'] : '') ?>">
                            <label for="request_id">Request ID</label>
                        </div>
                    </div>

                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <?= Html::dropDownListSelectpicker(
                                'request_method',
                                $request_methods,
                                !empty($_GET['request_method']) ? $_GET['request_method'] : null,
                                [
                                    'class' => 'selectpicker form-control form-control-lg custom-select',
                                    'id' => 'request_method',
                                    'data-actions-box' => 'true',
                                    'data-selected-text-format' => 'count == 1',
                                ]
                            ) ?>
                            <label for="request_method">Request method</label>
                        </div>
                    </div>

                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="request_host" id="request_host" class="form-control form-control-lg filled"
                                   placeholder="<?= BASE_DOMAIN?>"
                                   value="<?= View::toHtml(!empty($_GET['request_host']) ? $_GET['request_host'] : '') ?>">
                            <label for="request_host">Request Host</label>
                        </div>
                    </div>

                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="request_uri" id="request_uri" class="form-control form-control-lg"
                                   placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['request_uri']) ? $_GET['request_uri'] : '') ?>">
                            <label for="request_uri">Request Uri</label>
                        </div>
                    </div>

                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="ip" id="ip" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['ip']) ? $_GET['ip'] : '') ?>">
                            <label for="ip">IP</label>
                        </div>
                    </div>

                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="number" min="0" step="1" name="exec_time" id="exec_time" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml(!empty($_GET['exec_time']) ? $_GET['exec_time'] : '') ?>">
                            <label for="exec_time">Min exec time (ms)</label>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <a href="/log" class="btn btn-lg btn-outline-orange">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                </div>
            </form>
            <?php if (count($log_records)) { ?>
                <?php View::simplePagination("/log?" . $current_filters . (empty($current_filters) ? '' : '&'), $current_page, $next_page); ?>
                <div class="row mt-3">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>Request ID</th>
                                <th>Request date</th>
                                <th>Request URL/Method</th>
                                <th>IP</th>
                                <th>User</th>
                                <th>Exec time (ms)</th>
                                <th>Response code</th>
                                <th>Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($log_records as $log_record) { ?>
                                <tr>
                                    <td><?= View::toHtml($log_record->getRequestId()) ?></td>
                                    <td><?= View::toHtml(date(WithDate::DATE_SQL_FORMAT, $log_record->getDateAdded())) ?></td>
                                    <td><?= View::toHtml(
                                            trim($log_record->getRequestHost(), "/") . '/'
                                            . trim($log_record->getRequestUri(), "/")
                                            . " " . $log_record->getRequestMethod()
                                        ) ?>
                                    </td>
                                    <td><?= View::toHtml($log_record->getIp()) ?></td>
                                    <td><?= View::toHtml($users[$log_record->getUserId()]) ?></td>
                                    <td><?= View::toHtml($log_record->getRequestExecTime()) ?> ms</td>
                                    <td><?= View::toHtml($log_record->getResponseHttpCode()) ?></td>
                                    <td>
                                        <button type="button" class="btn btn-primary js-details-btn"
                                                data-record-id="<?= (int) $log_record->getId()?>"
                                        >Details</button>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php View::simplePagination("/log/?" . $current_filters . (empty($current_filters) ? '' : '&'), $current_page, $next_page); ?>
            <?php } else { ?>
                <div class="alert alert-warning mt-3">
                    No records found
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="detailsModal">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">

            </div>
        </div>
    </div>
</div>
<?= View::include_js(BACKEND_JS, 'log.js')?>
