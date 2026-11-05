<?php

use app\core\entities\SFTPExport;
use app\core\entities\Supplier;
use app\core\helpers\View;
use app\core\models\SFTPReports;

/**
 * @var SFTPExport $sftp
 * @var Supplier $supplier
 * @var string $login
 * @var string $word
 * @var array $sftp_report
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">SFTP</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-sign-out-alt"></i>
                            Export settings
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <h6 class="m-0 font-weight-bold text-primary float-left"><span><?= View::toHtml($supplier->getName()); ?></span></h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($sftp)) { ?>
                <div class="row">
                    <div class="col-9">
                        <div class="alert alert-danger p-2 d-none" id="msg_error"></div>
                        <div class="alert alert-success p-2 d-none" id="msg_success"></div>
                        <div class="row">
                            <div class="form-group col-6 mb-0">
                                <label for="username">User name</span>:</label>
                                <input type="text" id="username" class="form-control" readonly value="<?= View::toHtml($sftp->getLogin()); ?>">
                            </div>
                            <div class="form-group col-6 mb-0">
                                <label for="password">Password <span class="text-lowercase">(min 10 characters, Uppercase/Lowercase characters with at least 1 number)</span>:</label>
                                <input type="text" name="password" id="password" class="form-control" required value="">
                            </div>
                            <?php if ($sftp->getIsActive()) { ?>
                                <div class="form-group col mt-3 mb-0">
                                    <p class="m-0"><b>Connection string:</b> <?= View::toHtml(SFTP_PROTOCOL . '://' . $sftp->getLogin() . '@' . SFTP_DOMAIN . ':' . SFTP_PORT); ?></p>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-3">
                        <?php if (!$sftp->getIsActive()) { ?>
                            <a href="/channels/sftp_restore/?supplier_id=<?= $supplier->getId(); ?>" class="btn btn-success col">Restore connection</a>
                        <?php } else { ?>
                            <a href="/channels/sftp_delete/?supplier_id=<?= $supplier->getId(); ?>" class="btn btn-danger col">Disable connection</a>
                        <?php } ?>
                        <button class="btn btn-success col mt-1" id="btn_update" type="button">Update</button>
                    </div>
                </div>
                <?php if($sftp->getIsActive()) { ?>
                    <div class="alert alert-danger p-2 d-none mt-1 sftp_tab_alert_upload" style="display: block">Upload not available. Users are not found</div>
                    <div id="sftp_tabs">
                        <ul>
                            <li><a data-tab="#report-tab" class="active">Reports</a></li><li><a data-tab="#upload-tab">Uploads</a></li>
                        </ul>
                        <div id="report-tab" class="tab_div">
                            <?php if (!empty($sftp_report)) { ?>
                                <div class="row">
                                    <div class="col">
                                        <table class="table table-hover mb-0">
                                            <colgroup>
                                                <col width="15%"/>
                                                <col width="20%"/>
                                                <col width="15%"/>
                                                <col width="25%"/>
                                                <col width="15%"/>
                                            </colgroup>
                                            <thead>
                                            <tr>
                                                <th>Report</th>
                                                <th class="text-center">Date/Time column</th>
                                                <th>Frequency</th>
                                                <th>Addition</th>
                                                <th class="text-center">Active</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($sftp_report as $report) { ?>
                                                <tr>
                                                    <td><?= View::toHtml($report['name']); ?></td>
                                                    <td>
                                                        <div class="form-check text-center">
                                                            <input type="checkbox" class="form-check-input chk_date" id="<?= $report['id']; ?>"<?= $report['date_time'] ? ' checked' : ''; ?>>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <select class="form-control custom-select sel_freq" id="<?= $report['id']; ?>">
                                                            <?php foreach(SFTPReports::FREQUENCY[$report['type_frequency']] as $key => $item) { ?>
                                                                <option<?= ($report['frequency'] == $key) ? ' selected' : '' ?> value="<?= $key; ?>"><?= $item; ?></option>
                                                            <?php } ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($report['additional']))
                                                            foreach ($report['additional'] as $field_name => $field_data) { ?>
                                                                <div class="form-row form-group mb-0">
                                                                    <div class="col-4 pt-2"><?= View::toHtml($field_data['name']); ?></div>
                                                                    <div class="form-group-element col-8 mb-1">
                                                                        <input class="form-control js-additional"
                                                                               type="<?= $field_data['type']; ?>"
                                                                               id="<?= "{$field_name}_{$report['id']}"; ?>"
                                                                               value="<?= View::toHtml($field_data['value']); ?>"/>
                                                                    </div>
                                                                </div>
                                                            <?php } ?>
                                                    </td>
                                                    <td>
                                                        <div class="form-check text-center">
                                                            <input type="checkbox" class="form-check-input chk_act" id="<?= $report['id']; ?>"<?= $report['is_active'] ? ' checked' : ''; ?>>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                        <div id="upload-tab"  class="tab_div">
                            <?php if (!empty($sftp_upload)) { ?>
                                <form name="sftp_upload_form">
                                <div class="row">
                                    <div class="col">
                                        <table class="table table-hover mb-0" style="width: 100%">
                                            <thead>
                                            <tr>
                                                <th>Upload</th>
                                                <th>User</th>
                                                <th class="text-center">Active</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($sftp_upload as $upload): ?>
                                                <tr>
                                                    <td>
                                                        <?= View::toHtml($upload['title']) ?>
                                                        <input type="hidden" name="type_id" value="<?=$upload['type_id']?>">
                                                    </td>
                                                    <td>
                                                        <select class="form-control custom-select sel_freq" name="user_id[<?=$upload['type_id']?>]" >
                                                            <?php foreach ($users as $user): ?>
                                                                <option value="<?=$user['id']?>" <?=$upload['user_id'] == $user['id'] ? 'selected': ''?>><?= View::toHtml($user['login']) ?></option>
                                                            <?php endforeach;?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <div class="form-check text-center">
                                                            <input type="checkbox" name="active[<?=$upload['type_id']?>]" value="1" class="form-check-input chk_act" <?= $upload['is_active'] ? ' checked' : ''; ?>>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </form>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>

            <?php } else { ?>
                <form method="POST" action="/channels/sftp_create">
                    <p class="font-weight-bold my-0">Create connection</p>
                    <input type="hidden" name="supplier_id" value="<?= $supplier->getId(); ?>">
                    <div class="row justify-content-end">
                        <div class="form-group col-4 mb-0">
                            <label for="username">User name: <span style="color:red">*</span></label>
                            <div class="input-group" id="shop_url">
                                <input type="text" name="username" id="username" class="form-control text-right" required value="<?= View::toHtml($login); ?>">
                                <div class="input-group-append">
                                    <span class="input-group-text">_<?= $supplier->getId(); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group col-8 mb-0">
                            <label for="password">Password<span style="color:red">*</span> <span class="text-lowercase">(min 10 characters, Uppercase/Lowercase characters with at least 1 number)</span>:</label>
                            <input type="text" name="password" id="password" class="form-control" required value="<?= View::toHtml($word); ?>">
                        </div>
                        <div class="col-auto mt-2">
                            <button class="btn btn-primary btn-icon-split" type="submit">
                                <span class="text text-white">Connect</span>
                                <span class="icon text-white">
                                    <i class="fas fa-fw fa-plug"></i>
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            <?php } ?>
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
<?= View::include_js(BACKEND_JS, "show_error.js")?>
<script>
   $('#sftp_tabs li a').click(function () {
       <?php if(count($users) == 0): ?>
           if ($(this).attr('data-tab') == '#upload-tab') {
               $('.sftp_tab_alert_upload').removeClass('d-none');
               return;
           }
       <?php endif;?>

       $('.tab_div').hide();
       var tabShow = $(this).attr('data-tab');
       $(tabShow).show();
       $('#sftp_tabs li a').removeClass('active');
       $(this).addClass('active');
   });

   $('#btn_update').click(() => {
       var sel_freq = {};
       $('.sel_freq').each(function(){
           sel_freq[$(this).prop('id')] = $(this).val();
       });
       var chk_act = {};
       $('.chk_act').each(function(){
           chk_act[$(this).prop('id')] = $(this).is(':checked') ? 1 : 0;
       });
       var chk_date = {};
       $('.chk_date').each(function(){
           chk_date[$(this).prop('id')] = $(this).is(':checked') ? 1 : 0;
       });
       var additional = new Map();
       $('.js-additional').each(function(key, item) {
           additional.set(item.id, item.value);
       });
       $.ajax({
           url: '/channels/sftp_update',
           method: 'POST',
           dataType: 'json',
           data: {
               supplier_id: <?= $supplier->getId(); ?>,
               pwd: $('#password').val(),
               freq: sel_freq,
               active: chk_act,
               date_time: chk_date,
               additional: Array.from(additional, ([id, value]) => ({id, value})),
               postDataSFTPUpload: $("[name=sftp_upload_form]").serializeArray(),
           },
           success: function (json) {
               if ('error' in json)
                   showErrorHtml('msg_error', json.error, 3000);
               else
                   showError('msg_success', 'Data is updated', 3000);
           },
           error: function (json) {
               showError('msg_error', 'Connection is not established', 3000)
           }
       });
   });
</script>