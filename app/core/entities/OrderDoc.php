<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\helpers\HTTP;
use app\core\statical\WithDate;
use app\core\Storage;
use app\core\helpers\FileWrapper;

class OrderDoc extends Entity
{
    private $id;
    private $name;
    private $document_url;
    private $create_date;
    private $update_date;
    private $user_id;
    private $status;
    private $comment;
    private $hash;

    const STATUS_NEW = 'new';
    const STATUS_LOADING = 'loading';
    const STATUS_LOADED = 'loaded';
    const STATUS_FAIL = 'fail';

    public function getId(): int
    {
        return (int)$this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return (string)$this->name;
    }

    public function setName($name): void
    {
        $this->name = (string)$name;
    }

    public function getDocumentUrl(): string
    {
        return (string)$this->document_url;
    }

    public function setDocumentUrl($document_url): void
    {
        $this->document_url = (string)$document_url;
    }

    public function getCreateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    public function setCreateDate($create_date): void
    {
        $this->create_date = WithDate::dateToInt($create_date);
    }

    public function getUpdateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->update_date);
    }

    public function setUpdateDate($update_date)
    {
        $this->update_date = WithDate::dateToInt($update_date);
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id): void
    {
        $this->user_id = $user_id;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status): void
    {
        $this->status = $status;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function setComment($comment): void
    {
        $this->comment = $comment;
    }

    public function getHash()
    {
        return $this->hash;
    }

    public function setHash($path): void
    {
        try {
            $file_wrapper = new FileWrapper(FileWrapper::getContainerByPath($path ?: ''));
            if ($file_wrapper->isFile($path ?: ''))
                $this->hash = md5(stream_get_contents($file_wrapper->getStream($path)));
        } catch (\Exception $exception) {
            return;
        }

    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save)) {
            return;
        }
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value) {
            if (!\in_array($data_field, $fields_to_save, true)) {
                unset($data[$data_field]);
            }
        }
        if (!$this->getId()) {
            $this->setId(Mysql::saveRow("order_docs", $data));
        } else {
            Mysql::saveRow("order_docs", $data);
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'document_url' => $this->getDocumentUrl(),
            'create_date' => $this->getCreateDate(),
            'update_date' => $this->getUpdateDate(),
            'user_id' => $this->getUserId(),
            'status' => $this->getStatus(),
            'comment' => $this->getComment(),
            'hash' => $this->getHash(),
        ];
    }

    public function existFile(): bool
    {
        $path = Storage::getFile(ORDERS_DOC_STORAGE, $this->name);
        if ($path === null)
            return false;
        return (new FileWrapper(FileWrapper::getContainerByPath($path)))->isFile($path);
    }

    public function getFullPath(): ?string
    {
        if ($this->existFile())
            return Storage::getFile(ORDERS_DOC_STORAGE, $this->name);
        return null;
    }

    public function getUploadUrl(int $order_id): string
    {
        return $this->existFile() ? HTTP::getDomain() . "/orders/get_doc_file?id=$order_id" : '';
    }

    public function isNew(): bool
    {
        return $this->getStatus() === self::STATUS_NEW;
    }
    public function isFail(): bool
    {
        return $this->getStatus() === self::STATUS_FAIL;
    }
    public function isLoaded(): bool
    {
        return $this->getStatus() === self::STATUS_LOADED;
    }

}
