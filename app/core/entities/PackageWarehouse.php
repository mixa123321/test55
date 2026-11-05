<?php

namespace app\core\entities;

use app\core\db\Mysql;

class PackageWarehouse extends Entity
{
    private $package_id;
    private $warehouse_id;
    private $default = 0;

    public function getPackageId()
    {
        return $this->package_id;
    }

    public function setPackageId($id)
    {
        $this->package_id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getDefault()
    {
        return $this->default;
    }

    public function setDefault($num)
    {
        $this->default = (int)$num;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save) || (count($fields_to_save) < 3 && in_array('default', $fields_to_save)))
            return;
        $data = $this->toArray();
        Mysql::saveRow('package_warehouses', $data);
    }

    public function toArray()
    {
        return [
            'package_id' => $this->getPackageId(),
            'warehouse_id' => $this->getWarehouseId(),
            'default' => $this->getDefault(),
        ];
    }
}
