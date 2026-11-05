<?php

use app\core\entities\SKU;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\tooltip\Tooltip;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var $authUser User
 * @var $SKU SKU
 * @var $SKU_barcode string
 * @var $back string
 * @var $sizes array
 * @var $redirect string
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Measure</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-archive"></i> <?= View::toHtml(sprintf("%s (#%d)", $SKU->getName(),  $SKU->getId())); ?> <span class="badge badge-primary"><?= View::toHtml($SKU_barcode); ?></span>
            </h6>
        </div>
        <form method="post" action="/SKUs/measure_change" enctype="multipart/form-data">
            <div class="card-body">
                <input type="hidden" name="SKU[id]" value="<?= View::toHtml($SKU->getId()); ?>">
                <input type="hidden" name="SKU[name]" value="<?= View::toHtml($SKU->getName()); ?>">
                <input type="hidden" name="SKU[hs_code]" value="<?= View::toHtml($SKU->getHsCode()); ?>">
                <input type="hidden" name="SKU[barcode]" value="<?= View::toHtml($SKU_barcode); ?>">
                <input type="hidden" name="SKU[supplier_id]" value="<?= View::toHtml($SKU->getSupplierId()); ?>">
                <input type="hidden" name="back" value="<?= View::toHtml($back); ?>">
                <input type="hidden" name="redirect" value="<?= View::toHtml($redirect); ?>">
                <div class="row justify-content-end d-md-none pb-3">
                    <div class="col-auto mb-1">
                        <button type="button" class="btn btn-primary btn-icon-split text-nowrap mr-0" data-toggle='collapse' data-target="#filter_show" id="filter_click">
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-image"></i>
                            </span>
                        </button>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12 col-md-12 col-xl-2">
                        <div class="row border m-0 collapse hide d-md-block" id="filter_show">
                            <div class="col">
                                <div class="form-group">
                                    <div class="row mt-2">
                                        <div class="col">
                                            <label for="imgPhoto">Select photo</label>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" id="full-img" class="btn btn-sm btn-icon-split btn-orange mb-2">
                                                <span class="icon">
                                                    <i class="fas fa-fw fa-eye"></i>
                                                </span>
                                            </button>
                                        </div>
                                        <div class="col-auto">
                                            <button type="button" id="cancel_img" class="btn btn-sm btn-icon-split btn-orange mb-2">
                                                <span class="icon">
                                                    <i class="fas fa-fw fa-undo"></i>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                    <?= Html::hiddens([
                                            ['MAX_FILE_SIZE', 10485760],
                                            ['null_photo', '', true]
                                        ]);
                                    ?>
                                    <input name="photo" id="photo" type="file" class="d-none">
                                    <img class="img-thumbnail m-0 w-100" title="photo" src="" id="imgPhoto"/>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-12 col-xl-10">
                        <div class="row">
                            <div class='col-md-3'>
                                <div class="form-group">
                                    <label for="depth">Length (cm):</label>
                                    <input name="SKU[depth]" id="depth" type="number" class="form-control" min="0.001" step="0.001"
                                           value="<?= View::toHtml($SKU->getDepth()); ?>">
                                </div>
                            </div>
                            <div class='col-md-3'>
                                <div class="form-group">
                                    <label for="width">Width (cm):</label>
                                    <input name="SKU[width]" id="width" type="number" class="form-control" min="0.001" step="0.001"
                                           value="<?= View::toHtml($SKU->getWidth()); ?>">
                                </div>
                            </div>
                            <div class='col-md-3'>
                                <div class="form-group">
                                    <label for="height">Height (cm):</label>
                                    <input name="SKU[height]" id="height" type="number" class="form-control" min="0.001" step="0.001"
                                           value="<?= View::toHtml($SKU->getHeight()); ?>">
                                </div>
                            </div>
                            <div class='col-md-3'>
                                <div class="form-group">
                                    <label for="weight">Weight (kg):</label>
                                    <input name="SKU[weight]" id="weight" type="number" class="form-control" min="0.001" step="0.001"
                                           value="<?= View::toHtml($SKU->getWeight()); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="alert alert-danger d-none" id="dims" name="dims">
                            Enter dimensions
                        </div>
                        <?php if (!empty($sizes)) { ?>
                            <div class="row">
                                <div class="col">
                                    <br/>
                                    <label><b>Override max capacity for this SKU</b></label>
                                    <table class="table table-hover mb-0">
                                        <colgroup>
                                            <col width="*"/>
                                            <col width="40%"/>
                                            <col width="20%"/>
                                        </colgroup>
                                        <thead>
                                        <tr>
                                            <th>Location (size)</th>
                                            <th>Override quantity</th>
                                            <th>
                                                <button class="btn btn-success btn-icon-split js-btn-calc-all" type="button">
                                                    <span class="icon"><i class="fas fa-fw fa-calculator"></i></span>
                                                    <span class="text text-white d-lg-block d-none">Calc All</span>
                                                </button>
                                            </th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        $capacity = $SKU->getMaxCapacity();
                                        $i = 0;
                                        foreach ($sizes as $key => $val) { ?>
                                            <tr>
                                                <td><?= View::toHtml($val[0]) ?></td>
                                                <td>
                                                    <input id="SKU_<?= $i; ?>" name="SKU[max_capacity][<?= $key ?>]" type="number" min="0" class="form-control"
                                                           value="<?= View::toHtml(empty($capacity[$key]) ? "" : $capacity[$key]) ?>">
                                                </td>
                                                <td>
                                                    <button class="btn btn-success btn-icon-split js-btn-calc" type="button" onclick="calculateQuantity(<?= $i; ?>, <?= $val[1]; ?>)">
                                                        <span class="icon"><i class="fas fa-fw fa-calculator"></i></span>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php
                                            $i++;
                                        } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php } ?>
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
<div id="full-page"></div>
<?= View::include_js_with_params(BACKEND_JS, "handle_image_form.js", [
    'imgFileInputId' => 'photo',
    'imgId' => 'imgPhoto',
    'cancelImgId' => 'cancel_img',
    'setNullId' => 'null_photo',
    'defaultCurrentSrc' => addslashes(COMMON_IMG . '/photo.png'),
    'currentSrc' => empty($SKU->getPhoto()) ? null : SKU::showPhotoSKU(urlencode($SKU->getPhoto()))
]); ?>
<?= (new Tooltip('Undo Photo', 'cancel_img'))->get(); ?>
<style>
    #full-page {
        display: none;
        position: absolute;
        z-index: 9999;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-size: contain;
        background-repeat: no-repeat no-repeat;
        background-position: center center;
        background-color: black;
    }
</style>
<script>
    $('#full-img').click(function () {
        const img = document.querySelector('#imgPhoto');
        const fullPage = document.querySelector('#full-page');
        fullPage.style.backgroundImage = 'url(' + img.src + ')';
        fullPage.style.display = 'block';
    });
    $('#full-page').click(function () {
        this.style.display='none';
    });
    document.getElementById('imgPhoto').addEventListener('change', function () {
        let default_current_src = <?= View::toJs(COMMON_IMG . '/photo.png') ?>;
        if (this.getAttribute("src") === default_current_src) {
            $('#full-img').prop('classList').add('d-none');
        } else {
            $('#full-img').prop('classList').remove('d-none');
        }
    });

    function calculateQuantity(num, Vmax)
    {
        var V = width.value * height.value * depth.value;
        if (isNaN(V) || !V) {
            if (dims.classList.contains('d-none'))
                dims.classList.remove('d-none');
            depth.focus();
            setTimeout("dims.classList.add('d-none');", 10000);
            return false;
        }
        if (!dims.classList.contains('d-none'))
            dims.classList.add('d-none');
        $('#SKU_' + num).val(Math.trunc(Vmax/V));
    }
    $('.js-btn-calc-all').click(function () {
        $('.js-btn-calc').trigger('click');
    });
</script>