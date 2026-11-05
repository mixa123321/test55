<?php


namespace app\core\services\archiving;

use DateTime;
use Exception;

class ArchivingMovementCSVService extends ArchivingCSVService
{

    const KEY = 'SKU_history_id';

    private $quarter;
    public static $fields = [ 'id', 'location_id', 'SKU_id', 'quantity', 'order_id', 'type', 'reason', 'event_date', 'SKU_history_id' ];

    private $warehouse_id;

    private static $data = [];

    /**
     * @throws Exception
     */
    public function __construct($warehouse_id, $quarter, $data = [])
    {
        $this->quarter = $quarter;
        $this->warehouse_id = $warehouse_id;
        $this->makeFile(self::DIR_MOVEMENT, $this->getFileName());

        if (!empty($data))
            $this->addData($data);
    }

    public function addData($data)
    {
        self::$data = array_merge(self::$data, $data);
    }

    public function getData($last_id = 0): array
    {
        return self::$data;
    }

    public function unsetData()
    {
        self::$data = [];
    }

    /**
     * @throws Exception
     */
    protected function getFileName(): string
    {
        /**
         * @var $start DateTime
         * @var $end DateTime
         */
        $start = $this->quarter['start'];
        $end = $this->quarter['end'];
        return 'movement_warehouse_' . $this->warehouse_id . '_quarter-' . $start->format('Y-m-d')
                                                           . '-' . $end->format('Y-m-d')
                                                           . '_created-' . date("Y-m-d-His");
    }

    public function destroyItems($items)
    {
        $this->destroyByIds('DELETE FROM movement WHERE id in (:ids)', $items);
    }
}