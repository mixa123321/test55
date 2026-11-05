<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Order;
use app\core\entities\OrderDoc;
use app\core\handlers\FileLoader;
use app\core\helpers\File;

class OrderDocs extends \app\core\models\OrderDocs
{
    const STEP = 10;

    public static function colorStatus($status): string
    {
        $doc_color = 'info';
        switch ($status) {
            case OrderDoc::STATUS_NEW:
                $doc_color = 'primary';
                break;
            case OrderDoc::STATUS_FAIL:
                $doc_color = 'danger';
                break;
            case OrderDoc::STATUS_LOADED:
                $doc_color = 'success';
                break;
        }
        return $doc_color;
    }

    public static function uploadDocs()
    {
        $limit = self::STEP;
        $status = OrderDoc::STATUS_NEW;
        $order_docs = (new SafeMysql())->fetchAll("SELECT * FROM order_docs 
                                                        WHERE document_url IS NOT NULL 
                                                          AND LENGTH(document_url) > 0
                                                          AND status = :status LIMIT :limit",
                                                    ['status' => $status, 'limit' => $limit]);
        if (empty($order_docs))
            return;
        foreach ($order_docs as $order_doc) {
            $error = '';
            $orderDoc = new OrderDoc($order_doc);
            if (!self::hasAwaitingOrders($orderDoc->getId()))
                continue;

            self::setLoading($orderDoc);
            $document_url = $orderDoc->getDocumentUrl();
            $file_response = FileLoader::collectFile($document_url);
            $loaded = false;
            if (!$file_response->getResult()) {
                $error = $file_response->getMessage();
            } else {
                $doc_name = !empty($file_response->getName()) ? $file_response->getName() : time() . '_' . uniqid($orderDoc->getId() . '_') . '.pdf';
                $doc_path_backup = ORDERS_STORAGE . time() . $doc_name;
                File::write($file_response->getContent(), $doc_path_backup);
                $similarDoc = self::getSimilar($doc_name, hash_file('md5', $doc_path_backup));
                unlink($doc_path_backup);
                if (!empty($similarDoc)) {
                    $order_ids = self::getOrderIds($orderDoc);
                    foreach ($order_ids as $order_id) {
                        self::mapOrderDoc($order_id, $similarDoc->getId());
                    }
                    self::remove($orderDoc);
                    $orderDoc = $similarDoc;
                    if ($similarDoc->getStatus() === OrderDoc::STATUS_LOADED)
                        $loaded = true;
                } else {
                    if (!empty(self::getByName($doc_name)))
                        $doc_name = time() . '_' . $doc_name;
                    $doc_path = ORDERS_DOC_STORAGE . $doc_name;
                    $loaded = File::write($file_response->getContent(), $doc_path);
                    if ($loaded)
                        self::setLoaded($orderDoc, $doc_name, $doc_path);
                    else
                        $error = 'Upload failed';
                }
            }
            if (!$loaded)
                self::setFail($orderDoc, $error);

            self::resolveOrdersByDoc($orderDoc->getId(), $document_url, $loaded, $error);
        }
    }

    public static function resolveOrdersByDoc($order_doc_id, $document_url, bool $loaded, string $error)
    {
        $order_doc_id = (int)$order_doc_id;
        $orders = (new SafeMysql())->fetchAll("SELECT o.* FROM order_docs_map odm 
                                                JOIN orders o ON odm.order_id = o.id
                                                    WHERE odm.doc_id = :order_doc_id",
                                                ['order_doc_id' => $order_doc_id]);
        if (!empty($orders))
            foreach ($orders as $order) {
                $orderObj = new Order($order);
                if ($orderObj->getStatusName() === Order::AWAITING_UPLOAD_DOCUMENTS)
                    if ($loaded) {
                        OrderHistories::successUploadDocs($orderObj->getId(), $document_url);
                        Orders::changeStatus($orderObj->getId(), Order::NEW, 0, 'Set NEW status after uploaded file');
                        $qties = OrderSKUs::getSKUsQuantitiesByOrder($orderObj->getId());
                        Orders::creationOrder($orderObj, $qties, -1);
                    } else
                        OrderHistories::failUploadDocs($orderObj->getId(), $document_url, $error);
            }
    }

    public static function hasAwaitingOrders($doc_id): bool
    {
        return !!(new SafeMysql())->fetchScalar('SELECT 1 FROM orders o JOIN order_docs_map odm ON o.id = odm.order_id AND odm.doc_id = :doc_id
            WHERE o.status = :status LIMIT 1', ['doc_id' => (int)$doc_id, 'status' => Order::AWAITING_UPLOAD_DOCUMENTS_NUM]);
    }

    public static function getIdsByOrders($order_ids): array
    {
        if (empty($order_ids) || !is_array($order_ids))
            return [];
        return (new SafeMysql())->fetchArrayOneField("
            SELECT odm.order_id
            FROM order_docs_map odm
                JOIN order_docs od ON odm.doc_id = od.id
            WHERE odm.order_id IN (:order_ids)", ['order_ids' => $order_ids]);
    }
}
