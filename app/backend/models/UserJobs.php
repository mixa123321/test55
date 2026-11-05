<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ASN;
use app\core\entities\FileJob;
use app\core\entities\Order;
use app\core\entities\Shipping;
use app\core\entities\SKU;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\entities\UserJob;
use app\core\entities\Warehouse;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\Warehouses;
use app\core\statical\Timezones;

class UserJobs extends \app\core\models\UserJobs
{
    public const LIMIT_PER_PAGE = 10;
    private const FILTER_COLUMNS = ['addition'];

    public static function hasUserQueue(int $user_id): bool
    {
        return (boolean)(new SafeMysql())->fetchScalar("SELECT 1 FROM user_jobs WHERE user_id = :user_id", ['user_id' => $user_id]);
    }

    public static function getList(Warehouse $warehouse, $current_page = 1, $filters = []): array
    {
        $current_page = Request::getPage(null, $current_page);
        $whereSql = self::handleWhereSql($warehouse, $filters);
        if (!empty($whereSql))
            $whereSql = ' WHERE ' . $whereSql;
        $sql = "SELECT uj.*,
                       (SELECT count(ujn.id) FROM user_job_notifications ujn WHERE ujn.user_job_id = uj.id) as notifications,
                       u.login as user
                FROM user_jobs uj 
                    JOIN users u ON u.id = uj.user_id
                    JOIN persons p ON uj.user_id = p.user_id AND p.lc_id = :lc_id
                $whereSql
                ORDER BY uj.id DESC 
                LIMIT " . self::LIMIT_PER_PAGE . ' OFFSET ' . self::LIMIT_PER_PAGE * ($current_page - 1);
        $user_jobs = (new SafeMysql())->fetchAll($sql, [
            'lc_id' => $warehouse->getLCId()
        ]);
        array_walk($user_jobs, function (&$user_job) {
            self::handleUserJob($user_job);
        });
        $total = (new SafeMysql())->fetchScalar("
            SELECT count(uj.id)
            FROM user_jobs uj
                JOIN persons p ON uj.user_id = p.user_id AND p.lc_id = :lc_id
            $whereSql", [
                'lc_id' => $warehouse->getLCId()
        ]);
        return ['user_jobs' => $user_jobs, 'total' => $total];
    }

    public static function getListByUserId(Warehouse $warehouse, int $user_id, $current_page = 1, $filters = []): array
    {
        $current_page = Request::getPage(null, $current_page);
        $where_sql = self::handleWhereSql($warehouse, $filters);
        if (!empty($where_sql))
            $where_sql = ' AND ' . $where_sql;
        $sql = "SELECT uj.*,
                       (SELECT count(ujn.id) FROM user_job_notifications ujn WHERE ujn.user_job_id = uj.id) as notifications
                FROM user_jobs uj
                WHERE uj.user_id = :user_id $where_sql 
                ORDER BY uj.id DESC
                LIMIT " . self::LIMIT_PER_PAGE . ' OFFSET ' . self::LIMIT_PER_PAGE * ($current_page - 1);
        $user_jobs = (new SafeMysql())->fetchAll($sql, [
            'user_id' => $user_id
        ]);
        array_walk($user_jobs, function (&$user_job) {
            self::handleUserJob($user_job);
        });
        $total = (new SafeMysql())->fetchScalar("SELECT count(uj.id) FROM user_jobs uj WHERE uj.user_id = :user_id $where_sql", [
            'user_id' => $user_id
        ]);
        return ['user_jobs' => $user_jobs, 'total' => $total];
    }

    private static function handleWhereSql(Warehouse $warehouse, $filters): string
    {
        $data = [];
        foreach ($filters as $filter => $value) {
            switch ($filter) {
                case 'id':
                    $id = (int)$value;
                    if ($id > 0)
                        $data[] = "uj.id = $id";
                    break;
                case 'type':
                    $value = (int)$value;
                    if ($value && array_key_exists($value, UserJob::TYPES))
                        $data[] = "uj.type = '" . UserJob::TYPES[$value] . "'";
                    break;
                case 'status':
                    $value = (int)$value;
                    if ($value && array_key_exists($value, UserJob::STATUSES))
                        $data[] = "uj.status = '" . UserJob::STATUSES[$value] . "'";
                    break;
                case 'create_date':
                case 'update_date':
                    if (!empty($value)) {
                        $time_diff = !empty($warehouse) ? Timezones::getDiff($warehouse->getTimezone()) : 0;
                        $date_format = '%Y-%m-%d';
                        if (empty($time_diff))
                            $data[] = "DATE_FORMAT(uj.{$filter}, '{$date_format}') = '" . addslashes($value) . "'";
                        else {
                            $date_format .= ' %H:%i:%s';
                            $data[] = "DATE_FORMAT(uj.{$filter}, '{$date_format}') 
                                        BETWEEN '" . addslashes(Timezones::getDiff($warehouse->getTimezone(), $value . ' 00:00:00', TRUE))
                                    . "' AND '" . addslashes(Timezones::getDiff($warehouse->getTimezone(), $value . ' 23:59:59', TRUE)) . "'";
                        }
                    }
                    break;
                case 'user_id':
                    $user_id = (int)$value;
                    if ($user_id > 0)
                        $data[] = "uj.user_id = $user_id";
                    break;
                default:
                    if (!empty($value) && in_array($filter, self::FILTER_COLUMNS, true))
                        $data[] = "uj.$filter = '" . addslashes($value) . "'";
            }
        }
        return !empty($data) ? ('(' . implode(') AND (' , $data) . ')') : '';
    }

    public static function getAsyncData(array $user_job_ids, $user_id, $init = false, $admin = false): array
    {
        $active_ids = !$init ? (new SafeMysql())->fetchArrayOneField('SELECT id FROM user_jobs' .
                                                                    (!$admin ? ' WHERE user_id = :user_id' : ''),
                                                                    !$admin ? ['user_id' => $user_id] : []) : [];
        $whereArr = [];
        $params = [];
        if (empty($user_job_ids))
            return [[], $active_ids];
        $whereArr[] = ' uj.id in (:user_job_ids)';
        $params['user_job_ids'] = $user_job_ids;
        if (!$admin) {
            $whereArr[] = ' uj.user_id = :user_id';
            $params['user_id'] = $user_id;
        }
//        if (!$init) {
//            $whereArr[] = "uj.update_date >= '" . strtotime(time() - 60)."'";
//        }
        $whereSQl = '';
        if (!empty($whereArr))
            $whereSQl = 'WHERE ' . implode(' AND ', $whereArr);
        $user_jobs = (new SafeMysql())->fetchAll("SELECT *,
                                                (SELECT count(ujn.id) FROM user_job_notifications ujn WHERE ujn.user_job_id = uj.id) as notifications
                                                FROM user_jobs uj " . $whereSQl, $params);
        $res = [];
        foreach ($user_jobs as $user_job) {
            self::handleUserJob($user_job);
            $res[$user_job['id']] = [
//                'update_date' => date('jS M Y H:i', strtotime($user_job['update_date'])),
                'update_date' => $user_job['update_date'],
                'status' => $user_job['status'],
                'style' => self::handleStatusColor($user_job['status']),
                'progress' => round($user_job['progress'], 0),
                'notifications' => $user_job['notifications'],
                'download' => self::handleDownload($user_job),
            ];
        }
        return [$res, $active_ids];
    }

    private static function handleStatusColor($user_job_status): string
    {
        $style = 'light';
        switch ($user_job_status) {
            case UserJob::STATUS_NEW:
                $style = 'primary';
                break;
            case UserJob::STATUS_PROGRESS:
                $style = 'warning';
                break;
            case UserJob::STATUS_ERROR:
            case UserJob::STATUS_ABORT:
                $style = 'danger';
                break;
            case UserJob::STATUS_DONE:
                $style = 'success';
                break;
        }
        return $style;
    }

    private static function handleUserJob(array &$user_job): void
    {
        $user_job['progress'] = self::handleProgress($user_job);
        $user_job['addition'] = self::handleAddition($user_job);
        $user_job['download'] = self::handleDownload($user_job);
    }

    public static function handleProgress($user_job)
    {
        $progress = 0;
        switch ($user_job['status']) {
            case UserJob::STATUS_DONE:
                $progress = 100;
                break;
            case UserJob::STATUS_ERROR:
            case UserJob::STATUS_ABORT:
            case UserJob::STATUS_PROGRESS:
                $progress = self::getProgress($user_job);
                break;
        }
        if ($progress > 100)
            $progress = 100;
        return $progress;
    }

    public static function getProgress($user_job)
    {
        switch ($user_job['type']) {
            case UserJob::TYPE_UPLOAD_ORDER:
                $file_jobs = FileJobs::getListByUserJobId($user_job['id']);
                if (empty($file_jobs))
                    return 0;
                $count = count($file_jobs);
                $is_done = 0;
                foreach ($file_jobs as $file_job)
                    if (!empty($file_job['is_done']))
                        $is_done++;
                return ($is_done * 100) / $count;
            case UserJob::TYPE_ORDERS_BULK_CANCEL:
                $addition = json_decode($user_job['addition'], true);
                return $addition['progress'];
            case UserJob::TYPE_GENERATE_ORDER_CSV:
            case UserJob::TYPE_GENERATE_SKU_CSV:
            case UserJob::TYPE_GENERATE_ASN_CSV:
            case UserJob::TYPE_GENERATE_EXPIRED_BATCHES_CSV:
            case UserJob::TYPE_GENERATE_BUNDLES_CSV:
            case UserJob::TYPE_GENERATE_ASN_RETURN_CSV:
            case UserJob::TYPE_GENERATE_LOCATION_HISTORY_CSV:
                $addition = json_decode($user_job['addition'], 1);
                return (($addition['processed'] ?? 0) * 100) / ($addition['count'] ?? 1);
        }
        return 0;
    }

    private static function handleAddition($user_job): string
    {
        $addition_res = '';
        if (!empty($user_job['addition'])) {
            $addition = json_decode($user_job['addition'], 1);
            switch ($user_job['type']) {
                case UserJob::TYPE_UPLOAD_ORDER:
                    if (!empty($addition)) {
                        foreach ($addition as $key => $item) {
                            if (is_array($item))
                                $item = implode('; ', $item);
                            if ($key === 'warehouse_id')
                                $addition_res .= "<b><b>warehouse:</b></b> " . View::toHtml(self::handleWarehouse($addition['warehouse_id'])) . " <br>";
                            else
                                $addition_res .= '<b><b>' . View::toHtml($key) . ':</b></b> ' . View::toHtml($item) . '<br>';
                        }
                    } else
                        $addition_res = '';
                    break;
                case UserJob::TYPE_GENERATE_ORDER_CSV:
                case UserJob::TYPE_GENERATE_SKU_CSV:
                case UserJob::TYPE_GENERATE_ASN_CSV:
                case UserJob::TYPE_GENERATE_EXPIRED_BATCHES_CSV:
                case UserJob::TYPE_GENERATE_BUNDLES_CSV:
                case UserJob::TYPE_GENERATE_ASN_RETURN_CSV:
                case UserJob::TYPE_GENERATE_LOCATION_HISTORY_CSV:
                    $addition_res .= self::handleFilters($addition, $user_job);
                    break;
                case UserJob::TYPE_SKUS_BULK_UPDATE:
                    $addition_res .= "<b><b>fields:</b></b><br>";
                    if (!empty($addition['fields']))
                        foreach ($addition['fields'] as $key => $field)
                            $addition_res .= '<b>' . View::toHtml($key) . ':</b>' . View::toHtml(is_array($field) ? implode('; ', $field) : $field) . '<br>';
                    break;
            }
        }
        return $addition_res;
    }

    public static function handleDownload($user_job)
    {
        if ($user_job['type'] === UserJob::TYPE_SKUS_BULK_UPDATE) {
            $fileJob = FileJobs::getFirstByUserJobId($user_job['id']);
            if (!empty($fileJob) && !empty($fileJob->getPath()))
                return $fileJob->getId();
        }
        if ($user_job['status'] !== UserJob::STATUS_DONE)
            return null;
        if (in_array($user_job['type'],[
                                            UserJob::TYPE_GENERATE_ORDER_CSV,
                                            UserJob::TYPE_GENERATE_SKU_CSV,
                                            UserJob::TYPE_GENERATE_ASN_CSV,
                                            UserJob::TYPE_GENERATE_ASN_RETURN_CSV,
                                            UserJob::TYPE_GENERATE_EXPIRED_BATCHES_CSV,
                                            UserJob::TYPE_GENERATE_BUNDLES_CSV,
                                            UserJob::TYPE_GENERATE_LOCATION_HISTORY_CSV,
                                        ])) {
            /** @var $fileJob FileJob|null */
            $fileJob = (FileJobs::getByUserJobId($user_job['id']))[0] ?? null;
            if (!empty($fileJob) && !empty($fileJob->getPath()) && $fileJob->getIsDone())
                return $fileJob->getId();
        }
        return null;
    }

    public static function getObjectsByUserId($user_id)
    {
        $user_jobs = (new SafeMysql())->fetchAll("SELECT * FROM user_jobs WHERE user_id = :user_id", ['user_id' => $user_id]);
        $res = [];
        if (empty($user_jobs))
            return $res;
        foreach ($user_jobs as $user_job)
            $res[$user_job['id']] = new UserJob($user_job);
        return $res;
    }

    public static function remove($user_job_id)
    {
        return (new SafeMysql())->execute("DELETE FROM user_jobs WHERE id = :id", ['id' => (int)$user_job_id]);
    }

    public static function clearByUserId($user_id, $all = true)
    {
        if ($all) {
            return (new SafeMysql())->execute("DELETE FROM user_jobs WHERE user_id = :user_id", ['user_id' => (int)$user_id]);
        } else {
            return (new SafeMysql())->execute("DELETE FROM user_jobs WHERE user_id = :user_id AND status = :status", [
                'user_id' => (int)$user_id,
                'status' => UserJob::STATUS_DONE,
            ]);
        }
    }

    private static function handleFilters($addition, $user_job)
    {
        $addition_res = '';
        /**  @var $user User|null */
        $user = Users::getById($user_job['user_id']);
        $filters = $addition['filters'] ?? [];
        $view_filter_arr = [];
        if (!empty($user) && !empty($filters)) {
            $addition_res .= "<b><b>filters:</b></b><br>";
            if (!empty($filters['date_type']) && empty($filters['date_from']) && empty($filters['date_to']))
                unset($filters['date_type']);
            foreach ($filters as $filter => $value) {
                if ($filter === 'date_from' || $filter === 'date_to')
                    self::handleDateFormat($user_job, $value);
                if ($filter === 'status')
                    self::handleFilterStatus($user_job, $value);
                if ($filter === 'state')
                    self::handleFilterState($user_job, $value);
                if ($filter === 'warehouse_id') {
                    $filter = 'warehouse';
                    $value = self::handleWarehouse($value);
                }
                if ($filter === 'location_id') {
                    $filter = 'location';
                    $location = Locations::getByIds([(int)$value]);
                    $value = $location[(int)$value]['name'] ?? $value;
                }
                if ($filter === 'shipping_services_id') {
                    $filter = 'shipping_service';
                    $value = self::handleShippingService($value);
                }
                if ($filter === 'actioned') {
                    self::handleFilterActioned($user_job, $value);
                }
                switch ($user->getRoleId()) {
                    case Roles::MANAGER:
                    case Roles::SA:
                    case Roles::SUPPLIER_ADMIN:
                    case Roles::WORKER:
                        if ($filter === 'supplier_id' || $filter === 'supplier_ids') {
                            $filter = 'suppliers';
                            if (is_array($value))
                                $value = 'all available';
                            elseif ((int) $value > 0)
                                self::handleFilterSupplier($value);
                            else
                                $value = '';
                        }
                        break;
                    case Roles::SUPPLIER_USER:
                        if ($filter === 'supplier_id') {
                            $filter = 'suppliers';
                            if ((int) $value > 0)
                                self::handleFilterSupplier($value);
                            else
                                $value = '';
                        }
                }
                if (!empty($value))
                    $view_filter_arr[str_replace('_', ' ', $filter)] = str_replace('_', ' ', $value);
            }
        }
        if (!empty($view_filter_arr)) {
            foreach ($view_filter_arr as $key => $item) {
                if (is_array($item))
                    $item = implode('; ', $item);
                $addition_res .= '<b>' . View::toHtml($key) . ':</b> ' . View::toHtml($item) . ' <br>';
            }
        }
        return $addition_res;
    }

    /**
     * @param $warehouse_id
     * @return string
     */
    private static function handleWarehouse($warehouse_id): string
    {
        $warehouse = Warehouses::getById($warehouse_id);
        return !empty($warehouse) ? $warehouse->getName() : '';
    }

    private static function handleDateFormat($user_job, &$value)
    {
        switch ($user_job['type']) {
            case UserJob::TYPE_GENERATE_ORDER_CSV:
                $value = date('jS M Y', strtotime($value));
                break;
        }
    }

    private static function handleShippingService($shipping_service_id): string
    {
        static $shipping_services = [];
        if ($shipping_service_id === 'all')
            return $shipping_service_id;
        if (!isset($shipping_services[$shipping_service_id])) {
            if ($shipping_service_id == Shipping::COLLECTION_BY_CUSTOMER_ID)
                $shipping_services[$shipping_service_id] = Shipping::COLLECTION_BY_CUSTOMER;
            else
                $shipping_services[$shipping_service_id] = ShippingServices::getNameWithCarrierCodeById($shipping_service_id);
        }
        return $shipping_services[$shipping_service_id];
    }

    /**
     * @param $value
     * @return void
     */
    private static function handleFilterSupplier(&$value): void
    {
        /**  @var $supplier Supplier|null */
        $supplier = Suppliers::getById((int) $value);
        $value = method_exists($supplier, 'getName') ? $supplier->getName() : null;
    }

    /**
     * @param $user_job
     * @param $value
     * @return void
     */
    private static function handleFilterStatus($user_job, &$value): void
    {
        switch ($user_job['type']) {
            case UserJob::TYPE_GENERATE_ORDER_CSV:
                $value = Order::getStateName($value);
                break;
            case UserJob::TYPE_GENERATE_SKU_CSV:
                break;
            case UserJob::TYPE_GENERATE_ASN_CSV:
                if ($value !== 'all')
                    $value = ASN::getStateName($value);
                break;
            case UserJob::TYPE_GENERATE_ASN_RETURN_CSV:
                if ($value !== 'all')
                    $value = ASN::getStateNameReturn($value);
                break;
        }
    }

    /**
     * @param $user_job
     * @param $value
     * @return void
     */
    private static function handleFilterState($user_job, &$value): void
    {
        switch ($user_job['type']) {
            case UserJob::TYPE_GENERATE_ASN_CSV:
            case UserJob::TYPE_GENERATE_ORDER_CSV:
                break;
            case UserJob::TYPE_GENERATE_SKU_CSV:
                if ($value == '-1')
                    $value = 'Any';
                else
                    $value = SKU::getStateName($value) ?? '';
                break;
        }
    }

    private static function handleFilterActioned($user_job, &$value) {
        switch ($user_job['type']) {
            case UserJob::TYPE_GENERATE_ASN_RETURN_CSV:
                if ($value !== 'all')
                    $value = $value ? 'Yes' : 'No';
                break;
        }
    }

    public static function getUserList(int $lc_id): array
    {
        return (new SafeMysql())->fetchAll("
            SELECT DISTINCT u.id, u.login
            FROM user_jobs uj
                JOIN users u ON uj.user_id = u.id
                JOIN persons p ON u.id = p.user_id AND p.lc_id = :lc_id", ['lc_id' => $lc_id]);
    }
}
