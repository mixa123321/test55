<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\dbal\SafeMysql;

class ASNSKU extends Entity
{
    private $ASN_id;
    private $SKU_id;
    private $quantity;
    private $accepted_quantity;
    private $batch_number;

    public function getASNId()
    {
        return $this->ASN_id;
    }

    public function setASNId($ASN_id)
    {
        $this->ASN_id = (int)$ASN_id;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($SKU_id)
    {
        $this->SKU_id = (int)$SKU_id;
    }

    public function getQuantity()
    {
        return $this->quantity;
    }

    public function setQuantity($quantity)
    {
        $this->quantity = (int)$quantity;
    }

    public function getAcceptedQuantity()
    {
        return $this->accepted_quantity;
    }

    public function setAcceptedQuantity($quantity)
    {
        $this->accepted_quantity = (int)$quantity;
    }

    public function getBatchNumber()
    {
        return $this->batch_number;
    }

    public function setBatchNumber($batch_number)
    {
        $this->batch_number = $batch_number;
    }

    private function getBatchArray()
    {
        $batch_number = $this->getBatchNumber();
        if (empty($batch_number))
            return [];
        $addition = json_decode($batch_number, true);
        if (!is_array($addition))
            return false;
        return $addition;
    }

    private function getBatch($field)
    {
        if (($addition = $this->getBatchArray()) === false)
            return 0;
        return $addition[$field] ?? 0;
    }

    private function setBatch($field, $value)
    {
        if (($addition = $this->getBatchArray()) === false)
            return;
        $addition[$field] = $value;
        $this->batch_number = json_encode($addition);
    }

    public function getBatchId()
    {
        return (int)$this->getBatch('batch');
    }

    public function setBatchId($batch_id)
    {
        $this->setBatch('batch', $batch_id);
    }

    public function getCondition()
    {
        return (int)$this->getBatch('condition');
    }

    public function setCondition($condition)
    {
        $this->setBatch('condition', $condition);
    }

    public function getReason()
    {
        return (int)$this->getBatch('reason');
    }

    public function setReason($reason)
    {
        $this->setBatch('reason', $reason);
    }

    public function getRefund()
    {
        return (int)$this->getBatch('refund');
    }

    public function setRefund($refund)
    {
        $this->setBatch('refund', $refund);
    }

    public function getAction()
    {
        return (int)$this->getBatch('action');
    }

    public function setAction($action)
    {
        $this->setBatch('action', $action);
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        Mysql::saveRow('ASN_SKUs', $data);
    }

    public function update($fields_to_save)
    {
        unset($fields_to_save['ASN_id'], $fields_to_save['SKU_id']);
        if (empty($fields_to_save))
            return;
        $save = [];
        $params = [];
        if (in_array('quantity', $fields_to_save)) {
            $save[] = 'quantity = :quantity';
            $params['quantity'] = $this->getQuantity();
        }
        if (in_array('accepted_quantity', $fields_to_save)) {
            $save[] = 'accepted_quantity = :accepted_quantity';
            $params['accepted_quantity'] = $this->getAcceptedQuantity();
        }
        if (in_array('batch_number', $fields_to_save)) {
            $save[] = 'batch_number = :batch_number';
            $params['batch_number'] = $this->getBatchNumber();
        }
        if (empty($save))
            return;
        $params['ASN_id'] = $this->getASNId();
        $params['SKU_id'] = $this->getSKUId();
        (new SafeMysql())->execute('UPDATE ASN_SKUs SET ' . implode(',', $save) . ' WHERE ASN_id = :ASN_id AND SKU_id = :SKU_id', $params);
    }

    public function toArray()
    {
        return [
            'ASN_id' => $this->getASNId(),
            'SKU_id' => $this->getSKUId(),
            'quantity' => $this->getQuantity(),
            'accepted_quantity' => $this->getAcceptedQuantity(),
            'batch_number' => $this->getBatchNumber(),
        ];
    }
}
