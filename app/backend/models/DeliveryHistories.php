<?php

namespace app\backend\models;

use app\core\helpers\Validator;
use app\core\entities\DeliveryHistory;

class DeliveryHistories extends \app\core\models\DeliveryHistories
{
    /**
     * @param $delivery_history DeliveryHistory
     * @return array
     */
    public static function validate($delivery_history)
    {
        $rules = [
            'delivery_id' => [
                'val' => $delivery_history->getDeliveryId(),
                'rules' => [Validator::REQUIRED => 1]
            ],
            'comment' => [
                'val' => $delivery_history->getComment(),
                'rules' => [Validator::REQUIRED => 1]
            ],
            'fields' => [
                'val' => $delivery_history->getFields(),
                'rules' => [Validator::REQUIRED => 1]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        return $validator->getErrors();
    }

    private static function changeDelivery($data)
    {
        $deliveryHistory = new DeliveryHistory($data);
        $deliveryHistory->save(array_keys($data));
    }

    public static function createDelivery($delivery_id, $user_id, $fields = null)
    {
        self::changeDelivery([
            'delivery_id' => $delivery_id,
            'user_id' => $user_id,
            'comment' => 'create',
            'fields' => json_encode((!is_null($fields) && is_array($fields)) ? $fields : (Deliveries::getById($delivery_id))->toArray())
        ]);
    }

    public static function hashDelivery($delivery_id, $fields)
    {
        self::changeDelivery([
            'delivery_id' => $delivery_id,
            'user_id' => -1,
            'comment' => 'hash',
            'fields' => json_encode($fields),
        ]);
    }

    public static function updateDelivery($delivery_id, $fields, $user_id)
    {
        self::changeDelivery([
            'delivery_id' => $delivery_id,
            'user_id' => $user_id,
            'comment' => 'update',
            'fields' => json_encode($fields)
        ]);
    }

    public static function mergeDelivery($delivery_id, array $merge_ids, $user_id)
    {
        foreach ($merge_ids as $merge_id) {
            $delivery = Deliveries::getById($merge_id);
            if ($delivery) {
                self::changeDelivery([
                    'delivery_id' => $delivery_id,
                    'comment' => 'merge',
                    'fields' => json_encode($delivery->toArray()),
                    'user_id' => $user_id
                ]);
            }
        }
    }
}
