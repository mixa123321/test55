<?php

use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;

/**
 * @var $authUser User
 * @var $sizes array
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Size profiles</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-ruler-combined"></i>
                        Size profiles list (<?= count($sizes); ?> item<?= count($sizes) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <?php if ($authUser->hasWorkersAccess()) { ?>
                    <div class="col-auto">
                        <a class="btn btn-success btn-icon-split" href="/sizes/add_size?<?= http_build_query($_GET); ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                            <span class="text">Create new profile</span>
                        </a>
                    </div>
                <?php } ?>
                <div class="clearfix"></div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($sizes)) { ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="45%">
                        <col width="15%">
                        <col width="15%">
                        <col width="15%">
                        <col width="*">
                    </colgroup>
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Length (cm)</th>
                        <th>Width (cm)</th>
                        <th>Height (cm)</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    foreach ($sizes as $size) {
                        ?>
                        <tr>
                            <td><?= View::toHtml($size['name']) ?></td>
                            <td><?= View::toHtml(sprintf("%.2f", $size['depth'])) ?></td>
                            <td><?= View::toHtml(sprintf("%.2f", $size['width'])) ?></td>
                            <td><?= View::toHtml(sprintf("%.2f", $size['height'])) ?></td>
                            <td>
                                <a class="btn btn-success btn-icon-split btn-block d-flex justify-content-start text-nowrap" href="/sizes/edit_size?id=<?= $size['id'] ?>">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </span>
                                    <span class="text flex-fill">Edit</span>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            <?php } else
                echo Html::warning('Records is not found');
            ?>
        </div>
    </div>
</div>
