<?php

namespace app\backend\models;

use app\core\entities\Location;
use app\core\helpers\Validator;
use app\core\helpers\View;
use app\core\entities\Movement_;

class Movements_ extends \app\core\models\Movements_
{

    const TYPE_PUTAWAY = 'Putaway';
    const TYPE_MOVEMENT = 'Movement';
    const TYPE_NON_COMPLIANT = 'Non-Compliant';
    const TYPE_NO_VERIFY = 'NoVerify';
    const TYPE_PICKING = 'Picking';
    const TYPE_DROPPING = 'Dropping';
    const TYPE_SHORT_SHOP = 'ShortShip';
    const TYPE_PACKING = 'Packing';
    const TYPE_CHANGE_ORDER = 'ChangeOrd';
    const TYPE_ASN = 'ASN';

    /**
     * @param $temp Movement_
     * @return array
     */
    public static function validate($temp)
    {
        $rules = [
            'location_id' => [
                'val' => $temp->getLocationId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [0, PHP_INT_MAX]]
            ],
            'SKU_id' => [
                'val' => $temp->getSKUId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'quantity' => [
                'val' => $temp->getQuantity(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'order_id' => [
                'val' => $temp->getOrderId(),
                'rules' => [Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'type' => [
                'val' => $temp->getType(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 25]]
            ],
            'reason' => [
                'val' => $temp->getReason(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 255]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        return $validator->getErrors();
    }

    public static function setPutaway($loc, $sku, $quan, $batch_id, $user)
    {
        $data = [
            'location_id' => (int)$loc,
            'SKU_id' => (int)$sku,
            'quantity' => (int)$quan,
            'type' => self::TYPE_PUTAWAY,
            'reason' => "User #$user; Batch #$batch_id",
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function movement($loc, $loc_to, $SKU_id, $batch_id, $quan, $user)
    {
        $data = [
            'location_id' => $loc,
            'SKU_id' => $SKU_id,
            'quantity' => $quan,
            'type' => self::TYPE_MOVEMENT,
            'reason' => "User #$user; To #$loc_to" . (empty($batch_id) || $batch_id == -1 ? '' : "; Batch #$batch_id"),
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function setPartially($loc, $sku, $quan, $user)
    {
        $data = [
            'location_id' => (int)$loc,
            'SKU_id' => (int)$sku,
            'quantity' => (int)$quan,
            'type' => self::TYPE_NON_COMPLIANT,
            'reason' => "User #$user",
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function setNoVerify($loc, $sku, $quan, $user)
    {
        $data = [
            'location_id' => (int)$loc,
            'SKU_id' => (int)$sku,
            'quantity' => (int)$quan,
            'type' => self::TYPE_NO_VERIFY,
            'reason' => "User #$user",
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function setPicking($loc, $ord, $SKU_id, $batch_id, $quan, $user, $mode)
    {
        $data = [
            'location_id' => (int) $loc,
            'SKU_id' => (int) $SKU_id,
            'quantity' => (int) $quan,
            'order_id' => (int) $ord,
            'type' => self::TYPE_PICKING . strtoupper(substr($mode,0 ,1)),
            'reason' => "User #$user" . (empty($batch_id) ? '' : "; Batch #$batch_id"),
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function setDropping($loc, $ord, $SKU_id, $batch_id, $quan, $user)
    {
        $data = [
            'location_id' => (int)$loc,
            'SKU_id' => (int) $SKU_id,
            'quantity' => (int)$quan,
            'order_id' => (int)$ord,
            'type' => self::TYPE_DROPPING,
            'reason' => "User #$user" . (empty($batch_id) ? '' : "; Batch #$batch_id"),
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function setShortShip($order1_id, $order2_id, $SKU_id, $quantity, $user_id)
    {
        $data = [
            'location_id' => 0,
            'SKU_id' => $SKU_id,
            'quantity' => $quantity,
            'order_id' => $order1_id,
            'type' => self::TYPE_SHORT_SHOP,
            'reason' => "User #$user_id; To order #$order2_id",
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function setPacking($loc, $ord, $sku, $quan, $user)
    {
        $data = [
            'location_id' => (int)$loc,
            'SKU_id' => (int)$sku,
            'quantity' => (int)$quan,
            'order_id' => (int)$ord,
            'type' => self::TYPE_PACKING,
            'reason' => "User #$user",
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function changeOrder($loc, $ord, $sku, $quan, $user)
    {
        $data = [
            'location_id' => (int)$loc,
            'SKU_id' => (int)$sku,
            'quantity' => (int)$quan,
            'order_id' => (int)$ord,
            'type' => self::TYPE_CHANGE_ORDER,
            'reason' => "User #$user",
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function setDespatched($ord, $sku, $quan, $ship, $user)
    {
        $data = [
            'location_id' => 0,
            'SKU_id' => (int)$sku,
            'quantity' => -(int)$quan,
            'order_id' => (int)$ord,
            'type' => self::TYPE_DESPATCHED,
            'reason' => "User #$user; Shipping #$ship",
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function setASN($sku, $quan, $asn)
    {
        $data = [
            'location_id' => 0,
            'SKU_id' => (int)$sku,
            'quantity' => (int)$quan,
            'type' => self::TYPE_ASN,
            'reason' => "Arrived #$asn",
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function delASN($sku, $quan, $asn)
    {
        $data = [
            'location_id' => 0,
            'SKU_id' => (int)$sku,
            'quantity' => -(int)$quan,
            'type' => self::TYPE_ASN,
            'reason' => "Reverted #$asn",
        ];
        $keys = array_keys($data);
        $temp = new Movement_($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function availableLocations($locs, $src)
    {
        if (!count($locs))
            return '';
        $res = $add = '';
        if ($src === 'destination')
            $add = 'to';
        if (!empty($locs[Location::PF])) {
            $res .= "<div id='locPF$add' class='col-md-5 border border-success rounded m-1'><span class='text-success'>Pick Face</span><br/>";
            foreach ($locs[Location::PF] as $key => $loc)
                $res .= View::toHtml($key) . " (" . (int)$loc[1] . ")<br/>";
            $res .= '</div>';
        }
        if (!empty($locs[Location::ST])) {
            $res .= "<div id='locST$add' class='col-md-5 border border-success rounded m-1'><span class='text-success'>Storage</span><br/>";
            foreach ($locs[Location::ST] as $key => $loc)
                $res .= View::toHtml($key) . " (" . (int)$loc[1] . ")<br/>";
            $res .= '</div>';
        }
        if (!empty($locs[Location::PA])) {
            $res .= "<div id='locPA$add' class='col-md-5 border border-success rounded m-1'><span class='text-success'>Putaway</span><br/>";
            foreach ($locs[Location::PA] as $key => $loc)
                $res .= View::toHtml($key) . " (" . (int)$loc[1] . ")<br/>";
            $res .= '</div>';
        }
        if ($res == '')
            return '';
        $res = "<div id='available_{$src}_locs' class='row mt-1 px-md-3 px-2 d-none'><div class='col text-center'><span class='text-success'><b>Available $src locations</b></span><div class='row justify-content-center'>" .
               $res . '</div></div></div>';
        return $res;
    }
}
