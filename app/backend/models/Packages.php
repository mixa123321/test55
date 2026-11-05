<?php

namespace app\backend\models;

use app\api\models\ErrorCodes;
use app\core\entities\Package;
use app\core\helpers\Validator;

class Packages extends \app\core\models\Packages
{
    const FILTER_PARAM = 'packages_filters';

    public static function validate(Package $package, int $lc_id, array $warehouses, array $consumables, $client)
    {
        $rules = [
            'name' => [
                'val' => $package->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [2, 50]]
            ],
            'instruction' => [
                'val' => $package->getInstruction(),
                'rules' => [Validator::TYPE_STRING => [0, 1000]]
            ],
            'supplier_id' => [
                'val' => $package->getSupplierId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'state' => [
                'val' => $package->getState(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT_SET => [Package::STATE_LOCKED, Package::STATE_ACTIVE]]
            ],
            'barcode' => [
                'val' => $package->getBarcode(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'packaging_id' => [
                'val' => $package->getPackagingId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
        ];
        $validator = new Validator($rules);
        $validator->validate();
        $supplier = Suppliers::getById($package->getSupplierId());
        if (empty($supplier))
            $validator->addError(ErrorCodes::FIELD_INVALID, 'Company is not found', 'supplier_id');
        else {
            $exist_package_id = Packages::getByNameAndSupplierId($package->getName(), $package->getSupplierId());
            if ((!$package->getId() && $exist_package_id) || ($package->getId() && $exist_package_id && $package->getId() != $exist_package_id))
                $validator->addError(ErrorCodes::FIELDS_NOT_UNIQUE, 'combination Name + Company is not unique', 'name + supplier_id');
        }
        [$packagings_supplier, $consumables_supplier] = Consumables::getConsumables($lc_id, $package->getSupplierId(), $client);
        $packaging_ids = array_keys($packagings_supplier);
        $consumable_ids = array_keys($consumables_supplier);
        if (!in_array($package->getPackagingId(), $packaging_ids))
            $validator->addError(ErrorCodes::FIELD_INVALID, 'Packaging is not correct', 'packaging_id');
        if (empty($warehouses))
            $validator->addError(ErrorCodes::FIELD_REQUIRED, 'must be selected at least one warehouse');
        else
            foreach ($warehouses as $warehouse_id => $check)
                if (!in_array($warehouse_id, $supplier->getWarehouses())) {
                    $validator->addError(ErrorCodes::FIELD_INVALID, 'One of warehouses is not correct');
                    break;
                }
        foreach ($consumables as $consumable_id => $quantity) {

            if (!in_array($consumable_id, $consumable_ids)) {
                $validator->addError(ErrorCodes::FIELD_INVALID, 'One of consumables is not correct');
                break;
            }
            if ($quantity < 0) {
                $validator->addError(ErrorCodes::FIELD_INVALID, 'Quantity must not be less 0');
                break;
            }
        }
        return $validator->getErrors();
    }
}
