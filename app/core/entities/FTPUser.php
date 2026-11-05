<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\statical\WithDate;

class FTPUser extends Entity
{
    private $id;
    private $userid;
    private $passwd;
    private $uid;
    private $gid;
    private $homedir;
    private $shell;
    private $count;
    private $accessed;
    private $modified;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getUserId()
    {
        return $this->userid;
    }

    public function setUserId($id)
    {
        $this->userid = (string)$id;
    }

    public function getPasswd()
    {
        return $this->passwd;
    }

    public function setPasswd($txt)
    {
        $this->passwd = (string)$txt;
    }

    public function getUId()
    {
        return $this->uid;
    }

    public function setUId($id)
    {
        $this->uid = (int)$id;
    }

    public function getGId()
    {
        return $this->gid;
    }

    public function setGId($id)
    {
        $this->gid = (int)$id;
    }

    public function getHomeDir()
    {
        return $this->homedir;
    }

    public function setHomeDir($txt)
    {
        $this->homedir = (string)$txt;
    }

    public function getShell()
    {
        return $this->shell;
    }

    public function setShell($txt)
    {
        $this->shell = (string)$txt;
    }

    public function getCount()
    {
        return $this->count;
    }

    public function setCount($num)
    {
        $this->count = (int)$num;
    }

    public function getAccessed()
    {
        return empty($this->accessed) ? '' : date(WithDate::DATE_SQL_FORMAT, $this->accessed);
    }

    public function setAccessed($date)
    {
        $this->accessed = WithDate::dateToInt($date);
    }

    public function getModified()
    {
        return empty($this->modified) ? '' : date(WithDate::DATE_SQL_FORMAT, $this->modified);
    }

    public function setModified($date)
    {
        $this->modified = WithDate::dateToInt($date);
    }

    public static function getPassword($psw)
    {
        if (!empty($psw) && defined('SFTP_ENCODE'))
            return '{md5}' . base64_encode(md5($psw, true));
        return $psw;
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $data['passwd'] = self::getPassword($data['passwd']);
        $id = Mysql::saveRow('ftp_users', $data, Mysql::DB_SFTP);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'userid' => $this->getUserId(),
            'passwd' => $this->getPasswd(),
            'uid' => $this->getUId(),
            'gid' => $this->getGId(),
            'homedir' => $this->getHomeDir(),
            'shell' => $this->getShell(),
            'count' => $this->getCount(),
            'accessed' => $this->getAccessed(),
            'modified' => $this->getModified(),
        ];
    }
}