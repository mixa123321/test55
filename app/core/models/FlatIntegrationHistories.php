<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\FlatIntegrationHistory;

class FlatIntegrationHistories
{
    public static function add($flat_integration_id, $filename, $info, $mode)
    {
        $filename = explode(DIRECTORY_SEPARATOR, $filename);
        $data = [
            'flat_integration_id' => $flat_integration_id,
            'filename' => $filename[count($filename) - 1],
            'additional_info' => is_array($info) ? json_encode($info) : $info,
            'mode' => $mode,
        ];
        $history = new FlatIntegrationHistory($data);
        $history->save(array_keys($data));
        return $history->getId();
    }

    public static function upload($flat_integration_id, $filename)
    {
        return self::add($flat_integration_id, $filename, '', FlatIntegrationHistory::FILE_UPLOAD);
    }

    public static function move($flat_integration_id, $filename)
    {
        return self::add($flat_integration_id, $filename, '',FlatIntegrationHistory::FILE_MOVE);
    }

    public static function lastOrder($flat_integration_id, $orders, $despatched_date)
    {
        $id = (new SafeMysql())->fetchScalar("
            SELECT id
            FROM flat_integration_history
            WHERE flat_integration_id = :flat_integration_id AND `mode` = :mode", ['flat_integration_id' => (int)$flat_integration_id, 'mode' => FlatIntegrationHistory::LAST_ORDER]);
        $order_data = json_encode(['orders' => $orders, 'despatched_date' => $despatched_date]);
        if (empty($id))
            return self::add($flat_integration_id, '', $order_data,FlatIntegrationHistory::LAST_ORDER);
        (new SafeMysql())->execute("UPDATE flat_integration_history SET additional_info = :additional_info WHERE id = :id", [
            'additional_info' => $order_data,
            'id' => $id,
        ]);
    }

    public static function getLast($flat_integration_id)
    {
        $orders_data = (new SafeMysql())->fetchScalar("
            SELECT additional_info
            FROM flat_integration_history
            WHERE flat_integration_id = :flat_integration_id AND `mode` = :mode", ['flat_integration_id' => (int)$flat_integration_id, 'mode' => FlatIntegrationHistory::LAST_ORDER]);
        if (empty($orders_data))
            return [[], null];
        $orders_data = json_decode($orders_data, true);
        return [$orders_data['orders'], $orders_data['despatched_date']];
    }

    public static function delete($flat_integration_id, $filename)
    {
        return self::add($flat_integration_id, $filename, '',FlatIntegrationHistory::FILE_DELETE);
    }

    public static function done($history_id)
    {
        $data = [
            'id' => $history_id,
            'mode' => FlatIntegrationHistory::FILE_DONE,
        ];
        $history = new FlatIntegrationHistory($data);
        $history->save(array_keys($data));
    }

    public static function isDone($flat_integration_id, $filename)
    {
        $res = (new SafeMysql())->fetchScalar("
            SELECT id
            FROM flat_integration_history
            WHERE flat_integration_id = :flat_integration_id AND
                  filename = :filename AND
                  `mode` = :mode", [
                    'flat_integration_id' => (int)$flat_integration_id,
                    'filename' => $filename,
                    'mode' => FlatIntegrationHistory::FILE_DONE
        ]);
        return !empty($res);
    }

    public static function getHistories2Week($flat_integration_id)
    {
        $flat_integration_id = (int)$flat_integration_id;
        $sql = "SELECT `mode`, create_date, filename, additional_info
                FROM flat_integration_history
                WHERE flat_integration_id = :flat_integration_id AND DATE_FORMAT(create_date,'%Y-%m-%d') >= :date_from
                ORDER BY `mode`, create_date DESC";
        $histories_data = (new SafeMysql())->fetchAll($sql, ['flat_integration_id' => $flat_integration_id, 'date_from' => date('Y-m-d', strtotime('-2 weeks'))]);
        if (empty($histories_data))
            return [null, null, null, null];
        $last_orders = null;
        $histories = $histories_upload = $histories_export = [];
        foreach ($histories_data as $history_data) {
            switch ($history_data['mode']) {
                case FlatIntegrationHistory::LAST_ORDER:
                    $last_orders = json_decode($history_data['additional_info'], true);
                    break;
                case FlatIntegrationHistory::FILE_DONE:
                case FlatIntegrationHistory::FILE_UPLOAD:
                case FlatIntegrationHistory::FILE_MOVE:
                case FlatIntegrationHistory::FILE_DELETE:
                case FlatIntegrationHistory::ERROR:
                    $histories_upload[$history_data['filename']][] = [
                        'create_date' => $history_data['create_date'],
                        'mode' => $history_data['mode'],
                        'message' => $history_data['additional_info']
                    ];
                    break;
                case FlatIntegrationHistory::FILE_EXPORT_ORDERS:
                case FlatIntegrationHistory::FILE_EXPORT_SKUS:
                case FlatIntegrationHistory::FILE_MANUAL:
                    $histories_export[$history_data['filename']][] = [
                        'create_date' => $history_data['create_date'],
                        'mode' => $history_data['mode']
                    ];
                    break;
                case FlatIntegrationHistory::FILE_CHECK:
                    list($event_date, $event_time) = explode(' ', $history_data['create_date']);
                    list($event_hour, $event_min,) = explode(':', $event_time);
                    $histories[$event_date]["$event_hour:$event_min"] = json_decode($history_data['additional_info'], true);
                    break;
            }
        }
        return [$histories, $histories_upload, $histories_export, $last_orders];
    }
}