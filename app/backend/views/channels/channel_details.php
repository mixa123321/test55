<?php

use app\core\channels\shopify\ShopifyCredentials;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\helpers\Arr;
use app\core\helpers\View;
use app\core\models\Channels;

/**
 * @var User $authUser
 * @var array $shops
 * @var array $channel
 * @var array $availableLocations
 * @var int $channel_supplier_id
 * @var Supplier $supplier
 * @var array $warehouses_name
 * @var array $primary_shops
 */

$col_sm = 4;
if ($authUser->isRoleSA())
    $col_sm = 3;

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><?= View::toHtml($channel['name']) ?> connections</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                        <span>
                            <i class="fas fa-fw fa-sign-in-alt"></i>
                            <?= View::toHtml($channel['name']) ?> stores
                        </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <div class="row">
                        <h6 class="align-self-center col-auto m-0 pr-2 font-weight-bold text-primary float-left"><span><?= View::toHtml($supplier->getName()); ?></span></h6>
                        <?php if (Channels::isShopify($channel['name']) && !empty($primary_shops)) { ?>
                            <form method="POST" action="/channels/primary_shop">
                                <input type="hidden" name="id" value="<?= $channel_supplier_id; ?>"/>
                                <div class="form-group-element pr-2">
                                    <select class="form-control form-control-lg custom-select" id="primary_shop" name="primary_shop" onchange="submit();">
                                        <option value="0">None</option>
                                        <?php
                                            foreach ($shops['shops'] as $shop_id => $shop)
                                                if (in_array($shop_id, $primary_shops))
                                                    echo "<option value=\"$shop_id\"" . ($shop_id == $shops['primary_shop_id'] ? ' selected' : '') . '>' . View::toHtml(Channels::getFullShopUrl($channel['name'], json_decode($shop['credentials'], true)['shop_url'])) . '</option>';
                                        ?>
                                    </select>
                                    <label for="primary_shop">Primary shop</label>
                                </div>
                            </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if(!empty($shops)): ?>
                <div class="row">
                    <div class="col">
                        <table class="table table-hover">
                            <colgroup>
                                <col width="25%"/>
                                <col width="55%"/>
                                <col width="20%"/>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Connected to</th>
                                    <th>Configuration warehouses</th>
                                    <th>Actions / Utility</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shops['shops'] as $shop_id => $shop) {
                                    $shop_name = json_decode($shop['credentials'], true)['shop_url'];
                                    $active = $shop['is_active'] ? ['success', 'Active'] : ['danger', 'Disabled'];
                                    $availableLocation = empty($availableLocations[$shop_id]) ? [] : $availableLocations[$shop_id];
                                    $params = "id=$channel_supplier_id&shop_id=$shop_id";
                                    ?>
                                    <tr><td class="alert alert-danger d-none" id="err_<?= $shop_id; ?>" colspan="3"></td></tr>
                                    <tr>
                                        <td>
                                            <span><?= View::toHtml(Channels::getFullShopUrl($channel['name'], $shop_name)); ?>
                                            </span><br/>
                                            <span class="badge badge-<?= $active[0]; ?>"><?= $active[1]; ?></span>
                                            <?php if ($shop_id == $shops['primary_shop_id'] && $shop['is_active']) { ?>
                                                <br/><span class="badge badge-primary">Primary</span>
                                            <?php } ?>
                                            <?php if (Channels::isShopify($channel['name'])) {
                                                $credentials = json_decode($shop['credentials'], true);
                                                // secrets are not sent to the page, an empty field keeps the stored value
                                                $secret_placeholder = function (string $field) use ($credentials): string {
                                                    return empty($credentials[$field]) ? '' : 'Set - leave empty to keep';
                                                };
                                            ?>
                                                <div class="border my-2 p-3 border-secondary bg-light">
                                                    <p class="m-0 mb-2"><b>Connection</b>
                                                        <button id="btn_conn_<?= $shop_id; ?>" class="btn btn-outline-primary rounded m-0 ml-3 p-1 js-btn-conn"><i class="far fa-2x fa-save"></i></button>
                                                    </p>
                                                    Type of Apps
                                                    <div class="form-check form-check-inline ml-2">
                                                        <input type="radio" class="form-check-input js-radio-1" id="type_account_1_<?= $shop_id; ?>" name="type_apps_<?= $shop_id; ?>"<?= (!empty($credentials['type_apps']) && $credentials['type_apps'] === ShopifyCredentials::PRIVATE_APPS) ? ' checked' : ''; ?> value="<?= ShopifyCredentials::PRIVATE_APPS ?>">
                                                        <label class="form-check-label" for="type_account_1_<?= $shop_id; ?>">Private APPs</label>
                                                    </div>
                                                    <div class="form-check form-check-inline mr-2">
                                                        <input type="radio" class="form-check-input js-radio-2" id="type_account_2_<?= $shop_id; ?>" name="type_apps_<?= $shop_id; ?>"<?= (!empty($credentials['type_apps']) && $credentials['type_apps'] === ShopifyCredentials::CUSTOM_APPS) ? ' checked' : ''; ?> value="<?= ShopifyCredentials::CUSTOM_APPS ?>">
                                                        <label class="form-check-label" for="type_account_2_<?= $shop_id; ?>">Custom APPs</label>
                                                    </div>
                                                    <div class="form-check form-check-inline mr-0">
                                                        <input type="radio" class="form-check-input js-radio-3" id="type_account_3_<?= $shop_id; ?>" name="type_apps_<?= $shop_id; ?>"<?= (!empty($credentials['type_apps']) && $credentials['type_apps'] === ShopifyCredentials::OAUTH_APPS) ? ' checked' : ''; ?> value="<?= ShopifyCredentials::OAUTH_APPS ?>">
                                                        <label class="form-check-label" for="type_account_3_<?= $shop_id; ?>">Oauth APPs</label>
                                                    </div>
                                                    <div class="form-group mb-0">
                                                        <label for="api_key_<?= $shop_id; ?>">Api key: <span id="req_api_key_<?= $shop_id; ?>" style="color:red"></span></label>
                                                        <input type="password" autocomplete="new-password" name="api_key_<?= $shop_id; ?>" id="api_key_<?= $shop_id; ?>" class="form-control" placeholder="<?= $secret_placeholder('api_key'); ?>">
                                                        <label for="api_secret_<?= $shop_id; ?>">Password: <span id="req_api_secret_<?= $shop_id; ?>" style="color:red"></span></label>
                                                        <input type="password" autocomplete="new-password" name="api_secret_<?= $shop_id; ?>" id="api_secret_<?= $shop_id; ?>" class="form-control" placeholder="<?= $secret_placeholder('api_secret'); ?>">

                                                        <label for="access_token_<?= $shop_id; ?>">Access token: <span id="req_access_token_<?= $shop_id; ?>" style="color:red"></span></label>
                                                        <input type="password" autocomplete="new-password" name="access_token_<?= $shop_id; ?>" id="access_token_<?= $shop_id; ?>" class="form-control" placeholder="<?= $secret_placeholder('access_token'); ?>">

                                                        <label for="client_id_<?= $shop_id; ?>">Client id: <span id="req_client_id_<?= $shop_id; ?>" style="color:red"></span></label>
                                                        <input type="text" name="client_id_<?= $shop_id; ?>" id="client_id_<?= $shop_id; ?>" class="form-control" value="<?= View::toHtml($credentials['client_id'] ?? ''); ?>">
                                                        <label for="client_secret_<?= $shop_id; ?>">Client secret: <span id="req_client_secret_<?= $shop_id; ?>" style="color:red"></span></label>
                                                        <input type="password" autocomplete="new-password" name="client_secret_<?= $shop_id; ?>" id="client_secret_<?= $shop_id; ?>" class="form-control" placeholder="<?= $secret_placeholder('client_secret'); ?>">
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if ($shop['is_active']) { ?>
                                                <table width="100%">
                                                    <colgroup>
                                                        <col width="25%"/>
                                                        <col width="50%"/>
                                                        <col width="*"/>
                                                    </colgroup>
                                                    <thead>
                                                        <tr>
                                                            <th>Warehouse</th>
                                                            <th>Settings</th>
                                                            <th>Utility</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                    <?php foreach ($warehouses_name as $warehouse) {
                                                        $warehouse_data = $shop['shop_warehouses'][$warehouse['id']];
                                                        $num_id = "{$shop_id}_{$warehouse['id']}";
                                                    ?>
                                                        <tr>
                                                            <td>
                                                                <input type="hidden" id="chk_en_<?= $num_id; ?>" value="<?= (int)$warehouse_data['enable']; ?>"/>
                                                                <span<?= $warehouse_data['enable'] ? ' class="font-weight-bold"' : ''; ?>><?= View::toHtml($warehouse['name']); ?></span>
                                                                <div class="form-group mb-0">
                                                                    <div class="form-check">
                                                                        <input type="checkbox" class="form-check-input" id="chk_aw_<?= $num_id; ?>"
                                                                            <?= $warehouse_data['is_active'] ? ' checked' : ''; ?><?= !$warehouse_data['enable'] ? ' disabled' : ''; ?>>
                                                                        <label class="form-check-label" for="chk_aw_<?= $num_id; ?>">Active</label>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="form-group mb-0">
                                                                    <div class="form-check">
                                                                        <input type="checkbox"
                                                                               class="form-check-input"
                                                                               id="chk_do_<?= $num_id; ?>"
                                                                            <?= $warehouse_data['download_orders_flag'] ? ' checked' : ''; ?><?= !$warehouse_data['enable'] ? ' disabled' : ''; ?>>
                                                                        <label class="form-check-label" for="chk_do_<?= $num_id; ?>">Orders download</label>
                                                                    </div>
                                                                </div>
                                                                <div class="form-group mb-0">
                                                                    <div class="form-check">
                                                                        <input type="checkbox"
                                                                               class="form-check-input"
                                                                               id="chk_oa_<?= $num_id; ?>"
                                                                            <?= $warehouse_data['is_orders_approval_required'] ? ' checked' : ''; ?><?= !$warehouse_data['enable'] ? ' disabled' : ''; ?>>
                                                                        <label class="form-check-label" for="chk_oa_<?= $num_id; ?>">Manual orders approval</label>
                                                                    </div>
                                                                </div>
                                                                <div class="form-group mb-0">
                                                                    <div class="form-check">
                                                                        <input type="checkbox"
                                                                               class="form-check-input"
                                                                               id="chk_rm_<?= $num_id; ?>"
                                                                            <?= $warehouse_data['is_retain_me'] ? ' checked' : ''; ?><?= (!$warehouse_data['enable'] || !$warehouse_data['enable_retain_me']) ? ' disabled' : ''; ?>>
                                                                        <label class="form-check-label" for="chk_rm_<?= $num_id; ?>">Retain.Me</label>
                                                                    </div>
                                                                </div>
                                                                <?php if ($shop['is_active'] && Channels::isShopify($channel['name'])) { ?>
                                                                    <div class="form-group mb-0">
                                                                        <div class="form-check">
                                                                            <input type="checkbox"
                                                                                   class="form-check-input"
                                                                                   id="chk_no_<?= $num_id; ?>"
                                                                                <?= $warehouse_data['notify'] ? ' checked' : ''; ?><?= (!$warehouse_data['enable']) ? ' disabled' : ''; ?>>
                                                                            <label class="form-check-label" for="chk_no_<?= $num_id; ?>">Notify Customer</label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="input-group">
                                                                        <select class="custom-select locationSelect js-sel-location"
                                                                                id="sel_loc_<?= $num_id; ?>"
                                                                                <?= (!$warehouse_data['enable'] || empty($availableLocation)) ? ' disabled' : ''; ?>>
                                                                            <?php foreach($availableLocation as $location): ?>
                                                                                <option<?= ($warehouse_data['location_id'] == $location['id']) ? ' selected' : '' ?> value="<?= $location['id'] ?>"><?= View::toHtml($location['name']) . ' (#' . (int)$location['id'] . ')' ?></option>
                                                                            <?php endforeach; ?>
                                                                        </select>&nbsp;
                                                                        <button id="btn_sync_<?= $num_id; ?>" class="btn btn-primary rounded rounded-circle p-2 js-btn-sync"<?= (!$warehouse_data['enable'] || empty($availableLocation)) ? ' disabled' : ''; ?>>
                                                                            <i class="fas fa-fw fa-sync"></i>
                                                                        </button>
                                                                    </div>
                                                                <?php } ?>
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-primary col js-btn-shipping-mapping"
                                                                        id="btn_sm_<?= $num_id; ?>"
                                                                        <?= !$warehouse_data['enable'] ? ' disabled' : ''; ?>
                                                                >Shipping mapping</button>
                                                            </td>
                                                        </tr>
                                                    </tbody>
                                                    <?php } ?>
                                                </table>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <?php if (!$shop['is_active']) { ?>
                                                <a href="/channels/restore_connection/?<?= $params; ?>" class="btn btn-success col">Restore connection</a>
                                            <?php } else { ?>
                                                <a href="/channels/delete_connection/?<?= $params; ?>" class="btn btn-danger col mb-1">Disable connection</a>
                                                <?php if (Channels::isShopify($channel['name'])) { ?>
                                                    <div class="form-check-inline col">
                                                        <input type="checkbox" class="form-check-input js-btn-risk" id="chk_risk_<?= $shop_id; ?>"<?= $shop['is_risk'] ? ' checked' : ''; ?>/>
                                                        <label for="chk_risk_<?= $shop_id; ?>" class="my-2">Notifications about fraud risk</label>
                                                    </div>
                                                <?php } ?>
                                                <button class="btn btn-success col js-btn-update" id="btn_upd_<?= $shop_id; ?>" type="button">Update</button>
                                                <?php if ($authUser->isRoleSA()) { ?>
                                                    <div class="input-group my-1">
                                                        <input class="form-control" id="order_<?= $shop_id; ?>"/>
                                                        <div class="input-group-append">&nbsp;</div>
                                                        <div class="input-group-append">
                                                            <button class="btn btn-outline-primary px-3 border-secondary" onclick="showOrder(<?= $shop_id; ?>)">Get order</button>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table><hr class="mb-4"/>
                    </div>
                </div>
            <?php endif; ?>
            <form method="POST" action="/channels/connect">
                <p class="font-weight-bold mt-4 mb-2">Create connection</p>
                <input type="hidden" name="channel_id" value="<?= $channel['id']; ?> ">
                <input type="hidden" name="supplier_id" value="<?= $supplier->getId(); ?> ">
                <?php
                    if (Channels::isShopify($channel['name']) || Channels::isWooCommerce($channel['name']))
                        require_once __DIR__ . DIRECTORY_SEPARATOR . strtolower($channel['name']) . "_connect.php";
                    else { ?>
                        <div class="row justify-content-end">
                            <div class="form-group col-sm-6 mb-0">
                                <label for="shop_url">Shop URL: <span style="color:red">*</span></label>
                                <div class="input-group" id="shop_url">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">https://</span>
                                    </div>
                                    <input type="text" name="shop_url" id="shop_url" class="form-control" required>
                                </div>
                            </div>
                            <div class="form-group col-sm-<?= ($col_sm - 1)*2; ?> mb-0">
                                <label for="token">Token: <span style="color:red">*</span></label>
                                <input type="text" name="token" id="token" class="form-control" required>
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
                <?php } ?>
            </form>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/channels/?supplier_id=<?= $supplier->getId(); ?>">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-chevron-left"></i>
                            </span>
                        <span class="text">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    function copyToClipboard(element) {
        let $temp = $("<input>");
        $(element).append($temp);
        $temp.val($(element).text()).select();
        document.execCommand('copy');
        $temp.remove();
    }

    function showOrder(shop_id) {
        var order_id = $('#order_' + shop_id).val();
        const $modal = $('#orderRequest');
        const loader = $modal.find('#log-loading h5');
        if (!$('#log-response').prop('classList').contains('d-none'))
            $('#log-response').prop('classList').add('d-none');
        $modal.find('#log-response pre').html('');
        loader.text('Loading...');
        $modal.modal('show');
        $.ajax({
            url: "/channels/get_order",
            method: "POST",
            data: { shop_id: shop_id, order_id: order_id },
            dataType: "json",
                success: function (res) {
                    loader.text('');
                    if (res.error !== undefined) {
                       loader.text(res.error)
                    } else {
                        if (res.response !== undefined && res.response !== null) {
                            $('#log-response').prop('classList').remove('d-none');
                            $modal.find('#log-response pre').text(JSON.stringify(res.response, null, 4));
                        }
                   }
                },
                error: function () {
                    loader.text('Error. Please try again');
                    $modal.find('#log-request pre').html('');
                    $modal.find('#log-response pre').html('');
                }
        });
    }
</script>
<div class="modal" tabindex="-1" role="dialog" id="orderRequest">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Order</h3>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="log-loading">
                    <h5></h5>
                </div>
                <div id="log-response" class="logs d-none">
                    <div class="row">
                        <div class="col-md-10 pt-2">
                            <h5><b>Response</b></h5>
                        </div>
                        <div class="col-md-2 mb-2">
                            <button class="float-right border border-dark btn btn-xs mr-0" onclick="copyToClipboard('#log-response-content');">Copy</button>
                        </div>
                    </div>
                    <pre id="log-response-content" style="background-color: #fdfdfd; border: 1px solid #111111; padding: 20px"></pre>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $('.js-btn-shipping-mapping').click((e) => {
        var ids = e.target.id.split('_');
        location = "/channels/shipping_mapping/?shop_id=" + ids[2] + "&warehouse_id=" + ids[3];
    });

    var warehouses = <?= View::toJs(Arr::column($warehouses_name, 'id')) ?>;

    $('.js-btn-sync').click((e) => {
        var ids = e.currentTarget.id.split('_');
        location = '/channels/sync_locations/?shop_id=' + ids[2] + '&warehouse_id=' + ids[3];
    });

    $('.js-btn-risk').click((e) => {
        var ids = e.currentTarget.id.split('_');
        location = '/channels/set_risk/?shop_id=' + ids[2] + '&risk=' + ($('#chk_risk_' + ids[2]).is(':checked') ? 1 : 0);
    });

    $('.js-btn-update').click((e) => {
        var shop_id = e.target.id.split('_')[2];
        var enable = [];
        var is_active = [];
        var orders_download = [];
        var orders_approval = [];
        var is_retain_me = [];
        var notify = [];
        var location_id = [];
        for (i = 0; i < warehouses.length; i++) {
            enable[i] = $('#chk_en_' + shop_id + '_' + warehouses[i]).val();
            is_active[i] = $('#chk_aw_' + shop_id + '_' + warehouses[i]).is(':checked');
            orders_download[i] = $('#chk_do_' + shop_id + '_' + warehouses[i]).is(':checked');
            orders_approval[i] = $('#chk_oa_' + shop_id + '_' + warehouses[i]).is(':checked');
            location_id[i] = $('#sel_loc_' + shop_id + '_' + warehouses[i]).val();
            is_retain_me[i] = $('#chk_rm_' + shop_id + '_' + warehouses[i]).is(':checked');
            notify[i] = $('#chk_no_' + shop_id + '_' + warehouses[i]).is(':checked');
        }
        hideError('err_' + shop_id);
        $.ajax({
            url: '/channels/update_shop_warehouse',
            method: 'POST',
            dataType: 'json',
            data: {
                shop_id: shop_id,
                warehouses: JSON.stringify(warehouses),
                enable: JSON.stringify(enable),
                is_active: JSON.stringify(is_active),
                orders_download: JSON.stringify(orders_download),
                orders_approval: JSON.stringify(orders_approval),
                location_id: JSON.stringify(location_id),
                is_retain_me: JSON.stringify(is_retain_me),
                notify: JSON.stringify(notify)
            },
            success: function (json) {
                if ('error' in json)
                    showError('err_' + shop_id, json.error, 0);
                else
                    location = "/channels/channel_details/?<?= $_SESSION['channel_details']; ?>";
            },
            error: function (json) {
                location = '/';
            }
        });
    });

    $('.js-btn-conn').click((e) => {
        var id = e.currentTarget.id.split('_')[2];
        $.ajax({
            url: '/channels/update_connection',
            method: 'POST',
            dataType: 'json',
            data: {
                shop_id: id,
                type_apps: $('input[name=type_apps_' + id + ']:checked').val(),
                api_key: $('#api_key_' + id).val(),
                api_secret: $('#api_secret_' + id).val(),
                access_token: $('#access_token_' + id).val(),
                client_id: $('#client_id_' + id).val(),
                client_secret: $('#client_secret_' + id).val()
            },
            success: function (json) {
                if ('error' in json)
                    showError('err_' + id, json.error, 0);
                else
                    location = "/channels/channel_details/?<?= $_SESSION['channel_details']; ?>";
            },
            error: function (json) {
                showError('err_' + id, 'Connection error', 0);
            }
        });
    });

    function hideError(id)
    {
        $('#' + id).html('');
        $('#' + id).prop('classList').add('d-none');
    }

    $('.js-sel-location').mouseover(function() {
        $(this).tooltip({
            "html": true,
            "title": "<b>Utilise Shopify Locations</b> must be enabled to only download orders for this store"
        });
    });

<?php
    foreach ($shops['shops'] as $shop_id => $shop) {
        $type_apps = json_decode($shop['credentials'], true)['type_apps'];
        if ($type_apps == ShopifyCredentials::PRIVATE_APPS)
            echo "setPrivateAPPs('_$shop_id');\n";
        elseif ($type_apps == ShopifyCredentials::CUSTOM_APPS)
            echo "setCustomAPPs('_$shop_id');\n";
        elseif ($type_apps == ShopifyCredentials::OAUTH_APPS)
            echo "setOauthAPPs('_$shop_id');\n";
    }
?>

    $('.js-radio-1').change((e) => {
        var id = e.currentTarget.id.split('_')[3];
        setPrivateAPPs('_' + id);
    });

    $('.js-radio-2').change((e) => {
        var id = e.currentTarget.id.split('_')[3];
        setCustomAPPs('_' + id);
    });

    $('.js-radio-3').change((e) => {
        var id = e.currentTarget.id.split('_')[3];
        setOauthAPPs('_' + id);
    });
</script>