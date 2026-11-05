<?php

namespace app\backend\controllers;

use app\backend\models\ChannelHistories;
use app\backend\models\SupplierWarehouses;
use app\core\channels\BaseChannelConnector;
use app\core\channels\BaseChannelProcessor;
use app\core\channels\magento\MagentoConnector;
use app\core\channels\magento\MagentoDataProcessor;
use app\core\channels\shopify\ShopifyConnector;
use app\core\channels\shopify\ShopifyCredentials;
use app\core\channels\shopify\ShopifyDataProcessor;
use app\core\channels\woocommerce\WooCommerceConnector;
use app\core\channels\woocommerce\WooCommerceDataProcessor;
use app\core\dbal\SafeMysql;
use app\core\entities\FlatIntegration;
use app\core\entities\SFTPExport;
use app\core\entities\Shop;
use app\core\entities\ShopWarehouse;
use app\core\entities\Supplier;
use app\core\entities\UserChannel;
use app\core\helpers\HTTP;
use app\core\models\Channels as ChannelsModel;
use app\core\models\ChannelSuppliers;
use app\core\models\FlatIntegrationHistories;
use app\core\models\FlatIntegrations;
use app\core\models\FTPUsers;
use app\core\models\SFTPReports;
use app\core\models\SFTPUploads;
use app\core\models\Shippings;
use app\core\models\Shops;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\ShopWarehouses;
use app\core\models\Warehouses;
use app\core\statical\WithDate;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\Users as UsersModel;
use app\backend\models\SFTPExports;
use app\core\helpers\FileWrapper;

class Channels extends AuthController
{
    /** Shopify credential fields used (and required) by each type of apps */
    private const SHOPIFY_APPS_FIELDS = [
        ShopifyCredentials::PRIVATE_APPS => ['api_key', 'api_secret'],
        ShopifyCredentials::CUSTOM_APPS => ['access_token'],
        ShopifyCredentials::OAUTH_APPS => ['client_id', 'client_secret'],
    ];

    private const SHOPIFY_APPS_ERRORS = [
        ShopifyCredentials::PRIVATE_APPS => 'API Key or Password cannot be empty',
        ShopifyCredentials::CUSTOM_APPS => 'Access Token cannot be empty',
        ShopifyCredentials::OAUTH_APPS => 'Client id or Client secret cannot be empty',
    ];

    public function beforeAction($action)
    {
        parent::beforeAction($action);
        parent::checkSupplierAccess();
    }

    /**
     * Actions addressed by shop_id carry no supplier_id, so checkSupplierAccess() does not cover them.
     * @throws RedirectException
     */
    private function checkShopAccess($shop_id): void
    {
        if ($this->user->isRoleSA())
            return;
        $supplier = Shops::getSupplier((int)$shop_id);
        if (empty($supplier) || empty($this->available_suppliers[$supplier->getId()])) {
            View::addFlash('supplier_access', 'danger', 'Access denied');
            throw new RedirectException('/');
        }
    }

    /** @throws */
    public function index()
    {
        $suppliers = Arr::map($this->available_suppliers, 'id', 'name');
        if (empty($suppliers)) {
            View::addFlash('channels', 'danger', 'Company not found');
            throw new RedirectException('/');
        }
        $supplier_id = empty($_GET['supplier_id']) ? array_keys($suppliers)[0] : (int)$_GET['supplier_id'];
        if (empty($suppliers[$supplier_id]))
            $supplier_id = array_keys($suppliers)[0];
        $channels_users = ChannelsModel::getAllWithSuppliers(['supplier_id' => $supplier_id]);
        $channels = ChannelsModel::getAll();
        $data = [
            'channels_users' => $channels_users,
            'channels_list' => $channels,
            'suppliers' => $suppliers,
            'supplier_id' => $supplier_id,
            'sftp' => SFTPExports::getBySupplierId($supplier_id),
            'flat' => FlatIntegrations::getBySupplierId($supplier_id, $this->warehouse_id),
        ];
        $this->render($data, 'channels/channels');
    }

    /** @throws */
    public function channelDetails()
    {
        $this->disableWarehouseChange();
        if (empty($_GET))
            throw new RedirectException('/');
        $channel = ChannelsModel::getById($_GET['id']);
        if (empty($channel)) {
            View::addFlash('channels', 'danger', 'Channel is not found');
            throw new RedirectException('/');
        }
        $supplier = SuppliersModel::getById($_GET['supplier_id']);
        if (empty($supplier)) {
            View::addFlash('channels', 'danger', 'Company is not found');
            throw new RedirectException('/');
        }
        $users = Arr::map(UsersModel::getUserBySupplierId($supplier->getId()), 'id', 'login');
        if (empty($users)) {
            View::addFlash('channels', 'danger', 'Users are not found');
            throw new RedirectException('/channels/?' . http_build_query($_GET));
        }
        $channels_users = ChannelsModel::getAllWithSuppliers([
            'channel_id' => $channel['id'],
            'supplier_id' => $supplier->getId(),
            'is_active' => 'any',
        ]);
        if (!empty($channels_users[$channel['name']])) {
            $channel_supplier = $channels_users[$channel['name']][$supplier->getId()];
            $shops = ChannelsModel::getUserChannelsByChannelId($channel['id'], $supplier->getId(), false);
            if (!empty($shops))
                $shops = $shops[$channel_supplier];
        } else {
            $channel_supplier = 0;
            $shops = [];
        }
        $availableLocations = null;
        if ($shops) {
            $connectorName = ChannelsModel::getConnectorName($channel);
            if (!$connectorName)
                View::addFlash('channels', 'danger', 'Not found connector');
            $processorName = ChannelsModel::getProcessorName($channel);
            if (!$processorName)
                View::addFlash('channels', 'danger', 'Not found Data Processor');
            foreach ($shops['shops'] as $key => $shop) {
                $warehouse_id = array_keys($shop['shop_warehouses'])[0];
                $user_channel = [
                    'id' => $channel_supplier,
                    'supplier_id' => $shops['supplier_id'],
                    'user_id' => $shops['user_id'],
                    'channel_id' => $shops['channel_id'],
                    'primary_shop_id' => $shops['primary_shop_id'],
                    'is_active' => $shop['is_active'],
                    'is_risk' => $shop['is_risk'],
                    'credentials' => $shop['credentials'],
                    'shop_id' => $key,
                    'warehouse_id' => $warehouse_id,
                    'sw_id' => $shop['shop_warehouses'][$warehouse_id]['sw_id'] ?? 0,
                    'download_orders_flag' => $shop['shop_warehouses'][$warehouse_id]['download_orders_flag'] ?? 0,
                    'is_orders_approval_required' => $shop['shop_warehouses'][$warehouse_id]['is_orders_approval_required'] ?? 0,
                    'cache' => $shop['shop_warehouses'][$warehouse_id]['cache'] ?? '',
                    'is_daily' => $shop['shop_warehouses'][$warehouse_id]['is_daily'] ?? 0,
                    'location_id' => $shop['shop_warehouses'][$warehouse_id]['location_id'] ?? 0,
                    'last_upload' => $shop['shop_warehouses'][$warehouse_id]['last_upload'] ?? null,
                    'is_retain_me' => $shop['shop_warehouses'][$warehouse_id]['is_retain_me'] ?? 0,
                    'notify' => $shop['shop_warehouses'][$warehouse_id]['notify'] ?? 0,
                    'warehouse_active' => $shop['shop_warehouses'][$warehouse_id]['is_active'] ?? 0,
                ];
                /** @var BaseChannelConnector $Connector */
                $Connector = new $connectorName($user_channel['credentials']);
                /** @var BaseChannelProcessor $Processor */
                $Processor = new $processorName();
                $Processor->setConnector($Connector);
                $Processor->setUserChannel(new Shops($user_channel));
                if (method_exists($Processor, 'getLocations'))
                    $availableLocations[$key] = $Processor->getLocations();
            }
        }
        $warehouses_access = SuppliersModel::getWarehousesById($supplier->getId());
        $warehouses = $this->user->isRoleSA() ? Arr::maps(Warehouses::getAll($this->logistic_company->getId()), 'id', ['id', 'name'])
                                              : $warehouses_access;
        $warehouses_access = Arr::column($warehouses_access, 'id');
        if (!empty($shops))
            foreach ($warehouses as $warehouse)
                foreach ($shops['shops'] as $shop_id => $shop) {
                    if (empty($shop['shop_warehouses'][$warehouse['id']])) {
                        $shops['shops'][$shop_id]['shop_warehouses'][$warehouse['id']] = [
                            'name' => $warehouse['name'],
                            'download_orders_flag' => 0,
                            'is_orders_approval_required' => 0,
                            'is_daily' => 0,
                            'cache' => '',
                            'location_id' => 0,
                            'is_active' => 0,
                            'last_upload' => null,
                            'is_retain_me' => 0,
                            'notify' => 0,
                        ];
                        if (in_array($warehouse['id'], $warehouses_access)) {
                            $new_sw = new ShopWarehouse($shops['shops'][$shop_id]['shop_warehouses'][$warehouse['id']]);
                            $new_sw->setShopId($shop_id);
                            $new_sw->setWarehouseId($warehouse['id']);
                            $fields_to_save = array_keys($new_sw->toArray());
                            array_shift($fields_to_save);
                            $new_sw->save($fields_to_save);
                        }
                    }
                    $shops['shops'][$shop_id]['shop_warehouses'][$warehouse['id']]['enable'] = in_array($warehouse['id'], $warehouses_access);
                    $shops['shops'][$shop_id]['shop_warehouses'][$warehouse['id']]['enable_retain_me'] = SupplierWarehouses::getRetainMe($warehouse['id'], $shops['supplier_id']);
                }
        if (empty($shops))
            $primary_shops = [];
        else
            $primary_shops = array_keys(array_filter($shops['shops'], function($shop) {
                $shop_warehouses = array_filter($shop['shop_warehouses'], function($shop_warehouse) {
                    return $shop_warehouse['is_active'];
                });
                return $shop['is_active'] && count($shop_warehouses) > 0;
            }));
        $data = [
            'channel' => $channel,
            'shops' => $shops,
            'availableLocations' => $availableLocations,
            'channel_supplier_id' => $channel_supplier,
            'supplier' => $supplier,
            'warehouses_name' => $warehouses,
            'users' => $users,
            'primary_shops' => $primary_shops,
        ];
        $_SESSION['channel_details'] = http_build_query($_GET);
        $this->render($data, 'channels/channel_details');
    }

    /** @throws */
    public function sftpDetails($login = null, $word = null)
    {
        $this->disableWarehouseChange();
        if (empty($_GET) || empty($_GET['supplier_id']))
            throw new RedirectException('/');
        $supplier = SuppliersModel::getById($_GET['supplier_id']);
        if (empty($supplier)) {
            View::addFlash('channels', 'danger', 'Company is not found');
            throw new RedirectException('/');
        }
        $sftp = SFTPExports::getBySupplierId($_GET['supplier_id']);
        $users = UsersModel::getUserBySupplierId($_GET['supplier_id'], true);
        $data = [
            'supplier' => $supplier,
            'sftp' => $sftp,
            'login' => $login,
            'word' => $word,
            'sftp_report' => empty($sftp) ? [] : SFTPReports::getBySFTPExport($sftp->getId()),
            'sftp_upload' => empty($sftp) ? [] : SFTPUploads::getBySFTPExport($sftp->getId()),
            'users' => $users
        ];
        $_SESSION['sftp_details'] = http_build_query($_GET);
        $this->render($data, 'channels/sftp_details');
    }

    /** @throws */
    public function flatDetails()
    {
        $this->disableWarehouseChange();
        if (empty($_GET) || empty($_GET['supplier_id']))
            throw new RedirectException('/');
        $supplier = SuppliersModel::getById($_GET['supplier_id']);
        if (empty($supplier)) {
            View::addFlash('channels', 'danger', 'Company is not found');
            throw new RedirectException('/');
        }
        /** @var FlatIntegration $flat */
        $flat = FlatIntegrations::getBySupplierId($supplier->getId(), $this->warehouse_id);
        [$histories, $histories_upload, $histories_export, $last_orders] = FlatIntegrationHistories::getHistories2Week($flat->getId());
        $data = [
            'supplier' => $supplier,
            'flat' => $flat,
            'last_orders' => $last_orders,
            'histories' => $histories,
            'histories_upload' => $histories_upload,
            'histories_export' => $histories_export,
        ];
        $_SESSION['flat_details'] = http_build_query($_GET);
        $this->render($data, 'channels/flat_details');
    }

    /** @throws */
    public function sftpCreate()
    {
        if (empty($_POST) || empty($_POST['supplier_id']))
            throw new RedirectException('/');
        $_POST['supplier_id'] = (int)$_POST['supplier_id'];
        $error = false;
        if (empty($_POST['username'])) {
            View::addFlash('sftp', 'danger', 'User name is not found');
            $error = true;
        } elseif (!ctype_alnum($_POST['username'])) {
            View::addFlash('sftp', 'danger', 'User name has not alphanumeric character(s)');
            $error = true;
        }
        if (empty($_POST['password'])) {
            View::addFlash('sftp', 'danger', 'Password is not found');
            $error = true;
        } elseif (!preg_match('/[a-zA-Z!]+/', $_POST['password']) || !preg_match('/[0-9]+/', $_POST['password'])) {
            View::addFlash('sftp', 'danger', 'Password does not meet requirements');
            $error = true;
        }
        $sftp = new SFTPExport([
            'supplier_id' => $_POST['supplier_id'],
            'warehouse_id' => $this->warehouse_id,
            'is_active' => 1,
            'login' => "{$_POST['username']}_{$_POST['supplier_id']}",
            'password' => $_POST['password'],
        ]);
        $errors = SFTPExports::validate($sftp);
        if (!empty($errors)) {
            View::addFlash('sftp', 'danger', Arr::column($errors, 'message'));
            $error = true;
        }
        $_GET = HTTP::httpQueryToArray($_SESSION['sftp_details']);
        if ($error) {
            $this->sftpDetails($_POST['username'], $_POST['password']);
            return;
        }
        View::addFlash('sftp', 'success', 'SFTP connection is created');
        $clear_login = FTPUsers::clearLogin($sftp->getLogin(), $_POST['supplier_id']);
        $path = SFTP_STORAGE . DIRECTORY_SEPARATOR . $clear_login;

        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $sftp->save(['supplier_id', 'is_active', 'login', 'password']);
        ChannelHistories::connectSFTP($sftp, $this->user->getId());
        FTPUsers::addUser($sftp->getLogin(), $sftp->getPassword(), $path);
        $mysql->commit();
        if ((new FileWrapper(FileWrapper::getContainerByPath($path)))->getType() === FileWrapper::TYPE_AZURE) {
            $result = FTPUsers::createUser(FTPUsers::clearLogin($sftp->getLogin(), $_POST['supplier_id']), $sftp->getPassword());
            if (!$result) {
                View::addFlash('sftp', 'danger', 'User is not created on SFTPGo');
            }
            FTPUsers::initSftpFolders($_POST['supplier_id']);
        } else {
            (new FileWrapper(FileWrapper::getContainerByPath($path)))->handleDir($path);
        }
        $this->sftpDetails();
    }

    /** @throws */
    public function sftpDelete()
    {
        if (empty($_GET))
            throw new RedirectException('/');
        /** @var $sftp SFTPExport */
        $sftp = SFTPExports::getBySupplierId($_GET['supplier_id']);
        if (empty($sftp)) {
            View::addFlash('sftp','danger', "You don't have an active connection to cancel");
            throw new RedirectException('/channels/sftp_details/?' . $_SESSION['sftp_details']);
        }
        $sftp->setIsActive(0);
        $sftp->save(['id', 'is_active']);
        ChannelHistories::disableSFTP($sftp, $this->user->getId());
        View::addFlash('sftp', 'success', 'Connection disabled successfully');
        throw new RedirectException('/channels/sftp_details/?' . $_SESSION['sftp_details']);
    }

    /** @throws */
    public function flatDelete()
    {
        if (empty($_GET))
            throw new RedirectException('/');
        /** @var $flat FlatIntegration */
        $flat = FlatIntegrations::getBySupplierId($_GET['supplier_id'], $this->warehouse_id);
        if (empty($flat)) {
            View::addFlash('flat','danger', "You don't have an active connection to cancel");
            throw new RedirectException('/channels/flat_details/?' . $_SESSION['flat_details']);
        }
        $flat->setIsActive(0);
        $flat->save(['id', 'is_active']);
        ChannelHistories::disableFlat($flat, $this->user->getId());
        View::addFlash('flat', 'success', 'Connection disabled successfully');
        throw new RedirectException('/channels/flat_details/?' . $_SESSION['flat_details']);
    }

    /** @throws */
    public function sftpUpdate()
    {
        if (empty($_POST))
            return [];
        /** @var $sftp SFTPExport */
        $sftp = SFTPExports::getBySupplierId($_POST['supplier_id']);
        if (empty($sftp))
            return $this->response = ['error' => 'Connection is not found'];
        $errors = $history = [];
        $reports = SFTPReports::getBySFTPExport($sftp->getId());
        $nothing = $sftp->getPassword() === $_POST['pwd'] || empty($_POST['pwd']);
        if (!$nothing) {
            if (empty($_POST['pwd']))
                $errors[] = 'Password is not found';
            else {
                if (!ctype_alnum($_POST['pwd']) ||
                    !preg_match('/[a-zA-Z]+/', $_POST['pwd']) ||
                    !preg_match('/[0-9]+/', $_POST['pwd']))
                    $errors[] = 'Password does not meet requirements';
                $sftp->setPassword($_POST['pwd']);
                $errors_validate = SFTPExports::validate($sftp);
                if (!empty($errors_validate))
                    $errors = $errors + Arr::column($errors_validate, 'message');
                elseif (empty($errors)) {
                    $sftp->save(['id', 'password']);
                    FTPUsers::updateUser($sftp->getLogin(), $_POST['pwd']);
                    $history['password'] = $_POST['pwd'];
                }
            }
        }
        if (!empty($errors))
            return $this->response = ['error' => implode('<br/>', array_map([View::class, 'toHtml'], $errors))];
        $update = [];
        $additional = [];
        if (!empty($_POST['additional']))
            foreach ($_POST['additional'] as $add_field) {
                $parts = explode('_', $add_field['id']);
                if (count($parts) < 2)
                    continue;
                $report_id = array_pop($parts);
                if (empty($additional[$report_id]))
                    $additional[$report_id] = [];
                $field_name = implode('_', $parts);
                $additional[$report_id][$field_name] = $add_field['value'];
            }
        foreach ($reports as $report) {
            if ($report['is_active'] != $_POST['active'][$report['id']])
                $history[$report['id']]['is_active'] = $update[$report['id']]['is_active'] = $_POST['active'][$report['id']];
            if ($report['frequency'] != $_POST['freq'][$report['id']])
                $history[$report['id']]['frequency'] = $update[$report['id']]['frequency'] = $_POST['freq'][$report['id']];
            if ($report['date_time'] != $_POST['date_time'][$report['id']])
                $history[$report['id']]['date_time'] = $update[$report['id']]['date_time'] = $_POST['date_time'][$report['id']];
            if (empty($report['additional']))
                continue;
            $changes = $update_additional = [];
            foreach ($report['additional'] as $field_name => $meta_data) {
                if ($meta_data['value'] != $additional[$report['id']][$field_name])
                    $changes[$field_name] = $additional[$report['id']][$field_name];
                $update_additional[$field_name] = $additional[$report['id']][$field_name];
            }
            if (!empty($changes)) {
                $history[$report['id']]['additional'] = $changes;
                $update[$report['id']]['additional'] = $update_additional;
            }
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        foreach ($update as $report_id => $report) {
            $sftp_report = SFTPReports::getBySFTPExportReport($sftp->getId(), $report_id);
            $fields = [];
            $set_parts = [];
            if (isset($report['is_active'])) {
                $fields['is_active'] = $report['is_active'];
                $set_parts[] = 'is_active = :is_active';
            }
            if (isset($report['frequency'])) {
                $fields['frequency'] = $report['frequency'];
                $set_parts[] = 'frequency = :frequency';
            }
            if (isset($report['date_time'])) {
                $fields['date_time'] = $report['date_time'];
                $set_parts[] = 'date_time = :date_time';
            }
            if (isset($report['additional'])) {
                $fields['additional'] = json_encode($report['additional']);
                $set_parts[] = 'additional = :additional';
            }
            $set = implode(', ', $set_parts);
            if (empty($sftp_report)) {
                $fields['sftp_export_id'] = $sftp->getId();
                $fields['report_id'] = $report_id;
                $set .= ($set ? ', ' : '') . 'sftp_export_id = :sftp_export_id, report_id = :report_id';
                $sql = "INSERT INTO sftp_report SET $set";
            } else {
                $fields['where_sftp_export_id'] = $sftp->getId();
                $fields['where_report_id'] = $report_id;
                $sql = "UPDATE sftp_report
                        SET $set
                        WHERE sftp_export_id = :where_sftp_export_id AND report_id = :where_report_id";
            }
            $mysql->execute($sql, $fields);
        }
        $folder_params = self::saveSFTPUpload($sftp, $_POST['postDataSFTPUpload'], $_POST['supplier_id']);
        ChannelHistories::updateSFTP($sftp, $this->user->getId(), $history);
        $mysql->commit();
        foreach ($folder_params as $folder_param)
            SFTPUploads::createFolderForUpload($folder_param['type_id'], $folder_param['active'], $folder_param['supplier_id']); // Не создаються пустые папки запросом в Azure
        if ((new FileWrapper(FileWrapper::getContainerByPath(SFTP_STORAGE)))->getType() === FileWrapper::TYPE_AZURE)
            FTPUsers::initSftpFolders((int)$_POST['supplier_id']); //Для создания пустых папок нужен Rest запрос в SFTPGo,
        return $this->response = ['success' => 'Success!'];
    }

    private static function saveSFTPUpload($sftp, $post_data_sftp_upload, $supplier_id): array
    {
        $mysql = new SafeMysql();
        $mysql->execute('DELETE from sftp_upload where sftp_export_id = :sftp_export_id', [
            'sftp_export_id' => $sftp->getId(),
        ]);
        $data_to_upload = $result = [];
        foreach ($post_data_sftp_upload as $upload) {
            if ($upload['name'] == 'type_id')
                $type_id = $upload['value'];
            if ($upload['name'] == "user_id[$type_id]")
                $data_to_upload[$type_id]['user_id'] = $upload['value'];
            if ($upload['name'] == "active[$type_id]")
                $data_to_upload[$type_id]['active'] = $upload['value'];
        }
        foreach ($data_to_upload as $type_id => $item) {
            $active = (int) ($item['active'] ?? 0);
            $mysql->execute('INSERT INTO sftp_upload SET 
                sftp_export_id = :sftp_export_id,  
                sftp_upload_type_id = :sftp_upload_type_id,
                is_active = :is_active,
                user_id = :user_id
            ', [
                'sftp_export_id' => $sftp->getId(),
                'sftp_upload_type_id' => $type_id,
                'is_active'=> $active,
                // the user select is not posted when the supplier has no users (legacy stored 0)
                'user_id' => (int)($item['user_id'] ?? 0)
            ]);

            $result[] = ['type_id' => $type_id, 'active' => $active, 'supplier_id' => $supplier_id];
        }
        return $result;
    }

    public function flatUpdate()
    {
        if (empty($_POST))
            return [];
        /** @var $flat FlatIntegration */
        $flat = FlatIntegrations::getBySupplierId($_POST['supplier_id'], $this->warehouse_id);
        if (empty($flat))
            return $this->response = ['error' => 'Connection is not found'];
        // the password is not sent to the page (ADR-002 Phase 2 step 4c): an empty field keeps the stored one
        if (empty($_POST['path_from']) || empty($_POST['path_to']) ||
            trim($_POST['path_from']) == '' || trim($_POST['path_to']) == '')
            return $this->response = ['error' => "Field(s) cann't be empty"];
        $fields_to_save = ['id'];
        $credentials = $flat->getCredentials();
        $history = [];
        if (trim($_POST['pwd'] ?? '') !== '' && $credentials['pass'] !== $_POST['pwd']) {
            $history['pass'] = $credentials['pass'] = $_POST['pwd'];
            $fields_to_save[] = 'credentials';
        }
        if ($credentials['path_from'] !== $_POST['path_from']) {
            $history['path_from'] = $credentials['path_from'] = $_POST['path_from'];
            if (!in_array('credentials', $fields_to_save))
                $fields_to_save[] = 'credentials';
        }
        if ($credentials['path_to'] !== $_POST['path_to']) {
            $history['path_to'] = $credentials['path_to'] = $_POST['path_to'];
            if (!in_array('credentials', $fields_to_save))
                $fields_to_save[] = 'credentials';
        }
        if (count($fields_to_save) == 1)
            return $this->response = ['error' => 'Nothing to change'];
        $flat->setCredentials($credentials);
        $flat->save($fields_to_save);
        ChannelHistories::updateFlat($flat, $this->user->getId(), $history);
        return $this->response = ['success' => 'Success!'];
    }

    /** @throws */
    public function sftpRestore()
    {
        if (empty($_GET))
            throw new RedirectException('/');
        /** @var $sftp SFTPExport */
        $sftp = SFTPExports::getBySupplierId($_GET['supplier_id']);
        if (empty($sftp)) {
            View::addFlash('sftp','danger', "You don't have a disabled connection to restore");
            throw new RedirectException('/channels/sftp_details/?' . $_SESSION['sftp_details']);
        }
        $sftp->setIsActive(1);
        $sftp->save(['id', 'is_active']);
        ChannelHistories::restoreSFTP($sftp, $this->user->getId());
        View::addFlash('sftp', 'success', 'Connection restored successfully');
        throw new RedirectException('/channels/sftp_details/?' . $_SESSION['sftp_details']);
    }

    /** @throws */
    public function flatRestore()
    {
        if (empty($_GET))
            throw new RedirectException('/');
        /** @var $flat FlatIntegration */
        $flat = FlatIntegrations::getBySupplierId($_GET['supplier_id'], $this->warehouse_id);
        if (empty($flat)) {
            View::addFlash('flat','danger', "You don't have a disabled connection to restore");
            throw new RedirectException('/channels/flat_details/?' . $_SESSION['flat_details']);
        }
        $flat->setIsActive(1);
        $flat->save(['id', 'is_active']);
        ChannelHistories::restoreFlat($flat, $this->user->getId());
        View::addFlash('flat', 'success', 'Connection restored successfully');
        throw new RedirectException('/channels/flat_details/?' . $_SESSION['flat_details']);
    }

    /** @throws */
    public function restoreConnection()
    {
        if (empty($_GET))
            throw new RedirectException('/');
        $this->checkShopAccess(Request::getPositiveId(null, 'shop_id'));
        /**
         * @var $shop Shops
         */
        $shop = ChannelsModel::getUserChannel([
            'id' => Request::getPositiveId(),
            'shop_id' => Request::getPositiveId(null, 'shop_id'),
            'is_active' => 0,
            'is_active_warehouse' => 'any',
        ]);
        if (!$shop) {
            View::addFlash('channels','danger', "You don't have a disabled connection to restore");
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        $shop->getShop()->setIsActive(1);
        $shop->getUserChannel()->saveShop(['is_active']);
        ChannelHistories::restoreConnection($shop->getUserChannel(), $this->user->getId(), [
            'shop_id' => $shop->getShop()->getId()
        ]);
        View::addFlash('channels', 'success', 'Connection restored successfully');
        throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
    }

    /** @throws */
    public function setRisk()
    {
        if (empty($_GET) || empty($_GET['shop_id']) || !isset($_GET['risk']))
            throw new RedirectException('/');
        $this->checkShopAccess($_GET['shop_id']);
        /** @var $shop Shops */
        $shop = ChannelsModel::getUserChannel([
            'shop_id' => Request::getPositiveId(null, 'shop_id'),
            'is_active' => 'any',
            'is_active_warehouse' => 'any',
        ]);
        if (!$shop) {
            View::addFlash('channels','danger', "Shop isn't found");
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        $shop->getShop()->setIsRisk((int)$_GET['risk']);
        $shop->getUserChannel()->saveShop(['is_risk']);
        ChannelHistories::setRisk($shop->getUserChannel(), $this->user->getId(), [
            'shop_id' => $shop->getShop()->getId(),
            'is_risk' => $shop->getShop()->getIsRisk(),
        ]);
        View::addFlash('channels', 'success', 'Risk setting successfully');
        throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
    }

    /** @throws */
    public function primaryShop()
    {
        if (empty($_POST))
            throw new RedirectException('/');
        $this->checkShopAccess($_POST['primary_shop'] ?? 0);
        /** @var $shop Shops */
        $shop = ChannelsModel::getUserChannel([
            'id' => $_POST['id'],
            'shop_id' => $_POST['primary_shop'],
            'is_active_warehouse' => 'any',
        ]);
        $params = HTTP::httpQueryToArray($_SESSION['channel_details']);
        if (!$shop || $shop->getChannelSupplier()->getChannelId() != $params['id'] || $shop->getChannelSupplier()->getSupplierId() != $params['supplier_id']) {
            View::addFlash('channels','danger', "Shop isn't found");
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        $shop->getChannelSupplier()->setPrimaryShopId($_POST['primary_shop']);
        $shop->getChannelSupplier()->save(['id', 'primary_shop_id']);
        ChannelHistories::setPrimaryShop($shop->getUserChannel(), $this->user->getId(), $this->warehouse_id, [
            'primary_shop_id' => $shop->getChannelSupplier()->getPrimaryShopId(),
        ]);
        View::addFlash('channels', 'success', 'Primary shop setting successfully');
        throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
    }

    /** @throws */
    public function deleteConnection()
    {
        if (empty($_GET))
            throw new RedirectException('/');
        $this->checkShopAccess(Request::getPositiveId(null, 'shop_id'));
        /** @var $shop Shops */
        $shop = ChannelsModel::getUserChannel([
            'id' => Request::getPositiveId(),
            'shop_id' => Request::getPositiveId(null, 'shop_id'),
            'is_active' => 1,
            'is_active_warehouse' => 'any',
        ]);
        if (!$shop) {
            View::addFlash('channels','danger', "You don't have an active connection to cancel");
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        $shop->getShop()->setIsActive(0);
        $shop->getUserChannel()->saveShop(['is_active']);
        ChannelHistories::disableConnection($shop->getUserChannel(), $this->user->getId(), [
            'shop_id' => $shop->getShop()->getId()
        ]);
        View::addFlash('channels', 'success', 'Connection disabled successfully');
        throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
    }

    /** @throws */
    public function shippingMapping()
    {
        if (empty($_GET) || empty($_GET['shop_id']) || empty($_GET['warehouse_id']))
            throw new RedirectException('/');
        $this->checkShopAccess($_GET['shop_id']);
        $shop = Shops::getById($_GET['shop_id']);
        if (empty($shop)) {
            View::addFlash('channels', 'danger', 'Shop not found');
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        $shop_warehouse = ShopWarehouses::getByShopWarehouse($shop->getId(), $_GET['warehouse_id']);
        if (empty($shop_warehouse)) {
            View::addFlash('channels', 'danger', 'Shop not found for this warehouse');
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        $mappings = Shippings::getAllChannelMappings($shop->getId(), $_GET['warehouse_id']);
        if (empty($mappings)) {
            View::addFlash('channels', 'danger', 'Shipping mapping not found');
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        $this->disableWarehouseChange();
        $channel_supplier = ChannelSuppliers::getById($shop->getChannelSupplierId());
        $channel = ChannelsModel::getById($channel_supplier->getChannelId());
        $supplier = SuppliersModel::getById($channel_supplier->getSupplierId());
        $credential = $shop->getCredentials();
        $shipping_services = Shippings::getAllServicesByWarehouseId($_GET['warehouse_id']);
        foreach ($shipping_services as $shipping_service_id => $shipping_service)
            $shipping_services[$shipping_service_id] = "{$shipping_service['ccode']} - {$shipping_service['name']}";
        $data = [
            'title' => View::toHtml($supplier->getName()) . '<br/>' . View::toHtml(ChannelsModel::getFullShopUrl($channel['name'], $credential['shop_url'])),
            'mappings' => $mappings,
            'shipping_services' => [0 => "Not mapped"] + $shipping_services,
            'channel_supplier' => $channel_supplier,
            'shop_id' => $shop->getId(),
            'selected_warehouse_id' => $shop_warehouse->getWarehouseId(),
            'terms' => Shippings::TERMS
        ];
        $this->render($data, 'channels/channel_shipping_mapping');
    }

    /** @throws */
    public function confirmShippingMapping()
    {
        if (empty($_POST) || empty($_POST['id']) || empty($_POST['shop_id']) || empty($_POST['warehouse_id']))
            throw new RedirectException('/');
        $mappingData = array_map('intval', $_POST);
        $this->checkShopAccess($mappingData['shop_id']);
        /** @var $shop Shop */
        $shop = Shops::getById($mappingData['shop_id']);
        $shop_mapping_ids = $shop ? array_map('intval', Arr::column(Shippings::getAllChannelMappings($shop->getId(), $mappingData['warehouse_id']), 'id')) : [];
        if (!$shop || !in_array($mappingData['id'], $shop_mapping_ids, true)) {
            View::addFlash('channels', 'danger', 'Access denied');
            throw new RedirectException('/channels?' . $_SESSION['channel_details']);
        }
        if (!Shippings::checkDiffMapping($mappingData['id'], $mappingData['shipping_services_id'], $mappingData['terms']))
            View::addFlash('channels', 'warning', 'Not changes');
        else {
            if (Shippings::updateMapping($mappingData['id'], $mappingData['shipping_services_id'], $mappingData['terms'])) {
                ChannelHistories::shippingMapping($shop->getId(), $_POST['warehouse_id'], $this->user->getId(), $mappingData);
                View::addFlash('channels', 'success', 'Success');
            }
            else
                View::addFlash('channels', 'danger', 'Error');
        }
        throw new RedirectException("/channels/shipping_mapping?shop_id={$shop->getId()}&warehouse_id={$_POST['warehouse_id']}");
    }

    /** @throws */
    public function updateShopWarehouse()
    {
        if (empty($_POST))
            return [];
        $warehouses = json_decode($_POST['warehouses'], true);
        $enable = json_decode($_POST['enable'], true);
        $is_active_keys = json_decode($_POST['is_active'], true);
        $orders_download = json_decode($_POST['orders_download'], true);
        $orders_approval = json_decode($_POST['orders_approval'], true);
        $location_keys = json_decode($_POST['location_id'], true);
        $is_retain_me = empty($_POST['is_retain_me']) ? [] : json_decode($_POST['is_retain_me'], true);
        $notify = empty($_POST['notify']) ? [] : json_decode($_POST['notify'], true);
        $count_enable = array_sum($enable);
        $count_active = 0;
        $locations = [];
        foreach ($enable as $key => $is_enable)
            if ($is_enable && $is_active_keys[$key]) {
                $count_active++;
                $locations[] = $location_keys[$key];
            }
        if (empty($count_active))
            return $this->response = ['error' => 'You must set active shop'];
        $this->checkShopAccess($_POST['shop_id'] ?? 0);
        /** @var Supplier $supplier */
        $supplier = Shops::getSupplier($_POST['shop_id']);
        if (empty($supplier))
            return [];
        switch (Shops::getChannelName($_POST['shop_id'])) {
            case ShopifyConnector::INTEGRATION_NAME:
                if ($count_active > 1 && !$supplier->getShopifyLocations())
                    return $this->response = ['error' => 'You cannot selected more one active shop (option "Utilise Shopify fulfilment locations" not used)'];
                if ($count_active > $count_enable)
                    return $this->response = ['error' => 'You selected more active shops than locations'];
                $locations = array_unique($locations);
                if (count($locations) != $count_active)
                    return $this->response = ['error' => 'Duplicate of locations is found'];
                break;
            case WooCommerceConnector::INTEGRATION_NAME:
            case MagentoConnector::INTEGRATION_NAME:
                if ($count_active > 1)
                    return $this->response = ['error' => 'You can select one active shop'];
                break;
            default:
                return [];
        }
        /** @var ShopWarehouse[] $shop_warehouses */
        $shop_warehouses = ShopWarehouses::getAllShopWarehouse($_POST['shop_id']);
        foreach ($warehouses as $key => $warehouse_id)
            if ($enable[$key]) {
                $diff = [];
                $is_active = (int)$is_active_keys[$key];
                if ((int)$shop_warehouses[$warehouse_id]->getIsActive() !== $is_active)
                    $diff['is_active'] = $is_active;
                $shop_warehouses[$warehouse_id]->setIsActive($is_active);
                $is_orders_approval_required = (int)$orders_approval[$key];
                if ((int)$shop_warehouses[$warehouse_id]->getIsOrdersApprovalRequired() !== $is_orders_approval_required)
                    $diff['is_orders_approval_required'] = $is_orders_approval_required;
                $shop_warehouses[$warehouse_id]->setIsOrdersApprovalRequired($is_orders_approval_required);
                $download_orders_flag = (int)$orders_download[$key];
                if ((int)$shop_warehouses[$warehouse_id]->getDownloadOrdersFlag() !== $download_orders_flag)
                    $diff['download_orders'] = $download_orders_flag;
                $shop_warehouses[$warehouse_id]->setDownloadOrdersFlag($download_orders_flag);
                if (isset($is_retain_me[$key])) {
                    $is_retain_me_flag = (int)$is_retain_me[$key];
                    if ((int)$shop_warehouses[$warehouse_id]->getIsRetainMe() !== $is_retain_me_flag)
                        $diff['is_retain_me'] = $is_retain_me_flag;
                    $shop_warehouses[$warehouse_id]->setIsRetainMe($is_retain_me_flag);
                }
                if (isset($notify[$key])) {
                    $notify_flag = (int)$notify[$key];
                    if ((int)$shop_warehouses[$warehouse_id]->getNotify() !== $notify_flag)
                        $diff['notify'] = $notify_flag;
                    $shop_warehouses[$warehouse_id]->setNotify($notify_flag);
                }
                $location_id = (int)$location_keys[$key];
                if ((int)$shop_warehouses[$warehouse_id]->getLocationId() !== $location_id && !is_null($location_id))
                    $diff['location_id'] = $location_id;
                $shop_warehouses[$warehouse_id]->setLocationId($location_id);
                $shop_warehouses[$warehouse_id]->save(['id', 'is_orders_approval_required', 'download_orders_flag', 'is_active', 'location_id', 'is_retain_me', 'notify']);
                if (!empty($diff))
                    ChannelHistories::updateShop($_POST['shop_id'], $supplier->getId(), $this->user->getId(), $warehouse_id, $diff);
            } elseif (!empty($shop_warehouses[$warehouse_id]) && $shop_warehouses[$warehouse_id]->getIsActive()) {
                $shop_warehouses[$warehouse_id]->setIsActive(0);
                $shop_warehouses[$warehouse_id]->save(['id', 'is_active']);
                ChannelHistories::updateShop($_POST['shop_id'], $supplier->getId(), $this->user->getId(), $warehouse_id, [
                    'is_active' => 0,
                ]);
            }
        return $this->response = ['success' => 'success'];
    }

    public function getOrder()
    {
        if (empty($_POST) || empty($_POST['shop_id']) || empty($_POST['order_id']))
            return $this->response = ['error' => 'Invalid information'];
        $this->checkShopAccess($_POST['shop_id']);
        /** @var $shop Shops */
        $shop = ChannelsModel::getUserChannel([
            'shop_id' => $_POST['shop_id'],
        ]);
        if (empty($shop))
            return $this->response = ['error' => 'Shop is not found'];
        $channel = ChannelsModel::getById($shop->getUserChannel()->getChannelId());
        switch ($channel['name']) {
            case ShopifyConnector::INTEGRATION_NAME:
                $processor = new ShopifyDataProcessor();
                $connector = ShopifyConnector::class;
                break;
            case WooCommerceConnector::INTEGRATION_NAME:
                $processor = new WooCommerceDataProcessor();
                $connector = WooCommerceConnector::class;
                break;
            case MagentoConnector::INTEGRATION_NAME:
                $processor = new MagentoDataProcessor();
                $connector = MagentoConnector::class;
                break;
            default:
                return $this->response = ['error' => 'Channel is under construction'];
        }
        $processor->setUserChannel($shop);
        $processor->setConnector(new $connector($shop->getShop()->getCredentials()));
        $supplier = SuppliersModel::getById($shop->getChannelSupplier()->getSupplierId());
        if (empty($supplier) || $supplier->getDel() == Supplier::LOCKED)
            return $this->response = ['error' => 'Supplier is not found or locked'];
        $processor->setSupplier($supplier);
        $order = $processor->getOrder(urlencode($_POST['order_id']));
        if (empty($order))
            return $this->response = ['error' => 'Order is not found'];
        return $this->response = ['response' => json_decode($order, true)];
    }

    /** @throws */
    public function connect()
    {
        if (empty($_POST))
            throw new RedirectException('/');
        if (empty($_POST['shop_url'])) {
            View::addFlash('channels', 'danger', 'Please provide shop URL');
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        $shop_url = trim($_POST['shop_url']);
        $channel = ChannelsModel::getById($_POST['channel_id']);
        $user = empty($_POST['user_id']) ? $this->user : UsersModel::getById($_POST['user_id']);
        // the channel owner must be one of the selected supplier's users (the form only offers those)
        if (!empty($_POST['user_id']) && !in_array((int)$_POST['user_id'], array_map('intval', Arr::column(UsersModel::getUserBySupplierId((int)($_POST['supplier_id'] ?? 0)), 'id')), true))
            $user = null;
        if (empty($user)) {
            View::addFlash('channels', 'danger', 'User is not found');
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        /** @var $shop Shops */
        $shop = ChannelsModel::getUserChannel([
            'shop_url' => $shop_url,
            'is_active' => 'any'
        ]);
        if ($shop !== null) {
            View::addFlash('channels', 'danger', "Connection to the '{$shop_url}' already exists!");
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        unset($_POST['channel_id'], $_POST['user_id']);
        $connectorName = ChannelsModel::getConnectorName($channel);
        /** @var BaseChannelConnector $Connector */
        $Connector = new $connectorName($_POST);
        $Connector->checkConnection();
        if (!empty($Connector->connection_errors)) {
            View::addFlash('channels', 'danger', $Connector->connection_errors);
            throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
        }
        $user_channel = new UserChannel();
        $user_channel->setChannelId($channel['id']);
        $user_channel->setSupplierId($_POST['supplier_id']);
        $user_channel->setUserId($user->getId());
        $user_channel->setIsActive(1);
        $user_channel->setIsRisk(1);
        $user_channel->setWarehouseId($this->user->getWarehouseId());
        $user_channel->setTimeProducts(WithDate::startDay(0));
        // WooCommerce
        if ($Connector instanceof WooCommerceConnector)
            $user_channel->setTimeOrders(WithDate::startDay(time() - 60 * 60 * 24 * 30));
        $user_channel->setCredentials($Connector->getCredentials()->toJson());
        $user_channel->save();
        ChannelHistories::connectUserChannel($user_channel);
        View::addFlash('channels', 'success', 'Connection completed successfully');
        throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
    }

    /** @throws */
    public function syncLocations()
    {
        if (empty($_GET) || empty($_GET['shop_id']) || empty($_GET['warehouse_id'])) {
            View::addFlash('channels', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $this->checkShopAccess($_GET['shop_id']);
        /** @var Supplier $supplier */
        $supplier = Shops::getSupplier($_GET['shop_id']);
        if (empty($supplier)) {
            View::addFlash('channels', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        /** @var $shop Shops */
        $shop = ChannelsModel::getUserChannel([
            'shop_id' => $_GET['shop_id'],
            'warehouse_id' => $_GET['warehouse_id'],
            'is_active' => 'any',
            'is_active_warehouse' => 'any',
        ]);
        if (empty($shop)) {
            View::addFlash('channels', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $shop->getShopWarehouse()->setCache('[]');
        $processor = new ShopifyDataProcessor();
        $processor->setUserChannel($shop);
        $processor->setConnector(new ShopifyConnector($shop->getShop()->getCredentials()));
        $processor->setSupplier($supplier);
        $processor->getLocations();
        ChannelHistories::syncLocations($shop->getUserChannel(), $_GET['warehouse_id'], $this->user->getId(), [
            'shop_id' => $shop->getShop()->getId(),
            'locations' => $processor->getUserChannel()->getShopWarehouse()->getCache(),
        ]);
        throw new RedirectException('/channels/channel_details/?' . $_SESSION['channel_details']);
    }

    public function updateConnection()
    {
        if (empty($_POST))
            return [];
        $shop_id = (int)($_POST['shop_id'] ?? 0);
        $type_apps = (int)($_POST['type_apps'] ?? 0);
        if (!isset(self::SHOPIFY_APPS_FIELDS[$type_apps]))
            return $this->response = ['error' => 'Type of Apps must be Private or Custom or Oauth'];
        $this->checkShopAccess($shop_id);
        $shop = Shops::getById($shop_id);
        if (empty($shop))
            return $this->response = ['error' => 'Shop is not found'];
        // Secrets are not sent to the page (ADR-002 Phase 2 step 4c): an empty field keeps the stored value.
        // Fields of the other types of apps are removed.
        $credentials = $shop->getCredentials();
        $new_credentials = $credentials;
        $new_credentials['type_apps'] = $type_apps;
        foreach (self::SHOPIFY_APPS_FIELDS as $type => $fields)
            foreach ($fields as $field) {
                $value = trim((string)($_POST[$field] ?? ''));
                if ($type !== $type_apps)
                    unset($new_credentials[$field]);
                elseif ($value !== '')
                    $new_credentials[$field] = $value;
            }
        foreach (self::SHOPIFY_APPS_FIELDS[$type_apps] as $field)
            if (empty($new_credentials[$field]))
                return $this->response = ['error' => self::SHOPIFY_APPS_ERRORS[$type_apps]];
        if ($new_credentials == $credentials)
            return $this->response = ['error' => 'Nothing to changing'];
        $shop->setCredentials($new_credentials);
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $shop->save(['id', 'credentials']);
        ChannelHistories::updateShopConnection($shop, $this->user->getId(), $this->warehouse_id);
        $mysql->commit();
        return $this->response = ['success' => 'success'];
    }
}