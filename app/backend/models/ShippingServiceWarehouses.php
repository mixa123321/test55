<?php

namespace app\backend\models;

use app\core\entities\ShippingServiceWarehouse;
use app\core\helpers\Validator;

class ShippingServiceWarehouses extends \app\core\models\ShippingServiceWarehouses
{
    /**
     * @param $shipping_service_warehouse ShippingServiceWarehouse
     * @return array
     */
    public static function validate(ShippingServiceWarehouse $shipping_service_warehouse)
    {
        $rules = [
            'shipping_service_id' => [
                'val' => $shipping_service_warehouse->getShippingServiceId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'warehouse_id' => [
                'val' => $shipping_service_warehouse->getWarehouseId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ]
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        if (!self::checkShippingServiceServiceAndWarehouseId($shipping_service_warehouse->getShippingServiceId(), $shipping_service_warehouse->getWarehouseId()))
            $validator->addError('uniq_index', '');
        return $validator->getErrors();
    }
}
