<?php
/**
 * @var $back string
 */

use app\core\helpers\View;
use app\core\helpers\HTTP;

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center mb-5">
        <h1 class="h5 mb-0 text-gray-800"><b><?= View::getTranslate('Access for your account has been restricted'); ?></b></h1>
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
