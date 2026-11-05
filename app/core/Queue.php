<?php

namespace app\core;

use app\backend\models\Batches;
use app\backend\models\Bundles;
use app\backend\models\Upload;
use app\backend\models\Webhooks;
use app\core\entities\Job;
use app\core\entities\JobData;
use app\core\entities\UserJob;
use app\core\handlers\SKUsBulk;
use app\core\models\Channels;
use app\core\models\Jobs;
use app\core\models\Orders;
use app\backend\models\Orders as OrdersBackend;
use app\backend\models\LocationHistoryReport;
use app\backend\models\SKUs as SKUsBackend;
use app\backend\models\ASNs as ASNsBackend;
use app\backend\models\ReturnASNs;
use app\core\models\Shops;
use app\core\models\UserJobs;
use robot\tasks\channels\ChannelRobot;
use app\core\helpers\Debug;
use app\core\models\FileJobs;

class Queue
{
    public static function execute($classes = [], $including = true)
    {
        $cronJobs = Jobs::getNewJobs();
        if (empty($cronJobs))
            return;
        try {
            /** @var Job $CronJob */
            foreach ($cronJobs as $CronJob)
                self::processingJob($CronJob, $classes, $including);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private static function processingJob(Job $CronJob, $classes, $including)
    {
        if (Jobs::changeStatus($CronJob, Jobs::STATUS_NEW, Jobs::STATUS_TOUCH) === false) {
            return $CronJob->getId();
        }
        $JobData = $CronJob->getJobData();
        $func_name = "{$JobData->getClass()}::{$JobData->getMethod()}";

        if (!empty($classes))
            if (($including && !in_array($func_name, $classes)) || (!$including && in_array($func_name, $classes))) {
                Jobs::setStatus($CronJob, Jobs::STATUS_NEW);
                return $CronJob->getId();
            }
        if (!Jobs::getStatusJob($CronJob->getId(), Jobs::STATUS_TOUCH))
            return $CronJob->getId();
        try {
            Jobs::setStatus($CronJob, Jobs::STATUS_IN_PROCESS);
            $result = call_user_func_array($func_name, $JobData->getParams());
            if (!empty($result['error']))
                throw new \Exception($result['error']);
        } catch (\Exception $e) {
            Jobs::setStatus($CronJob, Jobs::STATUS_ERROR, $e->getMessage());
            Debug::sendDebugInfo('Error processingJob. Job ' . $CronJob->getId(), $e->getMessage(), Debug::TEST);
            return false;
        }
        Jobs::setStatus($CronJob, Jobs::STATUS_COMPLETE, $result['success']);
        return true;
    }
//
//    public static function addJobToSetInventoryLevel($data)
//    {
//        if (empty($data['asn_id']) && empty($data['order_id']))
//            return;
//        $obj = !empty($data['asn_id']) ? ASNs::getById($data['asn_id']) : Orders::getById($data['order_id']);
//        if (empty($obj))
//            return;
//        $channels = ChannelSuppliers::getChannelsBySupplier($obj->getSupplierId(), $obj->getWarehouseId());
//        if (empty($channels))
//            return;
//        $JobData = new JobData();
//        $JobData->setClass(ChannelRobot::class);
//        $JobData->setMethod('run');
//        foreach ($channels as $channelName) {
//            $JobData->setParams(['channel_name' => $channelName, 'command' => ChannelRobot::SET_INVENTORY_LEVEL_COMMAND, 'params' => $data]);
//            Jobs::addNewJob($JobData);
//        }
//    }
//
//    public static function addJobToSetInventoryLevelSKU($data)
//    {
//        if (empty($data['SKU_id']) && empty($data['warehouse_id']))
//            return;
//        $SKU = SKUs::getById($data['SKU_id']);
//        if (empty($SKU))
//            return;
//        $channels = ChannelSuppliers::getChannelsBySupplier($SKU->getSupplierId(), $data['warehouse_id']);
//        if (empty($channels))
//            return;
//        $JobData = new JobData();
//        $JobData->setClass(ChannelRobot::class);
//        $JobData->setMethod('run');
//        foreach ($channels as $channelName) {
//            $JobData->setParams(['channel_name' => $channelName, 'command' => ChannelRobot::SET_INVENTORY_LEVEL_COMMAND, 'params' => $data]);
//            Jobs::addNewJob($JobData);
//        }
//    }

    public static function addJobToSetTrackInfo($data)
    {
        $order = Orders::getById($data['order_id']);
        if ($order === null || empty($order->getShopId()))
            return;
        /** @var Shops $shops */
        $shops = Channels::getUserChannel([
            'shop_id' => $order->getShopId(),
            'warehouse_id' => $order->getWarehouseId(),
        ]);
        if (!$shops)
            return;
        $channel = Channels::getById($shops->getChannelSupplier()->getChannelId());
        if (empty($channel['name']))
            return;
        $JobData = new JobData();
        $JobData->setClass(ChannelRobot::class);
        $JobData->setMethod('run');
        $JobData->setParams(['channel_name' => $channel['name'], 'command' => ChannelRobot::SET_TRACK_INFO_COMMAND, 'params' => $data]);
        Jobs::addNewJob($JobData);
    }

    public static function addJobToUpdateOrderSearch(array $data)
    {
        if ((int)$data['order_id'] > 0) {
            $JobData = new JobData();
            $JobData->setClass(Orders::class);
            $JobData->setMethod('updateSearchTableByOrderId');
            $JobData->setParams($data);
            Jobs::addNewJob($JobData);
        }
    }

    public static function addJobToWebhook(array $data)
    {
        if (empty($data['webhook']) || empty($data['order_id']))
            return;
        if (!in_array($data['webhook'], get_class_methods(Webhooks::class)))
            return;
        $JobData = new JobData();
        $JobData->setClass(Webhooks::class);
        $JobData->setMethod($data['webhook']);
        $JobData->setParams([$data['order_id'], $data['additional'] ?? null]);
        Jobs::addNewJob($JobData);
    }

    public static function addJobToSplitFile(array $data)
    {
        if (!empty($data['file']) && !empty($data['type']) && !empty($data['user_id']) && !empty($data['warehouse_id'])) {
            $JobData = new JobData();
            $JobData->setClass(Upload::class);
            $JobData->setMethod('handleOrderFileFromQueue');
            $filenameArr = explode(DIRECTORY_SEPARATOR, $data['file']);
            $data['user_job_id'] = UserJobs::addNew($data['user_id'],
                UserJob::TYPE_UPLOAD_ORDER,
                ['file name' => array_pop($filenameArr), 'warehouse_id' => $data['warehouse_id']]);
            unset($data['user_id']);
            $JobData->setParams($data);

            FileJobs::addNew($data['file'], $data['user_job_id']);

            if (is_null(Jobs::addNewJob($JobData)))
                return null;
            return $data['user_job_id'];
        }
        return null;
    }

    public static function addJobToOrderBulkCancel(array $data)
    {
        if (empty($data['user_id']) || empty($data['warehouse_id']) || empty($data['order_ids']))
            return null;
        $JobData = new JobData();
        $JobData->setClass(Orders::class);
        $JobData->setMethod('bulkCancel');
        $user_job_id = UserJobs::addNew($data['user_id'],UserJob::TYPE_ORDERS_BULK_CANCEL, ['order_ids' => $data['order_ids'], 'warehouse_id' => $data['warehouse_id']]);
        $JobData->setParams(['user_job_id' => $user_job_id]);
        if (is_null(Jobs::addNewJob($JobData)))
            return null;
        return $user_job_id;
    }

    public static function addJobToHandleFileOrders(array $data)
    {
        if (!empty($data['file']) && !empty($data['user_job_id']) && !empty($data['warehouse_id'])) {
            $JobData = new JobData();
            $JobData->setClass(Upload::class);
            $JobData->setMethod('handleOrderFileFromQueue');
            $JobData->setParams($data);
            Jobs::addNewJob($JobData);
        }
    }

    public static function addJobToGenerateReportCSV(array $data, $section)
    {
        if (empty($data['user_id']) || empty($data['filters']) || empty($data['count']))
            return null;
        $JobData = new JobData();
        list($className, $type) = self::getReportType($section);
        if (!$className || !$type)
            return null;
        $JobData->setClass($className);
        $JobData->setMethod('generateReportCSV');
        $params = [
            'count' => $data['count'],
            'filters' => array_filter($data['filters'], function ($el) {
                return is_string($el) ? trim($el) !== '' : !empty($el);
            })
        ];
        unset($data['filters'], $data['count']);
        if (isset($data['addition'])) {
            $params['addition'] = $data['addition'];
            unset($data['addition']);
        }
        $data['user_job_id'] = UserJobs::addNew($data['user_id'], $type, $params);
        unset($data['user_id']);
        $JobData->setParams($data);
        if (is_null(Jobs::addNewJob($JobData)))
            return null;
        return $data['user_job_id'];
    }

    public static function addJobToHandleReportCSV(array $data, $section)
    {
        if (!empty($data['user_job_id'])) {
            $JobData = new JobData();
            list($className, ) = self::getReportType($section);
            if (!$className)
                return null;
            $JobData->setClass($className);
            $JobData->setMethod('handleReportCSV');
            $JobData->setParams($data);
            if (is_null(Jobs::addNewJob($JobData)))
                return null;
        }
        return null;
    }

    /**
     * @param $section
     * @return array
     */
    private static function getReportType($section): array
    {
        $section = explode(DIRECTORY_SEPARATOR, str_replace('\\', DIRECTORY_SEPARATOR, $section));
        $section = array_pop($section);
        $className = null;
        $type = null;
        switch ($section) {
            case 'Orders':
                $className = OrdersBackend::class;
                $type = UserJob::TYPE_GENERATE_ORDER_CSV;
                break;
            case 'SKUs':
                $className = SKUsBackend::class;
                $type = UserJob::TYPE_GENERATE_SKU_CSV;
                break;
            case 'GoodsIn':
            case 'ASNs':
                $className = ASNsBackend::class;
                $type = UserJob::TYPE_GENERATE_ASN_CSV;
                break;
            case 'ReturnASNs':
                $className = ReturnASNs::class;
                $type = UserJob::TYPE_GENERATE_ASN_RETURN_CSV;
                break;
            case 'Batches':
                $className = Batches::class;
                $type = UserJob::TYPE_GENERATE_EXPIRED_BATCHES_CSV;
                break;
            case 'Bundles':
                $className = Bundles::class;
                $type = UserJob::TYPE_GENERATE_BUNDLES_CSV;
                break;
            case 'LocationHistoryReport':
                $className = LocationHistoryReport::class;
                $type = UserJob::TYPE_GENERATE_LOCATION_HISTORY_CSV;
                break;
        }
        return array($className, $type);
    }

    public static function addJobToBulkUpdateSKU(array $data)
    {
        if (empty($data['user_id']) || empty($data['warehouse_id']) || empty($data['fields']))
            return null;
        $JobData = new JobData();
        $JobData->setClass(SKUsBulk::class);
        $JobData->setMethod('handleUpdate');
        $user_job_id = UserJobs::addNew($data['user_id'],UserJob::TYPE_SKUS_BULK_UPDATE, [
            'warehouse_id' => $data['warehouse_id'], 'fields' => $data['fields']
        ]);
        $JobData->setParams(['user_job_id' => $user_job_id]);
        if (is_null(Jobs::addNewJob($JobData)))
            return null;
        return $user_job_id;
    }
}
