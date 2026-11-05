<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\services\crypto\StoredSecret;

class FlatIntegration extends Entity
{
    private $id;
    private $supplier_id;
    private $warehouse_id;
    private $credentials;
    private $is_active;
    private $class_name;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($id)
    {
        $this->supplier_id = (int)$id;
    }

    public function getWarehouseId()
    {
        return $this->warehouse_id;
    }

    public function setWarehouseId($id)
    {
        $this->warehouse_id = (int)$id;
    }

    public function getCredentials()
    {
        return json_decode($this->credentials, true);
    }

    /**
     * An array comes from the code; a string comes from the DB row, where it is encrypted at rest by StoredSecret
     * (or still plaintext JSON before migration). In memory the entity always holds the plaintext JSON.
     */
    public function setCredentials($credentials)
    {
        if (is_object($credentials))
            $credentials = $credentials->toArray();
        if (is_array($credentials))
            $credentials = json_encode($credentials);
        elseif (is_string($credentials))
            $credentials = StoredSecret::decrypt($credentials);
        $this->credentials = $credentials;
    }

    public function getIsActive()
    {
        return $this->is_active;
    }

    public function setIsActive($is_active)
    {
        $this->is_active = (int)$is_active;
    }

    public function getClassName()
    {
        return $this->class_name;
    }

    public function getFullClassName()
    {
        return '\\app\\core\\flat\\' . strtolower($this->class_name) . "\\{$this->class_name}";
    }

    public function setClassName($name)
    {
        $this->class_name = (string)$name;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (!empty($data['credentials']) && is_array($data['credentials']))
            $data['credentials'] = StoredSecret::encrypt((string)json_encode($data['credentials']));
        $id = Mysql::saveRow('flat_integration', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        $return = [
            'id' => $this->getId(),
            'supplier_id' => $this->getSupplierId(),
            'warehouse_id' => $this->getWarehouseId(),
            'credentials' => $this->getCredentials(),
            'is_active' => $this->getIsActive(),
            'class_name' => $this->getClassName(),
        ];
        return $return;
    }
}