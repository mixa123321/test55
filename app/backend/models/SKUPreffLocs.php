<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\models\SKUPreffLocs as CoreSKUPreffLocs;
use app\core\models\SuppliersWarehouses;

class SKUPreffLocs extends CoreSKUPreffLocs
{
    /**
     * @param $SKU_id
     * @param array<int, int> $location_priority_map
     */
    public static function create($SKU_id, array $location_priority_map)
    {
        if (empty($location_priority_map))
            return;
        $res = [];
        $SKU_id = (int)$SKU_id;
        foreach ($location_priority_map as $location_id => $priority)
            $res[] = "($SKU_id, $location_id, $priority)";
        (new SafeMysql())->execute('INSERT INTO SKU_preff_locs (SKU_id, location_id, priority) VALUES ' . implode(',', $res));
    }

    public static function update($SKU_id, $priority, $location_id)
    {
        (new SafeMysql())->execute("UPDATE SKU_preff_locs SET priority = :priority WHERE SKU_id = :SKU_id AND location_id = :location_id", [
            'priority' => $priority,
            'SKU_id' => $SKU_id,
            'location_id' => $location_id
        ]);
    }

    public static function deleteBySkuIdAndLocationsId($SKU_id, array $del)
    {
        if (empty($del))
            return;
        $SKU_id = (int)$SKU_id;
        (new SafeMysql())->execute("DELETE FROM SKU_preff_locs WHERE location_id IN (:location_ids) AND SKU_id = :SKU_id",
        [
            'SKU_id' => $SKU_id,
            'location_ids' => array_map('intval', $del)
        ]);
    }

    public static function getPriorityBySkuIdAndLocationsId($SKU_id, $location_id)
    {
        return (int)(new SafeMysql())->fetchScalar("SELECT priority FROM SKU_preff_locs
                                            WHERE SKU_id = :SKU_id AND location_id = :location_id", [
            'SKU_id' => $SKU_id,
            'location_id' => $location_id
        ]);
    }

    public static function getAssigned($locations): array
    {
        $locs = array_map(function($loc) {
            return $loc->getId();
        }, $locations);
        if (empty($locs))
            return [];
        return array_map('intval', (new SafeMysql())->fetchArrayOneField('
            SELECT DISTINCT l.id
            FROM locations l
                JOIN SKU_preff_locs spl ON l.id = spl.location_id
            WHERE l.id IN (:locs)', ['locs' => $locs]));
    }

    public static function getMaxPriority($SKU_id, $type_id, $warehouse_id)
    {
        return (int)(new SafeMysql())->fetchScalar("
            SELECT MAX(spl.priority)
            FROM SKU_preff_locs spl
                JOIN locations l ON spl.location_id = l.id AND l.warehouse = :warehouse_id AND l.type_id = :type_id
            WHERE SKU_id = :SKU_id", ['warehouse_id' => $warehouse_id, 'type_id' => $type_id, 'SKU_id' => $SKU_id]);
    }

    public static function add($SKU_id, Location $location): bool
    {
        $SKU_id = (int)$SKU_id;
        if (empty($SKU_id) || empty($location->getId()))
            return false;
        $priority = self::getMaxPriority($SKU_id, $location->getTypeId(), $location->getWarehouse()) + 1;
        (new SafeMysql())->execute("INSERT INTO SKU_preff_locs (SKU_id, location_id, priority) VALUES ($SKU_id, {$location->getId()}, $priority)");
        return true;
    }

    public static function getLocationsAutoAssign($supplier_id)
    {
        $supplier = Suppliers::getById($supplier_id);
        if (empty($supplier))
            return [];
        $supplier_warehouse = SuppliersWarehouses::getWarehouses($supplier->getId());
        if (empty($supplier_warehouse))
            return [];
        $locs = [];
        foreach ($supplier_warehouse as $supplier_data) {
            if (!in_array($supplier_data['warehouse_id'], $supplier->getWarehouses()))
                continue;
            if (!$supplier_data['automatical_assignment'])
                continue;
            $locations = Locations::getBookLocationIds($supplier_data['warehouse_id'], $supplier->getId(), Location::ST);
            if (empty($locations))
                continue;
            $location_id = Locations::getIdFromEstimated($locations);
            if ($location_id)
                $locs[$supplier_data['warehouse_id']] = $location_id;
        }
        return $locs;
    }
}
