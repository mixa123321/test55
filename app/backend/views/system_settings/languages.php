<?php
/**
 * @var $records array
 * @var $tags array
 * @var $langs array
 */

use app\core\helpers\Html;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;

$show_tags = function ($tags) {
    $res = [];
    foreach ($tags as $tag)
        $res[] = "<span class='m-1 p-1 px-3 bg-success text-white'>" . View::toHtml($tag) . "</span>";
    return implode($res);
};

$show_translations = function ($translations) {
    $res = [];
    foreach ($translations as $code => $translation)
        $res[] = '<span class="m-1 p-1 px-3 bg-info text-white">' . View::toHtml(strtoupper($code)) . '</span> ' . View::toHtml($translation);
    return implode('<br/>', $res);
};

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Languages</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-file-word"></i>
                        Tokens List (<?= count($records); ?> item<?= count($records) > 1 ? 's' : '' ?>)
                    </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-icon-split" onclick="setLangForm()" type="button" disabled>
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-globe"></i>
                        </span>
                        <span class="text">Languages</span>
                    </button>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-icon-split" onclick="setTagForm()" type="button" disabled>
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-tags"></i>
                        </span>
                        <span class="text">Tags</span>
                    </button>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split" onclick="setTokenForm()" type="button">
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-plus"></i>
                        </span>
                        <span class="text">Token</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="form-row">
                    <div class="form-group col-3">
                        <div class="form-group-element">
                            <input type="text" name="token" id="token" class="form-control form-control-lg" placeholder=" "
                                   value="<?= View::toHtml($_GET['token'] ?? ''); ?>">
                            <label for="token">Token</label>
                        </div>
                    </div>
                </div>
                <div class="text-right mb-4">
                    <a href="/advanced/languages" class="btn btn-lg btn-outline-orange">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange">Filter</button>
                </div>
            </form>
            <?php if (!empty($records)) { ?>
                <table class="table table-hover mb-0">
                    <colgroup>
                        <col width="35%">
                        <col width="20%">
                        <col width="35%">
                        <col width="10%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Token</th>
                            <th>Tags</th>
                            <th>Translations</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $token_id => $record) { ?>
                            <tr>
                                <td><?= View::toHtml($record['token']); ?></td>
                                <td><?= $show_tags($record['tags']); ?></td>
                                <td><?= $show_translations($record['translations']); ?></td>
                                <td>
                                    <button class="btn btn-success btn-icon-split w-100" type="button" onclick="setTokenForm(<?= $token_id; ?>)" disabled>
                                        <span class="icon text-white w-100 text-left">
                                            <i class="fas fa-fw fa-edit"></i> Edit
                                        </span>
                                    </button>
                                    <button class="btn btn-success btn-icon-split w-100 mt-1" type="button" onclick="setTokenForm(<?= $token_id; ?>)" disabled>
                                        <span class="icon text-white w-100 text-left">
                                            <i class="fas fa-fw fa-plus"></i> Tag
                                        </span>
                                    </button>
                                    <button class="btn btn-success btn-icon-split w-100 mt-1" type="button" onclick="setTokenForm(<?= $token_id; ?>)" disabled>
                                        <span class="icon text-white w-100 text-left">
                                            <i class="fas fa-fw fa-plus"></i> Translation
                                        </span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else echo Html::warning("No records found"); ?>
        </div>
    </div>
</div>
<?php
    $options = [
        'class' => 'form-control form-control-lg custom-select'
    ];
    $modal_tokens = new SwitchModal('editTokens', '<span id="action_name"></span> token');
    echo $modal_tokens
            ->addContent('
                <input type="hidden" name="id" id="token_id">
                <div class="alert alert-danger d-none" id="response"></div>
                <div class="form-group mt-0">
                    <div class="form-group-element">
                        <input type="text" name="token" id="token_name" class="form-control form-control-lg" value="">
                        <label for="token_name">Token name <span style="color:red">*</span></label>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <div class="form-group-element">' .
                                Html::dropDownList('tag_id', $tags, null, $options + ['id' => 'token_tag_id']) . '
                                <label for="token_tag_id">Tags</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <div class="form-group-element">' .
                                Html::dropDownList('lang_id', $langs, null, $options + ['id' => 'token_lang_id']) . '
                                <label for="token_lang_id">Languages</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group my-0">
                    <div class="form-group-element">
                        <input type="text" name="translation" id="token_translation" class="form-control form-control-lg" value="">
                        <label for="token_translation">Translation <span style="color:red">*</span></label>
                    </div>
                </div>')
            ->addForm(SwitchModal::ACTION_DEFAULT, SwitchModal::METHOD_DEFAULT, ['id' => 'sendTokenForm'])
            ->addButton('Save', SwitchModal::TYPE_DEFAULT, SwitchModal::BUTTON_DONE)
            ->put();
?>
<script>
    const tokens = <?= View::toJs($records); ?>;

    $('#sendTokenForm').submit(e => {
        $.ajax({
            url: "/advanced/create_token",
            type : 'POST',
            data : $('#sendTokenForm').serialize(),
            dataType: 'json',
            success: function (res) {
                if (res.error !== undefined)
                    showError('response', res.error, 3000);
                else
                    window.location = '/advanced/languages';
            },
            error: function (res) {
                showError('response', 'Connection error', 3000);
            }
        });
        return false;
    });

    function setTokenForm(token_id = null)
    {
        document.getElementById('action_name').textContent = token_id ? 'Edit' : 'Create';
        const token = token_id ? tokens[token_id] : {token: '', tags: null, translations: null};
        document.getElementById('token_id').value = token_id;
        document.getElementById('token_name').value = token.token;
        //////// edit !!!
        // document.getElementById('token_tag_id').value = token.tags;
        // document.getElementById('token_lang_id').value = token.tags;
        document.getElementById('token_translation').value = token.translations;
        <?= $modal_tokens->show(); ?>
    }
</script>
