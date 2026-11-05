<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Batch;
use app\core\entities\SKU;
use app\core\entities\SKUHistory;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;

class SKUHistories
{
    const EVENT_ASN = 'ASN';
    const EVENT_REVERT = 'Revert';
    const EVENT_BATCHES = 'Batches';
    const EVENT_SERIAL_NUMBER = 'SerialNumber';
    const EVENT_PUTAWAY = 'Putaway';
    const EVENT_MOVEMENT = 'Movement';
    const EVENT_CLEAR_DH = 'ClearDH';
    const EVENT_NON_COMPLIANT = 'Non-Compliant';
    const EVENT_NO_VERIFY = 'NoVerify';
    const EVENT_COUNTING = 'Counting';
    const EVENT_PICKING = 'Picking';
    const EVENT_HANDOVER = 'Handover';
    const EVENT_DROPPING = 'Dropping';
    const EVENT_DROPPING_T = 'DroppingT';
    const EVENT_CANCEL_ORDER = 'CancelOrder';
    const EVENT_REINSTATE_ORDER = 'ReinstateOrder';
    const EVENT_PACKING = 'Packing';
    const EVENT_CHANGE_SKU = 'ChangeSKU';
    const EVENT_UPDATE_MEASURE = 'UpdateMeasure';
    const EVENT_UPDATE_DIMENSIONER = 'UpdateDimensioner';
    const EVENT_UPDATE_LOCATIONS = 'UpdateLocations';
    const EVENT_SET_PARAMS = 'SetParams';
    const EVENT_SET_STATE = 'SetState';
    const EVENT_SET_MAPPING = 'SetMapping';
    const EVENT_UPDATE_MIN_STOCK_LEVEL = 'UpdateMinStockLevel';
    const EVENT_UPDATE_EXPIRED = 'UpdateExpired';
    const EVENT_EXPIRED_SETTINGS = 'ExpiredSettings';
    const EVENT_DESTROY_EXPIRED = 'DestroyExpired';
    const EVENT_EXPIRED_ORDER = 'ExpiredOrder';
    const EVENT_STOCK = 'Stock';
    const EVENT_HARD_REMOVE = 'Remove';
    const EVENT_ADD_CHANNEL_PRODUCT = 'addChannelProduct';
    const EVENT_CHANGE_CHANNEL_EXTERNAL_ID = 'changeExternalId';
    const EVENT_DELETE_CHANNEL_SKU = 'deleteChannelSKU';
    const EVENT_SET_CHANNEL_INVENTORY = 'setChannelInventory';

    const STOCK_EVENTS = [
        self::EVENT_NO_VERIFY,
        self::EVENT_NON_COMPLIANT,
        self::EVENT_PUTAWAY,
        self::EVENT_MOVEMENT,
        self::EVENT_PACKING
    ];

    const REASON_RECONCILIATION = 'Reconciliation';

    public static $user_keys = ['User' => ['User', 'user_id', '[*].User', '[*].user_id']];
    public static $location_keys = ['From', 'To', 'Where', 'location_id', '[*].From', '[*].To', '[*].Where', '[*].location_id', '[*].inserted', '[*].deleted', '[*].updated'];
    protected static $remove_keys = ['Movement ID', '[*].Movement ID'];
    protected static $batch_keys = ['Batch', '[*].Batch'];
    protected static $order_keys = ['Order reference' => ['Order', 'order_id', '[*].Order', '[*].order_id']];
    protected static $ASN_keys = ['ASN ID', 'ASN_id', '[*].ASN ID', '[*].ASN_id'];

    public static function getEventDisplayNames(): array
    {
        return [
            self::EVENT_ASN => 'ASN',
            self::EVENT_PUTAWAY => 'Putaway',
            self::EVENT_MOVEMENT => 'Movement',
            self::EVENT_NON_COMPLIANT => 'Non-Compliant',
            self::EVENT_NO_VERIFY => 'No Verify',
            self::EVENT_COUNTING => 'Counting',
            self::EVENT_PICKING . 'L' => 'Picking Large',
            self::EVENT_PICKING . 'S' => 'Picking Small',
            self::EVENT_PICKING . 'M' => 'Picking Multi',
            self::EVENT_HANDOVER => 'Handover',
            self::EVENT_DROPPING => 'Dropping',
            self::EVENT_DROPPING_T => 'Dropping Table',
            self::EVENT_CANCEL_ORDER => 'Cancel Order',
            self::EVENT_PACKING => 'Packing',
            self::EVENT_EXPIRED_ORDER => 'Expired Order',
            self::EVENT_HARD_REMOVE => 'Remove',
            self::EVENT_CLEAR_DH => 'Clear DH',
            self::EVENT_DESTROY_EXPIRED => 'Destroy Expired',
            self::EVENT_EXPIRED_SETTINGS => 'Expired Settings',
            self::EVENT_UPDATE_EXPIRED => 'Update Expired',
            self::EVENT_SET_PARAMS => 'Set Params',
            self::EVENT_UPDATE_MEASURE => 'Update Measure',
            self::EVENT_UPDATE_LOCATIONS => 'Update Locations',
            self::EVENT_SET_MAPPING => 'Set Mapping',
        ];
    }

    private static function add(array $data)
    {
        $data['event_date'] = time();
        $temp = new SKUHistory($data);
        $temp->save(array_keys($data));
    }

    public static function addASN($SKU_id, $adjustment, $movement, $asn, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => (int)$adjustment,
            'event' => self::EVENT_ASN,
            'additional_info' => ['Movement ID' => (int)$movement, 'ASN ID' => (int)$asn],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function delASN($SKU_id, $adjustment, $movement, $asn, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => -(int)$adjustment,
            'event' => self::EVENT_REVERT,
            'additional_info' => ['Movement ID' => (int)$movement, 'ASN ID' => (int)$asn],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function updateBatch($SKU_id, $batch_id, $number_old, $expired_old, $number_new, $expired_new, $warehouse_id)
    {
        $data = [
            'SKU_id' => (int)$SKU_id,
            'event_date' => time(),
            'adjustment' => 0,
            'event' => self::EVENT_BATCHES,
            'additional_info' => [
                'Mode' => 'update',
                'Batch ID' => $batch_id,
                'Old batch' => ['number' => $number_old, 'expired' => $expired_old],
                'New batch' => ['number' => $number_new, 'expired' => $expired_new],
            ],
            'warehouse_id' => $warehouse_id
        ];
        $keys = array_keys($data);
        $temp = new SKUHistory($data);
        $temp->save($keys);
        return $temp->getId();
    }

    public static function deleteBatch($SKU_id, Batch $batch, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_BATCHES,
            'additional_info' => ['Mode' => 'delete', 'Batch' => $batch->toArray()],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addPutaway($loc, $SKU_id, $quan, $movement, $user, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_PUTAWAY,
            'additional_info' => ['Movement ID' => (int)$movement, 'Units' => (int)$quan, 'From' => 0, 'To' => (int)$loc, 'User' => $user],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function movement($loc_from, $loc_to, $SKU_id, $quan, $reason, $movement, $user, $warehouse_id, string $user_reason = '')
    {
        self::add([
            'SKU_id' => $SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_MOVEMENT,
            'additional_info' => ['Movement ID' => (int)$movement, 'Units' => (int)$quan, 'From' => (int)$loc_from, 'To' => (int)$loc_to, 'Reason' => $reason, 'User' => $user, 'UserReason' => $user_reason],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function clearDH($SKU_id, $quan, $loc_data, $warehouse_id)
    {
        self::add([
            'SKU_id' => $SKU_id,
            'adjustment' => $quan,
            'event' => self::EVENT_CLEAR_DH,
            'additional_info' => json_encode($loc_data),
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addPartially($loc, $SKU_id, $quan, $movement, $user, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_NON_COMPLIANT,
            'additional_info' => ['Movement ID' => (int)$movement, 'Units' => (int)$quan, 'From' => 0, 'To' => (int)$loc, 'User' => $user],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addNoVerify($loc, $SKU_id, $quan, $movement, $user, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_NO_VERIFY,
            'additional_info' => ['Movement ID' => (int)$movement, 'Units' => (int)$quan, 'From' => 0, 'To' => (int)$loc, 'User' => $user],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addCounting($locFrom, $locTo, $SKU_id, $batch_id, $quan, $movement, $user, $warehouse_id)
    {
        self::add([
            'SKU_id' => $SKU_id,
            'adjustment' => $quan,
            'event' => self::EVENT_COUNTING,
            'additional_info' => $quan ? ['Movement ID' => (int)$movement,
                'Batch' => empty($batch_id) ? '-' : $batch_id,
                'Units' => (int)$quan,
                'From' => $locFrom,
                'To' => $locTo,
                'User' => $user]
                : ['Where' => $locFrom,
                    'Batch' => $batch_id,
                    'User' => (int)$user],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addPicking($locFrom, $locTo, $ord, $SKU_id, $quan, $movement, $user, $mode, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_PICKING . strtoupper(substr($mode, 0, 1)),
            'additional_info' => ['Movement ID' => (int)$movement, 'Order' => $ord, 'Units' => (int)$quan, 'From' => (int)$locFrom, 'To' => (int)$locTo, 'User' => $user],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addHandover($locFrom, $locTo, $modes, $user, $warehouse_id)
    {
        self::add([
            'SKU_id' => -1,
            'adjustment' => 0,
            'event' => self::EVENT_HANDOVER,
            'additional_info' => ['From' => (int)$locFrom, 'To' => (int)$locTo, 'Type' => '"' . implode(',', $modes) . '"', 'User' => $user],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addDropping($locFrom, $locTo, $ord, $SKU_id, $batch_id, $quan, $movement, $user, $warehouse_id)
    {
        self::add([
            'SKU_id' => $SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_DROPPING,
            'additional_info' => [
                'Movement ID' => (int)$movement,
                'Order' => $ord,
                'Batch' => empty($batch_id) ? '-' : $batch_id,
                'Units' => (int)$quan,
                'From' => (int)$locFrom,
                'To' => (int)$locTo,
                'User' => $user
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addDroppingTable($loc, $ord, $SKU_id, $batch_id, $quan, $movement, $user, $warehouse_id)
    {
        self::add([
            'SKU_id' => $SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_DROPPING_T,
            'additional_info' => [
                'Movement ID' => (int)$movement,
                'Order' => $ord,
                'Batch' => empty($batch_id) ? '-' : $batch_id,
                'Units' => (int)$quan,
                'Where' => (int)$loc,
                'User' => $user
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addPacking($loc, $ord, $sku, $quan, $movement, $user, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$sku,
            'adjustment' => 0,
            'event' => self::EVENT_PACKING,
            'additional_info' => ['Movement ID' => (int)$movement, 'Order' => $ord, 'Units' => (int)$quan, 'Where' => (int)$loc, 'User' => $user],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addPackingVirtual($order_id, $SKU_id, $quan, $user_id, $warehouse, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_PACKING,
            'additional_info' => ['Movement ID' => -1, 'Order' => $order_id, 'Units' => (int)$quan, 'Warehouse' => $warehouse, 'User' => $user_id],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function reinstateOrder($SKU_id, $quan, $location_id, $order_id, $user_id, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_REINSTATE_ORDER,
            'additional_info' => [
                'Order' => (int)$order_id,
                'Units' => (int)$quan,
                'Where' => (int)$location_id,
                'User' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function cancelOrder($SKU_id, $quan, $location_id, $order_id, $user_id, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_CANCEL_ORDER,
            'additional_info' => [
                'Order' => (int)$order_id,
                'Units' => (int)$quan,
                'Where' => (int)$location_id,
                'User' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function changeSKU($order1_id, $order2_id, $SKU_id, $quantity, $movement, $user, $warehouse_id)
    {
        self::add([
            'SKU_id' => $SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_CHANGE_SKU,
            'additional_info' => ['Movement ID' => (int) $movement, 'Order from' => $order1_id, 'Order to' => $order2_id, 'Units' => (int) $quantity, 'User' => $user],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function updateMeasure($SKU_id, $user_id, $changes, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_UPDATE_MEASURE,
            'additional_info' => [
                'changes' => $changes,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function updateDimensioner($SKU_id, $user_id, $updates, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_UPDATE_DIMENSIONER,
            'additional_info' => [
                'updates' => $updates,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function updateLocations($SKU_id, $user_id, $changes, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_UPDATE_LOCATIONS,
            'additional_info' => [
                'changes' => $changes,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function updateParams($SKU_id, $user_id, $changes, $warehouse_id, $bulkFile = null)
    {
        $data = [
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_SET_PARAMS,
            'additional_info' => [
                'changes' => $changes,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ];
        if (!is_null($bulkFile))
            $data['additional_info']['bulk_file'] = $bulkFile;
        self::add($data);
    }

    public static function setState($SKU_id, $user_id, $state, $warehouse_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_SET_STATE,
            'additional_info' => [
                'state' => SKU::getStateName((int)$state),
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function setMapping($SKU_id, $user_id, $merge_SKU, $warehouse_id = 0)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_SET_MAPPING,
            'additional_info' => [
                'merge_SKU' => $merge_SKU,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function hardRemove($SKU_id, $user_id, $warehouse_id, $dump)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_HARD_REMOVE,
            'additional_info' => [
                'dump' => $dump,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addChannelProduct($SKU_id, $shop_id, $external_id, $user_id, $warehouse_id = 0)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_ADD_CHANNEL_PRODUCT,
            'additional_info' => [
                'shop_id' => $shop_id,
                'external_id' => $external_id,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function changeExternalId($SKU_id, $user_id, $SKU_channel_id, $updated_external_id, $warehouse_id = 0)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_CHANGE_CHANNEL_EXTERNAL_ID,
            'additional_info' => [
                'SKU_channel_id' => $SKU_channel_id,
                'updated_external_id' => $updated_external_id,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function deleteChannelSKU($SKU_id, $user_id, $SKU_channel_supplier_id, $warehouse_id = 0)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_DELETE_CHANNEL_SKU,
            'additional_info' => [
                'SKU_channel_supplier_id' => $SKU_channel_supplier_id,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function setChannelInventory($SKU_id, $user_id, $data, $warehouse_id = 0)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_SET_CHANNEL_INVENTORY,
            'additional_info' => [
                'data' => $data,
                'user_id' => $user_id
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function updateMinStockLevel($SKU_id, $user_id, $warehouse_id, $old, $new)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_UPDATE_MIN_STOCK_LEVEL,
            'additional_info' => [
                'old' => $old,
                'new' => $new,
                'user_id' => $user_id,
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function getIdByStock($SKU_id, $warehouse_id)
    {
        return (new SafeMysql())->fetchOne("
            SELECT id, adjustment
            FROM SKU_history
            WHERE SKU_id = :SKU_id AND warehouse_id = :warehouse_id AND event = :event
            ORDER BY event_date DESC
            LIMIT 1",
            ['SKU_id' => (int)$SKU_id, 'warehouse_id' => (int)$warehouse_id, 'event' => self::EVENT_STOCK]);
    }

    public static function getByEventDate($make_stock, $warehouse_id)
    {
        $histories = (new SafeMysql())->fetchAll("SELECT SKU_id, event_date, event, additional_info
                FROM SKU_history
                WHERE warehouse_id = :warehouse_id AND
                      event IN (:events) AND
                      DATE_FORMAT(event_date, '%Y-%m-%d') = :make_stock
                ORDER BY event_date DESC",
            ['warehouse_id' => (int)$warehouse_id, 'events' => self::STOCK_EVENTS, 'make_stock' => $make_stock]);
        if (empty($histories))
            return [];
        $res = [];
        foreach ($histories as $SKU)
            $res[$SKU['SKU_id']][$SKU['event_date']][] = ['event' => $SKU['event']] + json_decode($SKU['additional_info'], true);
        return $res;
    }

    public static function updateExpired($SKU_id, $data_expired, $comment, $warehouse_id, $user_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_UPDATE_EXPIRED,
            'additional_info' => $data_expired + ['comment' => $comment, 'user_id' => $user_id],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function updateExpiredSettings($SKU_id, $data_expired, $comment, $warehouse_id, $user_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_EXPIRED_SETTINGS,
            'additional_info' => $data_expired + ['comment' => $comment, 'user_id' => $user_id],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function destroyExpired($SKU_id, $quantity, $data_expired, $event, $warehouse_id, $user_id)
    {
        self::add([
            'SKU_id' => (int)$SKU_id,
            'adjustment' => $quantity,
            'event' => $event,
            'additional_info' => $data_expired + ['user_id' => $user_id],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function addOrderSKUSerialNumbers(int $order_id, int $SKU_id, string $serial_number, int $orders_packages_id, int $warehouse_id)
    {
        self::add([
            'SKU_id' => $SKU_id,
            'adjustment' => 0,
            'event' => self::EVENT_SERIAL_NUMBER,
            'additional_info' => [
                'Mode' => 'add',
                'order_id' => $order_id,
                'serial number' => $serial_number,
                'orders_packages_id' => $orders_packages_id,
            ],
            'warehouse_id' => $warehouse_id
        ]);
    }

    public static function prepareAdditionalInfo(
        array $history,
        ?array &$locations = null,
        ?array &$users = null,
        ?array &$orders = null,
        ?array &$ASNs = null,
        bool $populate = false,
        ?string $warehouse_label = null
    ): array {
        $additional_info = json_decode($history['additional_info'], true);
        if (empty($additional_info))
            return [];
        // clear not needed keys
        foreach (self::$remove_keys as $key)
            Arr::unsetByPath($additional_info, $key);
        // add users
        if ($users !== null)
            self::prepareField($additional_info, self::$user_keys, $users, $populate ? function($id) {
                $user = Users::getById($id);
                if (!$user)
                    return 'Unknown user #' . $id;
                return $user->getLogin() ?: ('User #' . $id);
            } : null, true);
        // add locations
        if ($locations !== null)
            self::prepareField($additional_info, self::$location_keys, $locations, $populate ? function($id) use ($warehouse_label) {
                $location = Locations::getById($id);
                if (!$location)
                    return 'Unknown location #' . $id;
                return $location->getLocation($warehouse_label ?: $location->getWarehouse());
            } : null, $populate);
        // add orders
        if ($orders !== null)
            self::prepareField($additional_info, self::$order_keys, $orders, $populate ? function($id) {
                $order = Orders::getById($id);
                if (!$order)
                    return 'Unknown order #' . $id;
                return $order->getRef() ?: ('Order #' . $id);
            } : null, $populate);
        // add ASNs
        if ($ASNs !== null)
            self::prepareField($additional_info, self::$ASN_keys, $ASNs, $populate ? function($id) {
                $ASN = ASNs::getById($id);
                if (!$ASN)
                    return 'Unknown ASN #' . $id;
                return $ASN->getRef() ?: ('ASN #' . $id);
            } : null, $populate);
        // add batch info
        if (!empty($history['batch_number']))
            foreach (self::$batch_keys as $key) {
                if (Arr::hasByPath($additional_info, $key)) {
                    $batch_info = Batch::toBatchInfo($history['batch_number'], $history['batch_expired'], false);
                    Arr::setByPath($additional_info, $key, $batch_info);
                }
            }
        return $additional_info;
    }

    private static function prepareField(array &$array, array $keys, array &$cache, ?callable $populate_func = null, $unset = false)
    {
        foreach ($keys as $key => $replace_with) {
            if (is_array($replace_with)) {
                if (!is_numeric($key))
                    $replace_with = array_combine($replace_with, array_fill(0, count($replace_with), $key));
                self::prepareField($array, $replace_with, $cache, $populate_func, $unset);
                continue;
            }
            if (is_numeric($key)) {
                $key = $replace_with;
                $parts = explode('.', $key);
                $replace_with = array_pop($parts);
            }
            if (!Arr::hasByPath($array, $key))
                continue;
            $values = Arr::getByPath($array, $key, null, true);
            // for [*].<key>...
            foreach ($values as $_key => $id) {
                $value = self::getValue($id, $cache, $populate_func, $unset);
                if ($value === null) {
                    Arr::unsetByPath($array, $_key);
                    continue;
                }
                $parts = explode('.', $_key);
                $new_key = array_pop($parts);
                if ($new_key != $replace_with) {
                    Arr::unsetByPath($array, $_key);
                    $parts[] = $replace_with;
                    $_key = implode('.', $parts);
                }
                Arr::setByPath($array, $_key, $value);
            }
        }
    }

    private static function getValue($value, array &$cache, ?callable $populate_func = null, $unset = false)
    {
        if (is_array($value)) {
            $new_array = [];
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $k = self::getValue($k, $cache, $populate_func, $unset);
                    if ($k === null)
                        continue;
                    $new_array[$k] = $v;
                } else
                    $new_array[$k] = self::getValue($v, $cache, $populate_func, $unset);
            }
            return $new_array;
        }
        if (array_key_exists($value, $cache))
            return $cache[$value];
        if ($populate_func && !empty($value))
            return $populate_func($value);
        if ($unset)
            return null;
        return $value;
    }
}
