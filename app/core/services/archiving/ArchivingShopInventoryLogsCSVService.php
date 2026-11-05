<?php

namespace app\core\services\archiving;

use app\core\dbal\SafeMysql;
use app\core\helpers\Arr;
use DateTime;

class ArchivingShopInventoryLogsCSVService extends ArchivingCSVService
{

    private $clean_threshold;

    public function __construct($clean_threshold)
    {
        $this->clean_threshold = $clean_threshold;
        $columns = (new SafeMysql())->fetchAll('SHOW COLUMNS FROM shop_inventory_logs');
        self::$fields = Arr::column($columns, 'Field');
        $this->makeFile(self::DIR_SHOP_INVENTORY_LOGS, $this->getFileName());
    }

    private function getFileName()
    {
        return 'shop_inventory_logs_' . $this->clean_threshold . '-01-'
            . (new DateTime($this->clean_threshold))->modify('last day of this month')->format(self::DATE_FORMAT)
            . '_created-' . date(self::DATE_FORMAT . "-His");
    }

    public function getData($last_id): array
    {
        $data = (new SafeMysql())->fetchAll("SELECT * FROM shop_inventory_logs
                                            WHERE DATE_FORMAT(create_date, '%Y-%m') = :clean_threshold AND id > :last_id
                                                LIMIT 100", [
            'clean_threshold' => $this->clean_threshold,
            'last_id' => $last_id,
        ]);
        if (empty($data))
            return [];
        return $data;
    }

    public function destroyItems($items)
    {
        $this->destroyByIds('DELETE FROM shop_inventory_logs WHERE id in (:ids)', $items);
    }
}