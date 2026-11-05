<?php

namespace app\backend\models;

use app\core\entities\Delivery;
use app\core\helpers\Validator;

class Deliveries extends \app\core\models\Deliveries
{
    private static $countries_with_county = [
        'US'
    ];

    /**
     * @param $delivery Delivery
     * @return array
     */
    public static function validate($delivery, $warehouse_virtual, $edit = false)
    {
        $rules = [
            'name' => [
                'val' => $delivery->getName(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'contact' => [
                'val' => $delivery->getContact(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [2, 255]]
            ],
            'phone' => [
                'val' => $delivery->getPhone(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'email' => [
                'val' => $delivery->getEmail(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [5, 255]]
            ],
            'address1' => [
                'val' => $delivery->getAddress1(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'address2' => [
                'val' => $delivery->getAddress2(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'address3' => [
                'val' => $delivery->getAddress3(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'town' => [
                'val' => $delivery->getTown(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'county' => [
                'val' => $delivery->getCounty(),
                'rules' => [Validator::TYPE_STRING => [0, 50]]
            ],
            'postcode' => [
                'val' => $delivery->getPostcode(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'country_id' => [
                'val' => $delivery->getCountryId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 5]]
            ],
        ];
        if (in_array($rules['country_id']['val'], self::$countries_with_county) || $warehouse_virtual) {
            $rules['county']['rules'][Validator::REQUIRED] = 1;
            $rules['county']['rules'][Validator::TYPE_STRING][0] = 1;
        }
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        if (!$edit)
            if (!empty($delivery->getId())) {
                $delivery_by_id = Deliveries::getById($delivery->getId());
                if ($delivery_by_id === null) {
                    $validator->addError("delivery_not_found", "Delivery not found");
                }
            }
        return $validator->getErrors();
    }

//    public static function addDelivery($delivery_data, &$errors)
//    {
//        $fields_to_save = array_keys($delivery_data);
//        $delivery = new Delivery($delivery_data);
//        $errors = Deliveries::validate($delivery);
//        if (!empty($errors))
//            return false;
//        $deliveries_by_contact = Deliveries::getByContact($delivery->getContact());
//        $exist = false;
//        foreach ($deliveries_by_contact as $delivery_by_contact) {
//            $exist |= $delivery->equals($delivery_by_contact);
//            if ($exist) {
//                $delivery = $delivery_by_contact;
//                break;
//            }
//        }
//        if (!count($deliveries_by_contact) || !$exist)
//            $delivery->save($fields_to_save);
//        return $delivery;
//    }
}
