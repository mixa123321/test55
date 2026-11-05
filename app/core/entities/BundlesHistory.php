<?php

namespace app\core\entities;

use app\core\db\Mysql;

class BundlesHistory extends Entity
{

    const EVENT_ADD = 'add';
    const EVENT_EDIT = 'edit';
    const EVENT_DELETE = 'delete';

    const EVENT_ADD_TO_ORDER = 'add_to_order';

    private $id;
    private $bundle_id;
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
        $this->id = $id;
    }

    public function getBundleId()
    {
        return $this->bundle_id;
    }

    public function setBundleId($bundle_id): void
    {
        $this->bundle_id = $bundle_id;
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

    public function setUserId($user_id): void
    {
        $this->user_id = $user_id;
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
            $this->setId(Mysql::saveRow("bundles_history", $data));
        } else {
            Mysql::saveRow("bundles_history", $data);
        }
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'bundle_id' => $this->getBundleId(),
            'event' => $this->getEvent(),
            'addition' => $this->getAddition(),
            'user_id' => $this->getUserId(),
            'created_date' => $this->getCreatedDate(),
        ];
    }
}
