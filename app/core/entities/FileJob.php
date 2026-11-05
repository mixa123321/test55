<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\helpers\FileWrapper;
use app\core\statical\WithDate;
use app\core\Storage;

class FileJob extends Entity
{

    private $id;
    private $filename;
    private $is_done;
    private $user_job_id;
    private $create_date;
    private $update_date;

    public const TYPE_ORDERS = 'orders';
    public const TYPE_ORDER_REPORT_CSV = 'order report csv';

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param $filename
     */
    public function setFilename($filename): void
    {
        $this->filename = $filename;
    }

    /**
     * @return mixed
     */
    public function getIsDone()
    {
        return (boolean)$this->is_done;
    }

    /**
     * @param mixed $is_done
     */
    public function setIsDone($is_done): void
    {
        $this->is_done = (boolean)$is_done;
    }

    /**
     * @return mixed
     */
    public function getUserJobId()
    {
        return $this->user_job_id;
    }

    /**
     * @param mixed $user_job_id
     */
    public function setUserJobId($user_job_id): void
    {
        $this->user_job_id = $user_job_id;
    }

    /**
     * @return mixed
     */
    public function getCreateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->create_date);
    }

    /**
     * @param mixed $create_date
     */
    public function setCreateDate($create_date)
    {
        $this->create_date = WithDate::dateToInt($create_date);
    }

    /**
     * @return mixed
     */
    public function getUpdateDate()
    {
        return date(WithDate::DATE_SQL_FORMAT, $this->update_date);
    }

    /**
     * @param mixed $update_date
     */
    public function setUpdateDate($update_date)
    {
        $this->update_date = WithDate::dateToInt($update_date);
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
            $this->setId(Mysql::saveRow("file_jobs", $data));
        } else {
            Mysql::saveRow("file_jobs", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'filename' => $this->getFilename(),
            'is_done' => $this->getIsDone(),
            'user_job_id' => $this->getUserJobId(),
            'create_date' => $this->getCreateDate(),
            'update_date' => $this->getUpdateDate(),
        ];
    }

    public function getPath(): ?string
    {
        if ((new FileWrapper(FileWrapper::getContainerByPath($this->filename)))->isFile($this->filename))
            return $this->filename;
        return Storage::getFile(EXTERNAL_STORAGE, str_replace(realpath(ROOT_STORAGE), '', $this->filename));
    }
}
