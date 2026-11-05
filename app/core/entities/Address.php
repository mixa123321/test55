<?php

namespace app\core\entities;

use app\core\helpers\Str;
use app\core\helpers\View;
use app\core\statical\Countries;

class Address extends Entity
{
    private $name;
    private $contact;
    private $phone;
    private $email;
    private $vat;
    private $eori;
    private $address1;
    private $address2;
    private $address3;
    private $town;
    private $county;
    private $postcode;
    private $country_id;

    private const MAX_LEN_STR = 60;

    public function __construct($data = [], $prefix = "")
    {
        if (!empty($prefix)) {
            $data = $this->filterFieldsByPrefix($data, $prefix);
        }

        parent::__construct($data);
    }

    public function empty()
    {
        foreach ($this->toArray() as $key)
            if (!empty($key))
                return false;
        return true;
    }

    public function toArray()
    {
        return [
            'name' => $this->getName(),
            'contact' => $this->getContact(),
            'email' => $this->getEmail(),
            'phone' => $this->getPhone(),
            'vat' => $this->getVat(),
            'eori' => $this->getEori(),
            'address1' => $this->getAddress1(),
            'address2' => $this->getAddress2(),
            'address3' => $this->getAddress3(),
            'town' => $this->getTown(),
            'postcode' => $this->getPostcode(),
            'county' => $this->getCounty(),
            'country_id' => $this->getCountryId(),
        ];
    }

    public function getContact()
    {
        return $this->contact;
    }

    public function setContact($contact)
    {
        $this->contact = (string)$contact;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = (string)$name;
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

    public function getVat()
    {
        return $this->vat;
    }

    public function setVat($vat)
    {
        $this->vat = (string)$vat;
    }

    public function getEori()
    {
        return $this->eori;
    }

    public function setEori($eori)
    {
        $this->eori = (string)$eori;
    }

    public function getAddressLine()
    {
        return Str::toOneLine(
            $this->address1,
            $this->address2,
            $this->address3
        );
    }

    private static function getStr($str)
    {
        if (strlen($str) > self::MAX_LEN_STR)
            return substr($str, 0, self::MAX_LEN_STR - 3) . '...';
        return $str;
    }

    public function toHash($lineLength = 0, $maxLines = 0, $separator = "<br/>")
    {
        $hashed = '<span class="badge badge-danger">Hashed</span>';
        $result = [];
        if (!empty($this->getName()))
            $result[] = $hashed;
        if (!empty($this->getContact()))
            $result[] = $hashed;
        if (!empty($this->getAddress1()))
            $result[] = $hashed;
        if (!empty($this->getAddress2()))
            $result[] = $hashed;
        if (!empty($this->getAddress3()))
            $result[] = $hashed;
        $line = View::toHtml($this->getTown());
        $line = trim($line, ", ");
        if (!empty($line))
            $result[] = $line;
        $line = View::toHtml($this->getCounty()) . ' ' . View::toHtml($this->getPostcode());
        $line = trim($line);
        if (!empty($line))
            $result[] = $line;
        $line = Countries::getName($this->getCountryId());
        if (!empty($line))
            $result[] = $line;
        if (!empty($this->getPhone()))
            $result[] = $hashed;
        if (!empty($this->getEmail()))
            $result[] = $hashed;
        if ($maxLines > 0)
            $result = \array_slice($result, 0, $maxLines);
        if ($lineLength > 0) {
            foreach ($result as &$line)
                $line = mb_substr($line, 0, $lineLength);
            unset($line);
        }
        return implode($separator, $result);
    }

    public function toString($lineLength = 0, $maxLines = 0, $separator = "<br/>")
    {
        $result = [];
        if (!empty($this->getName()))
            $result[] = View::toHtml(self::getStr($this->getName()));
        if (!empty($this->getContact()))
            $result[] = View::toHtml(self::getStr($this->getContact()));
        if (!empty($this->getAddress1()))
            $result[] = View::toHtml(self::getStr($this->getAddress1()));
        if (!empty($this->getAddress2()))
            $result[] = View::toHtml(self::getStr($this->getAddress2()));
        if (!empty($this->getAddress3()))
            $result[] = View::toHtml(self::getStr($this->getAddress3()));
        $line = View::toHtml($this->getTown());
        $line = trim($line, ", ");
        if (!empty($line))
            $result[] = $line;
        $line = View::toHtml($this->getCounty()) . ' ' . View::toHtml($this->getPostcode());
        $line = trim($line);
        if (!empty($line))
            $result[] = $line;
        $line = Countries::getName($this->getCountryId());
        if (!empty($line))
            $result[] = $line;
        if (!empty($this->getPhone()))
            $result[] = View::toHtml(self::getStr($this->getPhone()));
        if (!empty($this->getEmail()))
            $result[] = View::toHtml(self::getStr($this->getEmail()));
        if ($maxLines > 0)
            $result = \array_slice($result, 0, $maxLines);
        if ($lineLength > 0) {
            foreach ($result as &$line)
                $line = mb_substr($line, 0, $lineLength);
            unset($line);
        }
        return implode($separator, $result);
    }

    public function toShortString($lineLength = 0, $maxLines = 0, $separator = "<br/>")
    {
        $result = [];
        $line = View::toHtml($this->getTown());
        $line = trim($line, ", ");
        if (!empty($line))
            $result[] = $line;
        $line = View::toHtml($this->getCounty()) . ' ' . View::toHtml($this->getPostcode());
        $line = trim($line);
        if (!empty($line))
            $result[] = $line;
        $line = Countries::getName($this->getCountryId());
        if (!empty($line))
            $result[] = $line;
        if ($maxLines > 0)
            $result = \array_slice($result, 0, $maxLines);
        if ($lineLength > 0) {
            foreach ($result as &$line)
                $line = mb_substr($line, 0, $lineLength);
            unset($line);
        }
        return implode($separator, $result);
    }

    public function toSupplierAddressArray(): array
    {
        return [
            'address1' => $this->getAddress1(),
            'address2' => $this->getAddress2(),
            'address3' => $this->getAddress3(),
            'town' => $this->getTown(),
            'county' => $this->getCounty(),
            'postcode' => $this->getPostcode(),
            'country_id' => $this->getCountryId()
        ];
    }

    public function toSupplierAddressJson()
    {
        return json_encode($this->toSupplierAddressArray(), JSON_HEX_APOS );
    }

    public function toBillingAddressArray(): array
    {
        return [
            'name' => $this->getName(),
            'contact' => $this->getContact(),
            'email' => $this->getEmail(),
            'address1' => $this->getAddress1(),
            'address2' => $this->getAddress2(),
            'address3' => $this->getAddress3(),
            'town' => $this->getTown(),
            'county' => $this->getCounty(),
            'postcode' => $this->getPostcode(),
            'country_id' => $this->getCountryId()
        ];
    }

    public function toBillingAddressJson()
    {
        return json_encode($this->toBillingAddressArray(), JSON_HEX_APOS );
    }
}
