<?php
/**
 * @var $section_ws array
 * @var $section_ts array
 * @var $section_ls array
 * @var $pickers array
 * @var $packers array
 */

use app\core\entities\SectionBinding;
use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Section binding</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-th"></i>
                        Sections:
<!--                        --><?//= count($section_ws); ?><!-- of Order Bin, -->
                        <?= count($section_ts); ?> of Pack Station, <?= count($section_ls); ?> of Large Pack
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-1">
                    <ul class="nav nav-pills nav-stacked">
                    </ul>
                </div>
                <div class="col-lg-5 col">
                    <h4>Assignment Pack Station</h4>
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>Pack Station</th>
                            <th>Workers</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($section_ts as $key => $sw) {
                            $workers = array_map(function($el) {
                                return View::toHtml("{$el['login']} ({$el['name']})");
                            }, $sw[SectionBinding::TABLES]);
                            ?>
                            <tr>
                                <td><?= View::toHtml($key); ?></td>
                                <td><?= implode('<br/>', $workers); ?></td>
                                <td>
                                    <button class="btn btn-success btn-icon-split js-tables" type="button" id="sectionT_<?= $key; ?>"
                                            data-id="<?= $sw['id']; ?>"
                                            data-num="<?= $key; ?>"
                                            data-T='<?= View::toHtml(json_encode($workers)) ?>'
                                    >
                                            <span class="icon text-white">
                                                <i class="fas fa-fw fa-edit"></i>
                                            </span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="col-lg-5 col">
                    <h4>Assignment Large Pack</h4>
                    <table class="table table-hover">
                        <thead>
                        <tr>
                            <th>Pack Station</th>
                            <th>Workers</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($section_ls as $key => $sw) {
                            $workers = array_map(function($el) {
                                return View::toHtml("{$el['login']} ({$el['name']})");
                            }, $sw[SectionBinding::LARGES]);
                            ?>
                            <tr>
                                <td><?= View::toHtml($key); ?></td>
                                <td><?= implode('<br/>', $workers); ?></td>
                                <td>
                                    <button class="btn btn-success btn-icon-split js-larges" type="button" id="sectionL_<?= $key; ?>"
                                            data-id="<?= $sw['id']; ?>"
                                            data-num="<?= $key; ?>"
                                            data-L='<?= View::toHtml(json_encode($workers)) ?>'
                                    >
                                            <span class="icon text-white">
                                                <i class="fas fa-fw fa-edit"></i>
                                            </span>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal" tabindex="-1" role="dialog" id="editWorkers">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="title"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="/locations/save_binding">
                <input type="hidden" id="section_type" name="type"/>
                <input type="hidden" id="section_num" name="num"/>
                <div class="modal-body">
                    <div class="row mt-0 mb-1">
                        <div class="col font-weight-bold">Workers (<span id="type_id"></span>)</div>
                    </div>
                    <div class="row">
                        <div class="col" id="workers">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-orange btn-sm" data-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-success btn-icon-split js-btn-conf">
                        <span class="text">Save</span>
                        <span class="icon text-white">
                            <i class="fas fa-fw fa-save pt-1"></i>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script type="text/template" id="worker_template">
    <div class="row">
        <div class="col"><p class="form-control-static m-0">{{name}}</p></div>
        <div class="col-auto">
            <input type="checkbox" class="form-check-input" id="worker[{{id}}]" name="worker[{{id}}]"{{checked}}>
        </div>
    </div>
</script>
<script>
    //var workers_OB = <?//= json_encode($pickers); ?>//;
    var workers_TP = <?= View::toJs($packers) ?>;
</script>
<?= View::include_js(BACKEND_JS, "binding.js")?>