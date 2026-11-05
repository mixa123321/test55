<?php

use app\core\entities\User;
use app\core\helpers\View;

/**
 * @var User $authUser
 */

?>
<div class="row justify-content-end">
    <div class="form-group col-sm-4 mb-0">
        <label for="shop_url">Shop URL: <span style="color:red">*</span></label>
        <div class="input-group" id="shop_url">
            <div class="input-group-prepend">
                <span class="input-group-text">https://</span>
            </div>
            <input type="text" name="shop_url" id="shop_url" class="form-control" required>
        </div>
    </div>
    <div class="form-group col-sm-<?= $col_sm; ?> mb-0">
        <label for="consumer_key">Consumer key: <span style="color:red">*</span></label>
        <input type="text" name="consumer_key" id="consumer_key" class="form-control" required>
    </div>
    <div class="form-group col-sm-<?= $col_sm; ?> mb-0">
        <label for="consumer_secret">Consumer secret: <span style="color:red">*</span></label>
        <input type="password" name="consumer_secret" id="consumer_secret" class="form-control" required>
    </div>
    <?php if ($authUser->isRoleSA()) { ?>
        <div class="form-group col-sm-2 mb-0">
            <label for="user_id">User:</label>
            <select id="user_id" name="user_id" class="form-control custom-select">
                <?php foreach ($users as $user_id => $user) { ?>
                    <option value="<?= $user_id; ?>"><?= View::toHtml($user); ?></option>
                <?php } ?>
            </select>
        </div>
    <?php } ?>
    <div class="col-auto mt-2">
        <button class="btn btn-primary btn-icon-split" type="submit">
            <span class="text text-white">Connect</span>
            <span class="icon text-white">
                <i class="fas fa-fw fa-plug"></i>
            </span>
        </button>
    </div>
</div>
