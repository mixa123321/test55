<?php

namespace app\core\entities;

use app\core\db\Mysql;
use app\core\services\crypto\StoredSecret;

class SFTPExport extends Entity
{
    private $id;
    private $supplier_id;
    private $is_active;
    private $login;
    private $password;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getSupplierId()
    {
        return $this->supplier_id;
    }

    public function setSupplierId($id)
    {
        $this->supplier_id = (int)$id;
    }

    public function getIsActive()
    {
        return $this->is_active;
    }

    public function setIsActive($is)
    {
        $this->is_active = (int)$is;
    }

    public function getLogin()
    {
        return $this->login;
    }

    public function setLogin($txt)
    {
        $this->login = (string)$txt;
    }

    public function getPassword()
    {
        return $this->password;
    }

    /**
     * The DB value is encrypted at rest by StoredSecret (or still plaintext before migration); a value from the code is
     * plaintext and decrypt() returns it unchanged. In memory the entity always holds the plaintext password.
     */
    public function setPassword($txt)
    {
        $this->password = (string)StoredSecret::decrypt((string)$txt);
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        $data = StoredSecret::encryptFields($data, ['password']);
        $id = Mysql::saveRow('sftp_export', $data);
        if (!$this->getId())
            $this->setId($id);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'supplier_id' => $this->getSupplierId(),
            'is_active' => $this->getIsActive(),
            'login' => $this->getLogin(),
            'password' => $this->getPassword(),
        ];
    }
}