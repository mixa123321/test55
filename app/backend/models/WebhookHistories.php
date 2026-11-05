<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\WebhookHistory;
use app\core\helpers\Debug;
use app\core\virtual\VirtualNotification;
use app\core\helpers\FileWrapper;

class WebhookHistories extends \app\core\models\WebhookHistories
{
    const EXPIRE_MONTH = 6;

    public static function badgeStatus($status): string
    {
        if (!in_array($status, [WebhookHistory::STATUS_SUCCESS, WebhookHistory::STATUS_ERROR]))
            return '';
        return "<span class='badge badge-" . WebhookHistory::STATUS_COLOR[$status] . "'>" . WebhookHistory::STATUS_NAME[$status] . "</span>";
    }

    public static function addExternalHistory($name, $number, $json_data, $user_id, $group_name): int
    {
        $webhook_history = parent::addExternal($name, $number, $json_data, $user_id);
        VirtualNotification::sendWebhookToTelegram($webhook_history, $number, $group_name);
        return $webhook_history->getId();
    }

    public static function createInternalHistory($name): WebhookHistory
    {
        return parent::createInternal($name);
    }

    public static function report($now = null)
    {
        if (empty($now))
            $now = date('Y-m-d', time());
        self::saveHistory($now);
        self::clearHistory($now);
    }

    private static function saveHistory($now)
    {
        $sql = 'SELECT *
                FROM webhook_history
                WHERE create_date < DATE_ADD(NOW(), INTERVAL -' . self::EXPIRE_MONTH . ' MONTH) AND external = 1
                ORDER BY create_date';
        $histories = (new SafeMysql())->fetchAll($sql);
        if (empty($histories))
            return;
        $dirname = EXTERNAL_STORAGE . DIRECTORY_SEPARATOR . 'webhooks' . DIRECTORY_SEPARATOR;
        if (!file_exists($dirname)) {
            mkdir($dirname,0777,true);
            chmod($dirname, 0777);
        }
        $filename = $dirname . 'external_' . str_replace('-', '', $now) . '.csv';
        $output = tmpfile();
        fputcsv($output, ['id', 'name', 'external', 'number', 'json_data', 'status', 'create_date', 'user_id', 'response'], ',');
        foreach ($histories as $history)
            fputcsv($output, $history, ',');
        (new FileWrapper(FileWrapper::getContainerByPath($filename)))->writeFileResource($filename, $output);

        (new SafeMysql())->execute('
            DELETE FROM webhook_history
            WHERE create_date < DATE_ADD(NOW(), INTERVAL -:expire_month MONTH) AND external = 1', [
            'expire_month' => self::EXPIRE_MONTH,
        ]);
    }

    private static function clearHistory($now)
    {
        $old = 'external_' . date('Ymd', strtotime($now) - 3600*24*365.25);
        $dirname = EXTERNAL_STORAGE . DIRECTORY_SEPARATOR . 'webhooks' . DIRECTORY_SEPARATOR;
        $handle = opendir($dirname);
        if (!$handle) {
            Debug::sendDebugInfo('Clear history Webhook', $dirname, Debug::TEST);
            return;
        }
        while (($entry = readdir($handle)) !== false) {
            $filename = explode('.', $entry);
            if (empty($filename[1]) || strtolower($filename[1]) != 'csv')
                continue;
            if ($filename[0] < $old)
                unlink($dirname . $entry);
        }
    }
}
