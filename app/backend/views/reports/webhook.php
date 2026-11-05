<?php
/**
 * @var $webhook WebhookHistory
 */

use app\core\entities\WebhookHistory;
use app\backend\models\WebhookHistories;
use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Webhook</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary col">
                <i class="fas fa-fw fa-globe"></i> <?= View::toHtml($webhook->getName() . ' (' . $webhook->getCreateDate() . ')'); ?> <?= WebhookHistories::badgeStatus($webhook->getStatus()); ?>
            </h6>
            <div class="col-auto">
                <button class="float-right border border-dark btn btn-xs mr-0" onclick="copyToClipboard('#webhook-content');">Copy</button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12" id="webhook-content">
                    <pre class="mb-0" style="background-color: #fdfdfd; border: 1px solid #111111; padding: 20px"></pre>
                </div>
            </div>
            <div class="row d-none" id="webhook-response">
                <div class="col-12">
                    <h5 class="mt-2">Response</h5>
                </div>
                <div class="col-12" id="webhook-response-content">
                    <pre class="mb-0" style="background-color: #fdfdfd; border: 1px solid #111111; padding: 20px"></pre>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $('#webhook-content pre').text(JSON.stringify(<?= View::toJs($webhook->getJsonData()); ?>, null, 4));
    <?php if (!empty($webhook->getResponse())) { ?>
        $('#webhook-response').prop('classList').remove('d-none');
        $('#webhook-response-content pre').text(JSON.stringify(<?= View::toJs($webhook->getResponse()); ?>, null, 4));
    <?php } ?>

    function copyToClipboard(element) {
        let $temp = $("<input>");
        $(element).append($temp);
        $temp.val($(element).text()).select();
        document.execCommand('copy');
        $temp.remove();
    }
</script>
