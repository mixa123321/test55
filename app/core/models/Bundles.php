<?php

namespace app\core\models;

use app\backend\models\BundlesHistories;
use app\core\dbal\SafeMysql;
use app\core\entities\Bundle;
use app\core\entities\BundlesHistory;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\backend\models\Orders;
use app\core\entities\OrderHistory;
use app\core\helpers\Arr;
use app\core\helpers\Validator;

class Bundles
{

    public static function isBundleExists($supplier_id, $bundle_name)
    {
        $sql = "SELECT COUNT(*) AS cnt FROM bundles WHERE supplier_id = :supplier_id AND name = :bundle_name";
        $row = (new SafeMysql())->fetchOne($sql, [
            'supplier_id' => (int)$supplier_id,
            'bundle_name' => $bundle_name,
        ]);
        return (int)($row['cnt'] ?? 0);
    }

    public static function isBundleFromSupplier($supplier_id, $bundle_id): bool
    {
        $sql = "SELECT 1 AS res FROM bundles WHERE supplier_id = :supplier_id AND id = :bundle_id LIMIT 1";
        $row = (new SafeMysql())->fetchOne($sql, [
            'supplier_id' => (int)$supplier_id,
            'bundle_id'   => (int)$bundle_id,
        ]);

        return !empty($row);
    }

    public static function handleSKUs(Bundle $bundle, $SKUs)
    {
        $bundle_id = $bundle->getId();
        $name = $bundle->getName();
        $supplier_id = (int)$bundle->getSupplierId();
        $mysql = new SafeMysql();
        foreach ($SKUs as $SKU_id => $SKU_data)
            $mysql->execute("
                INSERT INTO bundles_SKUs (bundle_id, SKU_id, quantity) VALUES (:bundle_id, :SKU_id, :quantity)", [
                'bundle_id' => $bundle_id,
                'SKU_id' => (int)$SKU_id,
                'quantity' => (int)$SKU_data['quantity'],
            ]);
        //Check if any existing SKUs are matching bundle by barcode or customer reference
        $SKU_as_bundle = $mysql->fetchAll(
            'SELECT DISTINCT s.id
            FROM SKUs s
                LEFT JOIN SKU_barcodes sb ON sb.SKU_id = s.id
            WHERE s.id NOT IN (SELECT SKU_id FROM SKU_locations)
            AND (sb.barcode = :name OR s.customer_ref = :name)
            AND s.supplier_id = :supplier_id',
            ['name' => $name, 'supplier_id' => $supplier_id]
        );
        if (count($SKU_as_bundle) != 1)
            return;
        $SKU = $SKU_as_bundle[0];
        if (!empty($SKUs[$SKU['id']]))
            return;
        $mysql->execute("UPDATE SKUs SET state = :state WHERE id = :id", [
            'state' => SKU::BUNDLE_NUM,
            'id' => $SKU['id'],
        ]);
        $orders_bundles = $mysql->fetchAll("
            SELECT o.id, os.quantity, os.price
            FROM order_SKUs os
                JOIN orders o ON os.order_id = o.id
            WHERE SKU_id = :sku_id
            AND o.status = :status",
            [
                'sku_id' => (int)$SKU['id'],
                'status' => Order::BACKORDER_NUM
            ]
        );
        if (empty($orders_bundles))
            return;
        $order_history = new OrderHistory();
        $order_history->setUserId(-1);
        foreach ($orders_bundles as $order_bundle) {
            $order_history->setOrderId($order_bundle['id']);
            $history_bundle_SKUs = [];
            $mysql->beginTransaction();
            $sum_bundle = array_sum(array_map(function($item) { return $item['quantity']*$item['price']; }, $SKUs));
            $koeff = empty($sum_bundle) ? 0 : ($order_bundle['price'] / $sum_bundle);
            foreach ($SKUs as $SKU_id => $SKU_data) {
                $SKU_id = (int)$SKU_id;
                $history_bundle_SKUs[] = "#$SKU_id x {$SKU_data['quantity']}";
                $result_quantity = $SKU_data['quantity'] * $order_bundle['quantity'];
                $price = $SKU_data['price']*$koeff;
                $existing = $mysql->fetchOne("
                    SELECT quantity
                    FROM order_SKUs 
                    WHERE SKU_id = :sku_id 
                    AND order_id = :order_id",
                    [
                        'sku_id' => $SKU_id,
                        'order_id' => (int)$order_bundle['id']
                    ]
                );
                $existing_SKU_qty = (int)($existing['quantity'] ?? 0);
                if ($existing_SKU_qty) {
                    $mysql->execute("
                        UPDATE order_SKUs
                        SET price = (price*quantity + :new_price)/(quantity + :result_quantity), quantity = quantity + :result_quantity
                        WHERE SKU_id = :SKU_id AND order_id = :order_id", [
                        'new_price' => ($result_quantity * $price),
                        'result_quantity' => $result_quantity,
                        'SKU_id' => $SKU_id,
                        'order_id' => (int)$order_bundle['id'],
                    ]);
                } else {
                    $mysql->execute("
                        INSERT INTO order_SKUs (SKU_id, order_id, quantity, price) VALUES (:SKU_id, :order_id, :quantity, :price)", [
                        'SKU_id' => $SKU_id,
                        'order_id' => $order_bundle['id'],
                        'quantity' => $result_quantity,
                        'price' => $price,
                    ]);
                }
            }
            $mysql->execute("
                DELETE FROM order_SKUs WHERE SKU_id = :SKU_id AND order_id = :order_id", [
                'SKU_id' => (int)$SKU['id'],
                'order_id' => (int)$order_bundle['id'],
            ]);
            Bundles::addToOrder($order_bundle['id'], $SKU['id'], $order_bundle['quantity'], -1);
            $order_history->setCreateDate(time());
            $order_history->setComment("Recognized bundle '$name'. SKUs: " . implode(", ", $history_bundle_SKUs));
            $order_history->save(['order_id', 'create_date', 'user_id', 'comment']);
            $mysql->commit();
        }
    }

    public static function getById($bundle_id): ?Bundle
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM bundles WHERE id = :id",['id' => (int)$bundle_id]);
        return !empty($data) ? new Bundle($data) : null;
    }

    public static function getByOrderId($order_id)
    {
        return (new SafeMysql())->fetchAll("SELECT b.* FROM bundles b JOIN orders_bundles ob ON b.id = ob.bundle_id WHERE order_id = :order_id", ['order_id' => (int)$order_id]);
    }

    public static function getNamesBySupplier($supplier_id): array
    {
        return (new SafeMysql())->fetchAll("
            SELECT DISTINCT b.id, b.name
            FROM bundles b
                JOIN bundles_SKUs bs ON b.id = bs.bundle_id
                WHERE b.supplier_id = :supplier_id
                ORDER BY b.id DESC",
            ['supplier_id' => (int)$supplier_id]);
    }

    private static function getByNameAndSupplier($bundle_name, $supplier_id)
    {
        $result = (new SafeMysql())->fetchAll("
            SELECT bs.SKU_id, bs.quantity, b.id, b.cost_value, s.market_value
            FROM bundles b
                JOIN bundles_SKUs bs ON b.id = bs.bundle_id
                JOIN SKUs s ON bs.SKU_id = s.id AND s.supplier_id = :supplier_id
                WHERE b.name = :bundle_name
                AND b.supplier_id = :supplier_id", 
            ['bundle_name' => $bundle_name,'supplier_id' => (int)$supplier_id,]);
        SKUBarcodes::addBarcodesToArray($result, 'SKU_id');
        return $result;
    }

    public static function getBundleInfo($names, $supplier_id)
    {
        if (empty($names))
            return [];
        if (!is_array($names))
            $names = [$names];
        foreach ($names as $name)
            if (!empty($res = self::getByNameAndSupplier($name, $supplier_id)))
                return $res;
        return [];
    }

    public static function uploadCsv($uploaded_file, $supplier_id, $user_id = -1)
    {
        $file_handle = fopen($uploaded_file, "r");
        if (!$file_handle)
            return ['error' => 'Cannot open uploaded file'];
        $header = fgetcsv($file_handle);
        if (empty($header))
            return ['error' => 'File contents is empty'];
        $data_to_save = [];
        $total_rows = 0;
        $saved_rows = 0;
        while ($line = fgetcsv($file_handle)) {
            $total_rows++;
            if (empty($line[0]) || empty($line[1]) || empty($line[2]))
                continue;
            $bundle_name = trim(addslashes($line[0]));
            $sku_barcode = $line[1];
            $sku_obj = SKUs::getByBarcodeAndSupplier($sku_barcode, $supplier_id);
            if ($sku_obj === null)
                continue;
            if (empty($data_to_save[$bundle_name]))
                $data_to_save[$bundle_name] = [];
            $data_to_save[$bundle_name][$sku_obj->getId()] = ['quantity' => $line[2], 'price' => $sku_obj->getMarketValue()];
            $saved_rows++;
        }
        foreach ($data_to_save as $bundle_name => $skus)
            if (!self::isBundleExists($supplier_id, $bundle_name))
                self::add($bundle_name, $supplier_id, $skus, $user_id);
        return ['success' => "Total lines found: {$total_rows}. SKUs saved to bundles: {$saved_rows}"];
    }

    public static function getBundleOrders($bundle_id, $warehouse_id)
    {
        $records = (new SafeMysql())->fetchAll("
            SELECT o.id, o.ref, o.ref2, o.status, o.create_date, o.type_create, ob.quantity
            FROM orders o
                JOIN orders_bundles ob ON o.id = ob.order_id AND  ob.bundle_id = :bundle_id
            WHERE o.warehouse_id = :warehouse_id", 
            ['bundle_id' => (int)$bundle_id, 'warehouse_id' => (int)$warehouse_id,]);
        $stat = [
            'in_progress' => 0,
            'despatched' => 0,
            'cancelled' => 0,
        ];
        $orders = [];
        if (!empty($records)) {
            foreach ($records as $order) {
                if (Order::isDespatched($order['status']))
                    $stat['despatched'] += 1;
                elseif (Order::isCancelled($order['status']))
                    $stat['cancelled'] += 1;
                else {
                    $stat['in_progress'] += 1;
                    $order['status'] = Order::getStateName($order['status']);
                    $order['status_color'] = Order::colorStatus($order['status']);
                    $orders[] = $order;
                }
            }
        }
        return [$orders, $stat];
    }

    public static function hasOrderBundles($order_id): bool
    {
        return !empty((new SafeMysql())->fetchOne("SELECT 1 AS res FROM orders_bundles WHERE order_id = :order_id LIMIT 1",['order_id' => (int)$order_id]));
    }

    public static function hasOrders($bundle_id): bool
    {
        return !empty((new SafeMysql())->fetchOne("SELECT 1 FROM orders_bundles WHERE bundle_id = :bundle_id LIMIT 1", ['bundle_id' => (int)$bundle_id]));
    }

    public static function getOrderBundleSKUs($order_id): array
    {
        $sql = "SELECT b.id as bundle_id, b.name, ob.quantity as items, bh.addition FROM bundles b
                    JOIN orders_bundles ob ON ob.bundle_id = b.id AND ob.order_id = :order_id
                    LEFT JOIN bundles_history bh ON bh.id = ob.bundle_history_id AND bh.event = :event";
        $data = (new SafeMysql())->fetchAll($sql, ['order_id' => (int)$order_id, 'event' => BundlesHistory::EVENT_ADD_TO_ORDER,]);
        $res = [];
        if (empty($data))
            return $res;

        array_walk($data, function (&$item) {
            if (!empty($item['addition'])) {
                $SKUs_data = json_decode($item['addition'], TRUE)['SKUs'] ?? [];
                $SKUs = Arr::maps(SKUs::getByIds(array_keys($SKUs_data)), 'id', ['id', 'name', 'barcode', 'state']);
                array_walk($SKUs, function(&$SKU, $SKU_id) use ($SKUs_data) {
                   $SKU['quantity'] = intval($SKUs_data[$SKU_id] ?? 0);
                });
                $item['SKUs'] = $SKUs;
            }
            unset($item['addition']);
        });

        foreach ($data as $datum) {
            if (empty($res[$datum['bundle_id']]))
                $res[$datum['bundle_id']] = [];
            $SKU_items = !empty($datum['SKUs']) ? $datum['SKUs'] : Bundles::getBundleSKUs($datum['bundle_id']);
            if (!empty($SKU_items))
                foreach ($SKU_items as $SKU_item)
                    $res[$datum['bundle_id']][] = [
                        'bundle_id' => $datum['bundle_id'],
                        'bundle_name' => $datum['name'],
                        'SKU_id' => $SKU_item['id'],
                        'SKU_name' => $SKU_item['name'],
                        'barcode' => $SKU_item['barcode'],
                        'state' => $SKU_item['state'],
                        'quantity' => $SKU_item['quantity'],
                        'items' => $datum['items'],
                    ];
        }

        return $res;
    }

    public static function addToOrder($order_id, $bundle_id, $quantity, $user_id)
    {
        (new SafeMysql())->execute("INSERT INTO orders_bundles (order_id, bundle_id, quantity, bundle_history_id) VALUES (:order_id, :bundle_id, :quantity, :bundle_history_id)", [
            'order_id' => (int)$order_id,
            'bundle_id' => (int)$bundle_id,
            'quantity' => (int)$quantity,
            'bundle_history_id' => BundlesHistories::addToOrder($bundle_id, $order_id, $quantity, $user_id),
        ]);
    }

    public static function add($name, $supplier_id, $SKUs, $user_id, int $currency_id = 0, float $cost_value = 0)
    {
        $supplier_id = (int)$supplier_id;
        $bundle_id = (new SafeMysql())->insert("INSERT INTO bundles (supplier_id, name, currency_id, cost_value) VALUES (:supplier_id, :name, :currency_id, :cost_value)", [
            'supplier_id' => $supplier_id,
            'name' => $name,
            'currency_id' => $currency_id,
            'cost_value' => round($cost_value, 2),
        ]);
        self::handleSKUs(self::getById($bundle_id), $SKUs);
        $new_SKUs = [];
        foreach ($SKUs as $SKU_id => $SKU_data) {
            $new_SKUs[] = [
                'id' => $SKU_id,
                'quantity' => $SKU_data['quantity']
            ];
        }
        BundlesHistories::add($bundle_id, $user_id, $supplier_id, $name, $new_SKUs, $currency_id, $cost_value);
    }

    public static function edit(Bundle $bundle, $SKUs, $user_id): array
    {
        $old_bundle = self::getById($bundle->getId());
        if (empty($old_bundle))
            return [false, 'Not found'];

        $old_SKUs = array_map(function($SKU) {
            return [
                'id' => (int)$SKU['id'],
                'quantity' => $SKU['quantity']
            ];
        }, self::getBundleSKUs($bundle->getId()));

        $SKU_diff = false;

        foreach ($old_SKUs as $old_SKU) {
            if (!array_key_exists($old_SKU['id'], $SKUs)) {
                $SKU_diff = true;
            } else {
                if ((int)$SKUs[$old_SKU['id']]['quantity'] !== (int)$old_SKU['quantity']) {
                    $SKU_diff = true;
                }
            }
        }
        if (!$SKU_diff && (count($old_SKUs) !== count($SKUs))) {
            $SKU_diff = true;
        }

        if ($old_bundle->getName() === $bundle->getName() && $old_bundle->getCostValue() === $bundle->getCostValue() && $old_bundle->getCurrencyId() === $bundle->getCurrencyId() && !$SKU_diff) {
            return [false, 'Not difference'];
        }
        $mysql = new SafeMysql();
        $mysql->execute("UPDATE bundles SET name = :name, currency_id = :currency_id, cost_value = :cost_value WHERE id = :id", [
            'name' => $bundle->getName(),
            'currency_id' => $bundle->getCurrencyId(),
            'cost_value' => $bundle->getCostValue(),
            'id' => $bundle->getId(),
        ]);
        $mysql->execute('DELETE FROM bundles_SKUs WHERE bundle_id = :bundle_id', ['bundle_id' => $bundle->getId()]);
        self::handleSKUs($bundle, $SKUs);

        $new_SKUs = [];
        foreach ($SKUs as $SKU_id => $SKU_data) {
            $new_SKUs[] = [
                'id' => $SKU_id,
                'quantity' => $SKU_data['quantity']
            ];
        }

        BundlesHistories::edit($bundle->getId(), $user_id, $old_bundle->getName(), $bundle->getName(), $old_SKUs, $new_SKUs, $old_bundle->getCurrencyId(), $bundle->getCurrencyId(), $old_bundle->getCostValue(), $bundle->getCostValue());
        return [true, "Bundle " . $bundle->getName() . " updated successfully"];
    }

    public static function delete($bundle_id, $user_id)
    {
        $bundle = self::getById($bundle_id);
        if (empty($bundle))
            return;

        $SKUs = array_map(function($SKU) {
            return [
                'id' => $SKU['id'],
                'quantity' => $SKU['quantity']
            ];
        }, self::getBundleSKUs($bundle_id));
        $mysql = new SafeMysql();
        $mysql->execute("DELETE FROM bundles_SKUs WHERE bundle_id = :bundle_id", ['bundle_id' => (int)$bundle_id]);
        $mysql->execute("DELETE FROM bundles WHERE id = :id", ['id' => (int)$bundle_id]);

        BundlesHistories::delete($bundle_id, $user_id, $bundle->getSupplierId(), $bundle->getName(), $SKUs, $bundle->getCurrencyId(), $bundle->getCostValue());
    }

    public static function deleteSKU($bundle_id, $SKU_id, $user_id): ?int
    {
        $bundle_id = (int)$bundle_id;
        $SKU_id = (int)$SKU_id;

        $bundle = self::getById($bundle_id);
        if (empty($bundle))
            return null;

        $bundle_SKUs = self::getBundleSKUs($bundle->getId());

        (new SafeMysql())->execute("DELETE FROM bundles_SKUs WHERE bundle_id = :bundle_id AND SKU_id = :SKU_id", [
            'bundle_id' => $bundle_id,
            'SKU_id' => $SKU_id,
        ]);

        $mapSKUs = function($SKU) {
            return [
                'id' => (int)$SKU['id'],
                'quantity' => $SKU['quantity']
            ];
        };
        $old_SKUs = array_map($mapSKUs, $bundle_SKUs);
        $new_SKUs = array_map($mapSKUs, array_filter($bundle_SKUs, function($SKU) use ($SKU_id) {
            return (int)$SKU['id'] !== $SKU_id;
        }));

        BundlesHistories::edit($bundle->getId(), $user_id, $bundle->getName(), $bundle->getName(), $old_SKUs, $new_SKUs);

        return count($new_SKUs);
    }

    public static function getBundleSKUs($bundle_id): array
    {
        $sql = 'SELECT s.id, s.name, s.market_value, bs.quantity, s.state FROM bundles_SKUs bs  JOIN SKUs s ON s.id = bs.SKU_id WHERE bs.bundle_id = :bundle_id';
        $result = (new SafeMysql())->fetchAll($sql, ['bundle_id' => (int)$bundle_id]);                     
        SKUBarcodes::addBarcodesToArray($result);
        return $result;
    }

    public static function accumulationBundleSKU($SKU_id, $barcode, &$SKUs, $quantity, $price)
    {
        if (empty($SKUs[$SKU_id]))
            $SKUs[$SKU_id]['qty'] = 0;
        $SKUs[$SKU_id]['qty'] += $quantity;
        if (empty($SKUs[$SKU_id]['bundle_qty'][$barcode])) {
            $SKUs[$SKU_id]['bundle_qty'][$barcode] = $quantity;
            $SKUs[$SKU_id]['bundle_price'][$barcode] = $price;
        } else
            $SKUs[$SKU_id]['bundle_qty'][$barcode] += $quantity;
    }

    public static function walkSKUsWithBundles(&$ordSKUs)
    {
        array_walk($ordSKUs, function(&$item) {
            $sum = $nums = 0;
            if (!empty($item['bundle_qty']))
                foreach ($item['bundle_qty'] as $bundle => $qty) {
                    $nums += $qty;
                    $sum += $item['bundle_price'][$bundle] * $qty;
                }
            $item['price'] = ($sum + ($item['qty'] - $nums)*($item['price'] ?? 0)) / $item['qty'];
            unset($item['bundle_qty'], $item['bundle_price']);
        });
    }


    public static function getBundlesByOrder(Order $order): array
    {
        $sql = "
        SELECT b.name, ob.quantity quantity_order, bS.SKU_id, bS.quantity quantity_bundle
        FROM orders_bundles ob
            JOIN bundles b ON b.id = ob.bundle_id
            JOIN bundles_SKUs bS ON bS.bundle_id = ob.bundle_id AND bS.SKU_id IN (SELECT oS.SKU_id FROM order_SKUs oS WHERE oS.order_id = ob.order_id)
        WHERE ob.order_id = :order_id";
        return (new SafeMysql())->fetchAll($sql, ['order_id' => (int)$order->getId()]);
    }

    public static function validateCostValue(float $cost_value, string $name = 'Market Value'): array {
        $rules = [
            $name => [
                'val' => $cost_value,
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DOUBLE => [0, 1000000000]]
            ],
        ];
        return (new Validator($rules))->validate();
    }

    public static function getBundlesWithProblemCurrency(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $sql = "
        SELECT DISTINCT b.id
        FROM bundles b
            LEFT JOIN bundles_SKUs bS ON bS.bundle_id = b.id
            LEFT JOIN SKUs S ON S.id = bS.SKU_id
            LEFT JOIN currency c ON c.currency = S.market_currency AND c.id <> b.currency_id
        WHERE c.id IS NOT NULL OR b.currency_id = 0 AND b.id IN (:ids)";
        return (new SafeMysql())->fetchArrayOneField($sql, ['ids' => array_map('intval', $ids)]);
    }

    public static function getBundlesByIds($bundle_ids): array
    {
        if (!$bundle_ids)
            return [];
        $sql = "
        SELECT bundle_id, SKU_id, quantity 
        FROM bundles_SKUs
        WHERE bundle_id IN (:bundle_ids);";
        return (new SafeMysql())->fetchAll($sql, ['bundle_ids' => array_map('intval', $bundle_ids)]);
    }
}