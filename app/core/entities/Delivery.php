<?php

namespace app\core\entities;

use app\core\db\Mysql;

class Delivery extends Entity
{
    private $id;
    private $name;
    private $contact;
    private $phone;
    private $email;
    private $address1;
    private $address2;
    private $address3;
    private $town;
    private $county;
    private $postcode;
    private $country_id;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = (string)$name;
    }

    public function getContact()
    {
        return $this->contact;
    }

    public function setContact($contact)
    {
        $this->contact = (string)$contact;
    }

    public function getPhone()
    {
        return $this->phone;
    }

    public function setPhone($phone)
    {
        $this->phone = (string)$phone;
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function setEmail($email)
    {
        $this->email = (string)$email;
    }

    public function getAddress1()
    {
        return $this->address1;
    }

    public function setAddress1($address1)
    {
        $this->address1 = (string)$address1;
    }

    public function getAddress2()
    {
        return $this->address2;
    }

    public function setAddress2($address2)
    {
        $this->address2 = (string)$address2;
    }

    public function getAddress3()
    {
        return $this->address3;
    }

    public function setAddress3($address3)
    {
        $this->address3 = (string)$address3;
    }

    public function getTown()
    {
        return $this->town;
    }

    public function setTown($town)
    {
        $this->town = (string)$town;
    }

    public function getCounty()
    {
        return $this->county;
    }

    public function setCounty($county)
    {
        $this->county = (string)$county;
    }

    public function getPostcode()
    {
        return $this->postcode;
    }

    public function setPostcode($postcode)
    {
        $this->postcode = (string)$postcode;
    }

    public function getCountryId()
    {
        return $this->country_id;
    }

    public function setCountryId($country_id)
    {
        $this->country_id = (string)$country_id;
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
            $this->setId(Mysql::saveRow("deliveries", $data));
        } else {
            Mysql::saveRow("deliveries", $data);
        }
    }

    public function equals($data): bool
    {
        $arr = $this->toArray();
        $eq = true;
        foreach ($data as $field => $val) {
            if ($field === 'id')
                continue;
            $eq &= ((string)$val === (string)$arr[$field] && strlen($val) == strlen((string)$arr[$field]));
        }
        return $eq;
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'contact' => $this->getContact(),
            'phone' => $this->getPhone(),
            'email' => $this->getEmail(),
            'address1' => $this->getAddress1(),
            'address2' => $this->getAddress2(),
            'address3' => $this->getAddress3(),
            'town' => $this->getTown(),
            'county' => $this->getCounty(),
            'postcode' => $this->getPostcode(),
            'country_id' => $this->getCountryId(),
        ];
    }

}
