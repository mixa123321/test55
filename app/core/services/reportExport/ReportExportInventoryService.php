<?php

namespace app\core\services\reportExport;

use app\backend\models\Locations;
use app\backend\models\SKULocations;
use app\backend\models\SKUBarcodes;
use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\helpers\Arr;
use app\core\models\Warehouses;

class ReportExportInventoryService extends ReportExportService
{
    private $locations;

    public function setFields()
    {
        $this->fields = [
            'id',
            'name',
            'barcode',
            'customer_ref',
            'unique_customer_ref',
            'state',
            'available',
            'allocated',
            'backorder_replenish',
            'batches',
        ];
    }

    private function changeFields()
    {
        $tmp = [];
        foreach ($this->fields as $field) {
            $tmp[] = $field;
            if ($field == 'available')
                foreach ($this->locations as $loc_abbr => $loc_name)
                    $tmp[] = $loc_abbr;
        }
        $this->fields = $tmp;
    }

    /** @throws \Exception */
    public function __construct($supplier_id, $warehouse_id)
    {
        parent::__construct($supplier_id, $warehouse_id);
        $this->locations = Warehouses::getLocations($warehouse_id);
        if (!empty($this->locations))
            $this->changeFields();
        $this->makeFile("{$this->getReportName()}_" . date("Y-m-d-His"));
    }

    public function getData($last_id): array
    {
        $query = new QueryBuilder();
        $whereLocations = Locations::getExcludeLocationsPartialQuery($this->warehouse['id']);
        $query->setSelectFrom("
            SELECT s.id, s.name, s.customer_ref, s.unique_customer_ref, s.state,
                   IFNULL(av.available, 0) available,
                   IFNULL(al.allocated, 0) allocated,
                   IFNULL(br.backorder_replenish, 0) backorder_replenish
            FROM SKUs s
                LEFT JOIN (SELECT SKU_id, SUM(quantity) available
                           FROM SKU_locations $whereLocations
                           WHERE type = 1 AND reason = '2'
                           GROUP BY SKU_id) av ON s.id = av.SKU_id
                LEFT JOIN (SELECT SKU_id, SUM(quantity) allocated
                           FROM SKU_locations $whereLocations
                           WHERE NOT (type = 1 AND reason = '2') AND type <> 4
                           GROUP BY SKU_id) al ON s.id = al.SKU_id
                LEFT JOIN (SELECT st.SKU_id, SUM(st.qty) backorder_replenish
                           FROM (SELECT os.order_id, os.SKU_id, os.quantity - SUM(IFNULL(sl.quantity, 0)) qty
                                 FROM order_SKUs os
                                     JOIN orders o ON os.order_id = o.id AND o.status = :status AND o.warehouse_id = :warehouse_id
                                     LEFT JOIN SKU_locations sl ON os.SKU_id = sl.SKU_id AND
                                                                   (os.order_id = sl.order_id OR
                                                                    (sl.order_id IS NULL AND
                                                                     sl.type = 1 AND
                                                                     POSITION('2_' IN sl.reason) = 1 AND
                                                                     SUBSTRING_INDEX(sl.reason, '_', -1) = os.order_id
                                                                    )
                                                                   )
                                 GROUP BY os.order_id, os.SKU_id) st
                           GROUP BY st.SKU_id) br ON s.id = br.SKU_id");
        $query->addWhere('s.supplier_id = :supplier_id');
        $query->addWhere('s.id > :last_id');
        $query->setLimit('10000');
        $query->setOrderBy('s.id ASC');
        $data = (new SafeMysql())->fetchAll($query->build(), [
            'warehouse_id' => $this->warehouse['id'],
            'supplier_id' => $this->sftp->getSupplierId(),
            'last_id' => $last_id,
            'status' => Order::BACKORDER_NUM,
        ]);
        if (empty($data))
            return [];
        SKUBarcodes::addBarcodesToArray($data);
        $SKU_ids = Arr::column($data, 'id', false);
        $SKU_warehouses = [];
        if (!empty($this->locations))
            $SKU_warehouses = SKULocations::getVirtual($this->warehouse['id'], $SKU_ids);
        $batches = SKULocations::getBatches($this->warehouse['id'], $SKU_ids);
        array_walk($data, function(&$item) use($batches, $SKU_warehouses) {
            $item['state'] = SKU::getStateName($item['state']);
            $item['batches'] = '';
            if (!empty($batches[$item['id']]))
                foreach ($batches[$item['id']] as $batch) {
                    $expired = date('jS M Y', strtotime($batch['expired']));
                    $item['batches'] .= "#{$batch['batch_number']}({$batch['quantity']}) {$expired}. ";
                }
            if (!empty($SKU_warehouses) && !empty($SKU_warehouses[$item['id']]))
                foreach ($SKU_warehouses[$item['id']] as $name => $quantity)
                    $item[$name] = $quantity;
        });
        return $data;
    }
}