<?php
/**
 * @var $company string
 * @var $tracking_info array
 */

use app\core\helpers\View;

?>

<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Tracking information</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary"><?= View::toHtml($company) ?></h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-10">
                    <?php
                    $last_key = array_key_last($tracking_info);
                    foreach ($tracking_info as $key => $item) {
                        $hr = $last_key === $key ? '' : '<hr>';
                        $tracking_number = View::toHtml($item['tracking_number']);
                        $url = $item['url'];
                        $scheme = parse_url($url, PHP_URL_SCHEME);
                        $url = in_array($scheme, ['http', 'https'], true) ? $url : '#';
                        $key++;
                        echo "<div>Package " . (int)$key . ": <a href='" . View::toHtml($url) . "' rel='nofollow' onclick='addClick(" . View::toHtml(View::toJs($_GET['number'] ?? '')) . ", " . (int)$item['id'] . ")'>$tracking_number</a></div>$hr";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= View::include_js(FRONTEND_JS, "tracking.js"); ?>
