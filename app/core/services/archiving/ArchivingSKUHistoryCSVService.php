<?php


namespace app\core\services\archiving;

use app\core\dbal\SafeMysql;
use app\core\models\SKUBarcodes;
use app\core\models\SKUHistories;
use app\core\statical\WithDate;
use DateTime;
use Exception;

class ArchivingSKUHistoryCSVService extends ArchivingCSVService
{

    private $warehouse_id;
    private $clean_threshold;
    public static $fields = [ 'id', 'SKU_id', 'name', 'barcode', 'adjustment', 'event', 'additional_info', 'event_date' ];

    public $quarter = [
        'start' => null,
        'end' => null,
    ];

    /**
     * @var $movement_service ArchivingMovementCSVService
     */
    private $movement_service;

    /**
     * @param $warehouse_id
     * @param null $clean_threshold
     * @throws Exception
     */
    public function __construct($warehouse_id, $clean_threshold = null)
    {
        $this->warehouse_id = (int)$warehouse_id;

        list('start' => $this->quarter['start'], 'end' => $this->quarter['end']) = WithDate::getQuarter(new DateTime(date('Y-m-d', $clean_threshold)));

        list('start' => $this->clean_threshold) = WithDate::getDatesOfQuarter('previous', null, 'Y-m-d');

        $this->makeFile(self::DIR_SKU_HISTORY, $this->getFileName());
    }

    private function getFileName(): string
    {
        /**
         * @var $start DateTime
         * @var $end DateTime
         */
        $start = $this->quarter['start'];
        $end = $this->quarter['end'];
        return 'SKU_history_warehouse_' . $this->warehouse_id . '_quarter-' . $start->format(self::DATE_FORMAT)
                                                              . '-' . $end->format(self::DATE_FORMAT)
                                                              . '_created-' . date(self::DATE_FORMAT . "-His");
    }

    /**
     * @throws Exception
     */
    public function getData($last_id): array
    {
        /**
         * @var $start DateTime
         * @var $end DateTime
         */
        $start = $this->quarter['start'];
        $end = $this->quarter['end'];
        $data = (new SafeMysql())->fetchAll("SELECT s.name, h.* FROM SKU_history h
                                            LEFT JOIN SKUs s ON s.id = h.SKU_id
                                                WHERE DATE(h.event_date) < :clean_threshold
                                                    AND h.id > :last_id
                                                    AND DATE(h.event_date) BETWEEN :start_date AND :end_date
                                                    AND h.warehouse_id = :warehouse_id
                                                LIMIT 100", [
            'clean_threshold' => $this->clean_threshold,
            'last_id' => $last_id,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'warehouse_id' => $this->warehouse_id,
        ]);
        if (empty($data))
            return [];
        SKUBarcodes::addBarcodesToArray($data, 'SKU_id');
        $this->handleMovement($last_id, $start, $end);
        return $data;
    }

    /**
     * @throws Exception
     */
    private function handleMovement($last_id, DateTime $start, DateTime $end): void
    {
        $mysql = new SafeMysql();
        $SKU_histories = $mysql->fetchAll("SELECT id, additional_info, event_date FROM SKU_history
                                                    WHERE id > :last_id AND additional_info LIKE :movement_pattern
                                                         AND DATE(event_date) < :clean_threshold
                                                          AND DATE(event_date) BETWEEN :start_date AND :end_date
                                                           AND warehouse_id = :warehouse_id
                                                           LIMIT 100", [
            'last_id' => $last_id,
            'movement_pattern' => '%Movement ID%',
            'clean_threshold' => $this->clean_threshold,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'warehouse_id' => $this->warehouse_id,
        ]);
        if (empty($SKU_histories))
            return;

        $data = [];
        $event_dates = [];

        foreach ($SKU_histories as $SKU_history) {
            $additional_info = json_decode($SKU_history['additional_info'], TRUE);
            if (!empty($additional_info['Movement ID'])) {
                $data[$additional_info['Movement ID']] = (int)$SKU_history['id'];
                $event_dates[$additional_info['Movement ID']] = $SKU_history['event_date'];
            }
        }
        if (empty($data)) {
            return;
        }

        $items = $mysql->fetchAll("SELECT * FROM movement WHERE id IN (:ids)", [
            'ids' => array_keys($data),
        ]);

        array_walk($items, function(&$item) use ($data, $event_dates) {
            $item[ArchivingMovementCSVService::KEY] = $data[$item['id']] ?? null;
            $item['event_date'] = $event_dates[$item['id']] ?? null;
        });

        if (!empty($items)) {
            if (empty($this->movement_service))
                $this->movement_service = new ArchivingMovementCSVService($this->warehouse_id, $this->quarter, $items);
            else
                $this->movement_service->addData($items);
            $this->movement_service->handle();
            $this->movement_service->unsetData();
        }
    }

    public function destroyItems($items)
    {
        $this->destroyByIds('DELETE FROM SKU_history WHERE id in (:ids) AND event != :event', $items, ['event' => SKUHistories::EVENT_STOCK]);
    }

    public function archiving()
    {
        parent::archiving();
        if ($this->movement_service instanceof ArchivingMovementCSVService)
            $this->movement_service->archiving();
    }
}