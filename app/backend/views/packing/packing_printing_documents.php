<?php
/**
 * @var $packages array
 * @var $package_types array
 * @var $order_info array
 * @var $packing_slip_url string
 * @var $packing_slip_type string
 * @var $retain_me_slip_error string
 * @var $source_id int
 * @var $carrier Carrier
 * @var $serial_numbers array
 * @var $timer_print_pdf int|null
 */

use app\backend\models\Pack;
use app\core\entities\Carrier;
use app\core\entities\Order;
use app\core\entities\OrderDoc;
use app\core\Environment;
use app\core\helpers\HTTP;
use app\core\helpers\View;
use app\core\helpers\Html;
use app\core\models\PackingSlips;
use app\core\statical\WithDate;
use app\core\helpers\modal\InfoModal;
use app\core\helpers\modal\ConfirmModal;
use app\core\entities\Shipping;

$render_shipping_errors = function() use ($order_info) {
    $render = '';
    if (!empty($order_info['shipping_data'])) {
        $render .= '<h3>Shipping requests</h3>';
        foreach ($order_info['shipping_data'] as $package_name => $shipping_datum) {
            $render .= '<div class="border">';
            $render .= '<h5 class="m-2">' . View::toHtml($package_name . (!empty($shipping_datum[0]['box']) ? ' (' . $shipping_datum[0]['box'] . ')' : '')) . '</h5>';
            $render .= '<table class="table table-hover">';
            $render .= '<colgroup>
                            <col width="25%">
                            <col width="65%">
                            <col width="10%">
                        </colgroup>';
            $render .= '<thead><tr><th>Date</th><th>Message</th><th>File</th></tr></thead><tbody>';
            foreach ($shipping_datum as $shipping) {
                $render .= '<tr>';
                $render .=    '<td>';
                $render .=        !empty($shipping['create_date']) ? View::toHtml(date(WithDate::DATE_FRONT_FORMAT, strtotime($shipping['create_date']))) : '';
                $render .=    '</td>';
                switch ($shipping['status']) {
                    case 31:
                        $style = 'warning'; break;
                    case 4:
                        $style = 'success'; break;
                    default:
                        $style = 'danger';
                }
                $render .=    '<td class="text-' . $style . ' font-weight-bold">';
                $render .=        $shipping['status'] == 4 ? 'Success' : View::toHtml($shipping['url']);
                $render .=    '</td>';
                $render .=    '<td>';
                if (!empty($shipping['tracking_number']))
                    $render .=    '<a onclick="checkFile(' . View::toHtml(View::toJs(urldecode($shipping['tracking_number'] . ($shipping['status'] == 4 ? '.txt' : '')))) . ')"
                                      class="btn btn-primary btn-icon-split">
                                        <span class="icon text-white-50">
                                            <i class="fa fa-download"></i>
                                        </span>
                                    </a>';
                $render .=    '</td>';
                $render .= '</tr>';
            }
            $render .= '</tbody>
                        </table>
                        </div>';
        }
    }
    return $render;
};

$render_return_errors = function() use ($order_info) {
    $render = '';
    if (!empty($order_info['return_data'])) {
        $render .= '<br><h3>Return labels</h3>';
        $render .= '<div class="border">';
        $render .= '<table class="table table-hover">';
        $render .= '<colgroup>
                        <col width="25%">
                        <col width="75%">
                    </colgroup>';
        $render .= '<thead><tr><th>Date</th><th>Tracking number / Message</th></tr></thead><tbody>';
        foreach ($order_info['return_data'] as $returns) {
            $render .= '<tr>';
            $render .=    '<td>';
            $render .=        !empty($returns['create_date']) ? View::toHtml(date('jS M Y H:i', strtotime($returns['create_date']))) : '';
            $render .=    '</td>';
            switch ($returns['status']) {
                case -2:
                    $style = 'danger';
                    $msg = View::toHtml($returns['secondary_barcode']);
                    break;
                case -1:
                    $style = 'secondary';
                    $msg = 'Cancelled';
                    break;
                default:
                    $style = 'success';
                    $msg = 'Success';
            }
            $render .=    '<td class="text-' . $style . ' font-weight-bold">' . View::toHtml($returns['tracking_number']);
            $render .=        "<br/>$msg";
            $render .=    '</td>';
            $render .= '</tr>';
        }
        $render .= '</tbody>
                    </table>
                    </div>';
    }
    return $render;
};

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            Printing documents
        </h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-tasks"></i>
                         Order #<?= (int)$order_info['id'] ?>
                        (Reference: <?= View::toHtml($order_info['reference'] ?: "N/A") ?>)
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <p>
                    <a class="btn btn-link btn-sm" data-toggle="collapse"
                       href="#collapseExample" role="button" aria-expanded="false"
                       aria-controls="collapseExample">
                        How to install ZPL printer driver
                    </a>
                </p>
                <div class="collapse" id="collapseExample">
                    <div class="card card-body">
                        <ul>
                            <li>
                                <a class="btn btn-link btn-sm" target="_blank"
                                   href="/resources/download_printer_driver">
                                    Click here
                                </a>
                                to download printer driver and save it on your computer.
                            </li>
                            <li>Run downloaded file eplprinter.exe (double click on it). On Windows 7 and higher
                                you may need Administrator privileges on first run to allow install driver
                                in the computer's registry.
                            </li>
                            <li>In opened window, select thermal printer from the list of installed printers and
                                click save.
                            </li>
                            <li>NOTE: printer can be a network printer (connected to another computer and shared).
                            </li>
                            <li>Browser will ask your permission to run this software (eplprinter) at first label
                                print.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="row justify-content-md-center">
                <div class='col-lg-10 col-md-12 mb-md-3'>
                    <?php if (Order::isPrintingDocumentsIssue($order_info['status'])) { ?>
                        <?= $render_shipping_errors() ?>
                        <?= $render_return_errors() ?>
                        <br/>
                        <?= Html::info($order_info['error_instructions']) ?>
                        <a href="<?= View::toHtml($order_info['back_url']) ?>" class="btn btn-secondary mt-3">
                            Return back
                        </a>
                    <?php } else { ?>
                        <?php if ($packing_slip_type == PackingSlips::TYPE_RETAIN_ME) { ?>
                            <?php if (!empty($retain_me_slip_error)) { ?>
                                <div class="alert alert-warning js-retain-me-warning"><?= View::toHtml($retain_me_slip_error); ?></div>
                                <div class="alert alert-success js-retain-me-success" style="display: none">Packing
                                    slip successfully requested. Please wait while it's printing
                                </div>
                                <button class="btn btn-orange js-retainme-print"
                                        data-id="<?= (int)$order_info['id'] ?>"
                                        data-source_id="<?= (int)$source_id ?>"
                                        type="button">Try printing again
                                </button>
                            <?php } ?>
                        <?php } elseif ($packing_slip_type !== PackingSlips::TYPE_NONE) { ?>
                            <button class="btn btn-primary" id="btn_print_packing_slip" type="button">
                                Print Packing slip (manually)
                            </button>
                        <?php } ?>
                        <?php
                            /** @var $orderDoc OrderDoc */
                            $orderDoc = $order_info['orderDoc'];
                            if (!empty($orderDoc) && $orderDoc->existFile()) { ?>
                                <button
                                        id="btn_print_doc"
                                        class="btn btn-primary float-right"
                                        type="button"
                                        data-toggle="tooltip" data-placement="top" title="<?= View::toHtml(preg_replace('/\d+_/', '', $orderDoc->getName())); ?>"
                                        onmouseover="$(event.target).tooltip('show')"
                                >
                                    Print Doc File
                                </button>
                            <?php } ?>
                        <?php if ($order_info['packing']) { ?>
                            <div class="row justify-content-md-center my-2">
                                <div class='col-12'>
                                    <div class="alert alert-info">
                                        <h4 class="alert-heading">Instructions</h4>
                                        <p><?= View::toHtml($order_info['packing']) ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="row">
                            <div class="col-6">
                                <h2>Boxes</h2>
                            </div>
                            <div class="col-6">
                                <?php if (!empty($order_info['shipping_data'])) { ?>
                                    <button id="btn_shipping_requests_button" class="btn btn-icon-split float-md-right mt-2">Show requests</button>
                                <?php } ?>
                            </div>
                        </div>
                        <?php foreach ($packages as $package_id => $package) { ?>
                            <div class="order-box p-3 mb-3">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="order-box-header p-2">
                                            <div class="row">
                                                <div class="col-sm-6">
                                                    <h4 class="title"><?= View::toHtml($package['name']) ?></h4>
                                                </div>
                                                <div class="col-sm-6">
                                                    <span><?= View::toHtml($package_types[$package['package_type_id']] ?? '-') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-sm-6">
                                                <div class="form-check-inline">
                                                    <input type="checkbox" class="form-check-input"
                                                           id="order_inserts"
                                                           name="packages[<?= $package_id; ?>]"
                                                           <?= (int)$package['order_inserts'] ? ' checked' : ''; ?>
                                                           disabled>
                                                    <label for="order_inserts" class="mt-2">Order insert used</label>
                                                </div>
                                            </div>
                                            <div class="col-sm-2"></div>
                                            <div class="col-md-4">
                                                <span class="alert-info w-100 float-right mt-1 pl-1">
                                                    Length: <span
                                                            class="float-right pr-2"><?= ((double)$package['length'] > 0) ? (number_format($package['length'], 2) . ' cm') : '-' ?></span>
                                                </span>
                                                <span class="alert-info w-100 float-right pl-1">
                                                    Width: <span
                                                            class="float-right pr-2"><?= ((double)$package['width'] > 0) ? (number_format($package['width'], 2) . ' cm') : '-' ?></span>
                                                </span>
                                                <span class="alert-info w-100 float-right pl-1">
                                                    Height: <span
                                                            class="float-right pr-2"><?= ((double)$package['height'] > 0) ? (number_format($package['height'], 2) . ' cm') : '-' ?></span>
                                                </span>
                                                <span class="alert-info w-100 float-right pl-1">
                                                    Weight: <span
                                                            class="float-right pr-2"><?= ((double)$package['weight'] > 0) ? (number_format($package['weight'], 2) . ' kg') : '-' ?></span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="skus-area mt-3">
                                            <?php foreach ($package['skus'] as $sku) { ?>
                                                <div class="row mb-3 pb-3 border-bottom">
                                                    <div class="col-<?= $serial_numbers ? 8 : 10 ?>">
                                                        <span><?= View::toHtml($sku['name']) ?></span>
                                                        <br/>
                                                        <span><strong><?= View::toHtmlConvertWithPrimaryBarcode($sku['barcode']) ?></strong></span>
                                                    </div>
                                                    <div class="col-2 mt-1">
                                                        <input type="text" class="form-control-plaintext" readonly
                                                               value="<?= View::toHtml($sku['quantity']) ?>">
                                                    </div>
                                                    <?php if ($sku['serial_number']) { ?>
                                                        <div class="col-2 text-right">
                                                            <button type="button" class="btn btn-primary btn-icon-split mt-2 show_serial_numbers" id="show_serial_number_<?= $sku['id'] ?>" data-package-id="<?= $package_id ?>" data-sku-id="<?= $sku['id'] ?>">
                                                                <span class="icon text-white-50">
                                                                    <i class="fas fa-fw fa-cube"></i>
                                                                </span>
                                                                <span class="text d-none d-md-block pl-1 pr-1"><span id="quantity_serial_numbers_<?= $sku['id']; ?>">SN</span></span>
                                                            </button>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <?php if(!empty($order_info['shipping_services_id'])) { ?>
                                            <?php if (!empty($package['shipping']['tracking_number'])) { ?>
                                                <h4>Assigned Tracking Number:
                                                    <span class="badge badge-secondary">
                                                    <?= View::toHtml($package['shipping']['tracking_number']) ?>
                                                </span>
                                                </h4>
                                                <div class="alert alert-info">
                                                    <i class="fa fa-info-circle"></i>
                                                    Please print out the shipping label and put it on this box.
                                                </div>
                                                <a class="btn btn-primary" target="_blank"
                                                   href="<?= HTTP::getZprnUrl("/main/print_label?shipment_id={$package['shipping']['id']}&hash=" . md5($package['shipping']['tracking_number'])) ?>">
                                                    Print Shipping Label
                                                </a>
                                            <?php } else { ?>
                                                <span class="alert alert-warning w-100">Label not found</span>
                                            <?php }
                                        } else { ?>
                                            <h4>
                                                <?= Shipping::COLLECTION_BY_CUSTOMER ?>
                                            </h4>
                                            <h5>No shipping label needed</h5>
                                        <?php } ?>
                                        <?php if (!empty($package['returns'])) { ?>
                                            <?php if (!empty($package['returns']['tracking_number'])) { ?>
                                                <h4 class="mt-3">Tracking Number (for return):
                                                    <span class="badge badge-secondary">
                                                    <?= View::toHtml($package['returns']['tracking_number']) ?>
                                                </span>
                                                </h4>
                                                <div class="alert alert-info">
                                                    <i class="fa fa-info-circle"></i>
                                                    Please print out the return label and put it on this box.
                                                </div>
                                                <a class="btn btn-primary" target="_blank"
                                                   href="<?= HTTP::getZprnUrl("/main/print_label?return_id={$package['returns']['id']}&hash=" . md5($package['returns']['tracking_number'])) ?>">
                                                    Print Return Label
                                                </a>
                                            <?php } else { ?>
                                                <span class="alert alert-warning w-100">Label not found</span>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>

                                </div>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <button type="button" class="btn btn-danger float-left" id="unpackBoxes">Unpack boxes</button>
                </div>
                <div class="col">
                    <form method="POST" action="/pack/confirm_printing" <?= !Order::isPrintingDocuments($order_info['status']) ? 'disabled' : '' ?>>
                        <input type="hidden" name="order_id" value="<?= (int)$order_info['id'] ?>">
                        <button type="submit"
                                class="btn btn-success float-right"
                            <?= !Order::isPrintingDocuments($order_info['status']) ? 'disabled' : '' ?>
                        >
                            <span class="text">Confirm</span>
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
echo (new ConfirmModal('unpack', 'Unpack boxes'))->addContent('<div class="row">
                                            <div class="col-12 mb-3">
                                                Do you really want to unpack all boxes in this order?
                                            </div>
                                            <div class="col-12 mb-3 d-none" id="manualUnpack"></div>
                                        </div>
                                        <div class="alert alert-danger d-none mb-0" id="cancelError"></div>')
    ->addCustomButtons(View::btnModalDismiss('Cancel', 'secondary js-cancel-unpack') .
        '<button class="btn btn-danger btn-icon-split js-btn-conf" type="button">
            <span class="text">Unpack</span>
            <span class="icon text-white-50">
                <i class="fas fa-fw fa-box-open"></i>
            </span>
         </button>')
    ->put();

$modal_shipping_requests = new InfoModal('shippingRequests', $carrier->getCode() . 'Requests', InfoModal::SIZE_LG);
echo $modal_shipping_requests->addContent($render_shipping_errors())->put();

$modal_warning_unpack = new InfoModal('warningUnpack', 'Unpack', InfoModal::SIZE_NORMAL);
echo $modal_warning_unpack
    ->addContent('<h5>Cancelling is impossible. You should do it manually</h5><p id="warningText" class="mb-0"></p>')
    ->put();

echo (new InfoModal('serialNumbers', 'Serial numbers'))->addContent('<table class="table table-hover mb-0"><tbody id="tbody_serial_numbers"></tbody></table>')->put();

?>

<script>
    var packing_slip_url = "<?= $packing_slip_url?>";
    var isDev = <?= View::toJs(!Environment::isProduction()) ?>;
    var toPrintPackingSlip = <?= View::toJs($packing_slip_type == PackingSlips::TYPE_GENERIC || $packing_slip_type == PackingSlips::TYPE_VOKE) ?>;
    var order_id = <?= (int)$order_info['id']; ?>;
    var back_url = '<?= Pack::errorUrl($_SESSION['packing_mode']); ?>';
    var doc_exist = <?= (!empty($orderDoc) && $orderDoc->existFile()) ? 1 : 0 ?>;
    var doc_url = "<?= !empty($orderDoc) ? $orderDoc->getUploadUrl((int)$order_info['id']) : '' ?>";
    var serial_numbers = <?= View::toJs($serial_numbers) ?>;
    var timer_print_pdf = <?= View::toJs($timer_print_pdf) ?>;

    $('#btn_shipping_requests_button').on('click', function () {
        <?= $modal_shipping_requests->show(); ?>
    });

    function showWarningUnpack() {
        <?= $modal_warning_unpack->show(); ?>
    }

    $('#warningUnpackModalInfo button').on('click', function (e) {
        window.location.href = back_url;
    });

    function checkFile(filename) {
        event.preventDefault();
        $.post(
            "/pack/check_request_file",
            {filename},
            res => {
                if (res[0] !== undefined && res[0] === 'success')
                    window.open(`/pack/get_request_file/?filename=${filename}`,'_blank');
                else
                    alert('File not found. Contact your system administrator')
            },
            "json"
        );
    }

</script>
<?= View::include_js(BACKEND_JS, "printing_documents.js") ?>
