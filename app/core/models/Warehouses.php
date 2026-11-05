<?php

namespace app\core\models;

use app\backend\models\SupplierWarehouses;
use app\core\dbal\SafeMysql;
use app\core\entities\Address;
use app\core\entities\LogisticCompany;
use app\core\entities\Size;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\services\crypto\StoredSecret;
use app\core\virtual\BaseVirtualConnector;
use app\core\virtual\BaseVirtualCredentials;
use app\core\virtual\BaseVirtualProcessor;
use app\backend\models\Suppliers;
use app\core\entities\Location;

class Warehouses
{
    public static $available_floors = [
        -1 => 'Downstairs',
        1 => 'Mezzanine'
    ];

    public static function getByName($lc_id, $name)
    {
        $wh_info = (new SafeMysql())->fetchOne(
            "SELECT * FROM warehouses WHERE name = :name AND lc_id = :lc_id LIMIT 1",
            ['name' => $name, 'lc_id' => (int)$lc_id]
        );
        if (!empty($wh_info))
            return new Warehouse($wh_info);
        return null;
    }

    public static function changingAddress(Warehouse $old_warehouse, ?Address $new_address)
    {
        $change_address = false;
        if (!is_null($new_address)) {
            $old_address = $old_warehouse->getAddress();
            if (($old_address['company'] ?? '') !== $new_address->getContact())
                $change_address = $old_address['company'] = $new_address->getContact();
            if (($old_address['address1'] ?? '') !== $new_address->getAddress1())
                $change_address = $old_address['address1'] = $new_address->getAddress1();
            if (($old_address['address2'] ?? '') !== $new_address->getAddress2())
                $change_address = $old_address['address2'] = $new_address->getAddress2();
            if (($old_address['town'] ?? '') !== $new_address->getTown())
                $change_address = $old_address['town'] = $new_address->getTown();
            if (($old_address['county'] ?? '') !== $new_address->getCounty())
                $change_address = $old_address['county'] = $new_address->getCounty();
            if (($old_address['postcode'] ?? '') !== $new_address->getPostcode())
                $change_address = $old_address['postcode'] = $new_address->getPostcode();
            if (($old_address['country_id'] ?? '') !== $new_address->getCountryId())
                $change_address = $old_address['country_id'] = $new_address->getCountryId();
        }
        $new_data = $change_address !== false ? ['address' => $old_address] : [];
        if (!empty($new_data))
            $new_data['id'] = $old_warehouse->getId();
        return $new_data;
    }

    public static function changingConnection(Warehouse $old_warehouse, $new_connection)
    {
        $new_connections = [];
        $diff_connection = array_udiff_assoc($new_connection, $old_warehouse->getPickingHistory(), function ($item1, $item2) {
            if (is_array($item1) && is_array($item2))
                return (int)!empty(array_diff_assoc($item1, $item2));
            if (is_array($item1) || is_array($item2))
                return 1;
            return (int)($item1 != $item2);
        });
        if (!empty($diff_connection))
            $new_connections['picking_history'] = $new_connection;
        if (!empty($new_connections))
            $new_connections['id'] = $old_warehouse->getId();
        return $new_connections;
    }

    public static function changingData(Warehouse $old_warehouse, Warehouse $new_warehouse)
    {
        $new_data = [];
        if ($old_warehouse->getLabel() !== $new_warehouse->getLabel())
            $new_data['label'] = $new_warehouse->getLabel();
        if ($old_warehouse->getTimezone() !== $new_warehouse->getTimezone())
            $new_data['timezone'] = $new_warehouse->getTimezone();
        if ($old_warehouse->getCountryId() !== $new_warehouse->getCountryId())
            $new_data['country_id'] = $new_warehouse->getCountryId();
        if ($old_warehouse->getMezzanine() !== $new_warehouse->getMezzanine())
            $new_data['mezzanine'] = $new_warehouse->getMezzanine();
        if ($old_warehouse->getCurrencyId() !== $new_warehouse->getCurrencyId())
            $new_data['currency_id'] = $new_warehouse->getCurrencyId();
        if ($old_warehouse->getBilling() !== $new_warehouse->getBilling())
            $new_data['billing'] = $new_warehouse->getBilling();
        if (!empty($new_data))
            $new_data['id'] = $old_warehouse->getId();
        return $new_data;
    }

    public static function changingProcess(Warehouse $old_warehouse, Warehouse $new_warehouse)
    {
        $new_data = [];
        if ($old_warehouse->getCollectionByCustomer() !== $new_warehouse->getCollectionByCustomer())
            $new_data['collection_by_customer'] = $new_warehouse->getCollectionByCustomer();
        if ($old_warehouse->getMultiDropping() !== $new_warehouse->getMultiDropping())
            $new_data['is_multi_dropping'] = $new_warehouse->getMultiDropping();
        if (!empty($new_data))
            $new_data['id'] = $old_warehouse->getId();
        return $new_data;
    }

    public static function changingConfiguration(Warehouse $old_warehouse, $new_size_CA, $new_size_TP, $new_size_OB)
    {
        $new_sizes = [];
        if (!empty(array_diff_assoc($new_size_CA, $old_warehouse->getCA())))
            $new_sizes['CA'] = $new_size_CA;
        if (!empty(array_diff_assoc($new_size_TP, $old_warehouse->getTP())))
            $new_sizes['TP'] = $new_size_TP;
        if (!empty(array_diff_assoc($new_size_OB, $old_warehouse->getOB())))
            $new_sizes['OB'] = $new_size_OB;
        if (!empty($new_sizes))
            $new_sizes['id'] = $old_warehouse->getId();
        return $new_sizes;
    }

    public static function getById($id): ?Warehouse
    {
        $wh_info = (new SafeMysql())->fetchOne("SELECT * FROM warehouses WHERE id = :id LIMIT 1", [
            'id' => (int) $id
        ]);
        if (!empty($wh_info))
            return new Warehouse($wh_info);
        return null;
    }

    public static function getByIds($ids)
    {
        if (empty($ids))
            return [];
        if (!is_array($ids))
            $ids = [$ids];
        $warehouses = (new SafeMysql())->fetchAll("SELECT * FROM warehouses WHERE id IN (:ids)", ['ids' => array_map('intval', $ids)]);
        $lcs = array_unique(array_column($warehouses, 'lc_id'));
        if (count($lcs) !== 1)
            return [];
        return $warehouses;
    }

    public static function getAll(int $lc_id)
    {
        $warehouses = (new SafeMysql())->fetchAll("SELECT * FROM warehouses WHERE lc_id = :lc_id", ['lc_id' => $lc_id]);
        array_walk($warehouses, function(&$warehouse) {
            $warehouse['is_virtual'] = (int)$warehouse['is_virtual'];
            $warehouse['country_id'] = $warehouse['country_id'] ?: '';
        });
        return $warehouses;
    }

    /**
     * @param int $lc_id
     * @return Warehouse[]
     * @throws \app\core\db\DBException
     */
    public static function getAllObjects(int $lc_id): array
    {
        $warehouses = (new SafeMysql())->fetchAll("SELECT * FROM warehouses WHERE lc_id = :lc_id", ['lc_id' => $lc_id]);
        $objects = [];
        foreach ($warehouses as $warehouse) {
            $warehouse['is_virtual'] = (int)$warehouse['is_virtual'];
            $warehouse['country_id'] = $warehouse['country_id'] ?: '';
            $objects[$warehouse['id']] = new Warehouse($warehouse);
        }
        return $objects;
    }

    public static function getAllId($lc_id)
    {
        return Arr::column(self::getAll($lc_id), 'id');
    }

    public static function getLC($ids) : ?LogisticCompany
    {
        if (empty($ids))
            return null;
        if (!is_array($ids))
            $ids = [$ids];
        $data = (new SafeMysql())->fetchAll("
            SELECT DISTINCT lc.*
            FROM warehouses w
                JOIN logistic_companies lc ON w.lc_id = lc.id 
            WHERE w.id IN (:ids)", ['ids' => array_map('intval', $ids)]);
        if (count($data) !== 1)
            return null;
        return new LogisticCompany($data[0]);
    }

    public static function getLabel($warehouse_id)
    {
        $label = (new SafeMysql())->fetchScalar(
            "SELECT IF(label = '' OR label IS NULL, :wid_default, label) FROM warehouses WHERE id = :wid",
            ['wid_default' => (int)$warehouse_id, 'wid' => (int)$warehouse_id]
        );
        return empty($label) ? $warehouse_id : $label;
    }

    public static function getCountryId($warehouse_id)
    {
        $country_id = (new SafeMysql())->fetchScalar("SELECT country_id FROM warehouses WHERE id = :id", ['id' => (int)$warehouse_id]);
        return $country_id ?: 'GB';
    }

    public static function isVirtual($warehouse_id)
    {
        return (bool)(new SafeMysql())->fetchScalar("SELECT is_virtual FROM warehouses WHERE id = :id", ['id' => (int)$warehouse_id]);
    }

    public static function isCollectionByCustomer($warehouse_id)
    {
        return (bool)(new SafeMysql())->fetchScalar("SELECT collection_by_customer FROM warehouses WHERE id = :id", ['id' => (int)$warehouse_id]);
    }

    public static function getLocations($warehouse_id)
    {
        $settings = (new SafeMysql())->fetchScalar(
            "SELECT picking_history FROM warehouses WHERE id = :warehouse_id AND is_virtual = 1",
            [
                'warehouse_id' => (int) $warehouse_id
            ]
        );
        if ($settings === false)
            return false;
        if (empty($settings))
            return [];
        $settings = json_decode($settings, true);
        if (empty($settings['locations']))
            return [];
        return $settings['locations'];
    }

    public static function getSubwarehouses($warehouse_id)
    {
        $locations = [];
        foreach (Warehouses::getLocations($warehouse_id) as $warehouse_abbr => $warehouse_name) {
            $virt_loc = Location::SPECIAL_LOCATION__VIRTUAL;
            $loc = Locations::getByLocation($warehouse_id, $virt_loc[0], $virt_loc[1], $virt_loc[2], $warehouse_abbr);
            if (empty($loc) || count($loc) != 1)
                return false;
            $locations[$warehouse_abbr] = $loc[0]->getId();
        }
        return $locations;
    }

    private static function getCredentials($warehouse_id)
    {
        $credentials = (new SafeMysql())->fetchScalar("
            SELECT picking_history
            FROM warehouses
            WHERE id = :id AND is_virtual = 1", ['id' => (int)$warehouse_id]);
        if ($credentials === false || $credentials === null)
            return false;
        if (empty($credentials))
            return [];
        $credentials = json_decode($credentials, true);
        return is_array($credentials) ? StoredSecret::decryptFields($credentials, Warehouse::SECRET_CONNECTION_FIELDS) : $credentials;
    }

    private static function setCredentials($warehouse_id, $credentials)
    {
        (new SafeMysql())->execute("
            UPDATE warehouses
            SET picking_history = :credentials
            WHERE id = :warehouse_id AND is_virtual = 1", [
            'credentials' => json_encode(StoredSecret::encryptFields($credentials, Warehouse::SECRET_CONNECTION_FIELDS)),
            'warehouse_id' => (int)$warehouse_id,
        ]);
        return true;
    }

    public static function getVirtualCredentials($warehouse_id, $supplier_id)
    {
        $credentials = self::getCredentials($warehouse_id);
        if (empty($credentials))
            return [null, null];
        $class_name = BaseVirtualCredentials::getClassName($credentials['class']);
        if (empty($credentials['class']) || !class_exists($class_name))
            return [null, null];
        $virtual_credentials = SupplierWarehouses::getVirtualCredentials($warehouse_id, $supplier_id);
        if ($virtual_credentials === false)
            return [null, null];
        $virtual_credentials = new $class_name([
            'host' => $credentials['host'] ?? '',
            'api_key_name' => $credentials['api_key_name'] ?? '',
            'api_key' => $credentials['api_key'] ?? '',
            'supplier_id' => $supplier_id,
        ] + $virtual_credentials);
        return [new $class_name($virtual_credentials), $credentials['class']];
    }

    public static function setVirtualCredentials($warehouse_id, BaseVirtualCredentials $credentials_obj)
    {
        $warehouse_id = (int)$warehouse_id;
        $credentials = SupplierWarehouses::getVirtualCredentials($warehouse_id, $credentials_obj->getSupplierId());
        if ($credentials === false)
            return false;
        $new_credentials = $credentials_obj->toArray();
        if (empty($credentials['access_token']) || $credentials['access_token'] != $new_credentials['access_token'])
            $credentials['access_token'] = $new_credentials['access_token'];
        if (empty($credentials['expires']) || $credentials['expires'] != $new_credentials['expires'])
            $credentials['expires'] = $new_credentials['expires'];
        (new SafeMysql())->execute("
            UPDATE suppliers_warehouses
            SET virtual_credentials = :credentials
            WHERE warehouse_id = :warehouse_id AND supplier_id = :supplier_id", [
            'credentials' => StoredSecret::encrypt((string)json_encode($credentials)),
            'warehouse_id' => $warehouse_id,
            'supplier_id' => $credentials_obj->getSupplierId(),
        ]);
        return true;
    }

    public static function getVirtualAPIKey($warehouse_id)
    {
        $credentials = self::getCredentials($warehouse_id);
        if (empty($credentials) || empty($credentials['api_key_name']) || empty($credentials['api_key']))
            return [];
        return ['name' => $credentials['api_key_name'], 'key' => $credentials['api_key']];
    }

    public static function setVirtualAPIKey($warehouse_id, $name, $key)
    {
        $warehouse_id = (int)$warehouse_id;
        $credentials = self::getCredentials($warehouse_id);
        if ($credentials === false || empty($name) || empty($key))
            return false;
        $credentials['api_key_name'] = $name;
        $credentials['api_key'] = $key;
        return self::setCredentials($warehouse_id, $credentials);
    }

    public static function getVirtualConnector($warehouse_id, $supplier_id)
    {
        list($credentials, $class) = self::getVirtualCredentials($warehouse_id, $supplier_id);
        if (empty($credentials) || empty($class))
            return [$credentials, $class];
        $connector_name = BaseVirtualConnector::getClassName($class);
        return [new $connector_name($warehouse_id, $credentials), $class];
    }

    public static function getVirtualProcessor($warehouse_id, $supplier_id)
    {
        list($connector, $class) = self::getVirtualConnector($warehouse_id, $supplier_id);
        if (empty($connector) || empty($class))
            return null;
        $processor_name = BaseVirtualProcessor::getClassName($class);
        $warehouse_ids = Arr::column(Suppliers::getWarehousesById($supplier_id), 'id');
        if (!in_array($warehouse_id, $warehouse_ids))
            return null;
        return new $processor_name($connector, $supplier_id);
    }

    public static function getDefaultShippingService($warehouse_id)
    {
        return (new SafeMysql())->fetchOne(
            "SELECT ss.carrier_id, ss.id shipping_service_id
                FROM warehouses w
                    JOIN shipping_services ss ON w.default_shipping_service_id = ss.id 
                WHERE w.id = :warehouse_id
                LIMIT 1",
            ['warehouse_id' => (int)$warehouse_id]
        );
    }
}