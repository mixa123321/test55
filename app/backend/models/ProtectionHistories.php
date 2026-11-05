<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\helpers\formatters\FormatterCSV;
use app\core\helpers\HTTP;
use app\core\helpers\notifiers\Notifier;
use app\core\helpers\notifiers\TelegramNotifier;
use app\core\helpers\Request;
use app\core\models\Messages;

class ProtectionHistories extends \app\core\models\ProtectionHistories
{
    const MAX_LEN_TELEGRAM  = 65;
    const MAX_LEN_COL1      = 25;

    public static function addEvent()
    {
        $headers = Request::getRequestHeaders();
        self::addAttack(
            HTTP::getHost(),
            HTTP::getRequestUri(),
            Request::getIp(),
            $headers['GEOIP_CONTINENT_CODE'] ?? $_SERVER['GEOIP_CONTINENT_CODE'] ?? '',
            $headers['GEOIP_COUNTRY_CODE'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? '',
            $headers['GEOIP_COUNTRY_NAME'] ?? $_SERVER['GEOIP_COUNTRY_NAME'] ?? ''
        );
    }

    public static function report($now = null)
    {
        if (empty($now))
            $now = date('Y-m-d', time());
        self::reportPath($now);
        self::reportIP($now);
        self::saveHistory($now);
        self::clearHistory($now);
    }

    private static function clearHistory($now)
    {
        $old = date('Ymd', strtotime($now) - 3600*24*365.25);
        $dirname = EXTERNAL_STORAGE . DIRECTORY_SEPARATOR . 'protection' . DIRECTORY_SEPARATOR;
        $handle = opendir($dirname);
        while (($entry = readdir($handle)) !== false) {
            $filename = explode('.', $entry);
            if (empty($filename[1]) || strtolower($filename[1]) != 'csv')
                continue;
            if ($filename[0] < $old)
                unlink($dirname . $entry);
        }
    }

    private static function saveHistory($now)
    {
        $sql = "SELECT * 
                FROM protection_histories
                WHERE attack_date < :now
                ORDER BY attack_date";
        $histories = (new SafeMysql())->fetchAll($sql, ['now' => $now]);
        $dirname = EXTERNAL_STORAGE . DIRECTORY_SEPARATOR . 'protection' . DIRECTORY_SEPARATOR;
        if (!file_exists($dirname)) {
            mkdir($dirname,0777,true);
            chmod($dirname, 0777);
        }
        $filename = $dirname . str_replace('-', '', $now) . '.csv';
        $csv = new FormatterCSV($filename);
        $csv->addHeader(['id', 'attack_date', 'host', 'params', 'ip', 'continent', 'country_code', 'country_name']);
        $csv->addData($histories);
        (new SafeMysql())->execute("
            DELETE FROM protection_histories
            WHERE attack_date < :now", [
            'now' => $now,
        ]);
    }

    private static function reportPath($now)
    {
        $sql = "SELECT SUBSTR(`host`, 1, " . self::MAX_LEN_COL1 . ") col1, COUNT(*) col2, GROUP_CONCAT(DISTINCT params) col3
                FROM protection_histories
                WHERE attack_date < :now
                GROUP BY `host`
                ORDER BY col2 DESC";
        $paths = (new SafeMysql())->fetchAll($sql, ['now' => $now]);
        $lines = ProtectionHistories::tableTelegram($paths, [
            'col1' => 'host',
            'col2' => 'nums',
            'col3' => 'path'
        ]);
        if (!empty($lines))
            Messages::addDebugInfo("Report $now (path)", $lines, Messages::GROUP_PROTECTION, Messages::TEST_PARAMETERS);
//            (new TelegramNotifier(Notifier::GROUP_PROTECTION, "Report $now (path)\n<pre>$lines</pre>", TelegramNotifier::PARSE_HTML))->send();
    }

    private static function reportIP($now)
    {
        $sql = "SELECT IF(country_code = '','-', CONCAT(country_code, ' (', continent, ')')) col1, COUNT(*) col2, GROUP_CONCAT(DISTINCT ip) col3
                FROM protection_histories
                WHERE attack_date < :now
                GROUP BY country_code
                ORDER BY col2 DESC";
        $countries = (new SafeMysql())->fetchAll($sql, ['now' => $now]);
        $lines = ProtectionHistories::tableTelegram($countries, [
            'col1' => 'country',
            'col2' => 'nums',
            'col3' => 'ip'
        ]);
        if (!empty($lines))
            Messages::addDebugInfo("Report $now (IP)", $lines, Messages::GROUP_PROTECTION, Messages::TEST_PARAMETERS);
//            (new TelegramNotifier(Notifier::GROUP_PROTECTION, "Report $now (IP)\n<pre>$lines</pre>", TelegramNotifier::PARSE_HTML))->send();
    }

    private static function tableTelegram($data, $header)
    {
        if (empty($data) || array_keys($data[0]) != ['col1', 'col2', 'col3'])
            return '';
        $lines = '';
        $max = [
            'col1' => 0,
            'col2' => 0,
            'col3' => 0,
        ];
        array_walk($data, function($item) use(&$max) {
            foreach ($item as $key => $value) {
                if (strlen($value) > $max[$key])
                    $max[$key] = strlen($value);
                if (strlen($key) > $max[$key])
                    $max[$key] = strlen($key);
            }
        });
        $len_table = $max['col1'] + $max['col2'] + 10;
        if ($len_table + $max['col3'] > self::MAX_LEN_TELEGRAM)
            $max['col3'] = self::MAX_LEN_TELEGRAM - $len_table;
        $lines .= '╔' . implode('╤', array_map(function($item) { return str_repeat('═', $item + 2); }, array_values($max))) . "╗\n";
        $lines .= '║ ' . implode(' │ ', array_map(function($item) use($max, $header) { return "<b>{$header[$item]}" . str_repeat(' ', $max[$item] - strlen($header[$item])) . '</b>'; }, array_keys($max))) . " ║\n";
        $lines .= '╠' . implode('╪', array_map(function($item) { return str_repeat('═', $item + 2); }, array_values($max))) . "╣\n";
        foreach ($data as $datum) {
            $path = $datum['col3'];
            $item_data = $datum;
            do {
                $item_data['col3'] = substr($path, 0, $max['col3']);
                $path = substr($path, $max['col3']);
                $lines .= '║ ' . implode(' │ ', array_map(function($item) use($max, $item_data) { return htmlspecialchars($item_data[$item]) . str_repeat(' ', $max[$item] - strlen($item_data[$item])); }, array_keys($max))) . " ║\n";
                $item_data['col1'] = $item_data['col2'] = '';
            } while (!empty($path));
            if ($datum != end($data))
                $lines .= '╟' . implode('┼', array_map(function($item) { return str_repeat('─', $item + 2); }, array_values($max))) . "╢\n";
        }
        $lines .= '╚' . implode('╧', array_map(function($item) { return str_repeat('═', $item + 2); }, array_values($max))) . '╝';
        return $lines;
    }
}
