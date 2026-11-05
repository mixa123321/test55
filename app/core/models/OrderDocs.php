<?php

namespace app\core\models;

use app\backend\models\Orders;
use app\backend\models\OrderSKUs;
use app\backend\models\OrderHistories;
use app\core\db\Mysql;
use app\core\dbal\SafeMysql;
use app\core\entities\File;
use app\core\entities\Order;
use app\core\entities\OrderDoc;
use app\core\ErrorHandler;
use app\core\helpers\File as FileHelper;
use app\core\helpers\FileWrapper;

class OrderDocs
{

    public static function mapOrderDoc($order_id, $orderDoc_id)
    {
        $order_id = (int)$order_id;
        $orderDoc_id = (int)$orderDoc_id;
        $exist = (new SafeMysql())->checkUniqueFields('order_docs_map', [
            'order_id' => $order_id,
            'doc_id' => $orderDoc_id,
        ]);
        if (!$exist)
            Mysql::saveRow('order_docs_map', [
                'order_id' => $order_id,
                'doc_id' => $orderDoc_id
            ]);
    }

    public static function getById($id): ?OrderDoc
    {
        $doc = (new SafeMysql())->fetchOne('SELECT * FROM order_docs WHERE id = :id', ['id' => (int)$id]);
        return !empty($doc) ? new OrderDoc($doc) : null;
    }

    public static function getDocByDocumentUrl($document_url): ?OrderDoc
    {
        $doc = (new SafeMysql())->fetchOne("SELECT * FROM order_docs
                                                    WHERE document_url = :document_url
                                                        ORDER BY create_date DESC LIMIT 1",
                                                            ['document_url' => $document_url]);
        return !empty($doc) ? new OrderDoc($doc) : null;
    }

    public static function getOrderIds(OrderDoc $orderDoc): array
    {
        return (new SafeMysql())->fetchArrayOneField('SELECT order_id FROM order_docs_map WHERE doc_id = :doc_id', ['doc_id' => (int)$orderDoc->getId()]);
    }

    public static function updateDocumentUrl(Order $order, $document_url, $user_id = -1)
    {
        self::clear($order);
        OrderHistories::updateDoc($order->getId(), $user_id, $document_url);
        self::setDocumentUrl($order, $document_url, $user_id);
    }

    public static function setDocumentUrl(Order $order, $document_url, $user_id = -1): Order
    {
        if (empty($document_url))
            return $order;

        $orderDoc = self::getDocByDocumentUrl($document_url);
        if (empty($orderDoc)) {
            $data = [
                'document_url' => $document_url,
                'user_id' => $user_id,
                'status' => OrderDoc::STATUS_NEW
            ];
            $orderDoc = new OrderDoc($data);
            $orderDoc->save(array_keys($data));
        }
        self::mapOrderDoc($order->getId(), $orderDoc->getId());
        if ($orderDoc->getStatus() !== OrderDoc::STATUS_LOADED && $order->getStatus() > Order::CANCELLED_NUM)
            Orders::changeStatus($order->getId(), Order::AWAITING_UPLOAD_DOCUMENTS, $user_id);
        elseif ($orderDoc->getStatus() === OrderDoc::STATUS_LOADED && $order->awaitingUploadDocuments())
            self::updateOrder($order, 'Set NEW status after update with existing file');

        return $order;
    }

    public static function updateFile(Order $order, $file, $user_id = -1): array
    {
        $orderDoc = self::getByOrderId($order->getId());
        $res = self::setFilePDF($order, new File($file), $user_id);
        if (isset($res[0]) && $res[0]) {
            OrderHistories::updateDoc($order->getId(), $user_id);
            if (!empty($orderDoc))
                self::clear($order, $orderDoc);
            if ($order->awaitingUploadDocuments())
                self::updateOrder($order, 'Set NEW status after update file');
        }

        return $res;
    }

    public static function setFilePDF(Order $order, File $file, $user_id = -1): array
    {
        switch ($file->getError()) {
            case 0:
                if ($file->getType() !== 'application/pdf')
                    return [false, 'Must be in PDF format'];
                break;
            case 4:
                return [true, ''];
            default:
                return [false, ErrorHandler::errorFileUpload($file->getError())];
        }
        if (empty($file->getName()))
            return [false, 'File error'];
        // getType() is what the browser claims (or is hard-coded for SFTP files): check the real content too
        if ($file->getReceivingType() === File::RECEIVING_TYPE_UPLOAD)
            $is_pdf = FileHelper::getMimeType($file->getTmpName()) === 'application/pdf';
        elseif ($file->getReceivingType() === File::RECEIVING_TYPE_FILE_SYSTEM)
            $is_pdf = (new \finfo(FILEINFO_MIME_TYPE))->buffer((string)stream_get_contents(
                (new FileWrapper(FileWrapper::getContainerByPath($file->getTmpName())))->getStream($file->getTmpName())
            )) === 'application/pdf';
        else
            $is_pdf = false;
        if (!$is_pdf)
            return [false, 'Must be in PDF format'];
        $docName = FileHelper::safeFileName($file->getName(), 'document', 'pdf');
        $size = $file->getSize();
        if ($size > FileHelper::MAX_FILE_PDF_SIZE || is_null($size))
            return [false, 'File size is ' . FileHelper::formatMB((float)$size)  . ' MB. Maximum file size ' . FileHelper::formatMB(FileHelper::MAX_FILE_PDF_SIZE) . ' MB'];

        if ($file->getReceivingType() === File::RECEIVING_TYPE_UPLOAD) {
            $similarDoc = OrderDocs::getSimilar($docName, md5(stream_get_contents(fopen($file->getTmpName(), 'r'))));
        } elseif ($file->getReceivingType() === File::RECEIVING_TYPE_FILE_SYSTEM) {
            $file_wrapper = new FileWrapper(FileWrapper::getContainerByPath($file->getTmpName()));
            $similarDoc = OrderDocs::getSimilar($docName, md5(stream_get_contents($file_wrapper->getStream($file->getTmpName()))));
        } else
            return [false, 'File type not supported ' . $file->getReceivingType()];
        if (!empty($similarDoc)) {
            self::mapOrderDoc($order->getId(), $similarDoc->getId());
            return [true, 'The file is valid and has been successfully uploaded'];
        }
        if (!empty(OrderDocs::getByName($docName)))
            $docName = time() . '_' . random_int(1, 99999) . '_' . $docName;
        $doc_path = ORDERS_DOC_STORAGE . $docName;
        $copy_result = false;
        if ($file->getReceivingType() === File::RECEIVING_TYPE_UPLOAD)
            $copy_result = FileHelper::moveUploadedFile($file->getTmpName(), $doc_path);
        elseif ($file->getReceivingType() === File::RECEIVING_TYPE_FILE_SYSTEM)
            $copy_result = FileHelper::copyFile($file->getTmpName(), $doc_path);
        if ($copy_result) {
            $orderDoc_data = [
                'name' => $docName,
                'user_id' => $user_id,
                'status' => OrderDoc::STATUS_LOADED,
                'update_date' => time(),
                'hash' => $doc_path
            ];
            $orderDoc = new OrderDoc($orderDoc_data);
            $orderDoc->save(array_keys($orderDoc_data));
            self::mapOrderDoc($order->getId(), $orderDoc->getId());
            return [true, 'The file is valid and has been successfully uploaded'];
        } else
            return [false, 'Forbidden action'];
    }

    public static function getByOrderId($order_id): ?OrderDoc
    {
        return self::getFirst($order_id);
    }

    public static function getFirst($order_id): ?OrderDoc
    {
        if (!$order_id)
            return null;
        $order_id = (int)$order_id;
        $doc = (new SafeMysql())->fetchOne("SELECT * FROM order_docs od
                                                JOIN order_docs_map odm ON od.id = odm.doc_id AND odm.order_id = :order_id 
                                                    LIMIT 1",
                                                        ['order_id' => $order_id]);
        return !empty($doc) ? new OrderDoc($doc) : null;
    }

    public static function checkAwaitingDocs($order_id): bool
    {
        $order_id = (int)$order_id;
        $statusNew = OrderDoc::STATUS_NEW;
        $statusFail = OrderDoc::STATUS_FAIL;
        return (bool)(new SafeMysql())->fetchScalar("SELECT 1 FROM order_docs od
                                                JOIN order_docs_map odm ON od.id = odm.doc_id
                                                    AND odm.order_id = :order_id 
                                                        WHERE od.status IN (:status_new, :status_fail)", 
                                                            ['order_id' => $order_id, 'status_new' => $statusNew, 'status_fail' => $statusFail]);
    }

    public static function removeDocFromOrder(Order $order, $user_id = -1)
    {
        self::clear($order);
        OrderHistories::removeDoc($order->getId(), $user_id);
        if ($order->awaitingUploadDocuments())
            self::updateOrder($order, 'Set NEW status after remove file');
    }

    public static function clear(Order $order, $orderDoc = null)
    {
        if (empty($orderDoc))
            $orderDoc = self::getByOrderId($order->getId());
        if (!empty($orderDoc)) {
            $mysql = new SafeMysql();
            $mysql->execute("DELETE FROM order_docs_map WHERE order_id = :order_id AND doc_id = :doc_id", [
                'order_id' => $order->getId(),
                'doc_id' => $orderDoc->getId(),
            ]);
            if (!$mysql->fetchScalar("SELECT 1 FROM order_docs_map WHERE doc_id = :doc_id LIMIT 1", ['doc_id' => (int)$orderDoc->getId()]))
                self::removeWithFile($orderDoc);
        }
    }

    public static function remove(OrderDoc $orderDoc)
    {
        $mysql = new SafeMysql();
        $mysql->execute("DELETE FROM order_docs_map WHERE doc_id = :doc_id", ['doc_id' => $orderDoc->getId()]);
        $mysql->execute("DELETE FROM order_docs WHERE id = :id", ['id' => $orderDoc->getId()]);
    }

    public static function removeWithFile(OrderDoc $orderDoc)
    {
        self::remove($orderDoc);
        if ($orderDoc->existFile())
            unlink($orderDoc->getFullPath());
    }

    public static function updateOrder(Order $order, $comment): void
    {
        Orders::changeStatus($order->getId(), Order::NEW, -1, $comment);
        Orders::creationOrder($order, OrderSKUs::getSKUsQuantitiesByOrder($order->getId()), -1);
    }

    public static function getSimilar($name, $hash): ?OrderDoc
    {
        if (empty($name) || empty($hash))
            return null;
        $res = (new SafeMysql())->fetchOne("SELECT * FROM order_docs WHERE hash = :hash
                                            AND (name = :name OR name LIKE :like_name)",
                                                [
                                                    'hash' => $hash,
                                                    'name' => $name,
                                                    'like_name' => '__________\\_' . $name
                                                ]);

        return !empty($res) ? new OrderDoc($res) : null;
    }

    public static function getByName($name): ?OrderDoc
    {
        if (empty($name))
            return null;
        $res = (new SafeMysql())->fetchOne("SELECT * FROM order_docs WHERE name = :name", ['name' => $name]);
        return !empty($res) ? new OrderDoc($res) : null;
    }

    public static function setLoading(OrderDoc $orderDoc)
    {
        $orderDoc->setStatus(OrderDoc::STATUS_LOADING);
        $orderDoc->setUpdateDate(time());
        $orderDoc->save(['id', 'status', 'update_date']);
    }

    public static function setLoaded(OrderDoc $orderDoc, $doc_name, $doc_path, $comment = null)
    {
        $orderDoc->setName($doc_name);
        $orderDoc->setStatus(OrderDoc::STATUS_LOADED);
        $orderDoc->setComment($comment);
        $orderDoc->setHash($doc_path);
        $orderDoc->setUpdateDate(time());
        $orderDoc->save(['id', 'update_date', 'name', 'status', 'comment', 'hash']);
    }

    public static function setFail(OrderDoc $orderDoc, $error)
    {
        $orderDoc->setStatus(OrderDoc::STATUS_FAIL);
        $orderDoc->setComment($error);
        $orderDoc->setUpdateDate(time());
        $orderDoc->save(['id', 'update_date', 'status', 'comment']);
    }


}