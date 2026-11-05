<?php

use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;

/**
 * @var User $authUser
 * @var string $title
 * @var string $title2
 * @var string $example
 * @var string $data
 * @var string $back
 * @var string $upl_data
 * @var array|null $suppliers
 * @var array|null $users
 */

$supplier_id = $supplier_id ?? $_GET['supplier_id'] ?? null;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Upload <?= View::toHtml($title); ?></h1>
    </div>
    <form method="post" action="/upload/file" enctype="multipart/form-data">
        <input type="hidden" name="back" value="<?= View::toHtml($back); ?>" />
        <?php if (isset($back_params)) { ?>
            <input type="hidden" name="back_params" value='<?= View::toHtml($back_params); ?>' />
        <?php } ?>
        <input type="hidden" name="data" value="<?= View::toHtml($data); ?>" />
        <input type="hidden" name="upl_data" value="<?= View::toHtml($upl_data); ?>" />
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <div class="row">
                    <div class="col">
                        <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-map-marker-alt"></i>
                            <?= View::toHtml($title2); ?>
                        </span>
                        </h6>
                    </div>
                </div>
            </div>
            <div class="card-body">

                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <p class="form-control-static text-lg"><b>Guidance before uploading</b></p>
                        <p class="form-control-static">You must use the template below, and remember to leave the required column headers in your CSV file (as in the first line of the example file below), but remove the second and third lines of the file as these lines are purely for demonstration purposes only. Maximum 500 rows per file</p>
                        <p class="form-control-static"><a href="/upload/demo/?csv=<?= urlencode($example); ?>"><b>Download example CSV file</b></a></p>
                        <?php if(!empty($suppliers) && !$authUser->isRoleSupplierUser()): ?>
                            <div class="row">
                                <div class="form-group col-md-5">
                                    <div class="form-group-element">
                                        <select id="supplier_id" name="supplier_id" class="form-control form-control-lg custom-select">
                                            <?php foreach ($suppliers as $row) { ?>
                                                <option value="<?= $row['id'] ?>"<?= $row['id'] == $supplier_id ? " selected" : "" ?>>
                                                    <?= View::toHtml($row['name']) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <label for="supplier_id">Select company</label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($users)): ?>
                            <div class="row">
                                <div class="form-group col-md-5">
                                    <div class="form-group-element">
                                        <select id="user_id" name="user_id" class="form-control form-control-lg custom-select">
                                            <?php foreach ($users as $row) { ?>
                                                <option value="<?= $row['id'] ?>"<?= !empty($_GET['user_id']) && $row['id'] === $_GET['user_id'] ? " selected" : "" ?>>
                                                    <?= View::toHtml($row['login']) ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                        <label for="user_id">Select user</label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <p class="form-control-static mt-2 mb-1"><b>Select CSV to upload</b></p>
                        <input type="hidden" name="MAX_FILE_SIZE" value="2000000" />
                        <div class="row">
                            <div class="form-group col-md-5">
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="upload_file" id="upload_file">
                                    <label class="custom-file-label" for="upload_file" id="name_file">Choose file</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= View::toHtml(HTTP::safeLinkUrl($back)); ?>">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success btn-icon-split" id="btUpload" type="submit" disabled>
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
<script>
    $('#upload_file').change(function() {
        var filename = this.files[0].name;
        $('#btUpload').prop('disabled', filename === '');
        $('#name_file').text(filename === '' ? 'Choose file' : filename);
    });
    <?php if (!empty($users)): ?>
        $('#supplier_id').change(function() {
            var supplier_id = $(this).val();
            var url = window.location.href.split('?')[0];
            if (supplier_id) {
                url += '?supplier_id=' + supplier_id;
            }
            window.location.href = url;
        });
    <?php endif; ?>
</script>
