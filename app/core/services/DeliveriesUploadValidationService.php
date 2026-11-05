<?php

namespace app\core\services;

use app\core\entities\Delivery;
use app\core\helpers\Validator;
use app\core\models\Deliveries;

class DeliveriesUploadValidationService
{
    /**
     * @param $delivery Delivery
     * @return array
     */
    public static function validate($delivery)
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
                'rules' => [Validator::TYPE_STRING => [0, 255]]
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
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }

        if (!empty($delivery->getId())) {
            $delivery_by_id = Deliveries::getById($delivery->getId());
            if ($delivery_by_id === null) {
                $validator->addError("delivery_not_found", "Delivery not found");
            }
        }

        return $validator->getErrors();
    }

}
