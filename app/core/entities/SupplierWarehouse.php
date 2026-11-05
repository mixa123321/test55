<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\services\crypto\StoredSecret;

class SupplierWarehouse extends Entity
{
    /**
     * Columns encrypted at rest by StoredSecret (ADR-002 Phase 2). The entity holds the plaintext: the models decrypt
     * on read, save() encrypts on write.
     */
    public const SECRET_FIELDS = ['shipping_api_key', 'retainme_credentials', 'virtual_credentials'];

    private $id;
    private $supplier_id;
    private $warehouse_id;
    private $VAT;
    private $EORI;
    private $sage_id;
    private $shipping_api_key;
    private $shipping_account_number;
    private $packing_slip_type;
    private $retainme_credentials;
    private $address;
    private $return_label = 0;
    private $virtual_credentials;
    private $reg_number;
    private $priority_order = 0;
    private $automatical_assignment;
    private $is_billing;
    private $billing_address;

    public function getId(): int
    {
        return (int)$this->id;
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

    public function getVAT()
    {
        return $this->VAT;
    }

    public function setVAT($VAT): void
    {
        $this->VAT = $VAT;
    }

    public function getEORI()
    {
        return $this->EORI;
    }

    public function setEORI($EORI): void
    {
        $this->EORI = $EORI;
    }

    public function getSageId()
    {
        return $this->sage_id;
    }

    public function setSageId($name)
    {
        $this->sage_id = (string)$name;
    }

    public function getShippingApiKey()
    {
        return $this->shipping_api_key;
    }

    public function setShippingApiKey($name)
    {
        $this->shipping_api_key = (string)$name;
    }

    public function getShippingAccountNumber()
    {
        return $this->shipping_account_number;
    }

    public function setShippingAccountNumber($name)
    {
        $this->shipping_account_number = (string)$name;
    }

    public function getPackingSlipType()
    {
        return $this->packing_slip_type;
    }

    public function setPackingSlipType($packing_slip_type): void
    {
        $this->packing_slip_type = $packing_slip_type;
    }

    public function getRetainmeCredentials()
    {
        return $this->retainme_credentials;
    }

    public function setRetainmeCredentials($retainme_credentials): void
    {
        $this->retainme_credentials = $retainme_credentials;
    }

    public function getAddress()
    {
        return $this->address;
    }

    public function setAddress($address): void
    {
        $this->address = $address;
    }

    public function getReturnLabel()
    {
        return $this->return_label;
    }

    public function setReturnLabel($is_label)
    {
        $this->return_label = (int)$is_label;
    }

    public function getVirtualCredentials()
    {
        return $this->virtual_credentials;
    }

    public function setVirtualCredentials($virtual_credentials): void
    {
        $this->virtual_credentials = $virtual_credentials;
    }

    public function getRegNumber()
    {
        return $this->reg_number;
    }

    public function setRegNumber($reg_number): void
    {
        $this->reg_number = $reg_number;
    }

    public function getPriorityOrder()
    {
        return $this->priority_order;
    }

    public function setPriorityOrder($priority): void
    {
        $this->priority_order = $priority;
    }

    public function getAutomaticalAssignment()
    {
        return $this->automatical_assignment;
    }

    public function setAutomaticalAssignment($automatical_assignment)
    {
        $this->automatical_assignment = $automatical_assignment;
    }

    public function getIsBilling()
    {
        return $this->is_billing;
    }

    public function setIsBilling($is)
    {
        $this->is_billing = (int)$is;
    }

    public function getBillingAddress()
    {
        return $this->billing_address;
    }

    public function setBillingAddress($address): void
    {
        $this->billing_address = $address;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $data = StoredSecret::encryptFields($data, self::SECRET_FIELDS);
        $id = Mysql::saveRow('suppliers_warehouses', $data);
        if (empty($this->getId()))
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'supplier_id' => $this->getSupplierId(),
            'warehouse_id' => $this->getWarehouseId(),
            'VAT' => $this->getVAT(),
            'EORI' => $this->getEORI(),
            'sage_id' => $this->getSageId(),
            'shipping_api_key' => $this->getShippingApiKey(),
            'shipping_account_number' => $this->getShippingAccountNumber(),
            'packing_slip_type' => $this->getPackingSlipType(),
            'retainme_credentials' => $this->getRetainmeCredentials(),
            'address' => $this->getAddress(),
            'return_label' => $this->getReturnLabel(),
            'virtual_credentials' => $this->getVirtualCredentials(),
            'reg_number' => $this->getRegNumber(),
            'priority_order' => $this->getPriorityOrder(),
            'automatical_assignment' => $this->getAutomaticalAssignment(),
            'is_billing' => $this->getIsBilling(),
            'billing_address' => $this->getBillingAddress(),
        ];
    }
}
