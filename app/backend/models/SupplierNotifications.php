<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Batch;
use app\core\entities\Order;
use app\core\entities\SupplierNotification;
use app\core\entities\SupplierNotificationHistory;
use app\core\helpers\Arr;
use app\core\helpers\HTTP;
use app\core\models\Warehouses;

class SupplierNotifications extends \app\core\models\SupplierNotifications
{
    const SKU_MINIMUM_AMOUNT    = 1;
    const SKU_OUT_OF_STOCK      = 2;
    const ASN_ARRIVAL           = 3;
    const ASN_COMPLETION        = 4;
    const ASN_NOTIFICATION      = 34;
//    const RETURNS = 5;
    const SHIPPING_MAPPING      = 6;
    const PENDING_APPROVAL      = 7;
    const CONSUMABLE_COUNT      = 8;
    const MIN_CONSUMABLE_COUNT  = 9;
    const ORDERS_ERROR          = 10;
    const ORDERS_BACKORDER      = 11;
    const ORDERS_NOTIFICATION   = 1011;
    const SKU_EXPIRED           = 12;
    const ORDERS_QUERY          = 13;

    private static $func_notification = [
        self::SKU_MINIMUM_AMOUNT => 'SKUMinimum',
        self::SKU_OUT_OF_STOCK => 'SKUOutOfStock',
        self::ASN_ARRIVAL => 'Arrival',
        self::ASN_COMPLETION => 'Completion',
//        self::RETURNS => 'Returns',
        self::SHIPPING_MAPPING => 'ShippingMappingApproval',
        self::PENDING_APPROVAL => 'ShippingMappingApproval',
        self::CONSUMABLE_COUNT => 'ConsumableCount',
        self::MIN_CONSUMABLE_COUNT => 'MinConsumableCount',
    ];

    private static $order_statuses = [
        self::ORDERS_ERROR      => Order::ERROR,
        self::ORDERS_BACKORDER  => Order::BACKORDER,
        self::ORDERS_QUERY      => Order::QUERY,
    ];

    public static function sendNotificationASNs($now = null)
    {
        $date_notifier = empty($now) ? date('Y-m-d', time() - 3600*24) : $now;
        $sql = "SELECT se.name, snh.id, snh.create_date, snh.type_id, snh.additional
                FROM supplier_notification_history snh
                    JOIN supplier_notifications sn ON snh.type_id = sn.type_id
                    JOIN supplier_emails se ON sn.supplier_email_id = se.id AND se.supplier_id = snh.supplier_id AND se.is_active = 1
                    JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
                WHERE snh.type_id IN (:type_ids) AND
                      snh.type = :type AND
                      DATE_FORMAT(snh.create_date, '%Y-%m-%d') = :date_notifier
                ORDER BY se.name, snh.create_date";
        $letters_date = (new SafeMysql())->fetchAll($sql, [
            'type_ids' => [self::ASN_ARRIVAL, self::ASN_COMPLETION],
            'type' => SupplierNotificationHistory::TYPE_NOTIFICATION_CREATE,
            'date_notifier' => $date_notifier
        ]);
        $subject = "ASNs notification ($date_notifier)";
        $letters = [];
        foreach ($letters_date as $value) {
            $messages = self::getMessage($value['type_id'], json_decode($value['additional'], true));
            if (!empty($messages))
                $letters[$value['name']][$value['id']] = $messages;
        }
        foreach ($letters as $email => $letter) {
            if (empty($letter))
                continue;
            $trs = [];
            foreach ($letter as $line)
                $trs[] = "<tr>
                              <td>{$line['created']}</td>
                              <td><a href='" . HTTP::getDomain() .  "{$line['url']}'>{$line['title']}</a></td>
                              <td>{$line['status']}</td>
                          </tr>";
            $message = "
                <html>
                    <head>
                        <title>$subject</title>
                    </head>
                    <body>
                        <table border='1'>
                            <tr>
                                <td><b>Created</b></td>
                                <td><b>ANS</b></td>
                                <td><b>Status</b></td>
                            </tr>
                            " . implode($trs) . "
                        </table>
                    </body>
                </html>";
            mail($email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
            SupplierNotificationHistories::sendNotification(self::ASN_NOTIFICATION, ['email' => $email, 'letter' => $letter]);
        }
    }

    public static function sendNotificationSKUsMinimum($lc_id)
    {
        list($letters, $SKU_lst) = self::getSKUMinimumLetters();
        if (empty($letters) || empty($SKU_lst))
            return;
        $SKUs_stock = self::getSKUStock($SKU_lst);
        $SKUs_minimum = self::getSKUMinimum($SKU_lst);
        $warehouses_lst = Arr::map(Warehouses::getAll($lc_id), 'id', 'name');
        foreach ($letters as $email => $SKUs) {
            $minimum = [];
            foreach ($SKUs as $SKU_id => $warehouses)
                if (!empty($warehouses))
                    foreach ($warehouses as $warehouse_id)
                        if (!empty($SKUs_minimum[$SKU_id][$warehouse_id])) {
                            $quantity = (int)($SKUs_stock[$SKU_id]['warehouse_id'][$warehouse_id] ?? 0);
                            if ($quantity < $SKUs_minimum[$SKU_id][$warehouse_id])
                                self::addMessageMinimum($SKU_id, $SKUs_stock, $warehouses_lst[$warehouse_id], $quantity, $SKUs_minimum[$SKU_id][$warehouse_id],$minimum);
                        }
            if (!empty($minimum))
                self::createMailMinimum($email, $minimum);
        }
    }

    public static function sendNotificationConsumablesMinimum($lc_id)
    {
        list($letters, $consumable_lst) = self::getConsumableMinimumLetters();
        if (empty($letters) || empty($consumable_lst))
            return;
        $consumables_stock = ConsumablesCount::getAllStock($consumable_lst);
        $consumables_minimum = ConsumablesCount::getConsumablesMinimum($consumable_lst);
        $warehouses_lst = Arr::maps(Warehouses::getAll($lc_id), 'id', ['id', 'name']);
        foreach ($letters as $email => $consumables) {
            $minimum = [];
            foreach ($consumables as $consumable_id => $warehouses)
                if (!empty($warehouses))
                    foreach ($warehouses as $warehouse_id)
                        if (!empty($consumables_minimum[$consumable_id][$warehouse_id])) {
                            $quantity = (int)($consumables_stock[$consumable_id]['warehouse_id'][$warehouse_id] ?? 0);
                            if ($quantity < $consumables_minimum[$consumable_id][$warehouse_id])
                                self::addMessageConsumableMinimum($consumable_id, $consumables_stock, $warehouses_lst[$warehouse_id], $quantity, $consumables_minimum[$consumable_id][$warehouse_id],$minimum);
                        }
            if (!empty($minimum))
                self::createMailConsumableMinimum($email, $minimum);
        }
    }

    public static function sendNotificationSKUs($lc_id)
    {
        $week = strtotime(date('Y-m-d')) - 3600*24*7;
        list($letters, $SKU_lst) = self::getSKUOutOfLetters();
        if (empty($letters) || empty($SKU_lst))
            return;
        $SKUs_stock = self::getSKUStock($SKU_lst);
        $SKUs_outof = self::getSKUOutOf($SKU_lst);
        $warehouses_lst = Arr::map(Warehouses::getAll($lc_id), 'id', 'name');
        foreach ($letters as $email => $SKUs) {
            $stock = [];
            foreach ($SKUs as $SKU_id => $warehouses)
                if (!empty($warehouses))
                    foreach ($warehouses as $warehouse_id) {
                        $message_outof = false;
                        if (!isset($SKUs_stock[$SKU_id]['warehouse_id'][$warehouse_id])) {
                            if (isset($SKUs_outof[$SKU_id]['warehouse_id'][$warehouse_id])) {
                                if (empty($SKUs_outof[$SKU_id]['warehouse_id'][$warehouse_id]['quantity']))
                                    $message_outof = ($SKUs_outof[$SKU_id]['warehouse_id'][$warehouse_id]['date'] > $week);
                                else {
                                    SKUHistories::updateStock($SKU_id, $warehouse_id, 0);
                                    $message_outof = true;
                                }
                            } else {
                                SKUHistories::updateStock($SKU_id, $warehouse_id, 0);
                                $message_outof = true;
                            }
                        } else {
                            $quantity = (int)($SKUs_outof[$SKU_id]['warehouse_id'][$warehouse_id]['quantity'] ?? -1);
                            if ($SKUs_stock[$SKU_id]['warehouse_id'][$warehouse_id] != $quantity) {
                                SKUHistories::updateStock($SKU_id, $warehouse_id, $SKUs_stock[$SKU_id]['warehouse_id'][$warehouse_id]);
                                $message_outof = true;
                            } elseif (empty($SKUs_outof[$SKU_id]['warehouse_id'][$warehouse_id]['quantity']))
                                $message_outof = true;
                        }
                        if ($message_outof)
                            self::addMessageOutOf($SKU_id, $SKUs_outof, $warehouses_lst[$warehouse_id], $stock);
                    }
            if (!empty($stock))
                self::createMailOutOfStock($email, $stock);
        }
    }

    public static function sendNotificationOrders(string $order_status)
    {
        if (!in_array($order_status, self::$order_statuses))
            die('Wrong order status');
        $order_status_num = Order::getStatusFromName($order_status);
        $email_status_num = array_search($order_status, self::$order_statuses);

        $letters = self::getOrdersLetters($email_status_num, $order_status_num);
        if (empty($letters))
            return;
        foreach ($letters as $email => $suppliers) {
            $letter_data = [];
            foreach ($suppliers as $supplier_name => $statuses) {
                $tables = [];
                foreach ($statuses as $status => $warehouses) {
                    $lines = [];
                    foreach ($warehouses as $warehouse_name => $orders_data) {
                        $tds = [];
                        foreach ($orders_data as $order_id)
                            $tds[] = "<a href=\"" . HTTP::getDomain() . "/orders/view_order?id=$order_id\">#$order_id</a>";
                        $lines[] = "<tr><td valign='top'>$warehouse_name</td><td>" . implode(' ', $tds) . "</td></tr>";
                    }
                    $tables[] = '<tr><td colspan="2" align="center"><b>Status "' . self::$order_statuses[$status] . '"</b></td></tr>' . implode($lines);
                }
                $letter_data[] = "<h3>$supplier_name</h3><table border=\"1\"><tr><th>Warehouse</th><th>Orders</th></tr>" . implode($tables) . '</table>';
            }
            self::createMailOrders($email, $letter_data);
        }
    }

    public static function sendNotificationExpired($create_date = null)
    {
        if (is_null($create_date))
            $create_date = time() - 24*3600;
        $letters = self::getExpiredLetters(date('Y-m-d', $create_date));
        if (empty($letters))
            return;
        foreach ($letters as $email => $suppliers) {
            $tables = [];
            foreach ($suppliers as $supplier_name => $warehouses) {
                $trs = [];
                foreach ($warehouses as $warehouse_name => $SKUs) {
                    $lines = [];
                    foreach ($SKUs as $SKU_id => $SKU_data)
                        $lines[] = "<td  valign=\"top\"><a href=\"" . HTTP::getDomain() . "/SKUs/?id=$SKU_id\">{$SKU_data['name']} #$SKU_id<br/>{$SKU_data['barcode']}</a></td>
                                    <td>" . implode('<br/>', $SKU_data['batches']) . "</td><td>{$SKU_data['quantity']}</td>";
                    $trs[] = '<tr><td  valign="top" rowspan="' . count($lines) . "\">$warehouse_name</td>" . implode('</tr><tr>', $lines) . '</tr>';
                }
                $tables[] = "<h3>$supplier_name</h3><table border=\"1\"><tr><th>Warehouse</th><th>SKU</th><th>Expired batches</th><th>Quantity</th></tr>" . implode($trs) . '</table>';
            }
            self::createMailExpired($email, $tables);
        }
    }

    public static function sendNotificationShippings()
    {
        $sql = "SELECT se.name, o.id, o.ref, css.channel_shipping_code, o.ref2, o.type_create, w.name wname, s.shop_url
                FROM orders o 
                    JOIN channels_shipping_services css ON o.channels_shipping_services_id = css.id
                    JOIN warehouses w ON o.warehouse_id = w.id
                    LEFT JOIN shops s ON o.shop_id = s.id
                    JOIN supplier_emails se ON o.supplier_id = se.supplier_id AND se.is_active = 1
                    JOIN supplier_notifications sn ON se.id = sn.supplier_email_id AND sn.type_id = " . self::SHIPPING_MAPPING . "
                    JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
                WHERE o.status = " . Order::AWAITING_CHANNEL_SERVICE_MAPPING_NUM;
        $letters_shippings = (new SafeMysql())->fetchAll($sql);
        $subject = "Shipping mapping required (" . date('Y-m-d', time() - 3600*24) . ")";
        $letters = [];
        foreach ($letters_shippings as $value) {
            $messages = self::getMessage(self::SHIPPING_MAPPING, [
                'id' => $value['id'],
                'ref' => $value['ref'],
                'ref2' => $value['ref2'],
                'type_create' => $value['type_create'],
                'wname' => $value['wname'],
                'shop' => $value['shop_url'],
            ]);
            if (!empty($messages))
                $letters[$value['name']][$value['channel_shipping_code']][] = $messages;
        }
        foreach ($letters as $email => $letter) {
            if (empty($letter))
                continue;
            $trs = [];
            foreach ($letter as $service => $orders) {
                $trservice = [];
                foreach ($orders as $order)
                    $trservice[] = "<td><a href='" . HTTP::getDomain() .  "{$order['url']}'>{$order['title']}</a></td>
                                    <td><b>Source:</b>{$order['source']}<br/><b>Warehouse:</b>{$order['warehouse']}</td>";
                for ($i = 0; $i < count($trservice); $i++)
                    $trservice[$i] = "<tr>" . (!$i ? ("<td" . (count($trservice) > 1 ? (" rowspan='" . count($trservice) . "'") : '') . ">$service</td>") : '') . "{$trservice[$i]}</tr>";
                $trs = array_merge($trs, $trservice);
            }
            $message = "
                <html>
                    <head>
                        <title>$subject</title>
                    </head>
                    <body>
                        <table border='1'>
                            <tr>
                                <td><b>Service</b></td>
                                <td colspan='2'><b>Orders</b></td>
                            </tr>
                            " . implode($trs) . "
                        </table>
                    </body>
                </html>";
            mail($email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
            SupplierNotificationHistories::sendNotification(self::SHIPPING_MAPPING, ['email' => $email, 'letter' => $letter]);
        }
    }

    public static function sendNotificationApproval()
    {
        $sql = "SELECT se.name, o.id, o.ref, o.ref2, o.type_create, w.name wname, s.shop_url
                FROM orders o
                    JOIN warehouses w ON o.warehouse_id = w.id
                    LEFT JOIN shops s ON o.shop_id = s.id
                    JOIN supplier_emails se ON o.supplier_id = se.supplier_id AND se.is_active = 1
                    JOIN supplier_notifications sn ON se.id = sn.supplier_email_id AND sn.type_id = " . self::PENDING_APPROVAL . "
                    JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
                WHERE o.status = " . Order::PENDING_APPROVAL_NUM;
        $letters_approval = (new SafeMysql())->fetchAll($sql);
        $subject = "Pending approval (" . date('Y-m-d', time() - 3600*24) . ")";
        $letters = [];
        foreach ($letters_approval as $value) {
            $messages = self::getMessage(self::PENDING_APPROVAL, array_slice($value, 1, 5) + ['shop' => $value['shop_url']]);
            if (!empty($messages))
                $letters[$value['name']][] = $messages;
        }
        foreach ($letters as $email => $letter) {
            if (empty($letter))
                continue;
            $trs = [];
            foreach ($letter as $order)
                $trs[] = "<tr>
                              <td><a href='" . HTTP::getDomain() .  "{$order['url']}'>{$order['title']}</a></td>
                              <td><b>Source:</b>{$order['source']}<br/><b>Warehouse:</b>{$order['warehouse']}</td>
                          </tr>";
            $message = "
                <html>
                    <head>
                        <title>$subject</title>
                    </head>
                    <body>
                        <table border='1'>
                            <tr>
                                <td><b>Orders</b></td>
                                <td><b>Description</b></td>
                            </tr>
                            " . implode($trs) . "
                        </table>
                    </body>
                </html>";
            mail($email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
            SupplierNotificationHistories::sendNotification(self::PENDING_APPROVAL, ['email' => $email, 'letter' => $letter]);
        }
    }

    public static function sendNotificationShipping($supplier_id, $type_id, $params)
    {
        if (empty($params['id']) || empty($params['required']))
            return;
        $emails = self::getEmails($supplier_id, $type_id);
        if (empty($emails))
            return;
        foreach ($emails as $email) {
            $date_message = date('jS F Y \a\t H:i:s', $params['required']);
            $message = "Shipping mapping required for order <a href='" . HTTP::getDomain() . "/orders/?id={$params['id']}'>{$params['ref']}" .
                       (empty($params['ref2']) ? '' : " ({$params['ref2']})") . "</a>. The message was sent $date_message";
            mail($email, 'Shipping mapping required', $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
        }
    }

    public static function sendNotificationConsumables($lc_id)
    {
        list($letters, $consumable_lst) = self::getConsumableOutOfLetters();
        if (empty($letters) || empty($consumable_lst))
            return;
        $consumables_stock = ConsumablesCount::getAllStock($consumable_lst);
        $warehouses_lst = Arr::maps(Warehouses::getAll($lc_id), 'id', ['id', 'name']);
        foreach ($letters as $email => $consumables) {
            $stock = [];
            foreach ($consumables as $consumable_id => $warehouses)
                if (!empty($warehouses))
                    foreach ($warehouses as $warehouse_id)
                        self::addMessageConsumable($consumable_id, $consumables_stock, $warehouses_lst[$warehouse_id], $stock);
            if (!empty($stock))
                self::createMailConsumables($email, $stock);
        }
    }

    public static function addNotification($supplier_id, $type_id, $params)
    {
        $SNT = SupplierNotificationTypes::getById($type_id);
        if (empty($SNT))
            return;
        SupplierNotificationHistories::addNotification($type_id, $supplier_id, $params);
        if ($type_id == self::SHIPPING_MAPPING)
            self::sendNotificationShipping($supplier_id, $type_id, $params);
    }

    private static function getMessage($type_id, $params)
    {
        if (in_array($type_id, array_keys(self::$func_notification)))
            return ('app\backend\models\SupplierNotifications::message' . self::$func_notification[$type_id])($params);
        return [];
    }

    private static function messageSKUMinimum($params)
    {
        if (empty($params['id']))
            return [];
        return [
            'title' => "{$params['name']} (#{$params['id']})<br/>{$params['barcode']}",
            'url' => "/SKUs/?id={$params['id']}",
            'warehouse' => $params['wname'],
            'quantity' => $params['quantity'],
            'min_stock_level' => $params['min_stock_level']
        ];
    }

    private static function messageSKUOutOfStock($params)
    {
        if (empty($params['id']))
            return [];
        return [
            'title' => "{$params['name']} (#{$params['id']})<br/>{$params['barcode']}",
            'url' => "/SKUs/?id={$params['id']}",
            'warehouse' => $params['wname'],
        ];
    }

    private static function messageConsumableCount($params)
    {
        if (empty($params['id']))
            return [];
        return [
            'title' => "{$params['name']} (#{$params['id']})",
            'url' => '/consumables/?like_name=' . urlencode($params['name']) . "&supplier_id={$params['supplier_id']}",
            'stock' => [[
                'warehouse' => $params['wname'],
                'quantity' => $params['quantity'],
            ]]
        ];
    }

    private static function messageMinConsumableCount($params)
    {
        if (empty($params['id']))
            return [];
        return [
            'title' => "{$params['name']} (#{$params['id']})",
            'url' => '/consumables/?like_name=' . urlencode($params['name']) . "&supplier_id={$params['supplier_id']}",
            'stock' => [[
                'warehouse' => $params['wname'],
                'quantity' => $params['quantity'],
                'min_stock_level' => $params['min_stock_level'],
            ]]
        ];
    }

    private static function messageArrival($params)
    {
        if (empty($params['id']) || empty($params['arrived']))
            return [];
        return [
            'created' => date('jS F Y \a\t H:i:s', $params['arrived']),
            'title' => "ASN #{$params['id']}" . (empty($params['ref']) ? '' : " ({$params['ref']})"),
            'url' => "/goods_in/view_asn/?id={$params['id']}",
            'status' => !empty($params['revert']) ? 'reverted to status \'New\'' : 'arrived',
        ];
    }

    private static function messageCompletion($params)
    {
        if (empty($params['id']) || empty($params['completed']))
            return [];
        return [
            'created' => date('jS F Y \a\t H:i:s', $params['completed']),
            'title' => "ASN #{$params['id']}" . (empty($params['ref']) ? '' : " ({$params['ref']})"),
            'url' => "/goods_in/view_asn/?id={$params['id']}",
            'status' => 'completed' . ($params['status'] != 'completed' ? " ({$params['status']})" : ''),
        ];
    }

    private static function messageShippingMappingApproval($params)
    {
        if (empty($params['id']))
            return [];
        return [
            'title' => "Order {$params['ref']}" . (empty($params['ref2']) ? '' : " ({$params['ref2']})"),
            'url' => "/orders/?id={$params['id']}",
            'source' => $params['type_create'] . (empty($params['shop']) ? '' : ", {$params['shop']}"),
            'warehouse' => $params['wname'],
        ];
    }

    public static function setActiveList($type_id, array $supplier_email_ids, $user_id)
    {
        $type_id = (int)$type_id;
        if (empty($supplier_email_ids))
            return;
        foreach ($supplier_email_ids as $supplier_email_id) {
            $supplier_email_id = (int)$supplier_email_id;
            $check = (new SafeMysql())->fetchScalar('SELECT 1 FROM supplier_notifications WHERE type_id = :type_id AND supplier_email_id = :supplier_email_id', [
                'type_id' => $type_id,
                'supplier_email_id' => $supplier_email_id
            ]);
            if (!$check) {
                $data = [
                  'type_id' => $type_id,
                  'supplier_email_id' => $supplier_email_id
                ];
                $sn = new SupplierNotification($data);
                $sn->save(array_keys($data));
                SupplierNotificationHistories::changeSupplierEmailForType($type_id, $supplier_email_id, true, $user_id);
            }
        }
    }

    public static function unsetActiveList($type_id, array $supplier_email_ids, $user_id)
    {
        $type_id = (int)$type_id;
        if (empty($supplier_email_ids))
            return;
        array_walk($supplier_email_ids, function(&$m) use ($user_id, $type_id) {
            $m = (int)$m;
            SupplierNotificationHistories::changeSupplierEmailForType($type_id, $m, false, $user_id);
        });
        $supplier_email_sql = implode(', ', $supplier_email_ids);
        (new SafeMysql())->execute("DELETE FROM supplier_notifications WHERE type_id = {$type_id} AND supplier_email_id IN ({$supplier_email_sql})");
    }

    public static function removeForSupplierEmail($supplier_email_id)
    {
        (new SafeMysql())->execute("DELETE FROM supplier_notifications WHERE supplier_email_id = :supplier_email_id", [
            'supplier_email_id' => (int)$supplier_email_id,
        ]);
    }

    private static function getOrdersLetters(int $email_status_num, int $order_status_num)
    {
        $sql = "SELECT se.name, o.id, sn.type_id, w.name wname, sup.name sname
                FROM orders o
                    JOIN suppliers sup ON o.supplier_id = sup.id
                    JOIN supplier_emails se ON o.supplier_id = se.supplier_id AND se.is_active = 1
                    JOIN supplier_notifications sn ON se.id = sn.supplier_email_id AND sn.type_id = :email_status_num AND o.status = :order_status_num
                    JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
                    JOIN warehouses w ON o.warehouse_id = w.id
                ORDER BY sn.type_id, se.name, o.id";
        $letters_orders = (new SafeMysql())->fetchAll($sql, [
            'email_status_num' => $email_status_num,
            'order_status_num' => $order_status_num
        ]);
        if (empty($letters_orders))
            return [];
        $letters = [];
        foreach ($letters_orders as $value)
            $letters[$value['name']][$value['sname']][$value['type_id']][$value['wname']][] = $value['id'];
        return $letters;
    }

    private static function getExpiredLetters($for_date)
    {
        $sql = "SELECT se.name, sh.additional_info, w.name wname, sup.name supname, s.name sname, s.id
                FROM SKU_history sh
                    JOIN SKUs s ON sh.SKU_id = s.id
                    JOIN suppliers sup ON s.supplier_id = sup.id
                    JOIN supplier_emails se ON s.supplier_id = se.supplier_id AND se.is_active = 1
                    JOIN supplier_notifications sn ON se.id = sn.supplier_email_id AND sn.type_id = " . self::SKU_EXPIRED . "
                    JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
                    JOIN warehouses w ON sh.warehouse_id = w.id
                WHERE sh.event = '" . SKUHistories::EVENT_UPDATE_EXPIRED . "' AND DATE_FORMAT(sh.event_date, '%Y-%m-%d') = :for_date
                ORDER BY se.name, s.id";
        $letters_expired = (new SafeMysql())->fetchAll($sql, ['for_date' => $for_date]);
        if (empty($letters_expired))
            return [];
        SKUBarcodes::addBarcodesToArray($letters_expired);
        $letters = [];
        foreach ($letters_expired as $value) {
            $additional = json_decode($value['additional_info'], true);
            if (empty($letters[$value['name']][$value['supname']][$value['wname']][$additional['SKU_id']]))
                $letters[$value['name']][$value['supname']][$value['wname']][$additional['SKU_id']] = ['name' => $value['sname'], 'barcode' => $value['barcode'], 'quantity' => $additional['quantity'] ?? 0];
            else
                $letters[$value['name']][$value['supname']][$value['wname']][$additional['SKU_id']]['quantity'] += $additional['quantity'] ?? 0;
            $name = Batch::toBatchInfo($additional['number'], $additional['expired'], false);
            if (empty($letters[$value['name']][$value['supname']][$value['wname']][$additional['SKU_id']]['batches']) ||
                !in_array($name, $letters[$value['name']][$value['supname']][$value['wname']][$additional['SKU_id']]['batches']))
                $letters[$value['name']][$value['supname']][$value['wname']][$additional['SKU_id']]['batches'][] = $name;
        }
        return $letters;
    }

    private static function getSKUOutOfLetters()
    {
        $sql = "SELECT se.name, s.id, sn.type_id, sup.warehouses
                FROM SKUs s
                    JOIN suppliers sup ON s.supplier_id = sup.id
                    JOIN supplier_emails se ON s.supplier_id = se.supplier_id AND se.is_active = 1
                    JOIN supplier_notifications sn ON se.id = sn.supplier_email_id AND sn.type_id = " . self::SKU_OUT_OF_STOCK . "
                    JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
                ORDER BY sn.type_id, se.name, s.id";
        $letters_SKUs = (new SafeMysql())->fetchAll($sql);
        if (empty($letters_SKUs))
            return [[], []];
        $letters = [];
        foreach ($letters_SKUs as $value)
            $letters[$value['name']][$value['id']] = json_decode($value['warehouses'], true);
        return [$letters, array_unique(Arr::column($letters_SKUs, 'id'))];
    }

    private static function getSKUMinimumLetters()
    {
        $sql = "SELECT se.name, s.id, sup.warehouses
                FROM SKUs s
                    JOIN suppliers sup ON s.supplier_id = sup.id
                    JOIN supplier_emails se ON s.supplier_id = se.supplier_id AND se.is_active = 1
                    JOIN supplier_notifications sn ON se.id = sn.supplier_email_id AND sn.type_id = " . self::SKU_MINIMUM_AMOUNT . "
                    JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
                ORDER BY sn.type_id, se.name, s.id";
        $letters_SKUs = (new SafeMysql())->fetchAll($sql);
        if (empty($letters_SKUs))
            return [[], []];
        $letters = [];
        foreach ($letters_SKUs as $value)
            $letters[$value['name']][$value['id']] = json_decode($value['warehouses'], true);
        return [$letters, array_unique(Arr::column($letters_SKUs, 'id'))];
    }

    private static function getConsumableOutOfLetters()
    {
        $sql = "SELECT se.name, con.id, sn.type_id, sup.warehouses
                FROM consumables con
                    JOIN suppliers sup ON con.supplier_id = sup.id
                    JOIN supplier_emails se ON con.supplier_id = se.supplier_id AND se.is_active = 1
                    JOIN supplier_notifications sn ON se.id = sn.supplier_email_id AND sn.type_id = " . self::CONSUMABLE_COUNT . "
                    JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
                ORDER BY sn.type_id, se.name, con.id";
        $letters_consumables = (new SafeMysql())->fetchAll($sql);
        if (empty($letters_consumables))
            return [[], []];
        $letters = [];
        foreach ($letters_consumables as $value)
            $letters[$value['name']][$value['id']] = json_decode($value['warehouses'], true);
        return [$letters, array_unique(Arr::column($letters_consumables, 'id'))];
    }

    private static function getConsumableMinimumLetters()
    {
        $sql = "SELECT se.name, con.id, sup.warehouses
                FROM consumables con
                    JOIN suppliers sup ON con.supplier_id = sup.id
                    JOIN supplier_emails se ON con.supplier_id = se.supplier_id AND se.is_active = 1
                    JOIN supplier_notifications sn ON se.id = sn.supplier_email_id AND sn.type_id = " . self::MIN_CONSUMABLE_COUNT . "
                    JOIN supplier_notification_type_map sntm ON se.supplier_id = sntm.supplier_id AND sn.type_id = sntm.type_id
                ORDER BY sn.type_id, se.name, con.id";
        $letters_consumables = (new SafeMysql())->fetchAll($sql);
        if (empty($letters_consumables))
            return [[], []];
        $letters = [];
        foreach ($letters_consumables as $value)
            $letters[$value['name']][$value['id']] = json_decode($value['warehouses'], true);
        return [$letters, array_unique(Arr::column($letters_consumables, 'id'))];
    }

    private static function getSKUStock($SKU_lst)
    {
        $sql = "SELECT s.id, s.name,
                       IFNULL(SUM(sl.quantity), 0) quantity,
                       l.warehouse
                FROM SKUs s
                    JOIN SKU_locations sl ON s.id = sl.SKU_id
                    JOIN locations l ON sl.location_id = l.id AND l.type_id IN (:allowed_types)
                WHERE s.id IN (:SKU_ids)
                GROUP BY s.id, l.warehouse";
        $SKUs_stock_res = (new SafeMysql())->fetchAll($sql, [
            'allowed_types' => Locations::$allowed_type_stock,
            'SKU_ids' => $SKU_lst
        ]);
        if (empty($SKUs_stock_res))
            return [];
        SKUBarcodes::addBarcodesToArray($SKUs_stock_res);
        $SKUs_stock = [];
        foreach ($SKUs_stock_res as $SKU) {
            if (empty($SKUs_stock[$SKU['id']]))
                $SKUs_stock[$SKU['id']] = ['name' => $SKU['name'], 'barcode' => $SKU['barcode']];
            $SKUs_stock[$SKU['id']]['warehouse_id'][$SKU['warehouse']] = $SKU['quantity'];
        }
        return $SKUs_stock;
    }

    private static function getSKUMinimum($SKU_lst)
    {
        $sql = "SELECT s.id,
                       IFNULL(sw.min_stock_level, 0) min_stock_level,
                       sw.warehouse_id
                FROM SKUs s
                    JOIN SKU_warehouses sw ON s.id = sw.SKU_id
                WHERE s.id IN (:SKU_ids)";
        $SKUs_minimum_res = (new SafeMysql())->fetchAll($sql, ['SKU_ids' => $SKU_lst]);
        if (empty($SKUs_minimum_res))
            return [];
        $SKUs_minimum = [];
        foreach ($SKUs_minimum_res as $SKU)
            $SKUs_minimum[$SKU['id']][$SKU['warehouse_id']] = $SKU['min_stock_level'];
        return $SKUs_minimum;
    }

    private static function getSKUOutOf($SKU_lst)
    {
        $sql = "SELECT s.id, UNIX_TIMESTAMP(sh.event_date) event_date, sh.adjustment, sh.warehouse_id, s.name
                FROM SKUs s
                    JOIN SKU_history sh ON s.id = sh.SKU_id AND event = '" . SKUHistories::EVENT_STOCK . "'
                WHERE s.id IN (:SKU_ids)";
        $SKUs_outof_res = (new SafeMysql())->fetchAll($sql, ['SKU_ids' => $SKU_lst]);
        if (empty($SKUs_outof_res))
            return [];
        SKUBarcodes::addBarcodesToArray($SKUs_outof_res);
        $SKUs_outof = [];
        foreach ($SKUs_outof_res as $SKU) {
            if (empty($SKUs_outof[$SKU['id']]))
                $SKUs_outof[$SKU['id']] = ['name' => $SKU['name'], 'barcode' => $SKU['barcode']];
            $SKUs_outof[$SKU['id']]['warehouse_id'][$SKU['warehouse_id']] = ['quantity' => $SKU['adjustment'], 'date' => $SKU['event_date']];
        }
        return $SKUs_outof;
    }

    private static function addMessageOutOf($SKU_id, $SKUs_outof, $warehouse, &$stock)
    {
        if (empty($SKUs_outof[$SKU_id])) {
            $SKU = SKUs::getById($SKU_id);
            $name = $SKU->getName();
            $barcode = SKUBarcodes::getBarcodesBySKUIdString($SKU->getId());
        } else {
            $name = $SKUs_outof[$SKU_id]['name'];
            $barcode = $SKUs_outof[$SKU_id]['barcode'];
        }
        $messages = self::getMessage(self::SKU_OUT_OF_STOCK, [
            'id' => $SKU_id,
            'name' => $name,
            'barcode' => $barcode,
            'wname' => $warehouse,
        ]);
        if (!empty($messages)) {
            if (empty($stock[$SKU_id]))
                $stock[$SKU_id] = $messages;
            else
                $stock[$SKU_id]['warehouse'] .= ', ' . $messages['warehouse'];
        }
    }

    private static function addMessageConsumable($consumable_id, $consumables_stock, $warehouse, &$stock)
    {
        if (empty($consumables_stock[$consumable_id])) {
            $consumable = Consumables::getById($consumable_id);
            $name = $consumable->getName();
            $supplier_id = $consumable->getSupplierId();
            $quantity = 0;
        } else {
            $name = $consumables_stock[$consumable_id]['name'];
            $supplier_id = $consumables_stock[$consumable_id]['supplier_id'];
            $quantity = $consumables_stock[$consumable_id]['warehouse_id'][$warehouse['id']] ?? 0;
        }
        $messages = self::getMessage(self::CONSUMABLE_COUNT, [
            'id' => $consumable_id,
            'name' => $name,
            'supplier_id' => $supplier_id,
            'quantity' => $quantity,
            'wname' => $warehouse['name'],
        ]);
        if (empty($stock[$consumable_id]))
            $stock[$consumable_id] = $messages;
        else
            $stock[$consumable_id]['stock'][] = $messages['stock'][0];
    }

    private static function addMessageMinimum($SKU_id, $SKUs_stock, $warehouse, $quantity, $min_stock_level, &$minimum)
    {
        if (empty($SKUs_stock[$SKU_id])) {
            $SKU = SKUs::getById($SKU_id);
            $name = $SKU->getName();
            $barcode = SKUBarcodes::getBarcodesBySKUIdString($SKU->getId());
        } else {
            $name = $SKUs_stock[$SKU_id]['name'];
            $barcode = $SKUs_stock[$SKU_id]['barcode'];
        }
        $messages = self::getMessage(self::SKU_MINIMUM_AMOUNT, [
            'id' => $SKU_id,
            'name' => $name,
            'barcode' => $barcode,
            'wname' => $warehouse,
            'quantity' => $quantity,
            'min_stock_level' => $min_stock_level,
        ]);
        if (!empty($messages))
            $minimum[] = $messages;
    }

    private static function addMessageConsumableMinimum($consumable_id, $consumables_stock, $warehouse, $quantity, $min_stock_level, &$minimum)
    {
        if (empty($consumables_stock[$consumable_id])) {
            $consumable = Consumables::getById($consumable_id);
            $name = $consumable->getName();
            $supplier_id = $consumable->getSupplierId();
        } else {
            $name = $consumables_stock[$consumable_id]['name'];
            $supplier_id = $consumables_stock[$consumable_id]['supplier_id'];
        }
        $messages = self::getMessage(self::MIN_CONSUMABLE_COUNT, [
            'id' => $consumable_id,
            'name' => $name,
            'supplier_id' => $supplier_id,
            'quantity' => $quantity,
            'min_stock_level' => $min_stock_level,
            'wname' => $warehouse['name'],
        ]);
        if (!empty($messages))
            $minimum[] = $messages;
    }

    private static function createMailMinimum($email, $minimum)
    {
        $subject = "SKU minimum amount (" . date('Y-m-d') . ")";
        $trs = [];
        foreach ($minimum as $line)
            $trs[] = "<tr>
                          <td><a href='" . HTTP::getDomain() .  "{$line['url']}'>{$line['title']}</a></td>
                          <td>{$line['quantity']}</td>
                          <td>{$line['min_stock_level']}</td>
                          <td>{$line['warehouse']}</td>
                      </tr>";
        $message = "
            <html>
                <head>
                    <title>$subject</title>
                </head>
                <body>
                    <table border='1'>
                        <tr>
                            <td><b>SKU</b></td>
                            <td><b>Stock</b></td>
                            <td><b>Minimum</b></td>
                            <td><b>Warehouse</b></td>
                        </tr>
                        " . implode($trs) . "
                    </table>
                </body>
            </html>";
        mail($email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
        SupplierNotificationHistories::sendNotification(self::SKU_MINIMUM_AMOUNT, ['email' => $email, 'minimum' => $minimum]);
    }

    private static function createMailOutOfStock($email, $stock)
    {
        $subject = "SKU out of stock (" . date('Y-m-d') . ")";
        $trs = [];
        foreach ($stock as $line)
            $trs[] = "<tr>
                          <td><a href='" . HTTP::getDomain() .  "{$line['url']}'>{$line['title']}</a></td>
                          <td>{$line['warehouse']}</td>
                      </tr>";
        $message = "
            <html>
                <head>
                    <title>$subject</title>
                </head>
                <body>
                    <table border='1'>
                        <tr>
                            <td><b>SKU</b></td>
                            <td><b>Warehouse</b></td>
                        </tr>
                        " . implode($trs) . "
                    </table>
                </body>
            </html>";
        mail($email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
        SupplierNotificationHistories::sendNotification(self::SKU_OUT_OF_STOCK, ['email' => $email, 'stock' => $stock]);
    }

    private static function createMailConsumables($email, $stock)
    {
        $subject = "Consumables stock (" . date('Y-m-d') . ")";
        $trs = [];
        foreach ($stock as $line) {
            $tr_start = "<tr><td rowspan='" . count($line['stock']) ."'><a href='" . HTTP::getDomain() .  "{$line['url']}'>{$line['title']}</a></td>";
            $tr_lines = [];
            foreach ($line['stock'] as $warehouse)
                $tr_lines[] = "<td>{$warehouse['warehouse']}</td><td>{$warehouse['quantity']}</td>";
            $trs[] = $tr_start . implode('</tr><tr>', $tr_lines) . '</tr>';
        }
        $message = "
            <html>
                <head>
                    <title>$subject</title>
                </head>
                <body>
                    <table border='1'>
                        <tr>
                            <td><b>Consumable</b></td>
                            <td><b>Warehouse</b></td>
                            <td><b>Quantity</b></td>
                        </tr>
                        " . implode($trs) . "
                    </table>
                </body>
            </html>";
        mail($email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
        SupplierNotificationHistories::sendNotification(self::CONSUMABLE_COUNT, ['email' => $email, 'consumables' => $stock]);
    }

    private static function createMailOrders($email, $suppliers)
    {
        $subject = "Orders (" . date('Y-m-d H:i') . ")";
        $message = "
            <html>
                <head>
                    <title>$subject</title>
                </head>
                <body>" .
                    implode($suppliers) . "
                </body>
            </html>";
        mail($email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
        SupplierNotificationHistories::sendNotification(self::ORDERS_NOTIFICATION, ['email' => $email, 'orders' => $suppliers]);
    }

    private static function createMailExpired($email, $suppliers)
    {
        $subject = "Expired batches (" . date('Y-m-d H:i') . ")";
        $message = "
            <html>
                <head>
                    <title>$subject</title>
                </head>
                <body>
                    <h3>Please kindly contact the operational team via email instructing how to process the below expired products.
                    <br/>
                    \"Destroy\" or \"Send to an Address of Choice\"</h3>" .
                    implode($suppliers) . "
                </body>
            </html>";
        mail($email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
        SupplierNotificationHistories::sendNotification(self::SKU_EXPIRED, ['email' => $email, 'expired' => $suppliers]);
    }

    private static function createMailConsumableMinimum($email, $minimum)
    {
        $subject = "Consumable minimum amount (" . date('Y-m-d') . ")";
        $trs = [];
        foreach ($minimum as $line) {
            $tr_start = "<tr><td rowspan='" . count($line['stock']) ."'><a href='" . HTTP::getDomain() .  "{$line['url']}'>{$line['title']}</a></td>";
            $tr_lines = [];
            foreach ($line['stock'] as $warehouse)
                $tr_lines[] = "<td>{$warehouse['warehouse']}</td><td>{$warehouse['quantity']}</td><td>{$warehouse['min_stock_level']}</td>";
            $trs[] = $tr_start . implode('</tr><tr>', $tr_lines) . '</tr>';
        }
        $message = "
            <html>
                <head>
                    <title>$subject</title>
                </head>
                <body>
                    <table border='1'>
                        <tr>
                            <td><b>Consumable</b></td>
                            <td><b>Warehouse</b></td>
                            <td><b>Quantity</b></td>
                            <td><b>Minimum</b></td>
                        </tr>
                        " . implode($trs) . "
                    </table>
                </body>
            </html>";
        mail($email, $subject, $message, "MIME-Version: 1.0\r\nContent-type: text/html; charset=utf-8\r\nFrom: notifier@" . API_DOMAIN . "\r\n");
        SupplierNotificationHistories::sendNotification(self::MIN_CONSUMABLE_COUNT, ['email' => $email, 'minimum' => $minimum]);
    }
}
