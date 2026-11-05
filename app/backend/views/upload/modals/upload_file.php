<?php

use app\core\helpers\Html;
use app\core\helpers\modal\Modal;
use app\core\helpers\View;

/**
 * @var string $title
 * @var ?string $example
 * @var ?string $note
 * @var string $handler
 * @var Modal $modal
 * @var ?array $suppliers
 * @var ?int $selected_supplier_id
 * @var bool $require_supplier
 * @var string $closeTitle
 * @var string $uploadTitle
 * @var string $type
 * @var string|array $mime
 * @var float $maxSize
 * @var bool $multiple
 * @var string $formSelector
 * @var bool $autoupload
 */

$closeTitle = $closeTitle ?? 'Close';
$uploadTitle = $uploadTitle ?? 'Upload';
$type = $type ?? 'file';
$mime = $mime ?? '*/*';
if (is_array($mime))
    $mime = implode(',', $mime);
$maxSize = $maxSize ?? 2;
$multiple = $multiple ?? false;
$autoupload = $autoupload ?? false;
$action = '/upload/' . ($multiple ? 'files_ajax' : 'file_ajax');
$formSelector = !empty($formSelector) ? '"' . $formSelector . '"' : $modal->jsGetBodyAttribute('form-selector');

$modal->addCustomButtons(
    Html::button($closeTitle, Html::TYPE_BUTTON__CANCEL, ['data-dismiss' => 'modal'])
    . Html::button($uploadTitle, Html::TYPE_BUTTON__PRIMARY, ['disabled' => true, 'type' => 'submit'])
);

?>
<?php if (!empty($title)): ?>
    <h6 class="mb-3 font-weight-bold"><?= $title ?></h6>
<?php endif; ?>
<div class="js-error bg-danger text-white p-3 d-none mb-1">
    Error uploading file
</div>
<div class="js-success bg-success text-white p-3 d-none mb-1">
    File uploaded successfully
</div>
<div class="js-warning bg-warning text-white p-3 d-none mb-1">
    File uploaded with warnings
</div>
<div class="js-loader text-center">
    <div class="spinner-border text-primary" role="status">
        <span class="sr-only">Loading...</span>
    </div>
</div>
<div class="row justify-content-center">
    <div class="col">
        <?php if (!empty($example)): ?>
        <div class="card bg-light mb-3">
            <div class="card-body p-3 font-weight-normal">
                <h7 class="card-title"><b>Guidance before uploading</b></h7>
                <p class="card-text">You must use the template below, and remember to leave the required column headers in your CSV file (as in the first line of the example file below), but remove the second and third lines of the file as these lines are purely for demonstration purposes only.</p>
                <a class="link" target="_blank" href="/upload/demo/?csv=<?= $example; ?>"><b>Download example CSV file</b></a>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($note)): ?>
            <div class="card bg-light mt-3">
                <div class="card-body p-3">
                    <h7 class="card-title"><b>Note</b></h7>
                    <p class="card-text"><?= $note; ?></p>
                </div>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= $action; ?>" enctype="multipart/form-data">
            <input type="hidden" name="handler" value="<?= $handler; ?>" />
            <?php if(!empty($suppliers)): ?>
                <div class="row">
                    <div class="form-group col">
                        <div class="form-group-element">
                            <select id="supplier_id" name="supplier_id" class="form-control form-control-lg custom-select"<?= ($require_supplier ?? false) ? ' required' : ''; ?>>
                                <?php if (empty($selected_supplier_id)): ?>
                                    <option value="">Select company</option>
                                <?php endif; ?>
                                <?php foreach ($suppliers as $row) { ?>
                                    <option value="<?= $row['id'] ?>"<?= !empty($selected_supplier_id) && $row['id'] === $selected_supplier_id ? " selected" : "" ?>><?= View::toHtml($row['name']) ?></option>
                                <?php } ?>
                            </select>
                            <label for="supplier_id">Select company</label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <p class="form-control-static mt-2 mb-1"><b>Select <?= $type; ?> to upload</b></p>
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= $maxSize * 1024 * 1024 ?>" />
            <div class="row">
                <div class="col">
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" name="upload_file<?= $multiple ? '[]' : ''; ?>" id="upload_file"
                            accept="<?= $mime; ?>" <?= $multiple ? 'multiple' : ''; ?>
                        />
                        <label class="custom-file-label" for="upload_file" id="name_file">Choose file</label>
                    </div>
                    <?php if ($multiple): ?>
                        <small class="form-text text-muted">You can upload multiple files at once</small>
                        <ul class="js-file-list mt-2 pl-0">
                            <script type="text/template" id="file-list-template">
                                <li class="list-group-item d-flex justify-content-between flex-row p-1">
                                    %name%
                                    <button type="button" class="btn btn-danger btn-icon-split js-btn-remove">
                                        <span class="icon text-white-50">
                                            <i class="fas fa-trash"></i>
                                        </span>
                                        <span class="text">Remove</span>
                                    </button>
                                </li>
                            </script>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    $(function() {
        const $modal = $('<?= $modal->getSelector(); ?>'),
            $form = $modal.find('form'),
            $btnUpload = $modal.find('button[type="submit"]'),
            $fileInput = $modal.find('input[type="file"]'),
            $name = $modal.find('#name_file'),
            $messages = {
                danger: $modal.find('.js-error'),
                success: $modal.find('.js-success'),
                warning: $modal.find('.js-warning'),
            },
            $loader = $modal.find('.js-loader'),
            $fileList = $modal.find('.js-file-list'),
            fileBuffer = new DataTransfer();
        let maxSize = <?= $maxSize ?>;

        $modal.on('show.bs.modal', function() {
            $loader.addClass('d-none');
            for (let key in $messages)
                $messages[key].addClass('d-none');
            $fileInput.val('');
            $btnUpload.prop('disabled', true);
            $name.html('Choose file');
            if ($fileList.length) {
                fileBuffer.items.clear();
                renderFileList();
            }
        });
        
        $fileInput.change(function() {
            // multiple
            if ($fileList.length) {
                for (let i = 0; i < this.files.length; i++)
                    fileBuffer.items.add(this.files[i]);
                renderFileList();
                <?php if ($autoupload): ?>
                if (fileBuffer.files.length > 0)
                    $btnUpload.click();
                <?php endif; ?>
                return;
            }
            var filename = this.files[0].name;
            $btnUpload.prop('disabled', filename === '');
            $name.text(filename === '' ? 'Choose file' : filename);
            <?php if ($autoupload): ?>
            if (filename !== '')
                $btnUpload.click();
            <?php endif; ?>
        });
        $fileInput.on('cancel', function() {
            $modal.trigger('upload.cancel');
        });
        $btnUpload.click(function(e) {
            e.preventDefault();
            $loader.removeClass('d-none');
            for (let key in $messages)
                $messages[key].addClass('d-none').html('');
            if ($fileList.length)
                $fileInput[0].files = fileBuffer.files;
            var formData = new FormData($form.get(0));
            <?php if ($require_supplier): ?>
            if (formData.get('supplier_id') === '') {
                $loader.addClass('d-none');
                $messages.danger.removeClass('d-none').html('Please select a company');
                return;
            }
            <?php endif; ?>
            var data = <?= $modal->jsGetBodyAttribute('form'); ?>;
            if (typeof data != 'undefined')
                if (typeof data === 'object')
                    for (var key in data) {
                        var formKey = 'form' + key.replace(/^([^[]+)/, '[$1]');
                        formData.append(formKey, data[key]);
                    }
                else
                    formData.append('form', data);
            // get form from selector
            else {
                var formSelector = <?= $formSelector; ?>;
                if (formSelector) {
                    var secondForm = new FormData($(formSelector).get(0));
                    for (var pair of secondForm.entries()) {
                        var key = 'form' + pair[0].replace(/^([^[]+)/, '[$1]');
                        formData.append(key, pair[1]);
                    }
                }
            }
            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: formData,
                cache: false,
                contentType: false,
                processData: false,
                success: function(data, status, xhr) {
                    $loader.addClass('d-none');
                    $fileInput.val('');
                    $btnUpload.prop('disabled', true);
                    $name.html('Choose file');
                    $fileList.find('li').remove();
                    fileBuffer.items.clear();
                    let showMessage = true;
                    var message = data.message
                        || (xhr.statusText == 'OK' ? null : xhr.statusText)
                        || 'Unknown error';
                    if (data.result)
                        for (let key in $messages)
                            if (data.result[key] && data.result[key] != '') {
                                var m = typeof data.result[key] === 'string'
                                    ? html_escape(data.result[key])
                                    : Object.values(data.result[key]).map(html_escape).join('<br/>');
                                $messages[key].removeClass('d-none').html(m);
                                showMessage = false;
                            }
                    if (data.status != 'success') {
                        if (showMessage) {
                            $messages.danger.removeClass('d-none')
                                .html(html_escape(message) + '<br/>' + $messages.danger.html());
                            $modal.scrollTop(0);
                        }
                        $modal.trigger('upload.error', message);
                        return;
                    }
                    if (showMessage) {
                        $messages.success.removeClass('d-none')
                            .html(html_escape(message));
                        $modal.scrollTop(0);
                    }
                    $modal.trigger('upload.success', data);
                    if (data.result && data.result.danger && data.result.danger.length > 0)
                        $modal.trigger('upload.error', data.result.danger);
                },
                error: function(ajax, status, error) {
                    if (ajax.status === 413) {
                        if ($fileList.length) {
                            let size = 0;
                            for (let i = 0; i < fileBuffer.files.length; i++)
                                size += fileBuffer.files[i].size;
                            size = Math.round(size / 1024 / 1024 * 100) / 100;
                            error = 'Total files size is ' + size + 'MB, which exceeds the maximum allowed size of ' + maxSize + 'MB';
                        } else {
                            const fileSize = Math.round($fileInput.get(0).files[0].size / 1024 / 1024 * 100) / 100;
                            error = 'File size is ' + fileSize + 'MB, which exceeds the maximum allowed size of ' + maxSize + 'MB';
                        }
                    }
                    $loader.addClass('d-none');
                    $fileInput.val('');
                    $btnUpload.prop('disabled', true);
                    $name.html('Choose file');
                    if ($fileList.length) {
                        fileBuffer.items.clear();
                        renderFileList();
                    }
                    $messages.danger.removeClass('d-none').text(error);
                    $modal.trigger('upload.error', error);
                }
            });
        });

        // remove file from file list
        $fileList.on('click', 'li .js-btn-remove', function() {
            fileBuffer.items.remove($(this).closest('li').index('.js-file-list li'));
            $(this).parent().remove();
            renderFileList();
        });

        function renderFileList() {
            $fileList.find('li').remove();
            $name.html(fileBuffer.files.length === 0
                ? 'Choose file'
                : fileBuffer.files.length + ' files selected'
            );
            const template = $('#file-list-template').html();
            for (let i = 0; i < fileBuffer.files.length; i++) {
                $fileList.append(template.replace(/%name%/g, () => html_escape(fileBuffer.files[i].name)));
            }
            $btnUpload.prop('disabled', fileBuffer.files.length === 0);
        }
    });
</script>