<?php

namespace app\core\entities;

use app\core\db\Mysql;

class FTPGroup extends Entity
{
    private $groupname;
    private $gid;
    private $members;

    public function getGroupname()
    {
        return $this->groupname;
    }

    public function setGroupname($txt)
    {
        $this->groupname = (string)$txt;
    }

    public function getGid()
    {
        return $this->gid;
    }

    public function setGid($id)
    {
        $this->gid = (int)$id;
    }

    public function getMembers()
    {
        return $this->members;
    }

    public function setMembers($txt)
    {
        $this->members = (string)$txt;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (count($data) != 3)
            return;
        Mysql::saveRow('ftp_groups', $data);
    }

    public function toArray()
    {
        return [
            'groupname' => $this->getGroupname(),
            'gid' => $this->getGid(),
            'members' => $this->getMembers(),
        ];
    }
}