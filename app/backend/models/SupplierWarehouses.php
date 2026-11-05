<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Address;
use app\core\entities\Charge;
use app\core\entities\RetainMeCredentials;
use app\core\entities\SupplierWarehouse;
use app\core\helpers\Validator;
use app\core\models\PackingSlips;
use app\core\services\crypto\Redactor;

class SupplierWarehouses extends \app\core\models\SuppliersWarehouses
{
    /**
     * @param $supplierWarehouse SupplierWarehouse
     * @return array
     */
    public static function validate(SupplierWarehouse $supplierWarehouse): array
    {
        $rules = [
            'warehouse_id' => [
                'val' => $supplierWarehouse->getWarehouseId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'supplier_id' => [
                'val' => $supplierWarehouse->getSupplierId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'VAT' => [
                'val' => $supplierWarehouse->getVAT(),
                'rules' => [Validator::TYPE_STRING => [0, 25]]
            ],
            'EORI' => [
                'val' => $supplierWarehouse->getEORI(),
                'rules' => [Validator::TYPE_STRING => [0, 15]]
            ],
            'sage_id' => [
                'val' => $supplierWarehouse->getSageId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'reg_number' => [
                'val' => $supplierWarehouse->getRegNumber(),
                'rules' => [Validator::TYPE_STRING => [0, 15]]
            ]
        ];
        $validator = new Validator($rules);
        $validator->validate();
        $address = json_decode($supplierWarehouse->getAddress(), true);
        if (empty($address))
            $validator->addError('empty_address', 'Active warehouse must have address');

        $addressValidator = new Validator([
            'address1' => [
                'val' => $address['address1'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'town' => [
                'val' => $address['town'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 50]]
            ],
            'postcode' => [
                'val' => $address['postcode'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 15]]
            ],
            'county' => [
                'val' => $address['county'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 50]]
            ],
            'country_id' => [
                'val' => $address['country_id'],
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 5]]
            ],
        ]);
        $addressValidator->validate();

        return array_merge($validator->getErrors(), $addressValidator->getErrors());
    }

    public static function hasDifferences($supplier_id, $new, $old, $user_id): bool
    {
        $diff_data = [];
        if (empty($old) && !empty($new)) {
            $warehouse_id = $new['warehouse_id'] ?? 0;
            unset($new['_supplier_id'], $new['warehouse_id']);
            array_walk($new, function(&$item) {
                $item = [
                    'old' => '',
                    'new' => $item
                ];
            });
            SuppliersHistory::setWarehouse($supplier_id, $warehouse_id, $new, $user_id);
            return true;
        }

        $main_fields = [
                        'VAT' => 'string',
                        'EORI' => 'string',
                        'sage_id' => 'string',
                        'shipping_api_key' => 'string',
                        'shipping_account_number' => 'string',
                        'return_label' => 'boolean',
                        'return_carrier_id' => 'string',
                        'packing_slip_type' => 'string',
                        'reg_number' => 'string',
                    ];
        foreach ($main_fields as $field => $typeof) {
            switch ($typeof) {
                case 'string':
                    $default = ''; break;
                case 'boolean':
                    $default = false; break;
                default:
                    $default = '';
            }
            $old_value = $old[$field] ?? $default;
            $new_value = $new[$field] ?? $default;
            settype($old_value, $typeof);
            settype($new_value, $typeof);
            if ($old_value !== $new_value)
                $diff_data[$field] = [
                    'old' => $old_value,
                    'new' => $new_value
                ];
        }

        $new_address = (new Address(json_decode($new['address'] ?? '') ?? []))->toSupplierAddressArray();
        $old_address = (new Address(json_decode($old['address'] ?? '') ?? []))->toSupplierAddressArray();

        foreach ($new_address as $address_key => $address_value)
            if ($address_value !== $old_address[$address_key])
                $diff_data['Address_' . $address_key] = [
                    'old' => $old_address[$address_key],
                    'new' => $address_value
                ];

        if ($new['packing_slip_type'] === PackingSlips::TYPE_RETAIN_ME) {
            $new_retainme_credentials = (new RetainMeCredentials(json_decode($new['retainme_credentials'] ?? '') ?? []))->toArray();
            $old_retainme_credentials = (new RetainMeCredentials(json_decode($old['retainme_credentials'] ?? '') ?? []))->toArray();
            foreach ($new_retainme_credentials as $credential => $new_retainme_credential)
                if ($new_retainme_credential !== $old_retainme_credentials[$credential]) {
                    $diff = ['old' => $old_retainme_credentials[$credential], 'new' => $new_retainme_credential];
                    // Passwords: history keeps only the fact of a change (SuppliersHistory redacts by key, this one is prefixed)
                    $diff_data['RetainMe_' . $credential] = Redactor::isSecretKey($credential) ? Redactor::mask($diff) : $diff;
                }
        }
        SuppliersHistory::updateWarehouse($supplier_id, $new['warehouse_id'] ?? 0, $diff_data, $user_id);
        return !empty($diff_data);
    }

    public static function getPackaging(int $sw_id): array
    {
        if (empty($sw_id))
            return [];
        return (new SafeMysql())->fetchAll("
            SELECT DISTINCT p.packaging_id, c.name, cg.id, cg.code, IF(cg.id IS NULL, NULL, cgp.is_default) is_default
            FROM suppliers_warehouses sw
                JOIN packages p ON sw.supplier_id = p.supplier_id
                JOIN package_warehouses pw ON p.id = pw.package_id AND sw.warehouse_id = pw.warehouse_id
                JOIN consumables c ON p.packaging_id = c.id
                LEFT JOIN charge_groups_packagings cgp ON p.packaging_id = cgp.packaging_id
                LEFT JOIN charge_groups cg ON cgp.charge_group_id = cg.id AND sw.warehouse_id = cg.warehouse_id AND cg.status
            WHERE sw.id = :sw_id
            ORDER BY p.packaging_id", ['sw_id' => $sw_id]);
    }

    public static function getPackagings(array $sw_ids): array
    {
        if (empty($sw_ids))
            return [];
        $packagings = (new SafeMysql())->fetchAll("
            SELECT DISTINCT sw.id, p.packaging_id, c.name
            FROM suppliers_warehouses sw
                JOIN packages p ON sw.supplier_id = p.supplier_id
                JOIN package_warehouses pw ON p.id = pw.package_id AND sw.warehouse_id = pw.warehouse_id
                JOIN consumables c ON p.packaging_id = c.id
            WHERE sw.id IN (:sw_ids)
            ORDER BY p.packaging_id", ['sw_ids' => $sw_ids]);
        if (!$packagings)
            return [];
        $res = [];
        foreach ($packagings as $packaging) {
            if (empty($res[$packaging['id']]))
                $res[$packaging['id']] = [];
            if (empty($res[$packaging['id']][$packaging['name']]))
                $res[$packaging['id']][$packaging['name']] = $packaging['packaging_id'];
        }
        return $res;
    }

    public static function getSuppliersBilling(int $warehouse_id): array
    {
        $suppliers = (new SafeMysql())->fetchAll("
            SELECT s.id, s.sage_id, GROUP_CONCAT(s.type) types
            FROM (
                SELECT sw.id, sw.sage_id, IF(GROUP_CONCAT(DISTINCT cc.status) = '0,1', 1, GROUP_CONCAT(DISTINCT cc.status)) cc_status, c.type
                FROM suppliers_warehouses sw
                    JOIN suppliers sup ON sw.supplier_id = sup.id AND JSON_CONTAINS(sup.warehouses, :warehouse_id_json)
                    JOIN charges_complex cc ON sw.id = cc.supplier_warehouse_id
                    JOIN charges c ON cc.charge_id = c.id AND c.status AND c.type
                WHERE sw.warehouse_id = :warehouse_id AND sw.is_billing
                GROUP BY sw.id, sw.sage_id, c.type) s
            WHERE s.cc_status <> '0'
            GROUP BY s.id, s.sage_id", [
            'warehouse_id' => $warehouse_id,
            'warehouse_id_json' => $warehouse_id
        ]);
        $res = [];
        if ($suppliers)
            foreach ($suppliers as $supplier) {
                $types = explode(',', $supplier['types']);
                $res[$supplier['sage_id']] = [
                    'id' => $supplier['id'],
                    'types' => array_map(function ($item) {
                        return Charge::TYPE_NAMES[$item];
                    }, $types),
                    'recurring' => []
                ];
            }
        $suppliers_recurring = (new SafeMysql())->fetchAll("
            SELECT sw.id, sw.sage_id, GROUP_CONCAT(cr.id) recurring
            FROM suppliers_warehouses sw
                JOIN suppliers sup ON sw.supplier_id = sup.id AND JSON_CONTAINS(sup.warehouses, :warehouse_id_json)
                JOIN charges_recurring cr ON sw.id = cr.supplier_warehouse_id AND cr.status
                JOIN charges c ON cr.charge_id = c.id AND c.status AND c.type = 0
            WHERE sw.warehouse_id = :warehouse_id AND sw.is_billing
            GROUP BY sw.id, sw.sage_id", [
            'warehouse_id' => $warehouse_id,
            'warehouse_id_json' => $warehouse_id
        ]);
        if ($suppliers_recurring)
            foreach ($suppliers_recurring as $supplier) {
                $recurring = explode(',', $supplier['recurring']);
                if (empty($res[$supplier['sage_id']]))
                    $res[$supplier['sage_id']] = [
                        'id' => $supplier['id'],
                        'types' => []
                    ];
                $res[$supplier['sage_id']]['recurring'] = $recurring;
            }
        return $res;
    }
}
