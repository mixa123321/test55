<?php
/**
 * @var $authUser User
 * @var $barcode string
 * @var $name string
 */

use app\core\entities\User;
use app\core\helpers\View;

//use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Barcode creator</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-map-marker-alt"></i>
                        Label information
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-7 col">
                    <form method="GET">
                        <div class="row">
                            <div class="col">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input id="barcode" class="form-control form-control-lg" placeholder=" " type="text"
                                               value="<?= !empty($barcode) ? View::toHtml($barcode) : ''; ?>"/>
                                        <label for="barcode">Barcode</label>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="col-sm-9 col-xl-10">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input id="name" class="form-control form-control-lg" placeholder=" " type="text"
                                                       value="<?= !empty($name) ? View::toHtml($name) : ''; ?>"/>
                                                <label for="name">Text</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-3 col-xl-2">
                                        <div class="form-group">
                                            <div class="form-group-element">
                                                <input id="quantity" class="form-control form-control-lg" min="1" type="number" value="1"/>
                                                <label for="quantity">Quantity</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <p class="form-control-static bg-danger p-2 mt-2 text-white text-center d-none" id="msg_error">Barcode, text or quantity not found</p>
                            </div>
                        </div>

                        <h4 class="text-info mt-3">Page config</h4>
                        <div class="row">
                            <div class="col">
                                <span><b>Start point (mm)</b></span>
                                <div class="form-group form-inline mt-2 mb-3">
                                    <label for="top-page" class="control-label col-3 pl-0">Top</label>
                                    <input id="top-page" class="form-control col-3" type="number" min="0" value="12.9"/>
                                    <label for="left-page" class="control-label col-3">Left</label>
                                    <input id="left-page" class="form-control col-3" type="number" min="0" value="7"/>
                                </div>
                                <span><b>Cell spacing (mm)</b></span>
                                <div class="form-group form-inline mt-2 mb-3">
                                    <label for="right-barcode" class="control-label col-3 pl-0">Horizontal</label>
                                    <input id="right-barcode" class="form-control col-3" type="number" min="0" value="2.6"/>
                                    <label for="down-barcode" class="control-label col-3">Vertical</label>
                                    <input id="down-barcode" class="form-control col-3" type="number" min="0" value="0.0"/>
                                </div>
                                <span><b>Barcodes</b></span>
                                <div class="form-group form-inline mt-2 mb-1">
                                    <label for="in-row" class="control-label col-3 pl-0">In row</label>
                                    <input id="in-row" class="form-control col-3" type="number" min="1" value="3"/>
                                    <label for="in-col" class="control-label col-3">In col</label>
                                    <input id="in-col" class="form-control col-3" type="number" min="1" value="8"/>
                                </div>
                                <p class="form-control-static bg-danger p-2 mt-2 rounded text-white text-center d-none" id="prn_error">Information invalid</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row justify-content-end">
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split js-btn-conf" type="button">
                        <span class="text">Generate</span>
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-chevron-right"></i>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $('.js-btn-conf').click(function() {
        var barcode = $('#barcode').val().trim();
        var name = $('#name').val().trim();
        var quantity = $('#quantity').val().trim()*1;
        if (barcode == '' || name == '' || quantity <= 0) {
            var msg_error = $('#msg_error');
            if (msg_error.prop('classList').contains('d-none')) {
                msg_error.prop('classList').remove('d-none');
                setTimeout(() => {
                    msg_error.prop('classList').add('d-none');
                }, 3000);
            }
            return false;
        }
        var top = $('#top-page').val().trim()*1;
        var left = $('#left-page').val().trim()*1;
        var right = $('#right-barcode').val().trim()*1;
        var down = $('#down-barcode').val().trim()*1;
        var m = $('#in-row').val().trim()*1;
        var n = $('#in-col').val().trim()*1;
        if (top < 0 || left < 0 || right < 0 || down < 0 || m < 1 || n < 1 ||
            (left + (m - 1))*right > 210 || (top + (n - 1)*down) > 297 || m > 5 || n > 20) {
            var prn_error = $('#prn_error');
            if (prn_error.prop('classList').contains('d-none')) {
                prn_error.prop('classList').remove('d-none');
                setTimeout(() => {
                    prn_error.prop('classList').add('d-none');
                }, 3000);
            }
            return false;
        }
        window.open('/housekeeping/barcode_generate/?barcode=' + encodeURIComponent(barcode) +
                                                   '&name=' + encodeURIComponent(name) +
                                                   '&qty=' + quantity +
                                                   '&top=' + top +
                                                   '&left=' + left +
                                                   '&right=' + right +
                                                   '&down=' + down +
                                                   '&in_row=' + m +
                                                   '&in_col=' + n);
    });
</script>
