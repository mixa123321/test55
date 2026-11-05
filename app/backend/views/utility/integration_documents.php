<?php
/**
 * @var $documents array
 * @var $authUser \app\core\entities\User
 */

use app\core\helpers\Html;
use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Integration documents</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row justify-content-end">
                <?php if ($authUser->isRoleSA()) { ?>
                    <div class="col-lg-4">
                        <h4>Create new document</h4>
                        <form method="POST" action="/integration_documents/add" enctype="multipart/form-data">
                            <div class="form-group-element">
                                <input type="text" name="doc_name" id="doc_name"
                                       class="form-control form-control-lg"
                                       placeholder=" "
                                       required
                                >
                                <label for="doc_name">Document name <span class="text-danger">*</span></label>
                            </div>
                            <div class="form-group mt-3">
                                <label for="doc_description">Document description</label>
                                <textarea rows="4" name="doc_description" class="form-control"></textarea>
                            </div>
                            <div class="form-group-element custom-file mb-4">
                                <input type="file" name="file" class="custom-file-input" id="upload_file">
                                <label for="file" class="custom-file-label" style="left: 0" id="upload_name">Select document file</label>
                            </div>
                            <div class="row justify-content-end">
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-lg btn-outline-success" disabled id="btn_upload">Create new</button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php } ?>
                <div class="col-lg-8">
                    <h4>Documents list</h4>
                    <?php if (count($documents)) { ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <colgroup>
                                    <col width="*"/>
                                    <col width="*"/>
                                    <col width="15%"/>
                                    <?php if ($authUser->isRoleSA()) { ?>
                                        <col width="25%"/>
                                    <?php } ?>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th>Document</th>
                                        <th>Created</th>
                                        <th>Status</th>
                                        <?php if ($authUser->isRoleSA()) { ?>
                                            <th>Action</th>
                                        <?php } ?>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($documents as $document) { ?>
                                    <tr>
                                        <td>
                                            <a class="link" href="/integration_documents/download?id=<?= (int)$document['id'] ?>">
                                                <?= View::toHtml($document['doc_name']) ?> <i
                                                        class="fa fa-download"></i>
                                            </a>
                                            <p><?= View::toHtml($document['doc_description']) ?></p>
                                        </td>
                                        <td>
                                            <?= View::toHtml(date('Y-m-d', $document['date_created'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($document['is_available']) { ?>
                                                <span class="badge badge-success">Available</span>
                                            <?php } else { ?>
                                                <span class="badge badge-danger">Unavailable</span>
                                            <?php } ?>
                                        </td>
                                        <?php if ($authUser->isRoleSA()) { ?>
                                            <td>
                                                <?php if ($document['is_available']) { ?>
                                                    <a class="btn btn-danger w-100" href="/integration_documents/change_status?id=<?= (int)$document['id'] ?>&is_available=0">
                                                        Set unavailable
                                                    </a>
                                                <?php } else { ?>
                                                    <a class="btn btn-primary w-100" href="/integration_documents/change_status?id=<?= (int)$document['id'] ?>&is_available=1">
                                                        Set available
                                                    </a>
                                                <?php } ?>
                                                <button class="btn btn-danger js-delete-btn w-100 mt-1" data-document-id="<?= View::toHtml($document['id']) ?>">
                                                    Delete
                                                </button>
                                            </td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } else
                        echo Html::warning('No documents found');
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="deleteDocumentModal">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete document</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger mb-0">
                    Are you sure?
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="button">No</button>
                <form action="/integration_documents/delete" method="POST">
                    <input type="hidden" name="id" id="document_id">
                    <button class="btn btn-danger">Yes</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?= View::include_js(BACKEND_JS, "integration_documents.js") ?>
