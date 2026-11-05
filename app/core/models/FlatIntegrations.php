<?php

namespace app\core\models;

use app\core\entities\FlatIntegration;
use app\core\dbal\SafeMysql;

class FlatIntegrations
{
    public static function getById($id)
    {
        $fi_info = (new SafeMysql())->fetchOne("SELECT * FROM flat_integration WHERE id = :id LIMIT 1", ['id' => (int)$id]);
        if (!empty($fi_info))
            return new FlatIntegration($fi_info);
        return null;
    }

    public static function getBySupplierId($supplier_id, $warehouse_id = 0)
    {
        $supplier_id = (int)$supplier_id;
        $warehouse_id = (int)$warehouse_id;
        $whereWH = empty($warehouse_id) ? '' : "AND warehouse_id = :warehouse_id";
        $params = [
            'supplier_id' => $supplier_id
        ];
        if (!empty($warehouse_id))
            $params['warehouse_id'] = $warehouse_id;
        $data = (new SafeMysql())->fetchAll("SELECT * FROM flat_integration WHERE supplier_id = :supplier_id $whereWH", $params);
        if (empty($data))
            return [];
        $fies = [];
        foreach ($data as $fi_info)
            $fies[] = new FlatIntegration($fi_info);
        return !empty($warehouse_id) ? $fies[0] : $fies;
    }

    public static function getAllActive($warehouse_id = 0)
    {
        $warehouse_id = (int)$warehouse_id;
        $whereWH = empty($warehouse_id) ? '' : "AND warehouse_id = :warehouse_id";
        $params = [];
        if (!empty($warehouse_id))
            $params['warehouse_id'] = $warehouse_id;
        $data = (new SafeMysql())->fetchAll("SELECT * FROM flat_integration WHERE is_active = 1 $whereWH", $params);
        if (empty($data))
            return [];
        $fies = [];
        foreach ($data as $fi_info)
            $fies[] = new FlatIntegration($fi_info);
        return $fies;
    }
}