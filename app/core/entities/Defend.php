<?php

namespace app\core\entities;

class Defend extends Entity
{
    private $hash;
    private $type;
    private $date;

    public function getHash(): string
    {
        return $this->hash;
    }

    public function setHash(string $hash): Defend
    {
        $this->hash = $hash;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): Defend
    {
        $this->type = $type;
        return $this;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function setDate($date): Defend
    {
        $this->date = $date;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'hash' => $this->getDate(),
            'type' => $this->getType(),
            'date' => $this->getDate(),
        ];
    }
}