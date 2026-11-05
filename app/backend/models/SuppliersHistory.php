<?php

namespace app\backend\models;

use app\core\entities\Supplier;
use app\core\entities\SupplierHistory;
use app\core\services\crypto\Redactor;

class SuppliersHistory extends \app\core\models\SuppliersHistory
{

    private static function store($data)
    {
        (new SupplierHistory($data))->save(array_keys($data));
    }

    public static function create($supplier_id, $info, $user_id)
    {
        self::store([
            'supplier_id' => $supplier_id,
            'type' => SupplierHistory::TYPE_CREATE,
            'additional' => json_encode($info),
            'user_id' => $user_id,
        ]);
    }

    public static function updateInfo($supplier_id, $info, $user_id)
    {
        self::store([
            'supplier_id' => $supplier_id,
            'type' => SupplierHistory::TYPE_UPDATE_INFO,
            'additional' => json_encode($info),
            'user_id' => $user_id,
        ]);
    }

    public static function changeAccess($supplier_id, $warehouse_id, $access, $user_id)
    {
        self::store([
            'supplier_id' => $supplier_id,
            'type' => SupplierHistory::TYPE_CHANGE_ACCESS,
            'additional' => json_encode([
                'warehouse_id' => $warehouse_id,
                'access' => $access
            ]),
            'user_id' => $user_id,
        ]);
    }

    public static function setWarehouse($supplier_id, $warehouse_id, $data, $user_id)
    {
        self::store([
            'supplier_id' => $supplier_id,
            'type' => SupplierHistory::TYPE_SET_WAREHOUSE,
            'additional' => json_encode([
                'warehouse_id' => $warehouse_id,
                'data' => Redactor::redact($data)
            ]),
            'user_id' => $user_id,
        ]);
    }

    public static function updateWarehouse($supplier_id, $warehouse_id, $data, $user_id)
    {
        if (!empty($data))
            self::store([
                'supplier_id' => $supplier_id,
                'type' => SupplierHistory::TYPE_UPDATE_WAREHOUSE,
                'additional' => json_encode([
                    'warehouse_id' => $warehouse_id,
                    'data' => Redactor::redact($data)
                ]),
                'user_id' => $user_id,
            ]);
    }

    public static function addUser($supplier_id, $warehouse_id, $supplier_user_id, $user_id)
    {
        self::store([
            'supplier_id' => $supplier_id,
            'type' => SupplierHistory::TYPE_ADD_USER,
            'additional' => json_encode([
                'warehouse_id' => $warehouse_id,
                'user_id' => $supplier_user_id
            ]),
            'user_id' => $user_id,
        ]);
    }

    public static function lock($supplier_id, $lock, $user_id)
    {
        self::store([
            'supplier_id' => $supplier_id,
            'type' => $lock ? SupplierHistory::TYPE_LOCK : SupplierHistory::TYPE_UNLOCK,
            'user_id' => $user_id,
        ]);
    }

}
