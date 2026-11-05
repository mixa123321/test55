<?php

namespace app\core\entities;

class Message extends Entity
{
    private $id;
    private $title;
    private $message;
    private $type;
    private $status;
    private $created_at;
    private $parameters;

    public function getId(): int
    {
        return (int)$this->id;
    }

    public function setId(int $id): Message
    {
        $this->id = $id;
        return $this;
    }

    public function getTitle(): string
    {
        return (string)$this->title;
    }

    public function setTitle(string $title): Message
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): string
    {
        return (string)$this->message;
    }

    public function setMessage(string $message): Message
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): string
    {
        return (string)$this->type;
    }

    public function setType(string $type): Message
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): string
    {
        return (string)$this->status;
    }

    public function setStatus(string $status): Message
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): string
    {
        return (string)$this->created_at;
    }

    public function setCreatedAt(string $created_at): Message
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getParameters(): string
    {
        return $this->parameters;
    }

    public function setParameters(string $parameters): Message
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'message' => $this->getMessage(),
            'type' => $this->getType(),
            'status' => $this->getStatus(),
            'created_at' => $this->getCreatedAt(),
            'parameters' => $this->getParameters(),
        ];
    }
}