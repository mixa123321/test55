<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Size;

class Sizes
{
    public static function createSize($warehouse_id, $size)
    {
        $id = (new SafeMysql())->insert("INSERT INTO sizes (id, name, width, height, depth, warehouse_id)
                VALUES (NULL, :name, :width, :height, :depth, :warehouse_id)
                ON DUPLICATE KEY UPDATE width=:width, height=:height, depth=:depth", [
            'name' => $size['name'],
            'width' => $size['width'],
            'height' => $size['height'],
            'depth' => $size['depth'],
            'warehouse_id' => (int)$warehouse_id,
        ]);
        return empty($id) ? self::getByName($warehouse_id, $size['name'])->getId() : $id;
    }

    public static function getByName($warehouse_id, $name)
    {
        $sql = "SELECT * FROM sizes WHERE name = :name AND warehouse_id = :warehouse_id LIMIT 1";
        $size_info = (new SafeMysql())->fetchOne($sql, [
            'name' => $name,
            'warehouse_id' => $warehouse_id
        ]);
        if (!empty($size_info))
            return new Size($size_info);
        return null;
    }

    public static function getById($id)
    {
        static $sizes = [];
        if (!array_key_exists($id, $sizes)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM sizes WHERE id = :id', [
                'id' => $id
            ]);
            if ($data)
                $sizes[$id] = new Size($data);
            else
                $sizes[$id] = null;
        }
        return $sizes[$id];
    }

    public static function getVolume($id)
    {
        /** @var Size $size */
        $size = self::getById($id);
        return $size->getHeight()*$size->getWidth()*$size->getDepth();
    }

    public static function getAllSizes($warehouse_id)
    {
        $data = (new SafeMysql())->fetchAll("SELECT * FROM sizes WHERE warehouse_id = :warehouse_id", [
            'warehouse_id' => $warehouse_id
        ]);
        if (!empty($data))
            return $data;
        return [];
    }

    public static function getAllSizesName($warehouse_id = 0)
    {
        $sql = "SELECT id, name, width*height*depth V FROM sizes";
        $params = [];

        if (!empty($warehouse_id)) {
            $sql .= " WHERE warehouse_id = :warehouse_id";
            $params['warehouse_id'] = $warehouse_id;
        }

        $data = (new SafeMysql())->fetchAll($sql, $params);
        if (!empty($data)) {
            $sizes = [];
            foreach ($data as $size)
                $sizes[$size['id']] = [$size['name'], (int)$size['V']];
            return $sizes;
        }
        return [];
    }

    public static function getIdsByWarehouse(int $warehouse_id): array
    {
        $data = (new SafeMysql())->fetchAll('SELECT id FROM sizes WHERE warehouse_id = :warehouse_id', [
            'warehouse_id' => $warehouse_id
        ]);
        return array_map('intval', array_column($data, 'id'));
    }
}