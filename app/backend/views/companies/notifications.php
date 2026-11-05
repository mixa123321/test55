<?php
/**
 * @var $authUser User
 * @var $supplier Supplier
 * @var $types array
 * @var $supplier_emails array
 * @var $is_account bool
 */

use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\helpers\View;

$exist_emails = !empty($supplier_emails);

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Notifications</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-envelope-open-text"></i>
                    </span>
                        <?= View::toHtml($supplier->getName()) ?>
                    </h6>
                </div>
                <div class="col-auto">
                    <?php if ($exist_emails)  { ?>
                    <button class="btn btn-orange btn-icon-split" onclick="showSupplierEmails()">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-at"></i>
                        </span>
                        <span class="text">Show Emails</span>
                    </button>
                    <?php } ?>
                    <button class="btn btn-success btn-icon-split" onclick="showEmailForm()">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Add New Email</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form id="activeTypes">
                <input type="hidden" name="supplier_id" value="<?= $supplier->getId() ?>">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>Type</th>
                            <th>Active</th>
                            <th>Emails</th>
                        </tr>
                    </thead>
                    <tbody id="bodyTypes">
                        <?php foreach ($types as $type) { ?>
                            <tr>
                                <td><?= $type['id'] ?></td>
                                <td><?= View::toHtml($type['name']) ?></td>
                                <td><input
                                            type="checkbox"
                                        <?= $type['is_active'] ? 'checked' : '' ?>
                                            name="types[<?= $type['id'] ?>]"
                                    ></td>
                                <td>
                                    <button class="btn btn-primary btn-icon-split"
                                            type="button" onclick="showSupplierEmails(<?= $type['id'] ?>, '<?= rawurlencode($type['name']); ?>')"
                                            <?= $exist_emails ? '' : 'disabled' ?>
                                    >
                                        <span class="icon text-white">
                                            <i class="fas fa-fw fa-inbox"></i>
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </form>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= $is_account ? '/account' : ("/suppliers/settings/?id=" . $supplier->getId()) ?> ">
                        <i class="fas fa-fw fa-chevron-left mr-2"></i>
                        <span class="ml-1">Back</span>
                    </a>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split"
                            id="storeActiveTypeList"
                            type="button"
                            onclick="submitActiveList()"
                            disabled
                    >
                        <span class="text text-white">Done</span>
                        <span class="icon text-white">
                                    <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="addNewEmailModal" tabindex="-1" role="dialog" aria-labelledby="mailModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add new email</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="newEmailForm">
            <div class="modal-body">
                <div id="email_form_response" class="mb-2"></div>
                <div class="form-group">
                    <div class="form-group-element">
                        <input type="hidden" name="supplier_id" value="<?= $supplier->getId() ?>">
                        <input type="email" name="name" class="form-control form-control-lg" value="">
                        <label for="email">Email: <span style="color:red">*</span></label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" data-dismiss="modal">close</button>
                <button type="submit" class="btn btn-orange" id="storeNewMail">SAVE</button>
            </div>
            </form>
        </div>
    </div>
</div>
<style>
    .modal.fade.removeEmail {
        z-index: 10000000 !important;
    }
    .tooltip-inner {
        background-color: #ffffff !important;
        color: #ffffff ;
    }
    .bs-tooltip-top .arrow::before, .bs-tooltip-auto[x-placement^="top"] .arrow::before {
        border-top-color: #626060 !important;
    }

    .bs-tooltip-right .arrow::before, .bs-tooltip-auto[x-placement^="right"] .arrow::before {
        border-right-color: #626060 !important;
    }


    .bs-tooltip-bottom .arrow::before, .bs-tooltip-auto[x-placement^="bottom"] .arrow::before {
        border-bottom-color: #626060 !important;
    }


    .bs-tooltip-left .arrow::before, .bs-tooltip-auto[x-placement^="left"] .arrow::before {
        border-left-color: #626060 !important;
    }
</style>
<div class="modal fade removeEmail m-1" id="removeEmailModal" tabindex="-1" role="dialog" aria-labelledby="removeEmailModal" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Remove <span id="remove_email"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete Email?
            </div>
            <div class="modal-footer">
                <form action="/account/remove_email" method="POST">
                    <input type="hidden" name="supplier_email_id" id="remove_supplier_email_id" value="0">
                    <input type="hidden" name="supplier_id" value="<?= $supplier->getId() ?>">
                    <input type="hidden" name="account" value="<?= $is_account ?>">
                    <button type="button" class="btn btn-outline-orange" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Remove</button>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="supplierEmailModal" tabindex="-1" role="dialog" aria-labelledby="supplierEmailModal" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Supplier emails <span id="email_title"></span></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="activeEmailForm" action="/account/store_active_list" method="POST">
                <input type="hidden" name="supplier_id" value="<?= $supplier->getId() ?>">
                <input type="hidden" name="type_id" value="0">
                <input type="hidden" name="account" value="<?= $is_account ?>">
                <div class="modal-body">
                    <div id="emails_response" class="mb-2"></div>
                    <?php if (!empty($supplier_emails))  { ?>
                    <table class="table table-hover" id="tableSupplierEmails">
                        <thead>
                        <tr>
                            <th>Email</th>
                            <th>Active</th>
                            <th class="action-remove">Remove</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($supplier_emails as $supplier_email) { ?>
                            <tr id="supplier_email_<?= $supplier_email['id'] ?>" class="<?= !$supplier_email['is_active'] ? 'table-secondary' : '' ?> ">
                                <td><label for="supplier_email_input_<?= $supplier_email['id'] ?>"><?= View::toHtml($supplier_email['name']) ?></label></td>
                                <td><input type="checkbox"
                                           id="supplier_email_input_<?= $supplier_email['id'] ?>"
                                           name="supplier_emails[<?= $supplier_email['id'] ?>]"
                                    >
                                </td>
                                <td class="action-remove">
                                    <button class="btn btn-danger btn-icon-split"
                                            type="button"
                                            onclick="confirmRemoveEmail(<?= (int)$supplier_email['id'] ?>, <?= View::toHtml(json_encode((string)$supplier_email['name'])) ?>, <?= !empty($supplier_email['used']) ? 1 : 0 ?>)"
                                        <?php if (!empty($supplier_email['used'])) { ?>
                                            onmouseover="dangerRemoveTooltip(this)"
                                        <?php } ?>
                                    >
                                        <span class="icon text-white">
                                            <i class="fas fa-fw fa-trash-alt"></i>
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <?php } else { ?>
                        <p class="alert alert-danger">Emails not found </p>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-orange" id="storeActiveEmails">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

    const emailFormResponse = $('#email_form_response');
    const emailsResponse = $('#emails_response');

    const supplier_id = parseInt('<?= $supplier->getId() ?>');

    let is_changed = false;
    const storeActiveTypeList = document.getElementById('storeActiveTypeList');
    document.getElementById('bodyTypes')
        .addEventListener('change', () => {
            is_changed = true;
            storeActiveTypeList.disabled = false
        });

    function submitActiveList() {
        $.ajax({
            type: "POST",
            url: '/account/change_active_types',
            data: $('#activeTypes').serialize(),
            success: function () {
                is_changed = false
                storeActiveTypeList.disabled = true
            }
        });
    }

    const newEmailForm = document.getElementById('newEmailForm');

    function showEmailForm(){
        if (is_changed)
            submitActiveList();
        emailFormResponse.html(null);
        newEmailForm.reset();
        $('#addNewEmailModal').modal('show')
    }

    newEmailForm.addEventListener('submit', e => {
        e.preventDefault();
        emailFormResponse.html(null);
        $.post('/account/add_email', $(e.target).serialize(), res => {
            if (res.result !== undefined) {
                if (res.result === 'error' && res.errors.length > 0) {
                    emailFormResponse.html(res.errors.reduce((temp, error) => {
                        temp += `<li class="alert-danger p-2">${error}</li>`;
                        return temp;
                    }, ''));
                } else window.location.reload(false);
            }
        }, "json");
    });

    const activeEmailForm = document.getElementById('activeEmailForm');
    const storeActiveEmails = document.getElementById('storeActiveEmails');
    const tableSupplierEmails = document.getElementById('tableSupplierEmails');
    if (tableSupplierEmails)
        tableSupplierEmails.addEventListener('change', () => storeActiveEmails.disabled = false);

    function showSupplierEmails(type_id = null, type_name = null)
    {
        if (is_changed)
            submitActiveList();
        emailsResponse.html(null);
        storeActiveEmails.disabled = true;
        const by_type = !!type_id;
        $('.action-remove').toggleClass('d-none', by_type);
        activeEmailForm.querySelector('input[name=type_id]').value = type_id;
        $('#email_title').html(by_type ? ('by type: <br><b>' + html_escape(decodeURIComponent(type_name)) + '</b>') : '<b>list</b>');
        $.post('/account/get_supplier_emails', {
            type_id, supplier_id
        }, res => {
            if (res.result === undefined || res.result === 'error')
                alert('Server error');
            if (res.result === 'success') {
                Object.entries(res.emails).forEach(m => {
                    let checkbox = activeEmailForm.querySelector('#supplier_email_' + +m[0] + ' input');
                    if (checkbox)
                        checkbox.checked = m[1] !== '0' || !m[1];
                });
                $('#supplierEmailModal').modal('show');
            }
        }, "json");
    }

    const spanUsed = '<span class="text-danger"> used <i class="fa fa-exclamation-triangle"></i></span>';

    function confirmRemoveEmail(email_id, name, used = false) {
        document.getElementById('remove_email').innerHTML = html_escape(name) + (used ? ` (${spanUsed})` : '');
        document.getElementById('remove_supplier_email_id').value = email_id;
        $('#removeEmailModal').modal('show');
    }

    function dangerRemoveTooltip(el) {
        $(el).tooltip({
            html: true,
            title: spanUsed,
        })
    }


</script>
