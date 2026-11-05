<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Address;
use app\core\entities\Warehouse;
use app\core\helpers\Validator;

class Warehouses extends \app\core\models\Warehouses
{
    public static function validate(Warehouse $warehouse) : array
    {
        $rules = [
            'label' => [
                'val' => $warehouse->getLabel(),
                'rules' => [Validator::TYPE_STRING => [0, 2]]
            ],
            'billing' => [
                'val' => $warehouse->getBilling(),
                'rules' => [Validator::TYPE_STRING => [0, 3]]
            ],
        ];
        $validator = new Validator($rules);
        $validator->validate();
        $warehouse_exist = self::getByName($warehouse->getLCId(), $warehouse->getName());
        if (!empty($warehouse_exist))
            if ($warehouse_exist->getId() !== $warehouse->getId())
                $validator->addError('unique_name', 'Name is not unique', 'name');
        return $validator->getErrors();
    }

    public static function validateCA(Warehouse $warehouse, $size_CA)
    {
        $rules = [
            'nums' => [
                'val' => $size_CA['nums'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'shelves' => [
                'val' => $size_CA['shelves'],
                'rules' => [Validator::REQUIRED => 1,Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'weight' => [
                'val' => $size_CA['weight'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0.01, PHP_FLOAT_MAX]]
            ],
        ];
        if ($warehouse->getMezzanine())
            $rules['nums_mez'] = [
                'val' => $size_CA['nums_mez'],
                'rules' => [Validator::TYPE_INT => [0, PHP_INT_MAX]]
            ];
        $validator = new Validator($rules);
        $validator->validate();
        $CA_size = Sizes::getById($size_CA['size_id']);
        if (empty($CA_size))
            $validator->addError('size_name_not_found', 'Size name is not found');
        return $validator->getErrors();
    }

    public static function validateTP($size_TP)
    {
        $rules = [
            'nums' => [
                'val' => $size_TP['nums'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'LP' => [
                'val' => $size_TP['LP'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
        ];
        $validator = new Validator($rules);
        $validator->validate();
        $TP_size = Sizes::getById($size_TP['size_id']);
        if (empty($TP_size))
            $validator->addError('size_name_not_found', 'Size name is not found');
        return $validator->getErrors();
    }

    public static function validateOB(Warehouse $warehouse, $size_OB)
    {
        $rules = [
            'nums' => [
                'val' => $size_OB['nums'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'cols' => [
                'val' => $size_OB['cols'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'rows' => [
                'val' => $size_OB['rows'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
        ];
        if ($warehouse->getMezzanine())
            $rules['nums_mez'] = [
                'val' => $size_OB['nums_mez'],
                'rules' => [Validator::TYPE_INT => [0, PHP_INT_MAX]]
            ];
        $validator = new Validator($rules);
        $validator->validate();
        $OB_size = Sizes::getById($size_OB['size_id']);
        if (empty($OB_size))
            $validator->addError('size_name_not_found', 'Size name is not found');
        return $validator->getErrors();
    }

    public static function validateConnection(&$connection_info)
    {
        $rules = [
            'host' => [
                'val' => $connection_info['host'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 300]]
            ],
            'api_key' => [
                'val' => $connection_info['api_key'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 50]]
            ],
        ];
        $validator = new Validator($rules);
        $validator->validate();
        return $validator->getErrors();
    }

    public static function setMinimumLocations(Warehouse $warehouse, $id_unlimited, $id_cart, $id_OB)
    {
        $data = [
            'nums' => 3,
            'size_id' => $id_cart,
            'shelves' => 3,
            'weight' => 50,
        ];
        if ($warehouse->getMezzanine())
            $data['nums_mez'] = 2;
        $warehouse->setCA($data);
        $warehouse->setTP([
            'nums' => 3,
            'LP' => 1,
            'size_id' => $id_unlimited,
        ]);
        $data = [
            'nums' => 5,
            'size_id' => $id_OB,
            'cols' => 3,
            'rows' => 10,
        ];
        if ($warehouse->getMezzanine())
            $data['nums_mez'] = 2;
        $warehouse->setOB($data);
        $warehouse->save(['id', 'CA', 'TP', 'OB']);
    }

    public static function createWithMinimumLocations($data)
    {
        $data['is_virtual'] = (int)(!empty($data['is_virtual']));
        $data['mezzanine'] = (int)(!empty($data['mezzanine']));
        $data['collection_by_customer'] = (int)(!empty($data['collection_by_customer']));
        $data['country_id'] = $data['address']['country_id'];
        $errors = [];
        if (!$data['is_virtual']) {
            $address = new Address([
                'contact' => $data['address']['company'],
                'country_id' => $data['address']['country_id'],
                'address1' => $data['address']['address1'],
                'address2' => $data['address']['address2'],
                'town' => $data['address']['town'],
                'county' => $data['address']['county'],
                'postcode' => $data['address']['postcode'],
            ]);
            $errors = Addresses::validate($address);
        }
        $warehouse = new Warehouse($data);
        $errors = array_merge($errors, Warehouses::validate($warehouse));
        if (!empty($errors))
            return $errors;
        if (empty($data['id']))
            unset($data['id']);
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $warehouse->save(array_keys($data));
        if (!empty($data['id'])) {
            $mysql->commit();
            return true;
        }
        [$id_unlimited, $id_cart, $id_OB] = Sizes::createMinimumSizes($warehouse->getId());
        self::setMinimumLocations($warehouse, $id_unlimited, $id_cart, $id_OB);
        Locations::createMinimumLocations($warehouse, $id_unlimited);
        $mysql->commit();
        return true;
    }
}