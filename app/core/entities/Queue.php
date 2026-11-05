<?php

namespace app\core\entities;

class Queue extends Entity
{
    const STATUS_NEW = 1;
    const STATUS_PROCESS = 2;
    const STATUS_DONE = 3;
    const STATUS_ERROR = 4;
    const STATUS_SKIP = 5;

    const ALL_STATUSES = [
        self::STATUS_NEW => 'new',
        self::STATUS_PROCESS => 'process',
        self::STATUS_DONE => 'done',
        self::STATUS_ERROR => 'error',
        self::STATUS_SKIP => 'skip'
    ];

    const TYPE_BULK_PRINT = 'bulkPrint';
    const TYPE_PDF_FROM_SFTP_TO_ORDER = 'pdfFromSftpToOrder';

    private $id;
    private $type;
    private $warehouse_id;
    private $user_id;
    private $status;
    private $json_data;
    private $create_date;

    public function getId(): int
    {
        return (int)$this->id;
    }

    public function setId(int $id): Queue
    {
        $this->id = $id;
        return $this;
    }

    public function getType(): string
    {
        return (string)$this->type;
    }

    public function setType(string $type): Queue
    {
        $this->type = $type;
        return $this;
    }

    public function getWarehouseId(): int
    {
        return (int)$this->warehouse_id;
    }

    public function setWarehouseId(int $warehouse_id): Queue
    {
        $this->warehouse_id = $warehouse_id;
        return $this;
    }


    public function getStatus(): int
    {
        return (int)$this->status;
    }

    public function setStatus(int $status): Queue
    {
        $this->status = $status;
        return $this;
    }

    public function getJsonData(): string
    {
        return (string)$this->json_data;
    }

    public function setJsonData(string $json_data): Queue
    {
        $this->json_data = $json_data;
        return $this;
    }

    public function getUserId(): int
    {
        return (int)$this->user_id;
    }

    public function setUserId(int $user_id): Queue
    {
        $this->user_id = $user_id;
        return $this;
    }

    public function getCreateDate(): string
    {
        return (string)$this->create_date;
    }

    public function setCreateDate(string $create_date): Queue
    {
        $this->create_date = $create_date;
        return $this;
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'warehouse_id' => $this->warehouse_id,
            'user_id' => $this->getUserId(),
            'status' => $this->getStatus(),
            'json_data' => $this->getJsonData(),
            'create_date' => $this->getCreateDate(),
        ];
    }
}