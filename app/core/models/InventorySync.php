<?php

namespace app\core\models;

use app\backend\models\Locations;
use app\core\entities\ShopWarehouse;
use app\core\helpers\Filter;
use app\core\dbal\SafeMysql;

class InventorySync
{
    const LIMIT_PER_PAGE = 20;

    public static function getInventory(ShopWarehouse $shop, $page, $get_all = false, $filters = [])
    {
        if (!$get_all) {
            if ($page < 1)
                $page = 1;
            $offset = self::LIMIT_PER_PAGE * ($page - 1);
        }
        $whereSKU = [];
        if (!empty($filters['SKU_id']))
            $whereSKU[] = 's.id = ' . (int)$filters['SKU_id'];
        if (!empty($filters['barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode(trim($filters['barcode']), Filter::getFilterTypeFromRequest($filters, 'barcode'));
            $whereSKU[] = $SKU_ids ? "s.id IN (" . implode(', ', $SKU_ids) . ")" : "FALSE";
        }
        $whereSKU = !empty($whereSKU) ? (' WHERE ' . implode(' AND ', $whereSKU)) : '';
        $whereLocations = Locations::getExcludeLocationsPartialQuery($shop->getWarehouseId());
        $sql = "
            SELECT " . (!$get_all ? 'SQL_CALC_FOUND_ROWS' : '') . "
                   s.id, scs.external_id, s.state, s.name, s.customer_ref,
                   (SELECT IFNULL(SUM(sl0.quantity), 0)
                    FROM SKU_locations sl0 $whereLocations
                    WHERE sl0.type = 1 AND sl0.order_id IS NULL AND sl0.reason = '2' AND s.id = sl0.SKU_id
                    GROUP BY sl0.SKU_id) `available`
            FROM SKUs s
                JOIN SKU_channel_suppliers scs ON s.id = scs.SKU_id AND scs.shop_id = :shop_id
                JOIN shops sh ON scs.shop_id = sh.id 
                JOIN channels_suppliers cs ON sh.channel_supplier_id = cs.id AND s.supplier_id = cs.supplier_id
                 $whereSKU
            ORDER BY s.id";
        $params = [
            'shop_id' => $shop->getShopId()
        ];
        if (!$get_all)
            $sql .= " LIMIT $offset, " . self::LIMIT_PER_PAGE;
        $records_raw = (new SafeMysql())->fetchAll($sql, $params);
        if ($get_all)
            return $records_raw;
        SKUBarcodes::addBarcodesToArray($records_raw);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS()');
        return ['records' => $records_raw, 'total' => (int)$total, 'page' => $page];
    }
}
