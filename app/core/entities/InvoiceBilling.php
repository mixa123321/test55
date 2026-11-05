<?php

namespace app\core\entities;

use app\core\db\Mysql;

class InvoiceBilling extends Entity
{
    const STATUS_NEW = 1;
    const STATUS_DONE = 2;

    private $id;
    private $year;
    private $month;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): InvoiceBilling
    {
        $this->id = $id;
        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(int $num): InvoiceBilling
    {
        $this->year = $num;
        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(int $num): InvoiceBilling
    {
        $this->month = $num;
        return $this;
    }

    public static function statusName(int $status): string
    {
        return !$status ? 'New' : 'Done';
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('invoices_billing', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'year' => $this->getYear(),
            'month' => $this->getMonth(),
        ];
    }
}