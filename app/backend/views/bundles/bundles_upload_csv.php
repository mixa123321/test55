<?php
use app\core\helpers\View;

/**
 * @var $authUser User
 * @var $title string
 * @var $title2 string
 * @var $example string
 * @var $data string
 * @var $back string
 * @var $upl_data string
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Bundles upload CSV</h1>
    </div>
    <form method="post" action="/bundles/bundles_upload_csv_process" enctype="multipart/form-data">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <div class="row">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-map-marker-alt"></i>
                            Bundles upload CSV
                        </span>
                        </h6>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <div class="alert alert-info">File format is 3 columns: 1.Bundle Name/SKU 2.SKU barcode 3.SKU quantity. The first line is headings</div>
                        <?php if(!empty($suppliers)): ?>
                            <div class="row">
                                <div class="form-group col-md-5">
                                    <div class="form-group-element">
                                        <select id="supplier_id" name="supplier_id" class="form-control form-control-lg custom-select">
                                            <?php foreach ($suppliers as $id => $name) { ?>
                                                <option value="<?= $id ?>"><?= View::toHtml($name) ?></option>
                                            <?php } ?>
                                        </select>
                                        <label for="supplier_id">Select company</label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <p class="form-control-static mt-2 mb-1"><b>Select CSV to upload</b></p>
                        <input type="hidden" name="MAX_FILE_SIZE" value="2000000" />
                        <div class="row">
                            <div class="form-group col-md-5">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="upload" id="upload_file" onchange="btUpload.disabled = (upload_file.value.trim() === '');">
                                    <label class="custom-file-label" for="upload_file">Choose file</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3 btn-sm" href="/bundles">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success btn-icon-split" name="btUpload" type="submit" disabled>
                            <span class="text">Upload</span>
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-file-upload"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
