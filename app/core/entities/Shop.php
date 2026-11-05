<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\services\crypto\StoredSecret;

class Shop extends Entity
{
    private $id;
    private $channel_supplier_id;
    private $is_active;
    private $credentials;
    private $is_risk;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getChannelSupplierId()
    {
        return $this->channel_supplier_id;
    }

    public function setChannelSupplierId($id)
    {
        $this->channel_supplier_id = (int)$id;
    }

    public function getIsActive()
    {
        return $this->is_active;
    }

    public function setIsActive($is_active)
    {
        $this->is_active = (int)$is_active;
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

    public function getIsRisk()
    {
        return $this->is_risk;
    }

    public function setIsRisk($is_risk)
    {
        $this->is_risk = (int)$is_risk;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (!empty($data['credentials']) && is_array($data['credentials'])) {
            // Shops are looked up by this column, not inside credentials (ADR-002 Phase 2 step 3)
            $data['shop_url'] = isset($data['credentials']['shop_url']) && is_string($data['credentials']['shop_url']) ? $data['credentials']['shop_url'] : null;
            $data['credentials'] = StoredSecret::encrypt((string)json_encode($data['credentials']));
        }
        $id = Mysql::saveRow('shops', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        $return = [
            'id' => $this->getId(),
            'channel_supplier_id' => $this->getChannelSupplierId(),
            'is_active' => $this->getIsActive(),
            'credentials' => $this->getCredentials(),
            'is_risk' => $this->getIsRisk(),
        ];
        return $return;
    }
}