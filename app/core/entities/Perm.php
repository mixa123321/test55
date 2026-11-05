<?php

namespace app\core\entities;

class Perm extends Entity
{
    private $access = false;
    private $reject = false;
    private $only_read = false;

    public function getAccess(): bool
    {
        return $this->access;
    }

    public function setAccess(): void
    {
        $this->access = true;
    }

    public function getReject(): bool
    {
        return $this->reject;
    }

    public function setReject(): void
    {
        $this->access = false;
        $this->only_read = false;
        $this->reject = true;
    }

    public function getOnlyRead(): bool
    {
        return $this->only_read;
    }

    public function setOnlyRead(): void
    {
        $this->only_read = true;
    }

    public function toArray(): array
    {
        return [
          'access' => $this->getAccess(),
          'reject' => $this->getReject(),
          'only_read' => $this->getOnlyRead(),
        ];
    }

    public function reset()
    {
        $this->access = false;
        $this->only_read = false;
        $this->reject = false;
    }

    public static function init($data): Perm
    {
        $perm = new self;
        $perm->access = $data['access'] ?? false;
        $perm->only_read = $data['only_read'] ?? false;
        $perm->reject = $data['reject'] ?? false;
        return $perm;
    }
}
