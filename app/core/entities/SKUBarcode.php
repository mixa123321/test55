<?php

namespace app\core\entities;

class SKUBarcode extends Entity
{
    private $SKU_id;
    private $barcode;

    public function getSKUId(): int
    {
        return (int)$this->SKU_id;
    }

    public function setSKUId(int $SKU_id): SKUBarcode
    {
        $this->SKU_id = $SKU_id;
        return $this;
    }

    public function getBarcode(): string
    {
        return (string)$this->barcode;
    }

    public function setBarcode(string $barcode): SKUBarcode
    {
        $this->barcode = $barcode;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'SKU_id' => $this->getSKUId(),
            'barcode' => $this->getBarcode(),
        ];
    }
}