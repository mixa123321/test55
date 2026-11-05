<?php

use app\backend\models\FlatIntegrationHistories;
use app\core\entities\FlatIntegration;
use app\core\entities\Supplier;
use app\core\helpers\View;

/**
 * @var FlatIntegration $flat
 * @var Supplier $supplier
 * @var array $last_orders
 * @var array $histories
 * @var array $histories_upload
 * @var array $histories_export
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Flat Integration</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-square"></i>
                            Flat integration settings
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <h6 class="m-0 font-weight-bold text-primary float-left"><span><?= View::toHtml($supplier->getName()); ?></span></h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-9">
                    <div class="alert alert-danger p-2 d-none" id="msg_error"></div>
                    <div class="alert alert-success p-2 d-none" id="msg_success"></div>
                    <div class="row">
                        <div class="form-group col-4 mb-2">
                            <label for="host">Host</span>:</label>
                            <input type="text" id="host" class="form-control" readonly value="<?= View::toHtml($flat->getCredentials()['host']); ?>">
                        </div>
                        <div class="form-group col-4 mb-2">
                            <label for="username">User name</span>:</label>
                            <input type="text" id="username" class="form-control" readonly value="<?= View::toHtml($flat->getCredentials()['user_name']); ?>">
                        </div>
                        <div class="form-group col-4 mb-2">
                            <label for="password">Password:</label>
                            <input type="password" autocomplete="new-password" name="password" id="password" class="form-control" placeholder="<?= empty($flat->getCredentials()['pass']) ? '' : 'Set - leave empty to keep'; ?>">
                        </div>
                        <div class="form-group col-4 mb-0">
                            <label for="path_from">Path from<span style="color:red">*</span>:</label>
                            <input type="text" name="path_from" id="path_from" class="form-control" required value="<?= View::toHtml($flat->getCredentials()['path_from']); ?>">
                        </div>
                        <div class="form-group col-4 mb-0">
                            <label for="path_to">Path to<span style="color:red">*</span>:</label>
                            <input type="text" name="path_to" id="path_to" class="form-control" required value="<?= View::toHtml($flat->getCredentials()['path_to']); ?>">
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <?php if (!$flat->getIsActive()) { ?>
                        <a href="/channels/flat_restore/?supplier_id=<?= $supplier->getId(); ?>" class="btn btn-success col">Restore connection</a>
                    <?php } else { ?>
                        <a href="/channels/flat_delete/?supplier_id=<?= $supplier->getId(); ?>" class="btn btn-danger col">Disable connection</a>
                    <?php } ?>
                    <button class="btn btn-success col mt-1" id="btn_update" type="button">Update</button>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <?php if (!empty($last_orders)) { ?>
                        <h6 class="mt-4 text-success"><span>Last export orders: <?= View::toHtml(implode(', ', $last_orders['orders'])); ?> (despatched at <?= View::toHtml($last_orders['despatched_date']); ?>)</span></h6>
                    <?php } ?>
                    <?php if (!empty($histories) || !empty($histories_upload) || !empty($histories_export)) { ?>
                        <hr class="mt-4">
                        <h3>History (last 2 weeks)</h3>
                        <ul class="nav nav-tabs nav-pills mt-3" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="upload-tab" data-toggle="tab" data-target="#upload" type="button" role="tab" aria-controls="upload" aria-selected="true">Upload</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="export-tab" data-toggle="tab" data-target="#export" type="button" role="tab" aria-controls="export" aria-selected="false">Export</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="check-tab" data-toggle="tab" data-target="#check" type="button" role="tab" aria-controls="check" aria-selected="false">Check</button>
                            </li>
                        </ul>
                        <div class="tab-content col-12">
                            <div class="tab-pane fade show active" id="upload" role="tabpanel" aria-labelledby="upload-tab">
                                <?php if (!empty($histories_upload)) { ?>
                                    <div class="col-12 mt-3">
                                        <div class="table-responsive" style="max-height: 500px">
                                            <table class="table table-hover mb-0">
                                                <thead>
                                                <th>File</th>
                                                <th>Event date</th>
                                                <th>Event</th>
                                                <th>Messages</th>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($histories_upload as $filename => $history_data) { ?>
                                                    <tr>
                                                    <td rowspan="<?= count($history_data); ?>"><?= View::toHtml($filename); ?></td>
                                                    <?php foreach ($history_data as $history) { ?>
                                                        <td><?= View::toHtml($history['create_date']); ?></td>
                                                        <td><?= FlatIntegrationHistories::showMode($history['mode']); ?></td>
                                                        <?php
                                                        $message = (string)$history['message'];
                                                        $decoded = json_decode($message);
                                                        if (json_last_error() === JSON_ERROR_NONE)
                                                            $message = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                        ?>
                                                        <td><pre><?= View::toHtml($message); ?></pre></td>
                                                        </tr>
                                                    <?php } ?>
                                                <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <div class="alert alert-warning my-2">
                                        No records found
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="tab-pane fade" id="export" role="tabpanel" aria-labelledby="export-tab">
                                <?php if (!empty($histories_export)) { ?>
                                    <div class="col-8 mt-3">
                                        <div class="table-responsive" style="max-height: 500px">
                                            <table class="table table-hover mb-0">
                                                <thead>
                                                <th>File</th>
                                                <th>Event date</th>
                                                <th>Event</th>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($histories_export as $filename => $history_data) { ?>
                                                    <tr>
                                                    <td rowspan="<?= count($history_data); ?>"><?= View::toHtml($filename); ?></td>
                                                    <?php foreach ($history_data as $history) { ?>
                                                        <td><?= View::toHtml($history['create_date']); ?></td>
                                                        <td><?= FlatIntegrationHistories::showMode($history['mode']); ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <div class="alert alert-warning my-2">
                                        No records found
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="tab-pane fade" id="check" role="tabpanel" aria-labelledby="check-tab">
                                <?php if (!empty($histories)) { ?>
                                    <div class="col-8 mt-3">
                                        <div class="table-responsive" style="max-height: 500px">
                                            <table class="table table-hover mb-0">
                                                <thead>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Files</th>
                                                </thead>
                                                <tbody>
                                                <?php foreach ($histories as $check_date => $history_data) { ?>
                                                    <tr>
                                                    <td rowspan="<?= count($history_data); ?>"><?= View::toHtml($check_date); ?></td>
                                                    <?php foreach ($history_data as $check_time => $history) { ?>
                                                        <td><?= View::toHtml($check_time); ?></td>
                                                        <td><?= implode('<br/>', array_map([View::class, 'toHtml'], $history)); ?></td>
                                                        </tr>
                                                    <?php } ?>
                                                <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php } else { ?>
                                    <div class="alert alert-warning my-2">
                                        No records found
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/channels/?supplier_id=<?= $supplier->getId(); ?>">
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
<script>
    $('#btn_update').click(() => {
        $.ajax({
            url: '/channels/flat_update',
            method: 'POST',
            dataType: 'json',
            data: {
                supplier_id: <?= $supplier->getId(); ?>,
                pwd: $('#password').val(),
                path_from: $('#path_from').val(),
                path_to: $('#path_to').val()
            },
            success: function (json) {
                if ('error' in json)
                    showError('msg_error', json.error, 3000);
                else {
                    if ($('#password').val() !== '')
                        $('#password').val('').attr('placeholder', 'Set - leave empty to keep');
                    showError('msg_success', 'Data is updated', 3000);
                }
            },
            error: function (json) {
                showError('msg_error', 'Connection is not established', 3000)
            }
        });
    });
</script>