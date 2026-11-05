<?php

namespace app\core\helpers\billet;

use app\core\entities\Entity;

class BilletData extends Entity
{
    private int $SKU_id;
    private string $name;
    private string $barcode;
    private ?string $numbers = null;
    private ?string $photo = null;
    private int $quantity;

    public function getSKUId(): int
    {
        return $this->SKU_id;
    }

    public function setSKUId(int $id)
    {
        $this->SKU_id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $data)
    {
        $this->name = $data;
    }

    public function getBarcode(): string
    {
        return $this->barcode;
    }

    public function setBarcode(string $data)
    {
        $this->barcode = $data;
    }

    public function getNumbers(): ?string
    {
        return $this->numbers;
    }

    public function setNumbers(?string $data)
    {
        $this->numbers = $data;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $data)
    {
        $this->photo = $data;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $num)
    {
        $this->quantity = $num;
    }

    public function toArray()
    {
        return [
            'SKU_id' => $this->getSKUId(),
            'name' => $this->getName(),
            'barcode' => $this->getBarcode(),
            'numbers' => $this->getNumbers(),
            'photo' => $this->getPhoto(),
            'quantity' => $this->getQuantity(),
        ];
    }
}