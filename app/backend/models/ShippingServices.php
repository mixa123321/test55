<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Carrier;
use app\core\entities\Order;
use app\core\entities\Shipping;
use app\core\entities\ShippingService;
use app\core\entities\ShippingServiceWarehouse;
use app\core\entities\ShippingServiceWarehouseHistory;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\File;
use app\core\helpers\Validator;
use app\core\helpers\View;
use app\core\models\Carriers;
use app\core\models\Warehouses;
use app\core\services\shipping\ShippingInternal;
use app\core\statical\Timezones;
use app\core\statical\WithDate;
use app\core\helpers\FileWrapper;

class ShippingServices extends \app\core\models\ShippingServices
{
    public static function validate(ShippingService $shipping_service, float $carrier_max_weight = 0) : array
    {
        $rules = [
            'name' => [
                'val' => $shipping_service->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'code' => [
                'val' => $shipping_service->getCode(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'carrier_id' => [
                'val' => (int)$shipping_service->getCarrierId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ]
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if ($shipping_service->getMaxWeight() < 0 || ($carrier_max_weight && $shipping_service->getMaxWeight() > $carrier_max_weight))
            $validator->addError('Shipping_service_max_weight', 'Not correct max weight');
        $shipping_service_2 = self::getByCode($shipping_service->getCode());
        if (!empty($shipping_service_2))
            if ($shipping_service->getId() !== (int)$shipping_service_2['id'])
                $validator->addError('Shipping_service_unique', 'Code must be unique');
        if (!empty($errors))
            return $errors;
        return $validator->getErrors();
    }

    public static function setListForWarehouse($warehouse_id, array $service_ids, $user_id)
    {
        $warehouse_id = (int)$warehouse_id;
        if (!empty($service_ids)) {
            foreach ($service_ids as $service_id) {
                $shipping_service_warehouse = [
                    'shipping_service_id' => (int)$service_id,
                    'warehouse_id' => (int)$warehouse_id
                ];
                $shipping_service_warehouse_obj = new ShippingServiceWarehouse($shipping_service_warehouse);
                $errors = ShippingServiceWarehouses::validate($shipping_service_warehouse_obj);
                if (empty($errors)) {
                    $shipping_service_warehouse_obj->save(array_keys($shipping_service_warehouse));
                    $type = ShippingServiceWarehouseHistory::TYPE_ADD;
                } else
                    $type = ShippingServiceWarehouseHistory::TYPE_ERROR;
                ShippingServiceWarehousesHistory::setHistory($shipping_service_warehouse_obj, $user_id, $type);
            }
        }
    }

    public static function unsetListForWarehouse($warehouse_id, array $service_ids, $user_id)
    {
        if (!empty($service_ids)) {
            (new SafeMysql())->execute("DELETE FROM shipping_service_warehouses WHERE warehouse_id = " . (int)$warehouse_id
                . " AND shipping_service_id IN (" . implode(',', $service_ids) . ")");
            foreach ($service_ids as $service_id) {
                ShippingServiceWarehousesHistory::setHistory(new ShippingServiceWarehouse([
                    'shipping_service_id' => $service_id,
                    'warehouse_id' => $warehouse_id
                ]), $user_id, ShippingServiceWarehouseHistory::TYPE_UNSET);
            }
        }
    }

    public static function getMaxWeight($service_id): ?float
    {
        if (empty($service_id))
            return null;
        return (double)(new SafeMysql())->fetchScalar("SELECT max_weight FROM shipping_services WHERE id = :id", ['id' => (int)$service_id]);
    }

    public static function setDefaultForWarehouse($warehouse_id, $shipping_service_id, $user_id)
    {
        $warehouse = Warehouses::getById($warehouse_id);
        $warehouse->setDefaultShippingServiceId($shipping_service_id);
        $warehouse->save(['id', 'default_shipping_service_id']);
        $shipping_service_warehouse = [
            'shipping_service_id' => (int)$shipping_service_id,
            'warehouse_id' => (int)$warehouse_id
        ];
        $shipping_service_warehouse_obj = new ShippingServiceWarehouse($shipping_service_warehouse);
        ShippingServiceWarehousesHistory::setHistory($shipping_service_warehouse_obj, $user_id,ShippingServiceWarehouseHistory::TYPE_DEFAULT);
    }

    public static function getByWarehouse(int $warehouse_id): array
    {
        $sql = "
            SELECT DISTINCT shs.id, CONCAT(shs.name, ' - ', c.code) value
            FROM shipping_services shs 
                JOIN carriers c ON shs.carrier_id = c.id
                JOIN logistic_company_carriers lcc ON c.id = lcc.carrier_id
                JOIN warehouse_carriers wc ON lcc.id = wc.lc_carrier_id AND wc.warehouse_id = :warehouse_id
                JOIN orders o ON o.shipping_services_id = shs.id AND o.warehouse_id = wc.warehouse_id
            ORDER BY shs.id";
        return (new SafeMysql())->fetchAll($sql, ['warehouse_id' => $warehouse_id]);
    }

    public static function getNameWithCarrierCodeById($id): string
    {
        return (new SafeMysql())->fetchScalar("
            SELECT CONCAT(shs.name, ' - ', c.code) value
            FROM shipping_services shs
                JOIN carriers c ON shs.carrier_id = c.id
            WHERE shs.id = :id
            LIMIT 1", ['id' => (int)$id]) ?? "";
    }

    public static function getServicesByTime($warehouse_id, $time)
    {
        $sql = "
            SELECT ss.id, ss.name, ss.code, DATE_FORMAT(ct.collection, '%H:%i') collection, IFNULL(ct.is_priority, 0) is_priority, ct.priority_level
            FROM shipping_services ss
                JOIN shipping_service_warehouses ssw ON ss.id = ssw.shipping_service_id AND ssw.warehouse_id = :warehouse_id
                LEFT JOIN collection_time ct ON ss.id = ct.shipping_service_id AND ssw.warehouse_id = ct.warehouse_id
            WHERE IFNULL(DATE_FORMAT(ct.collection, '%H:%i'), '00:00') <= :time
            ORDER BY IFNULL(ct.collection, '24:00:00'), ct.is_priority DESC, ct.priority_level, ss.id";
        $services = (new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id, 'time' => $time]);
        $services[] = [
            'id' => Shipping::COLLECTION_BY_CUSTOMER_ID,
            'name' => Shipping::COLLECTION_BY_CUSTOMER,
            'code' => '',
            'collection' => null,
            'is_priority' => 0,
            'priority_level' => null,
        ];
        $max_time = max(array_column($services, 'collection'));
        return array_filter($services, function($item) use($max_time) {
            return $item['collection'] == $max_time || empty($item['collection']);
        });
    }

    public static function getSequentialCollectionTimes($warehouse_id, $add_none = true)
    {
        $sql = "
            SELECT ss.id, ss.name, ct.collection
            FROM shipping_services ss
                JOIN shipping_service_warehouses ssw ON ss.id = ssw.shipping_service_id AND ssw.warehouse_id = :warehouse_id
                LEFT JOIN collection_time ct ON ss.id = ct.shipping_service_id AND ssw.warehouse_id = ct.warehouse_id
            ORDER BY IFNULL(ct.collection, '00:00:00'), ct.is_priority DESC, ct.priority_level, ss.id";
        $services = (new SafeMysql())->fetchAll($sql, ['warehouse_id' => (int)$warehouse_id]);
        $services[] = [
            'id' => Shipping::COLLECTION_BY_CUSTOMER_ID,
            'name' => Shipping::COLLECTION_BY_CUSTOMER,
            'collection' => '',
        ];
        $res = [];
        foreach ($services as $service) {
            $res[$service['collection']]['id'][] = $service['id'];
            $res[$service['collection']]['name'][] = $service['name'];
        }
        foreach ($res as $key => &$service) {
            $service['id'] = implode('_', $service['id']);
            $service['name'] = empty($key) ? 'Time not set' : date('H:i', strtotime($key)) . ' - ' . implode(', ', $service['name']);
        } unset($service);
        $res = Arr::map($res, 'id', 'name');
        return $add_none ? [-1 => 'Select service'] + $res : $res;
    }

    private static function inTimeInterval($times, $collection, $check_date)
    {
        $collection = $collection ?: '00:00:00.00';
        $end_time = null;
        foreach ($times as $time) {
            if ($time <= $collection)
                continue;
            $end_time = $time;
            break;
        }
        if (empty($end_time))
            $end_time = '23:59:59.999';
        return $check_date >= $collection && $check_date < $end_time;
    }

    public static function getStatistics(Warehouse $warehouse, $date_despatch)
    {
        $sql = "
            SELECT ss.id, CONCAT(ss.name, ' (', ss.code, ')') name, ct.collection, o.status, o.despatched_date
            FROM shipping_services ss
                JOIN shipping_service_warehouses ssw ON ss.id = ssw.shipping_service_id AND ssw.warehouse_id = :warehouse_id
                LEFT JOIN collection_time ct ON ss.id = ct.shipping_service_id AND ssw.warehouse_id = ct.warehouse_id
                LEFT JOIN orders o ON ss.id = o.shipping_services_id AND date_format(o.despatch_date, '%Y-%m-%d') = :date_despatch
            ORDER BY ct.collection, ss.id";
        $shipping_services = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse->getId(),
            'date_despatch' => addslashes($date_despatch)
        ]);
        if (empty($shipping_services))
            return [];
        $res = [];
        $times = array_values(Arr::cleanEmptyVal(array_unique(array_column($shipping_services, 'collection'))));
        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        foreach ($shipping_services as $shipping_service) {
            $id = $shipping_service['id'];
            if (empty($res[$id]))
                $res[$id] = [
                    'name' => $shipping_service['name'],
                    'collection' => $shipping_service['collection'] ? date('H:i', strtotime($shipping_service['collection'])) : '',
                    'total' => 0,
                    'stage-1' => 0,
                    'stage0' => 0,
                    'stage1' => 0,
                    'stage3' => 0,
                    'stage4' => 0,
                    'stage5' => 0,
                    'stage5_intime' => 0,
                ];
            if (!empty($shipping_service['status'])) {
                $res[$id]['total']++;
                $stage = Order::getStageNum($shipping_service['status']);
                $res[$id]["stage$stage"]++;
                if ($stage == Order::STAGE_DESPATCHED) {
                    $date_despatched = $time_diff ? Timezones::getDiff($warehouse->getTimezone(), $shipping_service['despatched_date']) : $shipping_service['despatched_date'];
                    if (empty($shipping_service['collection']) || self::inTimeInterval($times, $shipping_service['collection'], $date_despatched))
                        $res[$id]['stage5_intime']++;
                }
            }
        }
        return $res;
    }

    public static function getReportDaily(Warehouse $warehouse, $date_despatch)
    {
        $sql = "
            SELECT ss.id, CONCAT(ss.name, ' (', ss.code, ')') name, ct.collection, o.status, o.despatched_date, o.supplier_id, sup.name supname 
            FROM shipping_services ss
                JOIN shipping_service_warehouses ssw ON ss.id = ssw.shipping_service_id AND ssw.warehouse_id = :warehouse_id
                LEFT JOIN collection_time ct ON ss.id = ct.shipping_service_id AND ssw.warehouse_id = ct.warehouse_id
                LEFT JOIN orders o ON ss.id = o.shipping_services_id AND date_format(o.despatch_date, '%Y-%m-%d') = :date_despatch
                LEFT JOIN suppliers sup ON o.supplier_id = sup.id
            ORDER BY ct.collection, ss.id";
        $shipping_services = (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse->getId(),
            'date_despatch' => addslashes($date_despatch)
        ]);
        if (empty($shipping_services))
            return [[], []];
        $times = array_values(Arr::cleanEmptyVal(array_unique(array_column($shipping_services, 'collection'))));
        $suppliers = Arr::map($shipping_services, 'supplier_id', 'supname');
        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        $res = [];
        foreach ($shipping_services as $shipping_service) {
            $id = $shipping_service['id'];
            if (empty($res[$id])) {
                $res[$id] = ['service' => $shipping_service['name']];
                foreach ($suppliers as $supplier_id => $name)
                    $res[$id][$supplier_id] = [0, 0];
            }
            if (!empty($shipping_service['status'])) {
                $res[$id][$shipping_service['supplier_id']][0]++;
                if (Order::getStageNum($shipping_service['status']) == Order::STAGE_DESPATCHED) {
                    $date_despatched = $time_diff ? Timezones::getDiff($warehouse->getTimezone(), $shipping_service['despatched_date']) : $shipping_service['despatched_date'];
                    if (empty($shipping_service['collection']) || self::inTimeInterval($times, $shipping_service['collection'], $date_despatched))
                        $res[$id][$shipping_service['supplier_id']][1]++;
                }
            }
        }
        foreach ($res as $service_id => $suppliers_data)
            foreach ($suppliers_data as $supplier_id => $qties) {
                if ($supplier_id === 'service')
                    continue;
                if (!$qties[0] && !$qties[1])
                    $res[$service_id][$supplier_id] = 'N/A';
                else
                    $res[$service_id][$supplier_id] = (int)($qties[1] / $qties[0] * 100) . '%';
            }
        $suppliers = ['Service(s)'] + $suppliers;
        return [$suppliers, $res];
    }

    public static function getReportInterval(Warehouse $warehouse, $date_from, $date_to, $supplier_id)
    {
        $where_supplier = '';
        $params = [
            'warehouse_id' => $warehouse->getId(),
            'date_from' => $date_from,
            'date_to' => $date_to
        ];
        if ((int)$supplier_id) {
            $where_supplier = "AND o.supplier_id = :supplier_id";
            $params['supplier_id'] = (int)$supplier_id;
        }
        $sql = "
            SELECT ss.id, CONCAT(ss.name, ' (', ss.code, ')') name, ct.collection, o.status, o.despatch_date, o.despatched_date 
            FROM shipping_services ss
                JOIN shipping_service_warehouses ssw ON ss.id = ssw.shipping_service_id AND ssw.warehouse_id = :warehouse_id
                LEFT JOIN collection_time ct ON ss.id = ct.shipping_service_id AND ssw.warehouse_id = ct.warehouse_id
                LEFT JOIN orders o ON ss.id = o.shipping_services_id AND date_format(o.despatch_date, '%Y-%m-%d') BETWEEN :date_from AND :date_to $where_supplier 
            ORDER BY ct.collection, ss.id";
        $shipping_services = (new SafeMysql())->fetchAll($sql, $params);
        if (empty($shipping_services))
            return [[], []];
        $dates = WithDate::getInterval($date_from, $date_to);
        $times = array_values(Arr::cleanEmptyVal(array_unique(array_column($shipping_services, 'collection'))));
        $time_diff = Timezones::getDiff($warehouse->getTimezone());
        $res = [];
        foreach ($shipping_services as $shipping_service) {
            $id = $shipping_service['id'];
            if (empty($res[$id])) {
                $res[$id] = ['service' => $shipping_service['name']];
                foreach ($dates as $date)
                    $res[$id][$date] = [0, 0];
            }
            if (!empty($shipping_service['status'])) {
                $res[$id][$shipping_service['despatch_date']][0]++;
                if (Order::getStageNum($shipping_service['status']) == Order::STAGE_DESPATCHED) {
                    $date_despatched = $time_diff ? Timezones::getDiff($warehouse->getTimezone(), $shipping_service['despatched_date']) : $shipping_service['despatched_date'];
                    if (empty($shipping_service['collection']) || self::inTimeInterval($times, $shipping_service['collection'], $date_despatched))
                        $res[$id][$shipping_service['despatch_date']][1]++;
                }
            }
        }
        foreach ($res as $service_id => $dates_data)
            foreach ($dates_data as $date => $qties) {
                if ($date === 'service')
                    continue;
                if (!$qties[0] && !$qties[1])
                    $res[$service_id][$date] = 'N/A';
                else
                    $res[$service_id][$date] = (int)($qties[1] / $qties[0] * 100) . '%';
            }
        $dates = ['Service(s)'] + $dates;
        return [$dates, $res];
    }

    public static function uploadLogo($id, $code, $files, &$errors)
    {
        if (empty($files))
            return null;
        if (!$files['logo']['size'])
            return false;
        // the logo is written into a web-served folder: raster images only, name built from a safe code
        // and the real image type, never from the uploaded file name
        if (!File::isRasterImage($files['logo']['tmp_name'])) {
            $errors[] = ['message' => 'Logo must be an image (png, jpg, gif, bmp)'];
            return false;
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/', (string)$code)) {
            $errors[] = ['message' => 'Service code may contain only letters, digits, "_" and "-"'];
            return false;
        }
        $id = (int)$id;
        $ext = File::getImageType($files['logo']['tmp_name']);
        $logo_src = "service_$id" . '_' . "$code.$ext";
        $path = ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR;
        $original_path = $path . $logo_src;
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($path)))->handleDir($path);
        $file_wrapper->writeFilePath($original_path, $files['logo']['tmp_name']);
        $temp = tmpfile();
        $logo_name = "service_$id" . '_' . "$code.png";
        $converted_path = $path . $logo_name;
        if (!File::convert2PNG($files['logo']['tmp_name'], $temp, $ext))
            return false;
        $tmp_file = stream_get_meta_data($temp)['uri'];
        if (filesize($tmp_file))
            $file_wrapper->writeFileResource($converted_path, $temp);
        else
            $file_wrapper->writeFilePath($converted_path, $files['logo']['tmp_name']);
        return $logo_name;
    }

    public static function changingData(ShippingService $old_shipping_service, ShippingService $new_shipping_service)
    {
        $new_data = [];
        if ($old_shipping_service->getName() !== $new_shipping_service->getName())
            $new_data[] = 'name';
        if ($old_shipping_service->getLogo() !== $new_shipping_service->getLogo())
            $new_data[] = 'logo';
        if ($old_shipping_service->getIndiciaImage() !== $new_shipping_service->getIndiciaImage())
            $new_data[] = 'indicia_image';
        if ($old_shipping_service->getCode() !== $new_shipping_service->getCode())
            $new_data[] = 'code';
        if ($old_shipping_service->getMaxWeight() !== $new_shipping_service->getMaxWeight())
            $new_data[] = 'max_weight';
        if ($old_shipping_service->getNextDay() !== $new_shipping_service->getNextDay())
            $new_data[] = 'next_day';
        if ($old_shipping_service->getIsUntracked() !== $new_shipping_service->getIsUntracked())
            $new_data[] = 'is_untracked';
        if ($old_shipping_service->getSpecial() !== $new_shipping_service->getSpecial())
            $new_data[] = 'special';
        if ($old_shipping_service->getCarrierId() !== $new_shipping_service->getCarrierId())
            $new_data[] = 'carrier_id';
        if (!empty($new_data))
            $new_data[] = 'id';
        return $new_data;
    }

    public static function addShippingService($shipping_service_data, $files)
    {
        /** @var Carrier $carrier */
        $carrier = Carriers::getById($shipping_service_data['carrier_id']);
        if (empty($carrier))
            return ['error', ['Carrier is not found']];
        $errors = [];
        if (($logo_name = self::uploadLogo($carrier->getId(), $shipping_service_data['code'], $files, $errors)) === false)
            return ['error', array_column($errors, 'message') ?: ['File is not found']];
        $data = [
            'name' => $shipping_service_data['name'],
            'code' => $shipping_service_data['code'],
            'carrier_id' => $carrier->getId(),
            'is_untracked' => $shipping_service_data['is_untracked'],
            'max_weight' => $shipping_service_data['max_weight'],
            'next_day' => $shipping_service_data['next_day'],
        ];
        if ($carrier->getIsSpecial())
            $data['special'] = $shipping_service_data['special'];
        if (!is_null($logo_name)) {
            $data['logo'] = $logo_name;
            if ($carrier->getIsInternal()) {
                $label = File::createImageZpl(ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR . $logo_name, ShippingInternal::LABEL_WIDTH_IMAGE, ShippingInternal::PERCENTAGE_MONOCHROME);
                if (!$label)
                    return ['error', ['Indicia image has wrong format']];
                $data['indicia_image'] = $label;
            }
        }
        $shipping_service = new ShippingService($data);
        $errors = array_merge($errors, self::validate($shipping_service, $carrier->getMaxWeight()));
        if (!empty($errors))
            return ['error', array_map(function ($e) { return $e['message']; }, $errors)];
        $shipping_service->save(array_keys($data));
        View::addFlash('shipping_service_saved', 'success', "Shipping Service {$shipping_service->getName()} ({$shipping_service->getCode()}) was created");
        return ['success', []];
    }

    public static function changeShippingService($shipping_service_data, $files)
    {
        $old_shipping_service = self::getById($shipping_service_data['id']);
        if (empty($old_shipping_service))
            return ['error', ['Shipping service is not found']];
        /** @var Carrier $carrier */
        $carrier = Carriers::getById($shipping_service_data['carrier_id']);
        if (empty($carrier))
            return ['error', ['Carrier is not found']];
        $data = [
            'id' => $shipping_service_data['id'],
            'name' => $shipping_service_data['name'],
            'code' => $shipping_service_data['code'],
            'carrier_id' => $carrier->getId(),
            'is_untracked' => (int)$shipping_service_data['is_untracked'],
            'max_weight' => $shipping_service_data['max_weight'],
            'next_day' => $shipping_service_data['next_day'],
        ];
        if ($carrier->getIsSpecial())
            $data['special'] = $shipping_service_data['special'];
        $errors = [];
        if ($shipping_service_data['change']) {
            if (($logo_name = self::uploadLogo($carrier->getId(), $shipping_service_data['code'], $files, $errors)) === false)
                return ['error', array_column($errors, 'message') ?: ['File is not found']];
            if (!is_null($logo_name)) {
                $data['logo'] = $logo_name;
                if ($carrier->getIsInternal()) {
                    $label = File::createImageZpl(ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR . $logo_name, ShippingInternal::LABEL_WIDTH_IMAGE, ShippingInternal::PERCENTAGE_MONOCHROME);
                    if (!$label)
                        return ['error', ['Indicia image has wrong format']];
                    $data['indicia_image'] = $label;
                }
            }
        } else {
            $data['logo'] = $old_shipping_service->getLogo();
            $data['indicia_image'] = $old_shipping_service->getIndiciaImage();
        }
        $shipping_service = new ShippingService($data);
        $errors = array_merge($errors, self::validate($shipping_service, $carrier->getMaxWeight()));
        if (!empty($errors))
            return ['error', array_map(function ($e) { return $e['message']; }, $errors)];
        $shipping_service_change = self::changingData($old_shipping_service, $shipping_service);
        if (empty($shipping_service_change) && !$shipping_service_data['change'])
            View::addFlash('shipping_service', 'warning', 'Nothing to change');
        else {
            $shipping_service->save($shipping_service_change);
            View::addFlash('shipping_service_saved', 'success', "Shipping Service {$shipping_service->getName()} ({$shipping_service->getCode()}) was updated");
        }
        return ['success', []];
    }

    public static function getSpecialServices(int $warehouse_id, int $is_special)
    {
        if (!in_array($is_special, array_keys(ShippingService::SPECIAL_NAMES)))
            return [];
        $sql = "
            SELECT ss.id, c.id c_id
            FROM shipping_services ss 
                JOIN carriers c ON ss.carrier_id = c.id AND c.is_special
                JOIN logistic_company_carriers lcc ON c.id = lcc.carrier_id
                JOIN warehouse_carriers wc ON lcc.id = wc.lc_carrier_id AND wc.warehouse_id = :warehouse_id
            WHERE ss.special = :is_special";
        return (new SafeMysql())->fetchAll($sql, ['warehouse_id' => $warehouse_id, 'is_special' => $is_special]);
    }

    public static function getIsSpecial(int $warehouse_id, int $is_special)
    {
        if (!in_array($is_special, array_keys(ShippingService::SPECIAL_NAMES)))
            return ['', []];
        if (!$is_special) {
            $carrier_ids = Carriers::getSpecials($warehouse_id);
            if (!$carrier_ids)
                return ['', []];
            return ['AND o.carrier_id NOT IN (:carriers)', ['carriers' => $carrier_ids]];
        }
        $specials = ShippingServices::getSpecialServices($warehouse_id, $is_special);
        if (!$specials)
            return ['', []];
        $shipping_service_ids = array_unique(array_column($specials, 'id'));
        $carrier_ids = array_unique(array_column($specials, 'c_id'));
        return ['AND o.carrier_id IN (:carriers) AND o.shipping_services_id IN (:services)', [
            'carriers' => $carrier_ids,
            'services' => $shipping_service_ids,
        ]];
    }
}