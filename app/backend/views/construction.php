<?php

use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var $back string
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center mb-5">
        <h1 class="mb-0 text-gray-800 font-weight-bolder"><?= View::getTranslate('Page is under construction'); ?></h1>
    </div>
    <div class="card-footer">
        <div class="row mt-1">
            <div class="col">
                <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= View::toHtml(HTTP::safeLinkUrl($back)) ?>">
                    <i class="fas fa-fw fa-chevron-left mr-2"></i>
                    <span class="ml-1"><?= View::getTranslate('Back'); ?></span>
                </a>
            </div>
        </div>
    </div>
</div>
