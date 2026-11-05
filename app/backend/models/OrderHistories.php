<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\OrderHistory;
use app\core\entities\SKU;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Validator;
use app\core\helpers\View;
use app\core\statical\Timezones;
use app\core\statical\WithDate;

class OrderHistories extends \app\core\models\OrderHistories
{
    public static function validate(OrderHistory $order_history)
    {
        $rules = [
            'comment' => [
                'val' => $order_history->getComment(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 1000]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        return $validator->getErrors();
    }

    public static function getMode($order_id)
    {
        $order_id = (int)$order_id;
        $sql = "SELECT SUBSTR(SUBSTRING_INDEX(SUBSTR(comment, 8), ' picking', 1), 2) mode
                FROM order_history
                WHERE order_id = :order_id AND comment like :comment_pattern
                ORDER BY create_date DESC";
        $modes = (new SafeMysql())->fetchArrayOneField($sql, ['order_id' => $order_id, 'comment_pattern' => 'Status: %picking']);
        if (empty($modes))
            return false;
        return $modes[0];
    }

    private static function changeOrder($data)
    {
        $orderHistory = new OrderHistory($data);
        $orderHistory->save(array_keys($data));
    }

    public static function addCommentOrder($order_id, $user_id, $comment)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => $comment,
        ]);
    }

    public static function createOrder($order_id, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, 'Order created');
    }

    public static function recreateOrder($order_id, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, 'Order recreated');
    }

    public static function actualUser($order_id, $user_login)
    {
        self::addCommentOrder($order_id, -1, "Order was created by $user_login");
    }

    public static function archivedOrder($order_id)
    {
        self::addCommentOrder($order_id, -1, 'Order was archived');
    }

    public static function hashOrder($order_id)
    {
        self::addCommentOrder($order_id, -1, 'Order was hashed');
    }

    public static function cancelOrder($order_id, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, 'Order cancelled');
    }

    public static function reinstateOrder($order_id, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, 'Order reinstate');
    }

    public static function shortShipOrder($order1_id, $order2_id, $user_id, $reason)
    {
        self::addCommentOrder($order1_id, $user_id, "Order short ship ($reason $order2_id)");
    }

    public static function shortShipSKU($order_id, $SKU_id, $qty0, $qty1, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, "Change quantity ($qty0 => $qty1) of SKU #$SKU_id (short ship)");
    }

    public static function lockedSKUs($order_id, $SKU_id, $barcode, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, "SKU $barcode (#$SKU_id) is locked");
    }

    public static function substitutions($order_id, $SKU_id, $substs, $user_id)
    {
        $msg = array_map(function ($item) { return "SKU #{$item['id']} - {$item['quantity']} unit" . ($item['quantity'] > 1 ? 's' : ''); }, $substs);
        self::addCommentOrder($order_id, $user_id, "Substitution #$SKU_id: " . implode(', ', $msg));
    }

    public static function lockedAllSKU($order_id, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, 'All SKUs is locked or absent');
    }

    public static function addShortShipSKU($order1_id, $order2_id, $SKU_id, $qty, $user_id)
    {
        self::addCommentOrder($order1_id, $user_id, "Add SKU #$SKU_id ($qty items) from order #$order2_id (short ship)");
    }

    public static function changePriority($order_id, $user_id, $comment = null)
    {
        self::addCommentOrder($order_id, $user_id, empty($comment) ? "Set priority" : $comment);
    }

    public static function changeStatusOrder($order_id, $status, $user_id, $comment = null, $time = null)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => is_null($time) ? time() : $time,
            'user_id' => $user_id,
            'comment' => empty($comment) ? "Status: $status" : $comment,
        ]);
    }

    public static function undespatching($order_id, $user_id)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => 'Order is undespatched'
        ]);
    }

    public static function pickedSKU($order_id, $barcode, $batches, $quantity, $location_id, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, "SKU $barcode " . (empty($batches) ? '' : "($batches) ") . "$quantity item(s) picked from $location_id");
    }

    public static function unpickedSKU($order_id, $barcode, $batches, $quantity, $location_id, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, "SKU $barcode " . (empty($batches) ? '' : "($batches) ") . "$quantity item(s) unpicked from $location_id");
    }

    public static function droppedSKU($order_id, $barcode, $batches, $quantity, $location_id, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, "SKU $barcode " . (empty($batches) ? '' : "($batches) ") . "$quantity item(s) dropped to $location_id");
    }

    public static function forAveaSKU($order_id, $SKUs, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, 'Order contains quantity of 12 or more (' . implode(', ', $SKUs) . ')');
    }

    public static function forAveaBundle($order_id, $prices, $user_id)
    {
        self::addCommentOrder($order_id, $user_id, "Order contains bundle and prices more than 150 ($prices)");
    }

    public static function getByOrderId($order_id, $asc = false)
    {
        $sql = "SELECT * FROM order_history
                WHERE order_id = :order_id
                ORDER BY id" . (!$asc ? ' DESC' : '');
        return (new SafeMysql())->fetchAll($sql, ['order_id' => (int)$order_id]);
    }

    private static function isMeasureSKU($order_id, $SKU_id)
    {
        $order_id = (int) $order_id;
        $SKU_id = (int) $SKU_id;
        return (new SafeMysql())->fetchScalar("SELECT 1
                                  FROM order_history
                                  WHERE order_id = :order_id AND comment LIKE :comment_pattern",
            ['order_id' => $order_id, 'comment_pattern' => "%SKU #$SKU_id: dimensions setup required%"]);
    }

    private static function isUnassignedSKU($order_id, $SKU_id)
    {
        $order_id = (int) $order_id;
        $SKU_id = (int) $SKU_id;
        return (new SafeMysql())->fetchScalar("SELECT 1
                                  FROM order_history
                                  WHERE order_id = :order_id AND comment LIKE :comment_pattern",
            ['order_id' => $order_id, 'comment_pattern' => "%SKU #$SKU_id: location assignment required%"]);
    }

    public static function backOrderMeasure($order_id, $SKU_id, $user_id)
    {
        if (self::isMeasureSKU($order_id, $SKU_id))
            return;
        self::addCommentOrder($order_id, $user_id, "SKU #$SKU_id: dimensions setup required");
    }

    public static function backOrderUnassigned($order_id, $SKU_id, $user_id)
    {
        if (self::isUnassignedSKU($order_id, $SKU_id))
            return;
        self::addCommentOrder($order_id, $user_id, "SKU #$SKU_id: location assignment required");
    }

    public static function reserveSKU($order_id, SKU $SKU, $batch, $quantity, $location_name)
    {
        self::addCommentOrder($order_id, 0, "SKU " . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId())  . (empty($batch) ? '' : " ({$batch->getInfo()})") . ": reserved $quantity items in $location_name");
    }

    public static function cancelReservedSKUForLarge($order_id, SKU $SKU, $batch, $quantity, $location_name, $pre = false)
    {
        self::addCommentOrder($order_id, 0, "SKU " . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . (empty($batch) ? '' : " ({$batch->getInfo()})") . ": canceled " .($pre ? 'pre' : ''). " reserved $quantity items from $location_name");
    }

    public static function replenishSKU($order_id, SKU $SKU, $batch, $quantity, $location_name)
    {
        self::addCommentOrder($order_id, 0, "SKU " . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . (empty($batch) ? '' : " ({$batch->getInfo()})") . ": replenish $quantity items in $location_name");
    }

    public static function prepareSKU($order_id, SKU $SKU, $batch, $quantity, $location_name)
    {
        self::addCommentOrder($order_id, 0, "SKU " . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . (empty($batch) ? '' : " ({$batch->getInfo()})") . ": prepare $quantity items in $location_name");
    }

    public static function fixedSKU($order_id, SKU $SKU, $batch, $quantity, $location_name)
    {
        self::addCommentOrder($order_id, 0, "SKU " . SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()) . (empty($batch) ? '' : " ({$batch->getInfo()})") . ": assigned $quantity items in $location_name");
    }

    public static function saveDelivery($order_id, $user_id, $address_original, $address_changed)
    {
        self::addCommentOrder($order_id, $user_id, "Delivery address changed from:<br> " . $address_original->toString() .
            "<br><br>Delivery address changed to:<br> " . $address_changed->toString());
    }

    /* Values are escaped: history comments are rendered as raw HTML */
    public static function saveTaxNumbers($order_id, $user_id, $eori, $new_eori, $vat, $new_vat, $ioss, $new_ioss, $tax_id, $new_tax_id)
    {
        if ($eori === $new_eori && $vat === $new_vat && $ioss === $new_ioss && $tax_id === $new_tax_id)
            return;
        self::addCommentOrder($order_id, $user_id, "EORI Number changed from: '" . View::toHtml($eori) . "' to '" . View::toHtml($new_eori) . "'" .
            "<br>VAT Number changed from: '" . View::toHtml($vat) . "' to '" . View::toHtml($new_vat) . "'" .
            "<br>IOSS Number changed from: '" . View::toHtml($ioss) . "' to '" . View::toHtml($new_ioss) . "'" .
            "<br>TAX ID changed from: '" . View::toHtml($tax_id) . "' to '" . View::toHtml($new_tax_id) . "'");
    }

    public static function saveRef($order_id, $user_id, $ref, $new_ref, $ref2, $new_ref2, $purchase_order_number, $new_purchase_order_number)
    {
        self::addCommentOrder($order_id, $user_id, "Customer reference changed from: '$ref' to '$new_ref'" ."<br>Customer reference 2 changed from: '$ref2' to '$new_ref2'" ."<br>Purchase Order Number changed from: '$purchase_order_number' to '$new_purchase_order_number'");
    }

    public static function saveShippingService($order_id, $user_id, $service, $new_service)
    {
        self::addCommentOrder($order_id, $user_id, "Shipping service changed from: '{$service}' to '{$new_service}'");
    }

    public static function saveDDP($order_id, $user_id, $DDP, $new_DDP)
    {
        self::addCommentOrder($order_id, $user_id, "Terms changed from: '{$DDP}' to '{$new_DDP}'");
    }

    public static function removeDoc($order_id, $user_id)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => 'File removed',
        ]);
    }

    public static function updateDoc($order_id, $user_id = -1, $document_url = null)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => 'File updated' . (!is_null($document_url) ? (' by url ' . $document_url) : ''),
        ]);
    }

    public static function successUploadDocs($order_id, $document_url)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => 0,
            'comment' => 'File success uploaded from ' . $document_url,
        ]);
    }

    public static function failUploadDocs($order_id, $document_url, $error)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => 0,
            'comment' => 'File fail from ' . $document_url . '. '.  (!empty($error) ? $error : '')
        ]);
    }

    public static function resetUploadDoc($order_id, $document_url, $user_id)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => 'Reset upload file from ' . $document_url
        ]);
    }

    public static function updateCarrierId($order_id, $old_carrier, $new_carrier, $user_id = -1)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => "Carrier changed from '$old_carrier' to '$new_carrier'",
        ]);
    }

    public static function updateShippingServiceId($order_id, $old_shipping_service, $new_shipping_service, $user_id = -1)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => "Shipping service changed from '$old_shipping_service' to '$new_shipping_service'",
        ]);
    }

    public static function removeSKUsByReconciliation(int $order_id, $SKU_id, ?Batch $batch, int $quantity, int $user_id, string $loc_from_name)
    {
        self::changeOrder([
            'order_id' => $order_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => "SKU #$SKU_id " . ($batch ? $batch->getInfo() : '') . ": remove $quantity items by reconciliation $loc_from_name",
        ]);
    }

    public static function getOrderRisk($histories)
    {
        $comment = '';
        foreach ($histories as $history)
            if (strpos($history['comment'], 'Pending approval by Shopify fraud risk assessment.') === 0) {
                $comment = $history['comment'];
                break;
            }
        if (empty($comment))
            return $comment;
        $risk_text = 'Fraud risk';
        $pos = strpos($comment, "$risk_text#");
        $risks = explode("$risk_text#", substr($comment, $pos));
        $risks[0] = $risk_text;
        return implode('<br/>', $risks) . '.';
    }

    public static function getOrderShortship($histories, Warehouse $warehouse)
    {
        $order_data = [];
        foreach ($histories as $history)
            if (strpos($history['comment'], 'Order short ship (') === 0)
                $order_data[] = "{$history['comment']} at " . Timezones::getDiff($warehouse->getTimezone(), $history['create_date'], false, WithDate::DATE_FRONT_FORMAT);
        return $order_data;
    }

    public static function isOrdersRisk(&$orders)
    {
        if (empty($orders))
            return;
        $order_ids = Arr::column($orders, 'order_id');
        $sql = "SELECT oh.order_id
                FROM order_history oh
                    JOIN orders o ON oh.order_id = o.id AND o.status = :status
                    JOIN shops sh ON o.shop_id = sh.id AND sh.is_risk = 1
                WHERE oh.order_id IN (:order_ids) AND oh.comment LIKE :comment_pattern";
        $risks = (new SafeMysql())->fetchArrayOneField($sql,
            [
                'status' => Order::PENDING_APPROVAL_NUM,
                'order_ids' => $order_ids,
                'comment_pattern' => 'Pending approval by Shopify fraud risk assessment.%'
            ]
        );
        foreach ($orders as $key => $order)
            $orders[$key]['risks'] = (int)in_array($order['order_id'], $risks);
    }
}
