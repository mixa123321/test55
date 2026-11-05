<?php

use app\core\channels\shopify\ShopifyCredentials;
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
            <div class="input-group-append">
                <span class="input-group-text"><?= ShopifyCredentials::SUFFIX; ?></span>
            </div>
        </div>
    </div>
    <div class="form-group col-sm-2 mb-0">
        <label for="type_apps">Type of Apps</label><br/>
        <div class="form-check form-check-inline">
            <input type="radio" class="form-check-input" id="type_account_1" name="type_apps" value="1" checked>
            <label class="form-check-label" for="type_account_1">Private APPs</label>
        </div>
        <div class="form-check form-check-inline">
            <input type="radio" class="form-check-input" id="type_account_2" name="type_apps" value="2">
            <label class="form-check-label" for="type_account_2">Custom APPs</label>
        </div>
    </div>
    <div class="form-group col-sm-4 mb-0">
        <label for="api_key">Api key: <span id="req_api_key" style="color:red"></span></label>
        <input type="text" name="api_key" id="api_key" class="form-control">
        <label for="api_secret">Password: <span id="req_api_secret" style="color:red"></span></label>
        <input type="text" name="api_secret" id="api_secret" class="form-control">
        <label for="access_token">Access token: <span id="req_access_token" style="color:red"></span></label>
        <input type="text" name="access_token" id="access_token" class="form-control">
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
<script>
    setPrivateAPPs();

    function setPrivateAPPs(id = '')
    {
        $('#req_api_key' + id).html('*');
        $('#req_api_secret' + id).html('*');
        $('#req_access_token' + id).html('');
        $('#req_client_id' + id).html('');
        $('#req_client_secret' + id).html('');
        $('#api_key' + id).attr('required', true);
        $('#api_secret' + id).attr('required', true);
        $('#access_token' + id).attr('required', false);
        $('#client_id' + id).attr('required', false);
        $('#client_secret' + id).attr('required', false);
    }

    function setCustomAPPs(id = '')
    {
        $('#req_api_key' + id).html('');
        $('#req_api_secret' + id).html('');
        $('#req_access_token' + id).html('*');
        $('#req_client_id' + id).html('');
        $('#req_client_secret' + id).html('');
        $('#api_key' + id).attr('required', false);
        $('#api_secret' + id).attr('required', false);
        $('#access_token' + id).attr('required', true);
        $('#client_id' + id).attr('required', false);
        $('#client_secret' + id).attr('required', false);
    }

    function setOauthAPPs(id = '')
    {
        $('#req_api_key' + id).html('');
        $('#req_api_secret' + id).html('');
        $('#req_access_token' + id).html('');
        $('#req_client_id' + id).html('*');
        $('#req_client_secret' + id).html('*');
        $('#api_key' + id).attr('required', false);
        $('#api_secret' + id).attr('required', false);
        $('#access_token' + id).attr('required', false);
        $('#client_id' + id).attr('required', true);
        $('#client_secret' + id).attr('required', true);
    }

    $('#type_account_1').change(() => {
        setPrivateAPPs();
    });

    $('#type_account_2').change(() => {
        setCustomAPPs();
    });

    $('#type_account_3').change(() => {
        setOauthAPPs();
    });
</script>