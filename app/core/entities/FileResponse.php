<?php


namespace app\core\entities;


class FileResponse
{

    private $result;
    private $name;
    private $size;
    private $message;
    private $content;

    /**
     * @return bool
     */
    public function getResult(): bool
    {
        return (bool)$this->result;
    }

    /**
     * @param mixed $result
     */
    public function setResult($result): void
    {
        $this->result = !!$result;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return (int)$this->size;
    }

    /**
     * @param mixed $size
     */
    public function setSize($size): void
    {
        $this->size = (int)$size;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message): void
    {
        $this->message = $message;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param mixed $content
     */
    public function setContent($content): void
    {
        $this->content = $content;
    }

    public function toArray(): array
    {
        return [
            'result' => $this->getResult(),
            'name' => $this->getName(),
            'size' => $this->getSize(),
            'message' => $this->getMessage(),
            'content' => $this->getContent()
        ];
    }


}