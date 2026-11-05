<?php
/**
 * @var $authUser User
 * @var $back string
 * @var $fields array
 * @var $active_fields array
 * @var $example string
 */

use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\HTTP;
use app\core\services\SKUsBulkService;
use app\core\helpers\modal\InfoModal;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">SKUs bulk edit</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-map-marker-alt"></i>
                            Upload CSV file
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center mt-2 mb-2 p-0">
                <div class="col-md-10">
                    <p class="form-control-static text-lg"><b>Guidance before uploading</b></p>
                    <p class="form-control-static">Your CSV File must include the current SKU IDs of each SKU you would like to update. After file review, you can map CSV columns to correct fields and then bulk update.</p>
                    <p class="form-control-static"><a href="/upload/demo/?csv=<?= $example; ?>"><b>Download example CSV file</b></a></p>
                </div>
            </div>
            <div class="row justify-content-center mt-2 mb-2 p-0">
                <div class="col-md-10">
                    <div class="row w-50">
                        <div class="col">
                            <input type="hidden" name="MAX_FILE_SIZE" value="10485760">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="CSV_file" value="" id="CSV_file">
                                <label class="custom-file-label" for="CSV_file" id="CSV_file_name">Choose file</label>
                            </div>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-success btn-icon-split" id="prev_data_btn" disabled>
                                <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-arrow-right"></i>
                                </span>
                                <span class="text">PREVIEW</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row justify-content-center mt-2 mb-2 p-0">
                <div id="selectFields" class="col-md-10"></div>
            </div>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-outline-orange pl-3 btn-sm" href="<?= View::toHtml(HTTP::safeLinkUrl($back ?? '/')) ?>">
                        <i class="fas fa-fw fa-chevron-left mr-2"></i>
                        <span class="ml-1">Back</span>
                    </a>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-success btn-icon-split" id="update_btn" disabled>
                                <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-save"></i>
                                </span>
                        <span class="text">BULK UPDATE</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$modal_bulk_response = new InfoModal('bulkResponse', 'Bulk response', InfoModal::SIZE_NORMAL);
echo $modal_bulk_response->addBodyAttributes(['class' => 'modal-body mb-0'])->addContent('<div id="response"></div>')->put();
?>

<script>

    const fields = <?= View::toJs($fields) ?>;

    document.addEventListener('DOMContentLoaded', () => {

        let selectedFile = null;

        const prevBtn = document.getElementById('prev_data_btn');
        const updateBtn = document.getElementById('update_btn');
        const responseBlock = document.getElementById('response');
        const selectFieldsBlock = document.getElementById('selectFields');
        const CSV_file = document.getElementById('CSV_file');

        const showModal = () => {
            <?= $modal_bulk_response->show(); ?>
        };

        selectFieldsBlock.innerHTML = null;

        const renderResponseError = message => {
            if (typeof message === 'string')
                responseBlock.innerHTML = `<div class="alert alert-danger mb-0">${html_escape(message)}</div>`;
            if (Array.isArray(message)) {
                responseBlock.innerHTML = '';
                message.forEach(e => responseBlock.insertAdjacentHTML('beforeend', `<div class="alert alert-danger mb-0">${html_escape(e)}</div>`));
            }
        }

        const renderSelect = field => {
            const sessionKey = sessionStorage.getItem(field);
            return `<div class="form-group mb-1">
                        <div class="form-group-element">
                            <select class="selectpicker form-control form-control-sm custom-select selectField">
                                    <option value="">do not map</option>
                                    ${Object.entries(fields).reduce((temp, f) => {
                                        let selected = false;
                                        if (!sessionKey) {
                                            if (field.toUpperCase() === f[0].toUpperCase()
                                                || field.toUpperCase() === f[1].toUpperCase()) {
                                                selected = true;
                                            }
                                        } else {
                                            if (sessionKey === f[0])
                                                selected = true
                                        }
                                        temp += `<option value="${f[0]}" ${!selected || 'selected'}>${f[1]}</option>`;
                                        return temp;
                                    }, '')}
                            </select></div>
                    </div>`;
        }

        const renderFieldSelector = fields => {
            if (fields.length > 0) {
                selectFieldsBlock.innerHTML = `<table class="table table-hover">
                                                    <thead><tr><th>Title</th><th>Column</th></tr></thead>
                                                    ${fields.reduce((temp, field) => {
                                                    temp += `<tr class="p-0 m-0 fieldRow">
                                                                    <td class="align-middle py-0 px-3"><b class="form-control-static">${html_escape(field)}</b></td>
                                                                    <td class="align-middle p-0">
                                                                        <div class="selectBlock align-middle p-1">
                                                                            <input type="hidden" value="${html_escape(field)}" class="hiddenField">
                                                                            ${renderSelect(field)}
                                                                        </div>
                                                                    </td>
                                                                </tr>`;
                                                    return temp;
                                                }, '')}</table>`
            }
        }

        CSV_file.addEventListener('click', ({target}) => {
            selectedFile = null
            target.value = null
        })

        CSV_file.addEventListener('change', ({target}) => {
            selectedFile = null;
            responseBlock.innerHTML = null;
            selectFieldsBlock.innerHTML = null;
            if (target.files.length > 0) {
                selectedFile = target.files[0];
                prevBtn.disabled = false;
            } else {
                prevBtn.disabled = true;
            }
            updateBtn.disabled = true;
            const getFileName = name => {
                const MAX = 50;

                if (name.length < MAX) {
                    return name
                }

                let split = name.split('.');
                if (split.length === 2) {
                    return split[0].substr(0, MAX - 4) + '...' + split[1];
                }

                return name.substr(0, MAX)
            }
            document.getElementById('CSV_file_name').innerText = selectedFile === null ? 'Choose file' : getFileName(selectedFile.name);
        });

        selectFieldsBlock.addEventListener('change', ({target}) => {
            if (target.classList.contains('selectField')) {
                sessionStorage.setItem(target.closest('.selectBlock').querySelector('.hiddenField').value || 'null', target.value)
            }
        })

        async function checkFile() {
            if (!selectedFile)
                return false;

            let check = false;
            await selectedFile.slice(0, 1)
                .arrayBuffer()
                .then(() => check = true)
                .catch(err => {
                    renderResponseError(err.message);
                    showModal();
                    prevBtn.disabled = true;
                    updateBtn.disabled = true;
                    selectedFile = null;
                    document.getElementById('CSV_file_name').innerText = 'Choose file';
                    selectFieldsBlock.innerHTML = null;
                });
            return check;
        }

        prevBtn.addEventListener('click', async () => {
            responseBlock.innerHTML = null;
            selectFieldsBlock.innerHTML = null;
            prevBtn.disabled = true;
            if (!await checkFile())
                return;
            if (selectedFile) {
                let formData = new FormData();
                formData.append('CSV_file', selectedFile);
                try {
                    $.ajax({
                        url : '/SKUs/bulk_preview',
                        type : 'POST',
                        data : formData,
                        processData: false,
                        contentType: false,
                        success : response => {
                            if (response && response !== '') {
                                const data = JSON.parse(response)
                                if (data.error !== undefined) {
                                    renderResponseError(data.error);
                                    showModal();
                                }
                                if (data.result !== undefined) {
                                    renderFieldSelector(data.result)
                                    updateBtn.disabled = false
                                }
                            } else {
                                renderResponseError('Server error');
                                showModal();
                            }
                            prevBtn.disabled = false;
                        },
                        error: () => {
                            renderResponseError('File fail. Please load file again');
                            showModal();
                        }
                    });
                } catch (e) {
                    console.log(e)
                }

            } else {
                renderResponseError('File not found');
                showModal();
            }
        });

        updateBtn.addEventListener('click', async () => {
            responseBlock.innerHTML = null;

            let selectedFields = [];
            const nodes = selectFieldsBlock.getElementsByClassName('fieldRow');
            if (nodes.length > 0) {
                Array.prototype.forEach.call (nodes, function (node) {
                    const hiddenField = node.querySelector('.hiddenField');
                    const field_value = node.querySelector('.selectField').value || '';
                    if (hiddenField && field_value && field_value !== '') {
                        selectedFields.push({
                            csv: hiddenField.value,
                            field: field_value
                        })
                    }
                } );
            } else {
                updateBtn.disabled = true;
                prevBtn.disabled = false;
            }
            let errors = [];
            let hasID = selectedFields.find(f => f.field === '<?= SKUsBulkService::FIELD_ID ?>') !== undefined;
            if (!hasID)
                errors.push('Id is required')
            if (selectedFields.length === 1) {
                prevBtn.disabled = false;
                errors.push('Must be minimum one field except ID')
            } else {
                Object.keys(fields).forEach(f => {
                    if (selectedFields.filter(sf => sf.field === f).length > 1)
                        errors.push(`Field ${fields[f]} repeated`)
                });
            }

            if (errors.length > 0) {
                renderResponseError(errors);
                showModal();
            } else {
                if (!await checkFile())
                    return;
                updateBtn.disabled = true;
                let formData = new FormData();
                formData.append('CSV_file', selectedFile);
                selectedFields.forEach(sf => formData.append(sf.field, sf.csv))
                try {
                    $.ajax({
                        url : '/SKUs/bulk_store',
                        type : 'POST',
                        data : formData,
                        processData: false,
                        contentType: false,
                        success : response => {
                            updateBtn.disabled = false;
                            prevBtn.disabled = false;
                            const data = JSON.parse(response)
                            if (data.error !== undefined) {
                                renderResponseError(data.error)
                                showModal()
                            }
                            if (data.result !== undefined) {
                                if (data.result === null)
                                    return location.reload()
                                renderUpdateResponse(data.result)
                                showModal()
                            }
                        },
                        error: () => {
                            renderResponseError('File fail. Please load file again');
                            showModal()
                        }
                    });

                } catch (e) {
                    console.log(e)
                }
            }
        });

        const renderUpdateResponse = data => {
            if (data.length === 0)
                renderResponseError('Not data')
            data.forEach(item => {
                if (item.errors !== undefined && item.errors.length > 0) {
                    item.errors.forEach(e => responseBlock.insertAdjacentHTML('beforeend', `<div class="alert alert-danger mb-0">${html_escape(e)}</div>`))
                }
                if (item.result !== undefined && item.result.length > 0) {
                    item.result.forEach(i => responseBlock.insertAdjacentHTML('beforeend', `<div class="alert alert-success mb-0">${html_escape(i)}</div>`))
                }
            })
        }

    })

</script>