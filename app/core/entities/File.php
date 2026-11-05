<?php

namespace app\core\entities;

class File extends Entity
{
    const RECEIVING_TYPE_UPLOAD = 'upload';
    const RECEIVING_TYPE_FILE_SYSTEM = 'file_system';

    private $name;
    private $path;
    private $tmp_name;
    private $type;
    private $error = 0;
    private $size;
    private $rows;
    private $receiving_type = self::RECEIVING_TYPE_UPLOAD;

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param  mixed  $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param  mixed  $path
     */
    public function setPath($path): void
    {
        $this->path = $path;
    }

    /**
     * @return mixed
     */
    public function getTmpName()
    {
        return $this->tmp_name;
    }

    /**
     * @param  mixed  $tmp_name
     */
    public function setTmpName($tmp_name): void
    {
        $this->tmp_name = $tmp_name;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param  mixed  $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param  mixed  $error
     */
    public function setError($error): void
    {
        $this->error = $error;
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param  mixed  $size
     */
    public function setSize($size): void
    {
        $this->size = $size;
    }

    /**
     * @return mixed
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param  mixed  $rows
     */
    public function setRows($rows): void
    {
        $this->rows = $rows;
    }

    public function getReceivingType(): string
    {
        return $this->receiving_type;
    }

    public function setReceivingType(string $receiving_type): void
    {
        $this->receiving_type = $receiving_type;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'path' => $this->getPath(),
            'tmp_name' => $this->getTmpName(),
            'type' => $this->getType(),
            'error' => $this->getError(),
            'size' => $this->getSize(),
            'rows' => $this->getRows(),
            'receiving_type' => $this->getReceivingType(),
        ];
    }

    public static function create(): File
    {
        return new self();
    }
}
