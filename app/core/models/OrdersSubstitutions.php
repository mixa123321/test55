<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\OrdersSubstitutions as OrdersSubstitutionsEntity;
use app\backend\models\SKUs;

class OrdersSubstitutions
{
    public static function hasOrders($substitution_id): bool
    {
        return !!(new SafeMysql())->fetchScalar("SELECT 1 FROM orders_substitutions WHERE substitution_id = :id", ['id' => (int)$substitution_id]);
    }

    public static function hasSubstitutions($order_id): bool
    {
        return !!(new SafeMysql())->fetchScalar("SELECT COUNT(*) FROM orders_substitutions WHERE order_id = :id", ['id' => (int)$order_id]);
    }

    public static function add($order_id, $substitution_id, $quantity, $history_id)
    {
        $data = [
            'order_id' => $order_id,
            'substitution_id' => $substitution_id,
            'quantity' => $quantity,
            'history_id' => $history_id,
        ];
        $order_substitutions = new OrdersSubstitutionsEntity($data);
        $order_substitutions->save(array_keys($data));
    }

    public static function getOrderSubstitutionsSKUs($order_id)
    {
        $subst_SKUs = (new SafeMysql())->fetchAll("
            SELECT os.substitution_id,
                   sh.addition, os.quantity as items
            FROM orders_substitutions os
                JOIN SKUs s ON os.substitution_id = s.id
                JOIN substitution_history sh ON os.history_id = sh.id                
            WHERE os.order_id = :order_id", ['order_id' => (int)$order_id]);
        $res = [];
        if (empty($subst_SKUs))
            return $res;
        SKUBarcodes::addBarcodesToArray($subst_SKUs, 'substitution_id', 'barcodes', 'substitution_barcode');
        $SKUs = [];
        foreach ($subst_SKUs as $SKU_data) {
            if (empty($res[$SKU_data['substitution_id']]))
                $res[$SKU_data['substitution_id']] = [];
            $addition = json_decode($SKU_data['addition'], true);
            if (empty($addition) || $addition['order_id'] != $order_id)
                continue;
            foreach ($addition['SKUs'] as $datum) {
                if (empty($SKUs[$datum['id']]))
                    $SKUs[$datum['id']] = SKUs::getById($datum['id']);
                $res[$SKU_data['substitution_id']][] = [
                    'substitution_id' => $SKU_data['substitution_id'],
                    'substitution_barcode' => $SKU_data['substitution_barcode'],
                    'SKU_id' => $datum['id'],
                    'SKU_name' => !empty($SKUs[$datum['id']]) ? $SKUs[$datum['id']]->getName() : '-',
                    'barcode' => !empty($SKUs[$datum['id']]) ? SKUBarcodes::getBarcodesBySKUIdString($datum['id']) : '-',
                    'quantity' => $datum['quantity'],
                    'items' => $SKU_data['items'],
                ];
            }
        }
        return $res;
    }
}
