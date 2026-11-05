<?php

namespace app\backend\controllers;

use app\backend\models\Addresses;
use app\core\entities\Address;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\backend\models\Warehouses as WarehousesModel;
use app\core\models\config\WarehousesConfig;
use app\core\statical\Countries;
use app\core\statical\Currencies;
use app\core\models\Sizes;
use app\core\statical\Timezones;
use app\core\dbal\SafeMysql;
use app\core\statical\TypeLocation;
use app\core\models\LocationTypeZones;
use app\core\entities\LocationTypeZone;
use app\backend\models\Locations;
use app\core\exceptions\RedirectException;

class Warehouses extends AuthController
{
    public function settings(): void
    {
        $warehouse = WarehousesModel::getById($this->warehouse_id);
        $this->render([
            'warehouse' => $warehouse,
            'address' => new Address(!empty($warehouse->getAddress()) ? $warehouse->getAddress() : []),
            'country' => Countries::getName($warehouse->getCountryId() ?: 'GB'),
            'currency' => Currencies::getCurrency($warehouse->getCurrencyId()),
            'timezones' => ['UTC' => 'UTC'] + Timezones::getByCountryCode($warehouse->getCountryId()),
            'countries' => Countries::getAll(),
            'currencies' => Arr::map(Currencies::getAll(), 'id', 'currency'),
            'types' => LocationTypeZones::getTypes(),
            'location_type_zones' => LocationTypeZones::getAllGroupByTypeId((int)$this->warehouse_id),
            'max_length_zone' => Locations::ZONE_MAX_LEN,
            'regexp_zone' => Locations::REGEXP_LAT_NUM,
            'release_date' => WarehousesConfig::getAutomaticalRelease($this->warehouse_id),
            'sizes' => [0 => ' '] + Arr::map(Sizes::getAllSizes($warehouse->getId()), 'id', 'name'),
            'warehouse_config' => WarehousesConfig::readConfigsById($this->warehouse_id)
        ], 'warehouse/settings');
    }

    public function store()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST) || empty($_POST['warehouse_id']) || $_POST['warehouse_id'] != $this->warehouse_id)
            return $this->response = json_encode(['error' => 'Error data']);
        $new_address = null;
        if (!$this->warehouse_virtual) {
            $new_address = new Address([
                'contact' => $_POST['company'],
                'address1' => $_POST['address1'],
                'address2' => $_POST['address2'],
                'town' => $_POST['town'],
                'county' => $_POST['county'],
                'postcode' => $_POST['postcode'],
                'country_id' => $_POST['company_country']
            ]);
            $errors = Addresses::validate($new_address);
        } else {
            // the API key is not sent to the page (ADR-002 Phase 2 step 4c): an empty field keeps the stored one
            $api_key = trim((string)($_POST['api_key'] ?? ''));
            if ($api_key === '')
                $api_key = (string)(WarehousesModel::getById($_POST['warehouse_id'])->getPickingHistory()['api_key'] ?? '');
            $connection_info = [
              'host' => $_POST['host'],
              'class' => $_POST['class'],
              'api_key_name' => $_POST['api_key_name'],
              'api_key' => $api_key,
            ];
            $errors = WarehousesModel::validateConnection($connection_info);
        }
        $new_warehouse = new Warehouse([
            'id' => $_POST['warehouse_id'],
            'label' => $_POST['label'],
            'mezzanine' => $_POST['mezzanine'],
            'currency_id' => $_POST['currency_id'],
            'billing' => $_POST['billing'],
            'timezone' => $_POST['timezone'],
            'country_id' => $_POST['country_id'],
        ]);
        $errors = array_merge($errors, WarehousesModel::validate($new_warehouse));
        if (!empty($errors))
            return $this->response = json_encode(['error' => '<ul class="m-0 px-3">' . implode(array_map(function ($item) {
                    return '<li>' . View::toHtml($item['message']) . '</li>';
                }, $errors)) . '</ul>']);
        $warehouse = WarehousesModel::getById($_POST['warehouse_id']);
        if (!$this->warehouse_virtual)
            $fields = WarehousesModel::changingAddress($warehouse, $new_address);
        else {
            $connection_info['locations'] = $warehouse->getPickingHistory()['locations'] ?? [];
            $fields = WarehousesModel::changingConnection($warehouse, $connection_info);
        }
        $result = array_merge($fields, WarehousesModel::changingData($warehouse, $new_warehouse));
        if (empty($result)) {
            View::addFlash('warehouse', 'warning', 'Nothing to change');
            return $this->response = json_encode(['success' => '']);
        }
        $warehouse = new Warehouse($result);
        $warehouse->save(array_keys($result));
        View::addFlash('warehouse', 'success', 'Warehouse updated');
        return $this->response = json_encode(['success' => '']);
    }

    public function storeConfiguration()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST) || empty($_POST['warehouse_id']) || $_POST['warehouse_id'] != $this->warehouse_id)
            return $this->response = json_encode(['error' => 'Error data']);
        $warehouse = WarehousesModel::getById($_POST['warehouse_id']);
        $conf_CA = [
            'nums' => (int)($_POST['quantity_ca'] ?? 0),
            'size_id' => $_POST['size_CA'] ? Sizes::getById($_POST['size_CA'])->getId() : 0,
            'shelves' => (int)($_POST['shelves'] ?? 0),
            'weight' => (double)($_POST['weight'] ?? 0),
        ];
        $conf_TP = [
            'nums' => (int)($_POST['table_pack'] ?? 0),
            'LP' => (int)($_POST['large_pack'] ?? 0),
            'size_id' => $_POST['size_PA'] ? Sizes::getById($_POST['size_PA'])->getId() : 0,
        ];
        $conf_OB = [
            'nums' => (int)($_POST['quantity_ob'] ?? 0),
            'size_id' => $_POST['size_OB'] ? Sizes::getById($_POST['size_OB'])->getId() : 0,
            'cols' => (int)($_POST['cols'] ?? 0),
            'rows' => (int)($_POST['rows'] ?? 0),
        ];
        if ($warehouse->getMezzanine()) {
            $conf_CA['nums_mez'] = (int)($_POST['quantity_mez'] ?? 0);
            $conf_OB['nums_mez'] = (int)($_POST['quantity_ob_mez'] ?? 0);
        }
        $errors = array_merge(
            WarehousesModel::validateCA($warehouse, $conf_CA),
            WarehousesModel::validateTP($conf_TP),
            WarehousesModel::validateOB($warehouse, $conf_OB)
        );
        if (!empty($errors))
            return $this->response = json_encode(['error' => '<ul class="m-0 px-3">' . implode(array_map(function ($item) {
                    return '<li>' . View::toHtml($item['message']) . '</li>';
                }, $errors)) . '</ul>']);
        $fields = WarehousesModel::changingConfiguration($warehouse, $conf_CA, $conf_TP, $conf_OB);
        if (empty($fields)) {
            View::addFlash('warehouse', 'warning', 'Nothing to change');
            return $this->response = json_encode(['success' => '']);
        }
        $warehouse = new Warehouse($fields);
        $warehouse->save(array_keys($fields));
        View::addFlash('warehouse', 'success', 'Warehouse updated');
        return $this->response = json_encode(['success' => '']);
    }

    public function storeZones(): void {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (WarehousesModel::isVirtual($this->warehouse_id)) {
            $this->response = json_encode(['error' => 'Forbidden for virtual warehouses']);
            return;
        }
        $zones = $_POST['zones'] ?? [];
        if (!is_array($zones)) {
            $this->response = json_encode(['error' => 'Unknown error']);
            return;
        }
        $zones_for_write = [];
        foreach ($zones as $type_id => $zone) {
            if (!TypeLocation::isTypeLocation($type_id)) {
                $this->response = json_encode(['error' => 'Unknown error']);
                return;
            }
            foreach ($zone as $item) {
                if ($error = LocationTypeZones::validationZone($item)) {
                    $this->response = json_encode(['error' => $error]);
                    return;
                }
                $zones_for_write[] = new LocationTypeZone(['warehouse_id' => (int)$this->warehouse_id, 'type_id' => addslashes($type_id), 'zone' => addslashes($item)]);
            }
        }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        LocationTypeZones::deleteByWarehouse((int)$this->warehouse_id);
        LocationTypeZones::multiInsert($zones_for_write);
        $mysql->commit();
        $this->response = json_encode(['success' => '']);
    }

    public function storeRelease()
    {
        if (!Request::isAjax() || empty($_POST) || empty($_POST['warehouse_id']) || $_POST['warehouse_id'] != $this->warehouse_id)
            return $this->response = json_encode(['error' => 'Error data']);
        if ($_POST['amount'] < 0)
            return $this->response = json_encode(['error' => 'The value of the field must be not negative']);
        $time = (int)$_POST['amount'];
        $warehouse_id = (int)$_POST['warehouse_id'];
        $new_warehouse = new Warehouse([
            'id' => $warehouse_id,
            'collection_by_customer' => $_POST['collection'],
            'is_multi_dropping' => $_POST['is_multi_dropping'],
        ]);
        $warehouse = WarehousesModel::getById($warehouse_id);
        $result = WarehousesModel::changingProcess($warehouse, $new_warehouse);
        $warehouse = new Warehouse($result);
        $warehouse->save(array_keys($result));
        WarehousesConfig::saveConfigs([
            WarehousesConfig::automaticalRelease($warehouse_id, $time),
            WarehousesConfig::timerStatus($warehouse_id, (bool)$_POST[WarehousesConfig::TYPE_TIMER_STATUS]),
            WarehousesConfig::timerPrintPdf($warehouse_id, (int)$_POST[WarehousesConfig::TYPE_TIMER_PDF])
        ]);
        return $this->response = json_encode(['success' => '']);
    }
}
