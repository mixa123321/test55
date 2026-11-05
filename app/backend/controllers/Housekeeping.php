<?php

namespace app\backend\controllers;

use app\backend\models\BarCodes;
use app\backend\models\Housekeeping as HousekeepingModel;
use app\backend\models\Locations;
use app\backend\models\SKUHistories;
use app\backend\models\SKULocations;
use app\backend\models\SKUPreffLocs;
use app\backend\models\SKUs;
use app\backend\models\Suppliers;
use app\backend\models\Sizes;
use app\backend\models\Batches as BatchesModel;
use app\backend\models\SKUBarcodes;
use app\core\entities\Location;
use app\core\entities\SKUPreffLoc;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\FPDFExtended;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\BarCode128;
use app\core\models\InventoryLogs;
use app\core\models\Warehouses;
use app\core\statical\WithDate;
use FPDF;
use app\core\dbal\SafeMysql;
use app\core\helpers\Debug;

class Housekeeping extends AuthController
{
    /** @throws RedirectException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->mobile = true;
    }

    public function inventory()
    {
        $search = addslashes($_GET['s'] ?? '');
        $current = (int)($_GET['c'] ?? 1);
        $sid = (int)($_GET['sid'] ?? 0);
        $measure = (int)($_GET['measure'] ?? 0);
        $status = (int)($_GET['status'] ?? 0);
        $unassigned = (int)!empty($_GET['unassigned'] ?? 0);
        $present_in_orders = (int)!empty($_GET['present_in_orders'] ?? 0);
        $suppliers = Arr::map(Suppliers::getSuppliers4SKUs($this->warehouse, $this->user), 'id', 'name');
        if (empty($sid))
            $sid = array_keys($suppliers);
        elseif (empty($suppliers[$sid])) {
            View::addFlash('supplier', 'danger', "Supplier isn't found");
            $sid = array_keys($suppliers);
        }
        $warehouses_virtual = $this->warehouse_virtual ? array_keys(Warehouses::getLocations($this->warehouse_id)) : [];
        list($SKUs, $size) = SKUs::getSKUs($this->warehouse_id, $current, $search, $sid, $measure, $status, $unassigned, $present_in_orders, $warehouses_virtual);
        $batches = $is_batches = [];
        $all_sizes = Arr::map(Sizes::getAllSizes($this->warehouse_id), 'id', 'name');
        if (count($SKUs)) {
            $ids = Arr::column($SKUs, 'id', false);
            $places = SKUPreffLocs::getPlaces($this->warehouse_id, $ids);
            $sizes = SKUs::getSizes($ids);
            $is_batches = BatchesModel::isBatches($ids);
            $batches = SKULocations::getBatches($this->warehouse_id, $ids, false, [Location::DH]);
            $capacity = [];
            foreach ($SKUs as $key => $SKU) {
                $tmp = json_decode($sizes[$SKU['id']]['max_capacity'], true);
                if (!empty($tmp)) {
                    foreach ($tmp as $key_cap => $val)
                        if (empty($all_sizes[$key_cap]))
                            unset($tmp[$key_cap]);
                    $capacity[$SKU['id']] = Arr::cleanEmptyVal($tmp);
                } else
                    $capacity[$SKU['id']] = [];
                $SKUs[$key]['capacity'] = !empty($capacity[$SKU['id']]);
            }
            $loc_sizes = [];
            foreach ($places as $key => $place) {
                $SKU_id = $place['SKU_id'];
                if (empty($loc_sizes[$SKU_id][$place['type_id']]))
                    $loc_sizes[$SKU_id][$place['type_id']] = 0;
                $loc_sizes[$SKU_id][$place['type_id']] += ((count($capacity[$SKU_id]) > 0 && !empty($capacity[$SKU_id][$place['size_id']]))
                    ? $capacity[$SKU_id][$place['size_id']] * $place['nums'] : 0);
            }
            foreach ($SKUs as $key => $SKU) {
                $SKUs[$key]['PF_last'] = empty($loc_sizes[$SKU['id']][Location::PF]) ? '-' : $loc_sizes[$SKU['id']][Location::PF];
                $SKUs[$key]['ST_last'] = empty($loc_sizes[$SKU['id']][Location::ST]) ? '-' : $loc_sizes[$SKU['id']][Location::ST];
            }

            $SKU_ids_to_q_min = SKULocations::getQuantitySKUsForBundles($ids, $this->warehouse_id);
            foreach ($SKUs as &$SKU) {
                if (!isset($SKU_ids_to_q_min['SKU_ids_to_q_min'][$SKU['id']]))
                    continue;
                $SKU['bundle_available'] = $SKU_ids_to_q_min['SKU_ids_to_q_min'][$SKU['id']];
            }
            unset($SKU);
            foreach (array_merge($SKU_ids_to_q_min['errors_type_2'], $SKU_ids_to_q_min['errors_type_3']) as $message)
                Debug::sendDebugInfo('Inventory', $message, Debug::TEST);
        }
        $data = [
            'SKUs' => $SKUs,
            'batches' => $batches,
            'is_batches' => $is_batches,
            'search' => $search,
            'current' => $current,
            'suppliers' => [0 => 'all'] + $suppliers,
            'sid' => is_array($sid) ? 0 : $sid,
            'measure' => $measure,
            'status' => $status,
            'unassigned' => $unassigned,
            'present_in_orders' => $present_in_orders,
            'size' => $size,
            'pages' => (int)($size / RECORD_PAGE) + ($size % RECORD_PAGE > 0),
            'warehouses_virtual' => $warehouses_virtual,
        ];
        $this->render($data, 'inventory/inventory');
    }

    public function skuLocations()
    {
        if (Request::isAjax()) {
            $type = empty($_GET['type']) ? 0 : $_GET['type'];
            if (empty($type))
                return $this->jsonResponse(['error' => 'Unknown type']);
            $zone = empty($_GET['zone']) ? null : $_GET['zone'];
            $column = empty($_GET['column']) ? null : $_GET['column'];
            $SKU_id = empty($_GET['SKU_id']) ? null : $_GET['SKU_id'];
            $booked = empty($_GET['booked']) ? false : (bool)$_GET['booked'];
            $scan = empty($_GET['scan']) ? null : $_GET['scan'];
            $SKU = SKUs::getById($SKU_id);
            if (empty($SKU))
                return $this->jsonResponse(['error' => 'SKU not found']);
            if (!empty($scan)) {
                $location = Locations::getByScan($this->warehouse_id, $scan);
                if (empty($location) || $location->getTypeId() != $type)
                    return $this->jsonResponse(['error' => 'Location not found']);
                if (SKULocations::isReserve($location->getId()))
                    return $this->jsonResponse(['error' => 'Location is reserved']);
                $zone = $location->getZone();
                $column = $location->getColumn();
            }
            if (empty($zone) || empty($column))
                return $this->jsonResponse(['error' => 'Zone and column are required']);
            return $this->jsonResponse([
                'locations' => HousekeepingModel::searchLocations($this->warehouse_id, $SKU, $type, $zone, $column, $booked),
                'zone' => $zone,
                'column' => $column,
            ]);
        }

        $this->disableWarehouseChange();
        $search = '';
        if (isset($_POST['search']))
            $search = htmlentities($_POST['search']);
        elseif (isset($_GET['s']))
            $search = $_GET['s'];
        $current = isset($_GET['c']) ? (int)$_GET['c'] : 1;
        $sid = empty($_GET['sid']) ? '' : $_GET['sid'];
        $measure = empty($_GET['measure']) ? '' : $_GET['measure'];
        $status = empty($_GET['status']) ? '' : $_GET['status'];
        $unassigned = empty($_GET['unassigned']) ? '' : $_GET['unassigned'];
        $present_in_orders = empty($_GET['present_in_orders']) ? '' : $_GET['present_in_orders'];
        $back = "{$_GET['back']}/?s=".urlencode($search)."&c=$current&sid=$sid&measure=$measure&status=$status&unassigned=$unassigned&present_in_orders=$present_in_orders";
        $id = Request::getPositiveId();
        $SKU = SKUs::getById($id);
        if (empty($SKU)) {
            View::addFlash('SKU', 'danger', "SKU isn't found");
            throw new RedirectException($back);
        }
        $locations = SKULocations::getAllLocations($this->warehouse_id, $id);
        $preff_locs = [];
        $zones = [];
        $booked_zones = [];
        $types = [
            Location::PF => 'Pick Face',
            Location::ST => 'Storage',
        ];
        foreach (array_keys($types) as $loc_type)
            list($preff_locs[$loc_type], $zones[$loc_type], $booked_zones[$loc_type]) = HousekeepingModel::getLocationZonesAndColumns($this->warehouse_id, $SKU, $loc_type);
        $batches = SKULocations::getBatches($this->warehouse_id, [$id], true, [Location::DH]);
        $batches_locations = [];
        if (!empty($batches[$id]))
            foreach ($batches[$id] as $batch) {
                if (empty($batches_locations[$batch['location_id']]))
                    $batches_locations[$batch['location_id']] = [];
                $batches_locations[$batch['location_id']][] = $batch;
            }
        $data = [
            'SKU' => $SKU,
            'types' => $types,
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()),
            'back' => $back,
            'locations' => $locations,
            'batches' => $batches_locations,
            'preff_locs' => $preff_locs,
            'zones' => $zones,
            'booked_zones' => $booked_zones,
        ];
        $this->render($data, 'inventory/sku_locations');
    }

    /** @throws */
    public function batches()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $_GET['back'] = str_replace('^', '&', str_replace('^^', '?', $_GET['back']));
        $back = $_GET['back'];
        if (strpos($_GET['back'], '?') === false) {
            if (isset($_GET['c']))
                $current = (int)$_GET['c'];
            else
                $current = 1;
            $search = '';
            if (isset($_POST['search']))
                $search = htmlentities($_POST['search']);
            elseif (isset($_GET['s']))
                $search = $_GET['s'];
            $sid = empty($_GET['sid']) ? '' : $_GET['sid'];
            $measure = empty($_GET['measure']) ? '' : $_GET['measure'];
            $status = empty($_GET['status']) ? '' : $_GET['status'];
            $unassigned = empty($_GET['unassigned']) ? '' : $_GET['unassigned'];
            $present_in_orders = empty($_GET['present_in_orders']) ? '' : $_GET['present_in_orders'];
            $back .= "/?s=" . urlencode($search) . "&c=$current&sid=$sid&measure=$measure&status=$status&unassigned=$unassigned&present_in_orders=$present_in_orders";
        }
        $SKU = SKUs::getById($id);
        if (empty($SKU)) {
            View::addFlash('Batches', 'danger', "SKU isn't found");
            throw new RedirectException($back);
        }
        $batches = BatchesModel::getBatchesBySKU($id);
        if (empty($batches)) {
            View::addFlash('Batches', 'danger', "Batches aren't found for this warehouse");
            throw new RedirectException($back);
        }
        $data = [
            'batches' => $batches,
            'back' => $back,
            'SKU' => $SKU,
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()),
        ];
        $this->render($data, 'inventory/batches');
    }

    /** @throws */
    public function deleteBatch()
    {
        $batch_id = empty($_POST['batch_id']) ? 0 : (int)$_POST['batch_id'];
        $SKU_id = empty($_POST['SKU_id']) ? 0 : (int)$_POST['SKU_id'];
        $back = empty($_POST['back']) ? '' : addslashes($_POST['back']);
        $back_url = "/housekeeping/batches/?id=$SKU_id&back=" . str_replace('?', '^^', str_replace('&', '^', $back));
        if (empty($batch_id) || empty($SKU_id) || empty($back)) {
            View::addFlash('Batch', 'danger', 'Invalid information');
            throw new RedirectException($back);
        }
        if (empty(BatchesModel::getById($batch_id))) {
            View::addFlash('Batch', 'danger', "Batch isn't found");
            throw new RedirectException($back_url);
        }
        HousekeepingModel::deleteBatch($SKU_id, $batch_id, $this->warehouse_id);
        throw new RedirectException($back_url);
    }

    public function saveBatch()
    {
        if (empty($_POST) || empty($_POST['batch_id']) || empty($_POST['number']) || empty($_POST['expired']))
            return $this->response = ['error' => 'Invalid information'];
        $batches = BatchesModel::getById($_POST['batch_id']);
        if (empty($batches))
            return $this->response = ['error' => 'Invalid information'];
        return $this->response = HousekeepingModel::saveBatch($batches->getSKUId(), $_POST['batch_id'], $_POST['number'], $_POST['expired'], $this->warehouse, $this->user->getId());
    }

    /** @throws */
    public function assignment()
    {
        $id = Request::getPositiveId($_POST);
        $back = empty($_POST['back']) ? '' : $_POST['back'];
        if (empty($id) || empty($back)) {
            View::addFlash('housekeeping', 'danger', 'Invalid information');
            throw new RedirectException($back);
        }
        $PF_preff = !empty($_POST['PF_preff']) ? $_POST['PF_preff'] : [];
        $ST_preff = !empty($_POST['ST_preff']) ? $_POST['ST_preff'] : [];
        $PF_name = !empty($_POST['PF_name']) ? $_POST['PF_name'] : [];
        $ST_name = !empty($_POST['ST_name']) ? $_POST['ST_name'] : [];
        $error_PF = HousekeepingModel::checkLocations($PF_preff);
        $error_ST = HousekeepingModel::checkLocations($ST_preff, Location::ST);
        if ($error_PF !== false || $error_ST !== false) {
            $errors = [];
            if ($error_PF !== false)
                $errors = array_merge($errors, $error_PF);
            if ($error_ST !== false)
                $errors = array_merge($errors, $error_ST);
            View::addFlash('housekeeping', 'danger', $errors);
            $data = $this->getDataForRender($id, $back, $PF_preff, $ST_preff);
            $this->render($data, 'inventory/sku_locations');
            return;
        }
        $PF_objects = [];
        foreach ($PF_preff as $loc_id => $prio)
            $PF_objects[$loc_id] = (new SKUPreffLoc())->setSKUId($id)->setLocationId((int)$loc_id)->setPriority((int)$prio);
        $ST_objects = [];
        foreach ($ST_preff as $loc_id => $prio)
            $ST_objects[$loc_id] = (new SKUPreffLoc())->setSKUId($id)->setLocationId((int)$loc_id)->setPriority((int)$prio);
        $PF_old_objects = SKUPreffLocs::getPreffLocsObjects((int)$this->warehouse_id, $id, Location::PF);
        $ST_old_objects = SKUPreffLocs::getPreffLocsObjects((int)$this->warehouse_id, $id, Location::ST);
        /** @var SKUPreffLoc[] $PF_objects_insert */
        $PF_objects_insert = array_diff_key($PF_objects, $PF_old_objects);
        /** @var SKUPreffLoc[] $PF_objects_delete */
        $PF_objects_delete = array_diff_key($PF_old_objects, $PF_objects);
        /** @var SKUPreffLoc[] $PF_objects_update */
        $PF_objects_update = array_uintersect($PF_objects, $PF_old_objects, function (SKUPreffLoc $a, SKUPreffLoc $b): int {
            return $a->getLocationId() === $b->getLocationId() ? (int)($a->getPriority() === $b->getPriority()) : -1;
        });
        /** @var SKUPreffLoc[] $ST_objects_insert */
        $ST_objects_insert = array_diff_key($ST_objects, $ST_old_objects);
        /** @var SKUPreffLoc[] $ST_objects_delete */
        $ST_objects_delete = array_diff_key($ST_old_objects, $ST_objects);
        /** @var SKUPreffLoc[] $ST_objects_update */
        $ST_objects_update = array_uintersect($ST_objects, $ST_old_objects, function (SKUPreffLoc $a, SKUPreffLoc $b): int {
            return $a->getLocationId() === $b->getLocationId() ? (int)($a->getPriority() === $b->getPriority()) : -1;
        });
        $service_locations = Locations::getConstantLocationIds($this->warehouse_id);
        $ids_for_insert = array_merge(array_keys($PF_objects_insert), array_keys($ST_objects_insert));
        if ($error_bind_ids = array_intersect($ids_for_insert, $service_locations)) {
            $errors[] = 'Trying to bind to service location ' . Locations::getById(array_shift($error_bind_ids))->getLocation($this->warehouse_label);
            View::addFlash('housekeeping', 'danger', $errors);
            $data = $this->getDataForRender($id, $back, $PF_preff, $ST_preff);
            $this->render($data, 'inventory/sku_locations');
            return;
        }
        $del_loc_ids = array_merge(array_keys($PF_objects_delete), array_keys($ST_objects_delete));
        $del_locs = Locations::getByIdsLocations($del_loc_ids);
        $forbidden_to_delete = $changes = $successes = $warnings = $dangers = [];
        foreach ($del_locs as $loc)
            if (SKULocations::getQuantityInLocationBySKUId((int)$loc->getId(), (int)$id))
                $forbidden_to_delete[$loc->getId()] = $loc;
        if ($forbidden_to_delete) {
            foreach ($forbidden_to_delete as $loc) {
                $dangers[] = "Location {$loc->getLocation($this->warehouse_label)} cannot be deleted";
                if (isset($PF_objects_delete[$loc->getId()]))
                    $PF_preff[$loc->getId()] = $PF_objects_delete[$loc->getId()]->getPriority();
                elseif ($ST_objects_delete[$loc->getId()])
                    $ST_preff[$loc->getId()] = $ST_objects_delete[$loc->getId()]->getPriority();
            }
            View::addFlash('housekeeping', 'danger', $dangers);
            $data = $this->getDataForRender($id, $back, $PF_preff, $ST_preff);
            $this->render($data, 'inventory/sku_locations');
            return;
        }
        $PF_objects_delete = array_diff_key($PF_objects_delete, $forbidden_to_delete);
        $ST_objects_delete = array_diff_key($ST_objects_delete, $forbidden_to_delete);
        if (!$PF_objects_insert && !$ST_objects_insert && !$PF_objects_update && !$ST_objects_update && !$PF_objects_delete && !$ST_objects_delete) {
            $warnings[] = 'Nothing to change';
            $this->messagesWithRedirect($successes, $warnings, $dangers, $back);
        }
        if (Location::hasUniqueSKU(Location::PF)) {
            $locs_must_be_unique = array_keys($PF_objects);
            if ($other_SKUs = SKUPreffLocs::getOtherSKUsByIds($id, $locs_must_be_unique)) {
                foreach ($other_SKUs as $other_SKU)
                    $dangers[] = "Location " . Locations::getById($other_SKU['location_id'])->getLocation($this->warehouse_label) . " used in other SKU " . $other_SKU['barcode'];
                View::addFlash('housekeeping', 'danger', $dangers);
                $data = $this->getDataForRender($id, $back, $PF_preff, $ST_preff);
                $this->render($data, 'inventory/sku_locations');
                return;
            }
        }
        // check if booked
        $SKU = SKUs::getById($id);
        $booked_ids = SKULocations::getBookedLocationsBySomeOneElse($ids_for_insert, $this->warehouse_id, $SKU->getSupplierId());
        if (!empty($booked_ids)) {
            foreach (Locations::getByIds($booked_ids) as $loc) {
                $dangers[] = "Location {$loc['name']} is booked by another company";
            }
            View::addFlash('housekeeping', 'danger', $dangers);
            $data = $this->getDataForRender($id, $back, $PF_preff, $ST_preff);
            $this->render($data, 'inventory/sku_locations');
            return;
        }
        foreach ($PF_objects_insert as $location_id => $object) {
            $successes[] = "Location #{$PF_name[$location_id]} was inserted";
            $changes['inserted'][] = $location_id;
        }
        foreach ($ST_objects_insert as $location_id => $object) {
            $successes[] = "Location #{$ST_name[$location_id]} was inserted";
            $changes['inserted'][] = $location_id;
        }
        foreach ($PF_objects_update as $location_id => $object) {
            $warnings[] = "Location #{$PF_name[$location_id]} was updated";
            $changes['updated'][$location_id] = [$PF_old_objects[$location_id]->getPriority(), $object->getPriority()];
        }
        foreach ($ST_objects_update as $location_id => $object) {
            $warnings[] = "Location #{$ST_name[$location_id]} was updated";
            $changes['updated'][$location_id] = [$ST_old_objects[$location_id]->getPriority(), $object->getPriority()];
        }
        foreach ($PF_objects_delete as $location_id => $object)
            if (empty($del_locs[$location_id]))
                $dangers[] = "Location #$location_id was not found";
            else {
                $warnings[] = "Location {$del_locs[$location_id]->getLocation($this->warehouse_label)} was deleted";
                $changes['deleted'][] = $del_locs[$location_id]->getId();
            }
        foreach ($ST_objects_delete as $location_id => $object)
            if (empty($del_locs[$location_id]))
                $dangers[] = "Location #$location_id was not found";
            else {
                $warnings[] = "Location {$del_locs[$location_id]->getLocation($this->warehouse_label)} was deleted";
                $changes['deleted'][] = $del_locs[$location_id]->getId();
            }
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        SKUPreffLocs::deleteByIds($id, array_merge(array_keys($PF_objects_delete), array_keys($ST_objects_delete)));
        SKUPreffLocs::bulkInsertUpdate(array_merge($PF_objects, $ST_objects));
        SKUHistories::updateLocations($id, $this->user->getId(), $changes, $this->warehouse_id);
        $mysql->commit();
        $this->messagesWithRedirect($successes, $warnings, $dangers, $back);
    }

    private function getDataForRender(int $id, string $back, array $preff_PF, array $preff_ST): array
    {
        $SKU = SKUs::getById($id);
        $locations = SKULocations::getAllLocations($this->warehouse_id, $id);
        $preff_locs = [];
        $zones = [];
        $booked_zones = [];
        $types = [
            Location::PF => 'Pick Face',
            Location::ST => 'Storage',
        ];
        foreach (array_keys($types) as $loc_type) {
            list($preff_locs[$loc_type], $zones[$loc_type], $booked_zones[$loc_type]) = HousekeepingModel::getLocationZonesAndColumns($this->warehouse_id, $SKU, $loc_type);
            // update priorites from request
            switch ($loc_type) {
                case Location::PF:
                    foreach ($preff_locs[$loc_type] as &$loc)
                        if (isset($preff_PF[$loc['location_id']])) {
                            $loc['priority'] = $preff_PF[$loc['location_id']];
                            unset($preff_PF[$loc['location_id']]);
                        }
                    break;
                case Location::ST:
                    foreach ($preff_locs[$loc_type] as &$loc)
                        if (isset($preff_ST[$loc['location_id']])) {
                            $loc['priority'] = $preff_ST[$loc['location_id']];
                            unset($preff_ST[$loc['location_id']]);
                        }
                    break;
            }
        }
        // fill new priorities
        if (!empty($preff_PF) || !empty($preff_ST)) {
            $locs = Locations::getByIdsLocations(array_merge(array_keys($preff_PF), array_keys($preff_ST)));
            foreach ($preff_PF as $loc_id => $prio) {
                if (empty($locs[$loc_id]))
                    continue;
                $preff_locs[Location::PF][] = [
                    'location_id' => $loc_id,
                    'name' => $locs[$loc_id]->getLocation($this->warehouse_label),
                    'alias' => $locs[$loc_id]->getAlias(),
                    'nums' => 0,
                    'priority' => (int)$prio,
                ];
            }
            foreach ($preff_ST as $loc_id => $prio) {
                if (empty($locs[$loc_id]))
                    continue;
                $preff_locs[Location::ST][] = [
                    'location_id' => $loc_id,
                    'name' => $locs[$loc_id]->getLocation($this->warehouse_label),
                    'nums' => 0,
                    'priority' => (int)$prio,
                ];
            }
        }
        $batches = SKULocations::getBatches($this->warehouse_id, [$id], true, [Location::DH]);
        $batches_locations = [];
        if (!empty($batches[$id]))
            foreach ($batches[$id] as $batch) {
                if (empty($batches_locations[$batch['location_id']]))
                    $batches_locations[$batch['location_id']] = [];
                $batches_locations[$batch['location_id']][] = $batch;
            }
        return [
            'SKU' => $SKU,
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()),
            'back' => $back,
            'locations' => $locations,
            'batches' => $batches_locations,
            'types' => $types,
            'preff_locs' => $preff_locs,
            'zones' => $zones,
            'booked_zones' => $booked_zones,
        ];
    }

    /** @throws */
    private function messagesWithRedirect($successes, $warnings, $dangers, $back)
    {
        if (!empty($successes))
            View::addFlash('housekeeping', 'success', $successes);
        if (!empty($warnings))
            View::addFlash('housekeeping', 'warning', $warnings);
        if (!empty($dangers))
            View::addFlash('housekeeping', 'danger', $dangers);
        throw new RedirectException($back);
    }

    /** @throws */
    public function labelPrint()
    {
        $id = Request::getPositiveId();
        $barcode = $_GET['barcode'] ?? null;
        if (!$id || !$barcode) {
            View::addFlash('Inventory', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $SKU = SKUs::getById($id);
        $size = empty($_GET['size']) ? 3 : $_GET['size'];
        $qty = empty($_GET['qty']) ? 3 : $_GET['qty'];
        require (ROOT_PATH . VENDOR_PATH . '/FPDF/fpdf.php');
        $pdf = new FPDFExtended('P', 'pt', 'A4');
        $pdf->SetTitle("SKU");
        $pdf->SetFont('Courier', '', 13 - $size);
        $pdf->AddPage();
        $W = (int)(($pdf->GetPageWidth() - 55)/$size);
        $info = $SKU->getName();
        $barcode = new BarCode128($barcode,75, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf', 8);
        $H1 = $W * $barcode->Height() / $barcode->Width();
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $H0 = 0;
        $nums = 0;
        do {
            if ($y + $H0 + $H1 > ($pdf->GetPageHeight() - 30)) {
                $pdf->AddPage();
                $y = $pdf->GetY();
            }
            for ($i = 0; $i < $size; $i++) {
                $H0 = HousekeepingModel::writeSKU($pdf, $info, $W - 10, 13 - $size, $x + $i*$W, $y);
                $pdf->Image($barcode->getImage(), $x + $i*$W, $y + $H0, $W - 5, 0, 'PNG');
                $nums++;
                if ($nums >= $qty)
                    break;
            }
            $y += $H0 + $H1;
        } while ($nums < $qty);
        $pdf->Output();
    }

    /////////////////////////////////////////////

    public function palletMerge()
    {
        $this->mobile = true;
        $search = '';
        if (isset($_GET['s']))
            $search = htmlentities($_GET['s']);
        $data = [
            'search' => $search,
        ];
        $this->render($data, 'inventory/pallet_merge');
    }

    /** @throws RedirectException */
    public function mergeSKUs()
    {
        $this->mobile = true;
        $this->disableWarehouseChange();
        if (!empty($_POST))
            $loc = $_POST['search'];
        else
            $loc = $_GET['search'];
        $back = "/housekeeping/pallet_merge/?s=$loc";
        if (empty($loc)) {
            View::addFlash('PalletMerge', 'danger', "Location '$loc' invalid");
            throw new RedirectException($back);
        }
        $l = Locations::getByScan($this->warehouse_id, htmlentities($loc));
        if (is_null($l)) {
            View::addFlash('PalletMerge', 'danger', "Location '$loc' not found");
            throw new RedirectException($back);
        }
        if ($l->getWarehouse() != $this->warehouse_id || !$l->ST() || !$l->isActive()) {
            View::addFlash('PalletMerge', 'danger', "Location '$loc' invalid");
            throw new RedirectException($back);
        }
        if (Locations::isExpiredLocation($l) || Locations::isCancelLocation($l) || $l->isLocked()) {
            View::addFlash('PalletMerge', 'danger', "Location '$loc' is denied to use or locked");
            throw new RedirectException($back);
        }
        $SKUs = SKULocations::getSKUsFromLocation($l->getId());
        if (count($SKUs) == 0) {
            View::addFlash('PalletMerge', 'danger', "Location '$loc' is empty");
            throw new RedirectException($back);
        }
        $data = [
            'location' => $l,
            'SKUs' => $SKUs,
            'SKU_info' => json_encode(array_map(function($item) { return ['SKU_id' => $item['SKU_id'], 'supplier_id' => $item['id'], 'barcodes' => $item['barcodes']]; }, $SKUs)),
            'SKU_qty' => json_encode(array_map(function($item) { return [$item['SKU_id'], $item['summa']]; }, $SKUs)),
        ];
        $this->render($data, 'inventory/pallet_merge_SKUs');
    }

    public function checkDestination()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $res = HousekeepingModel::prepareDestination($_POST, $this->warehouse_id, $this->warehouse_label);
        if (!empty($res['error']))
            return $this->response = ['error' => $res['error']];
        return $this->response = ['success' => true];
    }

    public function completeDestination()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $res = HousekeepingModel::prepareDestination($_POST, $this->warehouse_id, $this->warehouse_label);
        if (!empty($res['error']))
            return $this->response = $res;
        return $this->response = HousekeepingModel::completeDestination((int)$_POST['loc_from_id'], $res['success'][0], $res['success'][1], $res['success'][2], $this->user, $this->warehouse_id);
    }

    /** @throws */
    public function completedDestination()
    {
        View::addFlash('PalletMerge', 'success', 'Pallet merge is completed');
        throw new RedirectException('/housekeeping/pallet_merge');
    }

    ///////////////////////////////////////////

    public function counting()
    {
        $search = '';
        if (isset($_GET['s']))
            $search = htmlentities($_GET['s']);
        $data = [
            'search' => $search,
        ];
        $this->render($data, 'inventory/counting');
    }

    /** @throws */
    public function SKUs()
    {
        $this->disableWarehouseChange();
        $loc = !empty($_POST) ? $_POST['search'] : $_GET['search'];
        if (empty($loc)) {
            View::addFlash('Reconciliation', 'danger', "Location '$loc' invalid");
            throw new RedirectException("/housekeeping/counting/?s=$loc");
        }
        $l = Locations::getByScan($this->warehouse_id, htmlentities(trim($loc)));
        if (is_null($l)) {
            View::addFlash('Reconciliation', 'danger', "Location '$loc' not found");
            throw new RedirectException("/housekeeping/counting/?s=$loc");
        }
        if ($l->DH()) {
            View::addFlash('Reconciliation', 'danger', 'Discrepancy Hold locations not supported. You can use SKUs interface');
            throw new RedirectException('/housekeeping/counting');
        }
        $count = !SKULocations::isReserve($l->getId());
        $SKUs = SKULocations::getSKUsFromLocation($l->getId());
        if (count($SKUs) == 0) {
            View::addFlash('Reconciliation', 'danger', "Location '$loc' is empty");
            throw new RedirectException("/housekeeping/counting/");
        }
        foreach ($SKUs as $key => $SKU)
            $SKUs[$key]['real'] = SKULocations::getQuantity($SKU['SKU_id'], $l->getId(), $SKU['batch_id']);
        $data = [
            'location' => $l,
            'count' => $count,
            'SKUs' => $SKUs,
        ];
        $this->render($data, 'inventory/counting_SKUs');
    }

    /** @throws */
    public function countingCheck()
    {
        $this->disableWarehouseChange();
        if (!empty($_POST))
            $data = $_POST;
        elseif (!empty($_GET))
            $data = $_GET;
        else {
            View::addFlash('counting', 'danger', 'Information invalid');
            throw new RedirectException('/');
        }
        $id = (int) $data['id'];
        $SKU_id = (int) $data['SKU_id'];
        $batch_id = (int) $data['batch_id'];
        $location = Locations::getById($id);
        if (empty($location) || $location->DH()) {
            View::addFlash('counting', 'warning', 'Location not found for reconciliation');
            throw new RedirectException('/housekeeping/counting');
        }
        $SKU = SKUs::getById($SKU_id);
        $qty = SKULocations::getQuantity($SKU_id, $id, $batch_id);
        if (empty($SKU) || !$qty) {
            View::addFlash('counting', 'warning', 'SKU barcode not found for reconciliation');
            throw new RedirectException('/housekeeping/SKUs/?search=' . $location->getLocation($this->warehouse_label));
        }
        $data = [
            'location' => $location,
            'SKU' => $SKU,
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()),
            'batch' => BatchesModel::getById($batch_id),
            'qty' => $qty,
            'actual' => (int)empty($_GET['btn']),
        ];
        $this->render($data, 'inventory/counting_accept');
    }

    /** @throws */
    public function countingChange()
    {
        $this->disableWarehouseChange();
        if (empty($_POST)) {
            View::addFlash('counting', 'danger', 'Information invalid. Contact system administrator');
            throw new RedirectException('/');
        }
        $location = Locations::getById((int)$_POST['loc_id']);
        if (empty($location) || $location->getWarehouse() != $this->warehouse_id || $location->DH()) {
            View::addFlash('counting', 'danger', 'Location not found');
            throw new RedirectException('/housekeeping/counting');
        }
        $reason = trim($_POST['reason']);
        if (empty($reason)) {
            View::addFlash('counting', 'danger', 'Empty reason');
            throw new RedirectException('/housekeeping/counting');
        } elseif (mb_strlen($reason) > 255) {
            View::addFlash('counting', 'danger', 'Too long reason');
            throw new RedirectException('/housekeeping/counting');
        }
        $SKU = SKUs::getById((int)$_POST['SKU_id']);
        if (empty($SKU)) {
            View::addFlash('counting', 'danger', 'SKU not found');
            throw new RedirectException('/housekeeping/counting');
        }
        $qty = (int)$_POST['qty'];
        $quantity = (int)$_POST['quantity'];
        if ($qty < 0 || $quantity < 0) {
            View::addFlash('counting', 'danger', 'Quantity must be greater than 0');
            throw new RedirectException('/housekeeping/counting');
        }
        if ((int)$_POST['qty'] == (int)$_POST['quantity']) {
            SKUHistories::addCounting($location->getId(), 0, $SKU->getId(), (int)$_POST['batch_id'], 0, 0, $this->user->getId(), $this->warehouse_id);
            throw new RedirectException('/housekeeping/SKUs/?search=' . $location->getLocation($this->warehouse_label));
        }
        $locs = $qty > $quantity
            ? Locations::getByDiscrepancyHoldMLocation($this->warehouse_id)
            : Locations::getByDiscrepancyHoldPLocation($this->warehouse_id);
        if (count($locs) != 1) {
            View::addFlash('counting', 'danger', 'Invalid ' . Location::DH . ' location. Contact system administrator');
            throw new RedirectException('/');
        }
        HousekeepingModel::createCounting($location->getId(), $locs[0]->getId(), $SKU->getId(), (int)$_POST['batch_id'], $quantity - $qty, SKUHistories::REASON_RECONCILIATION, $reason, $this->user->getId(), $this->warehouse_id);
        throw new RedirectException('/housekeeping/SKUs/?search=' . $location->getLocation($this->warehouse_label));
    }

    /////////////////////////////////////////////

    public function barcodeCreator()
    {
        $data = [];
        if (!empty($_GET))
            $data = [
                'barcode' => $_GET['barcode'] ? explode(",", $_GET['barcode'])[0] ?? '' : '',
                'name' => !empty($_GET['name']) ? $_GET['name'] : '',
            ];
        $this->render($data, 'utility/barcode_creator');
    }

    public function barcodeGenerate()
    {
        $barcode = new BarCode128($_GET['barcode'], 120, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf');
        require(ROOT_PATH . VENDOR_PATH . '/FPDF/fpdf.php');
        $pdf = new FPDF();
        $pdf->SetTitle('Barcodes');
        $pdf->AddPage();
        BarCodes::showBarCodeLabels($pdf, $barcode, $_GET['name'], (int)$_GET['qty'],
            (int)$_GET['top'], (int)$_GET['left'],
            (int)$_GET['right'], (int)$_GET['down'],
            (int)$_GET['in_row'], (int)$_GET['in_col']);
        $pdf->Output();
    }

    public function getReconciliationLog()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $SKU_id = Request::getPositiveId($_POST, 'SKU_id');
        $page = Request::getPositiveId($_POST, 'page');
        $page_size = 10;
        if (!$SKU_id || !$page)
            return $this->response = json_encode(['error' => 'Wrong data']);
        $count = SKUHistories::getCountBySKUIdAndEvent($SKU_id, SKUHistories::EVENT_MOVEMENT, $this->warehouse_id, '%' . SKUHistories::REASON_RECONCILIATION . '%');
        $history = SKUHistories::getBySKUIdAndEvent($SKU_id, SKUHistories::EVENT_MOVEMENT, $this->warehouse, ($page - 1) * $page_size, $page_size, '%' . SKUHistories::REASON_RECONCILIATION . '%');
        $history = SKUHistories::prepareMovementHistory($history, $this->warehouse);

        return $this->response = json_encode(['data' => ['history' => $history, 'pages' => ceil($count / $page_size), 'page' => $page]]);
    }
    /** @throws */
    public function locations()
    {
        $this->disableWarehouseChange();
        if (empty($_GET)) {
            View::addFlash('housekeeping', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $back = $_GET['back'];
        $id = (int)$_GET['loc'];
        unset($_GET['back'], $_GET['loc']);
        $param = http_build_query($_GET);
        $location = Locations::getById($id);
        if (empty($location) || $location->getWarehouse() != $this->warehouse_id) {
            View::addFlash('housekeeping', 'danger', 'Invalid location');
            throw new RedirectException("$back/?$param");
        }
        $SKUs = HousekeepingModel::getSKUs($id);
        if (empty($SKUs)) {
            View::addFlash('housekeeping', 'danger', 'SKUs in location is not found');
            throw new RedirectException("$back/?$param");
        }
        $data = [
            'back' => $back,
            'param' => $param,
            'location' => $location,
            'SKUs' => $SKUs,
            'SKUs_with_movements' => SKUHistories::getSKUsWithEvent(array_column($SKUs, 'SKU_id'), SKUHistories::EVENT_MOVEMENT, $this->warehouse_id, '%' . SKUHistories::REASON_RECONCILIATION . '%'),
        ];
        $this->render($data, 'inventory/view_location');
    }

    /** @throws */
    public function getPlacementHistory()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $SKU_id = Request::getPositiveId($_POST, 'SKU_id');
        if ($SKU_id <= 0)
            return $this->response = ['error'];
        $records = HousekeepingModel::getPlacementHistory($SKU_id, $this->warehouse_id);
        if (!empty($records))
            array_walk($records, function (&$record) {
                if (!empty($this->time_diff))
                    $record['date'] = $this->handleTimezone($record['date']);
                $record['date'] = date(WithDate::DATE_FRONT_FORMAT, strtotime($record['date']));
            });
        return $this->response = ['records' => $records];
    }

    public function getInventory()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST) || empty($_POST['SKU_id']))
            return $this->response = ['error' => 'Invalid information'];
        if (!$this->warehouse_virtual)
            return $this->response = ['error' => 'Warehouse is not virtual'];
        $SKU_id = Request::getPositiveId($_POST, 'SKU_id');
        $SKU = SKUs::getById($SKU_id);
        if (empty($SKU))
            return $this->response = ['error' => 'SKU is not found'];
        list($connector,) = Warehouses::getVirtualConnector($this->warehouse_id, $SKU->getSupplierId());
        if (empty($connector))
            return $this->response = ['error' => 'Connector is not found'];
        try {
            $inventory_SKU = $connector->getInventorySKU($SKU_id);
        } catch (\Exception $e) {
            return $this->response = ['error' => $e->getMessage()];
        }
        if ($inventory_SKU === false)
            return $this->response = ['error' => 'Inventory is empty'];
        return $this->response = ['success' => $inventory_SKU];
    }

    public function syncInventory()
    {
        if (!$this->warehouse_virtual)
            return $this->response = 'Warehouse is not virtual';
        if (empty($_POST) || empty($_POST['result']))
            return $this->response = 'Invalide information';
        $inventory = json_decode($_POST['result'], true);
        $inventory_log_id = InventoryLogs::addRecord($this->warehouse_id, $inventory[0]['ItemCode'] ?? 0, $this->user->getId(), $inventory, '', 'success');
        $SKU = SKUs::getById($inventory[0]['ItemCode'] ?? 0);
        if (empty($SKU))
            return $this->response = InventoryLogs::changeStatus($inventory_log_id, 'SKU is not found for this warehouse');
        list(, $class) = Warehouses::getVirtualConnector($this->warehouse_id, $SKU->getSupplierId());
        $method_name = "prepareInventory$class";
        if (!in_array($method_name, get_class_methods(HousekeepingModel::class)))
            return $this->response = InventoryLogs::changeStatus($inventory_log_id, 'Warehouse inventory is not supported');
        $inventory = HousekeepingModel::$method_name($inventory);
        if (is_string($inventory))
            return $this->response = InventoryLogs::changeStatus($inventory_log_id, $inventory);
        $locations = Warehouses::getSubwarehouses($this->warehouse_id);
        if ($locations === false)
            return $this->response = InventoryLogs::changeStatus($inventory_log_id, "Subwarehouse is not found");
        return $this->response = InventoryLogs::changeStatus($inventory_log_id, SKULocations::setInventory($inventory, $locations, $inventory_log_id));
    }
}
