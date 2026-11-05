<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SKUPreffLoc extends Entity
{
    private $SKU_id;
    private $location_id;
    private $priority;

    public function getSKUId(): int
    {
        return (int)$this->SKU_id;
    }

    public function setSKUId($id): SKUPreffLoc
    {
        $this->SKU_id = (int)$id;
        return $this;
    }

    public function getLocationId(): int
    {
        return (int)$this->location_id;
    }

    public function setLocationId($id): SKUPreffLoc
    {
        $this->location_id = (int)$id;
        return $this;
    }

    public function getPriority(): int
    {
        return (int)$this->priority;
    }

    public function setPriority($num): SKUPreffLoc
    {
        $this->priority = (int)$num;
        return $this;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value) {
            if (!\in_array($data_field, $fields_to_save, true)) {
                unset($data[$data_field]);
            }
        }
        Mysql::saveRow("SKU_preff_locs", $data);
    }

    public function toArray()
    {
        return [
            'SKU_id' => $this->getSKUId(),
            'location_id' => $this->getLocationId(),
            'priority' => $this->getPriority(),
        ];
    }
}
