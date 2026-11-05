<?php

use app\core\helpers\View;

/**
 * @var string $warehouse_label
 * @var array $types
 * @var array $sizes
 * @var string $back
 * @var array $loc
 * @var bool $floors
 * @var bool $change_size
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Location</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-map-marker-alt"></i> <?= empty($loc['id']) ? 'New' : 'Edit' ?> location
            </h6>
        </div>
        <form method="post" action="/locations/set_location<?= isset($change_size) ? '?change_size=' . (int)$change_size : ''?>">
            <input type="hidden" name="location[id]" value="<?= (int)($loc['id'] ?? 0) ?>">
            <input type="hidden" name="location[state]" value="1">
            <input type="hidden" name="back" value="<?= View::toHtml(str_replace('&', '^', $back)); ?>">
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <p class="form-control-static ml-1 mb-0">Allow only printable characters</p>
                    </div>
                    <div class="col-md-10">
                        <div class="form-row">
                            <div class="col-sm-4 col-lg-2">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input type="text" name="location[warehouse]" id="warehouse" class="form-control form-control-lg" readonly value="<?= View::toHtml($warehouse_label); ?>">
                                        <label for="warehouse">Warehouse</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-8 col-lg-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <?php if (!empty($loc['id'])) { ?>
                                            <input type="hidden" id="type_id" name="location[type_id]" value="<?= (int)$loc['type_id'] ?>">
                                        <?php } ?>
                                        <select id="type_id" name="location[type_id]" class="form-control form-control-lg custom-select" <?= !empty($loc['id']) ? 'disabled' : '' ?>>
                                            <?php foreach ($types as $id => $type) { ?>
                                                <option value="<?= View::toHtml($id) ?>"<?= (!empty($loc['type_id']) && $loc['type_id'] == $id) ? ' selected' : ''; ?>><?= View::toHtml($type) ?></option>
                                            <?php } ?>
                                        </select>
                                        <label for="type_id">Location type</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 col-lg-3">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input type="text" name="location[zone]" id="zone" class="form-control form-control-lg" placeholder=" " value="<?= View::toHtml($loc['zone'] ?? ''); ?>">
                                        <label for="zone">Zone <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 col-lg-2">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input type="text" name="location[column]" id="column" class="form-control form-control-lg" placeholder=" " value="<?= View::toHtml($loc['column'] ?? ''); ?>">
                                        <label for="column">Column <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4 col-lg-2">
                                <div class="form-group">
                                    <div class="form-group-element">
                                        <input type="text" name="location[row]" id="row" class="form-control form-control-lg" placeholder=" " value="<?= View::toHtml($loc['row'] ?? ''); ?>">
                                        <label for="row">Row <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-8 col-lg-5">
                                <div class="form-group last-div">
                                    <div class="form-group-element">
                                        <select id="size_id" name="location[size_id]" class="form-control form-control-lg custom-select" <?= !$change_size ? " disabled" : " " ?>>
                                            <?php foreach ($sizes as $id => $size) { ?>
                                                <option value="<?= View::toHtml($id) ?>"<?= (!empty($loc['size_id']) && $loc['size_id'] == $id) ? ' selected' : ''; ?>><?= View::toHtml($size[0]) ?></option>
                                            <?php } ?>
                                        </select>
                                        <label for="size_id">Location size</label>
                                        <?php if (isset($change_size) && !$change_size) { ?>
                                            <input type="hidden" name="location[size_id]" value="<?= (int)$loc['size_id'] ?>">
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-8 col-lg-3">
                                <div class="form-group last-div">
                                    <div class="form-group-element">
                                        <input type="text" name="location[alias]" id="alias" class="form-control form-control-lg" placeholder=" " value="<?= View::toHtml($loc['alias'] ?? ''); ?>">
                                        <label for="row">Alias</label>
                                    </div>
                                </div>
                            </div>
                            <?php if ($floors) { ?>
                                <div class="col-sm-4 col-lg-2">
                                    <div class="form-group">
                                        <label for="mezzanine">Mezzanine:</label>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input position-static" id="mezzanine" name="location[mezzanine]"<?= !empty($loc['mezzanine']) ? ' checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                        <a class="btn btn-outline-orange pl-3 btn-sm" href="/locations?<?= View::toHtml($back); ?>">
                            <i class="fas fa-fw fa-chevron-left mr-2"></i>
                            <span class="ml-1">Back</span>
                        </a>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" type="submit">
                            <span class="text text-white">Done</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    $(function() {
        var $typeSelect = $('#type_id');
        $typeSelect.change(function(e) {
            $('[data-toggle-for-types]').each(function() {
                var $this = $(this),
                    types = $this.data('toggle-for-types');
                if (!Array.isArray(types))
                    try {
                        types = JSON.parse(types);
                    } catch (e) {
                        types = [$this.data('toggle-for-types')];
                    }
                if (!Array.isArray(types))
                    types = [types];
                $this.toggle(types.indexOf($typeSelect.val()) !== -1);
            });
        });
        $typeSelect.trigger('change');
    });
</script>