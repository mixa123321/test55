<?php

use app\core\entities\FlatIntegration;
use app\core\entities\SFTPExport;
use app\core\helpers\View;

/**
 * @var array $channels_list
 * @var array $channels_users
 * @var array $suppliers
 * @var int $supplier_id
 * @var SFTPExport $sftp
 * @var FlatIntegration $flat
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Channels</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-sign-in-alt"></i>
                            Channels list
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <?php if (count($suppliers) == 1) { ?>
                        <h6 class="m-0 font-weight-bold text-primary float-left"><span><?= View::toHtml($suppliers[$supplier_id]); ?></span></h6>
                    <?php } else { ?>
                        <form method="get">
                            <select class="selectpicker form-control" name="supplier_id" onchange="submit();">
                                <?php foreach ($suppliers as $key => $name)
                                    echo "<option value='$key'" . ($key == $supplier_id ? ' selected' : '') . '>' . View::toHtml($name) . '</option>';
                                ?>
                            </select>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <colgroup>
                                <col width="*">
                                <col width="20%">
                                <col width="20%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                                <?php if (count($channels_list)) { ?>
                                    <?php foreach ($channels_list as $channel) { ?>
                                        <tr>
                                            <td>
                                                <?= View::toHtml($channel['name']);?>
                                            </td>
                                            <td>
                                                <?php if(!empty($channels_users[$channel['name']])) {?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php } else {?>
                                                    <span class="badge badge-secondary">Inactive</span>
                                                <?php } ?>
                                            </td>
                                            <td><a href="/channels/channel_details/?id=<?= $channel['id']; ?>&supplier_id=<?= $supplier_id; ?>" class="btn btn-primary">Details</a></td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                                <?php if (!empty($flat)) { ?>
                                    <tr>
                                        <td class="text-primary font-italic">Flat</td>
                                        <td>
                                            <?php if ($flat->getIsActive()) {?>
                                                <span class="badge badge-success">Active</span>
                                            <?php } else {?>
                                                <span class="badge badge-secondary">Inactive</span>
                                            <?php } ?>
                                        </td>
                                        <td><a href="/channels/flat_details/?supplier_id=<?= $supplier_id; ?>" class="btn btn-primary">Details</a></td>
                                    </tr>
                                <?php } ?>
                                <tr>
                                    <td class="text-primary font-italic">SFTP</td>
                                    <td>
                                        <?php if (!empty($sftp) && $sftp->getIsActive()) {?>
                                            <span class="badge badge-success">Active</span>
                                        <?php } else {?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php } ?>
                                    </td>
                                    <td><a href="/channels/sftp_details/?supplier_id=<?= $supplier_id; ?>" class="btn btn-primary">Details</a></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
