<?php

namespace app\backend\models;

use app\core\entities\ASN;
use app\core\helpers\Validator;
use app\core\entities\ASNHistory;

class ASNHistories extends \app\core\models\ASNHistories
{
    public static function validate(ASNHistory $ASN_history)
    {
        $rules = [
            'comment' => [
                'val' => $ASN_history->getComment(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 1000]]
            ],
            'status' => [
                'val' => $ASN_history->getStatus(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, 2]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        return $validator->getErrors();
    }

    private static function changeASN($data)
    {
        $ASNHistory = new ASNHistory($data);
        $errors = self::validate($ASNHistory);
        if (!empty($errors))
            return $errors;
        $ASNHistory->save(array_keys($data));
        return true;
    }

    public static function createASN($ASN_id, $creater, $user_id, $supplier_id)
    {
        $create_date = time();
        $comment = 'ASN created';
        if ($supplier_id)
            $comment .= ' for ' . Suppliers::getById($supplier_id)->getName();
        elseif ($user_id > 0)
            $comment .= ' for ' . Persons::getByUserId($user_id)->getName() . '#' . $user_id;
        $errors = self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => $create_date,
            'user_id' => $creater,
            'comment' => $comment,
            'status' => ASNHistory::ACTIVE,
        ]);
        return $errors === true ? $create_date : $errors;
    }

    public static function commentASN($ASN_id, $user_id, $comment)
    {
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => htmlspecialchars((string)$comment, ENT_QUOTES),
            'status' => ASNHistory::USERS,
        ]);
    }

    public static function cancelASN($ASN_id, $user_id)
    {
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => 'ASN cancelled',
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function cancelRASN($RASN_id, $user_id)
    {
        return self::changeASN([
            'ASN_id' => $RASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => 'Return ASN cancelled',
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function arrivedASN(ASN $ASN, $user_id)
    {
        $now = time();
        $errors = self::changeASN([
            'ASN_id' => $ASN->getId(),
            'create_date' => $now,
            'user_id' => $user_id,
            'comment' => 'Status: Arrived<br/>Marked as arrived',
            'status' => ASNHistory::ACTIVE,
        ]);
        if ($errors === true)
            SupplierNotifications::addNotification($ASN->getSupplierId(), SupplierNotifications::ASN_ARRIVAL, [
                'id' => $ASN->getId(),
                'ref' => $ASN->getRef(),
                'arrived' => $now,
            ]);
        return $errors;
    }

    public static function putsASN($SKU_id, $ASN_id, $quan, $pallet, $user_id)
    {
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => "Moved SKU #$SKU_id ($quan) from ASN #$ASN_id (Pallet $pallet) to putaway area",
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function noVerifyASN($ASN_id, $user_id, $SKU_id, $quan, $loc)
    {
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => "Moved SKU #$SKU_id ($quan) from ASN #$ASN_id to location $loc (without verify)",
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function putawayASN($ASN_id, $user_id)
    {
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => 'Status: Putaway<br/>Marked as putaway',
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function returnStatusASN($ASN_id, $status, $user_id)
    {
        $status_name = ASN::getStateNameReturn($status);
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => "Status: $status_name",
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function returnActionedASN($ASN_id, $actioned, $user_id)
    {
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => 'CS Actioned (' . ($actioned ? 'ON' : 'OFF') . ')',
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function completedASN(ASN $ASN, $type, $user_id)
    {
        $completed = time();
        $errors = self::changeASN([
            'ASN_id' => $ASN->getId(),
            'create_date' => $completed,
            'user_id' => $user_id,
            'comment' => 'Status: Completed<br/>Marked as completed',
            'status' => ASNHistory::ACTIVE,
        ]);
        if ($errors === true)
            SupplierNotifications::addNotification($ASN->getSupplierId(), SupplierNotifications::ASN_COMPLETION, [
                'id' => $ASN->getId(),
                'ref' => $ASN->getRef(),
                'status' => $type,
                'completed' => $completed,
            ]);
        return $errors === true ? $completed : $errors;
    }

    public static function notCompletedRASN(ASN $ASN, $user_id)
    {
        $completed = time();
        $errors = self::changeASN([
            'ASN_id' => $ASN->getId(),
            'create_date' => $completed,
            'user_id' => $user_id,
            'comment' => 'Return ASN is non-compliant',
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function revertASN(ASN $ASN, $user_id)
    {
        $now = time();
        $errors = self::changeASN([
            'ASN_id' => $ASN->getId(),
            'create_date' => $now,
            'user_id' => $user_id,
            'comment' => 'Status: New<br/>Revert arrived',
            'status' => ASNHistory::ACTIVE,
        ]);
        if ($errors === true)
            SupplierNotifications::addNotification($ASN->getSupplierId(), SupplierNotifications::ASN_ARRIVAL, [
                'id' => $ASN->getId(),
                'revert' => 1,
                'arrived' => $now,
            ]);
        return $errors === true ? $now : $errors;
    }

    public static function updateASN($ASN_id, $comment, $user_id)
    {
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => $comment,
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function toCompleteASN($ASN_id, $user_id, $SKU_id, $quantity, $accepted)
    {
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => sprintf("SKU #%d movement. Expected %d => Received %d", $SKU_id, $quantity, $accepted) . (!$quantity ? ' (unexpected item)' : ''),
            'status' => ASNHistory::ACTIVE,
        ]);
    }

    public static function deviationASN($ASN_id, $user_id, $SKU_id, $quantity, $loc)
    {
        return self::changeASN([
            'ASN_id' => $ASN_id,
            'create_date' => time(),
            'user_id' => $user_id,
            'comment' => sprintf("Non-Compliant SKU #%d (quantity: %d) accepted to location %s", $SKU_id, $quantity, $loc),
            'status' => ASNHistory::ACTIVE,
        ]);
    }
}
