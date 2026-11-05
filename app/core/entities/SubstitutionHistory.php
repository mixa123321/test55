<?php

namespace app\core\entities;

use app\core\db\Mysql;

class SubstitutionHistory extends Entity
{
    const EVENT_ADD         = 'add';
    const EVENT_UPDATE      = 'update';
    const EVENT_DELETE      = 'delete';
    const EVENT_ORDER       = 'order';
    const EVENT_ADD_COND    = 'add_conditions';
    const EVENT_UPDATE_COND = 'update_conditions';

    private $id;
    private $substitution_id;
    private $event;
    private $addition;
    private $user_id;
    private $created_date;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = (int)$id;
    }

    public function getSubstitutionId()
    {
        return $this->substitution_id;
    }

    public function setSubstitutionId($id): void
    {
        $this->substitution_id = (int)$id;
    }

    public function getEvent()
    {
        return $this->event;
    }

    public function setEvent($event): void
    {
        $this->event = $event;
    }

    public function getAddition()
    {
        return $this->addition;
    }

    public function setAddition($addition): void
    {
        $this->addition = $addition;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($id): void
    {
        $this->user_id = (int)$id;
    }

    public function getCreatedDate()
    {
        return $this->created_date;
    }

    public function setCreatedDate($created_date): void
    {
        $this->created_date = $created_date;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $id = Mysql::saveRow('substitution_history', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'substitution_id' => $this->getSubstitutionId(),
            'event' => $this->getEvent(),
            'addition' => $this->getAddition(),
            'user_id' => $this->getUserId(),
            'created_date' => $this->getCreatedDate(),
        ];
    }
}
