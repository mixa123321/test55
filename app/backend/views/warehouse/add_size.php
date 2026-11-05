<?php
/**
 * @var $size Size
 * @var $check_size bool
 */

use app\core\entities\Size;
use app\core\helpers\View;
?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Size profile</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-ruler"></i>  <?= empty($size->getId()) ? 'New' : 'Edit' ?>  profile
            </h6>
        </div>
        <form method="post" action="/sizes/set_size<?= isset($check_size) ? '?check_size=' . $check_size : ''?>">
            <input type="hidden" name="size[id]" value="<?= $size->getId() ?? '0' ?>">
            <input type="hidden" name="size[warehouse_id]" value="<?= $size->getWarehouseId() ?? '0' ?>">
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <?php if ($check_size == false) { ?>
                            <div class="alert alert-warning mt-3">
                                You can't change the parameters of this size! It is used in measures of SKUs!
                            </div>
                        <?php } ?>
                        <div class="form-row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <div class="form-group">
                                        <div class="form-group-element">
                                            <input type="text" name="size[name]" id="name" class="form-control text-uppercase form-control-lg" placeholder=" " value="<?= View::toHtml($size->getName() ?? '') ?>">
                                            <label for="name">Name <span class="text-danger">*</span></label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <div class="form-group">
                                        <div class="form-group-element">
                                            <input type="text" name="size[depth]" id="depth" class="form-control form-control-lg" placeholder=" " value="<?= $size->getDepth() ?? '' ?>"<?= ($check_size == false) ? ' disabled' : ''?>>
                                            <label for="depth">Length (cm) <span class="text-danger">*</span></label>
                                            <?php if ($check_size == false) { ?>
                                                <input type="hidden" name="size[depth]" value="<?= $size->getDepth() ?>">
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <div class="form-group">
                                        <div class="form-group-element">
                                            <input type="text" name="size[width]" id="width" class="form-control form-control-lg" placeholder=" " value="<?= $size->getWidth() ?? '' ?>"<?= ($check_size == false) ? ' disabled' : ''?>>
                                            <label for="width">Width (cm) <span class="text-danger">*</span></label>
                                            <?php if ($check_size == false) { ?>
                                                <input type="hidden" name="size[width]" value="<?= $size->getWidth() ?>">
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <div class="form-group">
                                        <div class="form-group-element">
                                            <input type="text" name="size[height]" id="height" class="form-control form-control-lg" placeholder=" " value="<?= $size->getHeight() ?? '' ?>"<?= ($check_size == false) ? ' disabled' : ''?>>
                                            <label for="height">Height (cm) <span class="text-danger">*</span></label>
                                            <?php if ($check_size == false) { ?>
                                                <input type="hidden" name="size[height]" value="<?= $size->getHeight() ?>">
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3 btn-sm" href="/sizes">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" type="submit">
                            <span class="text text-white">Done</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
