<?php

namespace app\backend\models;

use app\core\entities\Address;
use app\core\helpers\Validator;

class Addresses
{
    /**
     * @param $address Address
     * @return array
     */
    public static function validate(Address $address)
    {
       $rules = [
           'company' => [
               'val' => $address->getContact(),
               'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
           ],
           'address1' => [
               'val' => $address->getAddress1(),
               'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
           ],
           'address2' => [
               'val' => $address->getAddress2(),
               'rules' => [Validator::TYPE_STRING => [0, 255]]
           ],
           'town' => [
               'val' => $address->getTown(),
               'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 100]]
           ],
           'county' => [
               'val' => $address->getCounty(),
               'rules' => [Validator::TYPE_STRING => [0, 100]]
           ],
           'postcode' => [
               'val' => $address->getPostcode(),
               'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 20]]
           ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        return $validator->getErrors();
    }
}
