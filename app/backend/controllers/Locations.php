<?php

namespace app\backend\controllers;

use app\backend\models\BookRangeLocations;
use app\backend\models\Cart;
use app\backend\models\Locations as LocationsModel;
use app\backend\models\Movements;
use app\backend\models\Pick;
use app\backend\models\Sizes as SizesModel;
use app\backend\models\SKULocations;
use app\backend\models\SKUPreffLocs;
use app\backend\models\UserGroups;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\SectionBinding;
use app\core\entities\SKU;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\BookLocations;
use app\core\models\LocationsHistory;
use app\core\models\SectionBindings;
use app\core\models\Warehouses;
use app\core\statical\TypeLocation;
use Exception;
use app\core\helpers\Debug;

class Locations extends AuthController
{
    public function index()
    {
        $_GET['search_name'] = empty($_GET['search_name']) ? '' : trim($_GET['search_name']);
        $_GET['c'] = !empty($_GET['c']) ? (int)$_GET['c'] : 1;
        unset($_GET['change_size']);
        $locations = ['records' => [], 'total' => 0, 'current_page' => 1];
        $_SESSION[LocationsModel::FILTER_PARAM] = http_build_query($_GET);
        if (!empty($_GET['search_name'])) {
            /** @var Location $location */
            $location = LocationsModel::getByScan($this->warehouse_id, htmlentities(trim($_GET['search_name'])));
            if (is_null($location))
                View::addFlash('locations', 'danger', "Location '{$_GET['search_name']}' isn't found");
            else {
                $locations['records'] = [$location];
                $locations['total'] = 1;
                $_GET['search_name'] = '';
            }
            LocationsModel::setSearchLocation($_GET, $location);
        }
        if (empty($locations['records']))
            $locations = LocationsModel::getByFilters($_GET, $this->warehouse_id);
        list($locationSKUs, $sizes) = LocationsModel::prepareLocations($this->warehouse_id, $locations['records']);
        $not_free_locations = SKULocations::getNotFreeLocations($locations['records']);
        $warehouse = Warehouses::getById($this->warehouse_id);
        $floors = $warehouse->getMezzanine() ? Warehouses::$available_floors : [];
        $none_edit_locs = LocationsModel::getConstantLocationIds($this->warehouse_id);

        $none_lock_locs = array_unique(array_merge(SKUPreffLocs::getAssigned($locations['records']), $none_edit_locs));
        $data = [
            'total_records' => $locations['total'],
            'types' => LocationsModel::getAllTypeId($this->warehouse_id),
            'zones' => LocationsModel::getAllZone($this->warehouse_id),
            'columns' => LocationsModel::getAllColumn($this->warehouse_id),
            'rows' => LocationsModel::getAllRow($this->warehouse_id),
            'floors' => $floors,
            'locations' => $locations['records'],
            'none_lock_locs' => $none_lock_locs,
            'none_edit_locs' => $none_edit_locs,
            'total_pages' => ceil($locations['total'] / LocationsModel::LIMIT_PER_PAGE),
            'current_page' => $locations['current_page'],
            'sizes' => $sizes,
            'SKUs' => $locationSKUs,
            'not_free_locations' => $not_free_locations
        ];
        $this->render($data, 'warehouse/locations');
    }

    public function binding()
    {
        $data = [
            'section_ts' => SectionBindings::getWorkers4PackStation($this->warehouse_id),
            'section_ls' => SectionBindings::getWorkers4LargePack($this->warehouse_id),
//            'pickers' => Users::getWorkerList($this->warehouse_id, 'picking', false),
//            'packers' => Users::getWorkerList($this->warehouse_id, 'packing', false),
            'pickers' => UserGroups::getWorkersByGroupAccess($this->warehouse_id, 'picking'),
            'packers' => UserGroups::getWorkersByGroupAccess($this->warehouse_id, 'packing'),
        ];
        $this->render($data, 'warehouse/binding');
    }

    /** @throws */
    public function saveBinding()
    {
        if (empty($_POST)) {
            View::addFlash('bindings', 'danger', 'Invalid information');
            throw new RedirectException('/locations/binding');
        }
        $type = substr(strtoupper($_POST['type']), 0, 1);
        $num = strtoupper($_POST['num']);
        $workers = $_POST['worker'] ?? [];
        if ($type === SectionBinding::WORKERS)
            $workers_old = SectionBindings::getWorkers4OrderBin($this->warehouse_id);
        elseif ($type === SectionBinding::TABLES)
            $workers_old = SectionBindings::getWorkers4PackStation($this->warehouse_id);
        elseif ($type === SectionBinding::LARGES)
            $workers_old = SectionBindings::getWorkers4LargePack($this->warehouse_id);
        else {
            Debug::sendDebugInfo('Wrong bind type', 'type ' . $type . ' num ' . $num . ' user ' . $this->user->getId(), Debug::TEST);
            View::addFlash('bindings', 'danger', 'Wrong bind type');
            throw new RedirectException('/locations/binding');
        }
        $workers_del = $workers_old[$num][$type];
        foreach ($workers_del as $key => $worker)
            if (!empty($workers[$key]))
                unset($workers_del[$key], $workers[$key]);
        // delete
        if (!empty($workers_del)) {
            (new SafeMysql())->execute("
                DELETE FROM section_binding
                WHERE num = :num AND
                      type_id = :type_id AND
                      warehouse_id = :warehouse_id AND
                      object_id IN (:object_ids)", [
                'num' => $num,
                'type_id' => $type,
                'warehouse_id' => $this->warehouse_id,
                'object_ids' => array_map('intval', array_keys($workers_del)),
            ]);
        }
        // insert
        if (!empty($workers)) {
            $params = [
                'num' => $num,
                'type_id' => $type,
                'warehouse_id' => $this->warehouse_id,
            ];
            $values = [];
            $i = 0;
            foreach (array_keys($workers) as $worker_id) {
                $values[] = "(NULL, :num, :worker_id_$i, :type_id, :warehouse_id)";
                $params["worker_id_$i"] = (int)$worker_id;
                $i++;
            }
            $sql = 'INSERT INTO section_binding (id, num, object_id, type_id, warehouse_id) VALUES ' . implode(', ', $values);
            (new SafeMysql())->execute($sql, $params);
        }
        throw new RedirectException('/locations/binding');
    }

    /** @throws */
    public function editLocation()
    {
        $id = Request::getPositiveId();
        $lock = $_GET['lock'];
        $non_picking = $_GET['non_picking'];
        if (!$id) {
            View::addFlash('locations', 'danger', "Location isn't found");
        } else {
            /** @var Location $location */
            $location = LocationsModel::getById($id);

            if ($location === null || $location->getWarehouse() != $this->warehouse_id) {
                View::addFlash('locations', 'danger', "Location isn't found");
                throw new RedirectException('/locations');
            }
            if (empty($lock) && in_array($location->getId(), LocationsModel::getConstantLocationIds($this->warehouse_id))) {
                View::addFlash('locations', 'danger', "Location can't be locked");
                throw new RedirectException('/locations');
            }
            if ($lock == Location::ACTIVE && $location->isDetached()) {
                if (!empty(SectionBindings::getBindingType($location->getId()))) {
                    View::addFlash('locations', 'danger', "Location is attached to table, please unhook it first");
                    throw new RedirectException('/locations');
                }
                $user = Cart::getCartUser($location->getId(), $this->warehouse_id);
                if (!empty($user)) {
                    View::addFlash('locations', 'danger', "Location is attached to user {$user->getLogin()}");
                    throw new RedirectException('/locations');
                }
                // unpick all picked SKUs
                Pick::unpickSKUsInLoc($this->warehouse, $this->user, $location);
            }
            if ($lock != '') {
                $mysql = new SafeMysql();
                $mysql->beginTransaction();
                try {
                    $message = 'Cart unlocked';
                    if ($location->CA() && $lock == 0) {
                        $assigned_user = Cart::getCartUser($id, $this->warehouse_id);
                        $message = 'Cart locked';
                        if ($assigned_user) {
                            Cart::freeCartById($id);
                            $message = "Cart locked and removed from worker: {$assigned_user->getLogin()}";
                        }
                    }

                    $location->setState($lock);
                    $location->save(['id', 'state']);
                    LocationsHistory::addRecord($id, $this->user->getId(), 'lock', ['state' => $location->getState() == Location::LOCKED ? 'locked' : 'unlocked']);
                    View::addFlash('locations', 'success', $message);
                    $mysql->commit();
                } catch (Exception $e) {
                    $mysql->rollback();
                    View::addFlash('locations', 'danger', $e->getMessage());
                }
            }
            if ($non_picking != '') {
                $not_free_location = SKULocations::getNotFreeLocations([$location]);
                if (in_array($location->getId(), $not_free_location, true)) {
                    View::addFlash('locations', 'danger', "This location cannot be used");
                    throw new RedirectException('/locations');
                }

                $location->setIsNonPicking($non_picking);
                $location->save(['id', 'state', 'is_non_picking']);
                LocationsHistory::addRecord($id, $this->user->getId(), 'is_non_picking', [
                    'is_non_picking' => $location->getIsNonPicking() ? 'non_picking' : 'picking'
                ]);
            }
        }
        unset($_GET['id'], $_GET['lock'], $_GET['non_picking']);
        throw new RedirectException("/locations/?warehouse={$this->warehouse_id}&" . http_build_query($_GET));
    }

    public function addLocation()
    {
        $this->disableWarehouseChange();
        if (!isset($_GET['loc']))
            unset($_GET['search_name'], $_GET['type_id'], $_GET['zone'], $_GET['column'], $_GET['row'], $_GET['floors']);
        $loc = $_GET;
        if (!empty($_GET['loc'])) {
            $loc = json_decode($_GET['loc'], true);
            unset($_GET['loc']);
        }
        $sizes = SizesModel::getAllSizesName($this->warehouse_id);
        $warehouse = Warehouses::getById($this->warehouse_id);
        $vars = [
            'loc' => $loc,
            'types' => TypeLocation::getAll(),
            'sizes' => $sizes,
            'floors' => $warehouse->getMezzanine(),
            'back' => $_SESSION[LocationsModel::FILTER_PARAM],
        ];
        $vars['change_size'] = (int)($_GET['change_size'] ?? 1);
        $this->render($vars, 'warehouse/add_location');
    }

    /** @throws */
    public function editLocations()
    {
        $locations = $_GET;
        $id = (int)$locations['id'];
        $location = LocationsModel::getById($id);
        throw new RedirectException('/locations/add_location/?' . http_build_query([
            'loc' => json_encode($location->toArray()),
            'change_size' => (int)$locations['change_size'],
            'c' => (int)$locations['c'],
        ]));
    }

    /** @throws */
    public function setLocation()
    {
        if (empty($_POST)) {
            View::addFlash('add_location', 'danger', 'Invalid information');
            throw new RedirectException('/locations/add_location?' . str_replace('^', '&', $_POST['back']));
        }
        $loc_data = $_POST['location'];
        $loc_data['mezzanine'] = (int)isset($loc_data['mezzanine']);
        $loc_data['warehouse'] = $this->warehouse_id;
        $fields_to_save = array_keys($loc_data);
        $loc = new Location([
            'warehouse' => $loc_data['warehouse'],
            'type_id' => $loc_data['type_id'],
            'zone' => $loc_data['zone'],
            'column' => $loc_data['column'],
            'row' => $loc_data['row'],
            'mezzanine' => $loc_data['mezzanine'],
            'state' => $loc_data['state'],
        ]);
        $alternate_alias = '';
        if (!empty($loc_data['id'])) {
            $old_location = LocationsModel::getById($loc_data['id']);
            if (empty($old_location)) {
                View::addFlash('add_location', 'danger', 'Location is not found');
                throw new RedirectException('/locations/add_location?' . str_replace('^', '&', $_POST['back']) . '&loc=' . json_encode($loc_data) . '&change_size=' . (int)($_GET['change_size'] ?? 1));
            }
            $alternate_alias = $old_location->getAlternativeAlias();
            $loc->setId($old_location->getId());
        }
        $loc->setAlias((empty($loc_data['alias']) || $loc_data['alias'] == $alternate_alias)
            ? null
            : $loc_data['alias']
        );
        if (!empty($loc_data['size_id']))
            $loc->setSizeId($loc_data['size_id']);
        if (isset($old_location)) {
            $diff = [];
            foreach ($loc->toArray() as $field => $value)
                if ($value != $old_location->toArray()[$field])
                    $diff[$field] = [
                        'old' => $old_location->toArray()[$field],
                        'new' => $value
                    ];
            $diff_loc = array_udiff_assoc($loc->toArray(), $old_location->toArray(), function ($item1, $item2) {
                if (is_array($item1) && is_array($item2))
                    return (int)!empty(array_diff_assoc($item1, $item2));
                if (is_array($item1) || is_array($item2))
                    return 1;
                return (int)($item1 != $item2);
            });
        }
        $errors = LocationsModel::validate($loc, $this->user);
        if (!empty($errors)) {
            View::addFlash('add_location', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException('/locations/add_location?' . str_replace('^', '&', $_POST['back']) . '&loc=' . json_encode($loc_data) . '&change_size=' . (int)($_GET['change_size'] ?? 1));
        }
        if (isset($diff_loc) && empty($diff_loc))
            View::addFlash('change', 'warning', 'Nothing to change');
        else {
            $loc->save($fields_to_save);
            if (!empty($diff_loc) || !isset($diff_loc))
                LocationsHistory::addRecord($loc->getId(), $this->user->getId(), $loc_data['id'] ? 'edit' : 'create', !$loc_data['id'] ? $loc->toArray() : $diff);
            View::addFlash('save', 'success', $loc_data['id'] ? 'Location was updated' : 'Location was created');
        }
        throw new RedirectException('/locations/?' . str_replace('^', '&', $_POST['back']));
    }

    public function printBarcodes()
    {
        $chunk = !empty($_GET['chunk']) ? (int)$_GET['chunk'] : -1;
        list($locations, , $pages) = LocationsModel::getByLocationChunk(
            $chunk,
            $this->warehouse_id,
            urlencode($_GET['T'] ?? ''),
            urlencode($_GET['Z'] ?? ''),
            urlencode($_GET['C'] ?? ''),
            urlencode($_GET['R'] ?? ''),
        );
        if ($pages > 1 && $chunk === -1) {
            for ($chunk = 1; $chunk <= $pages; $chunk++)
                echo Html::openWindow(
                    '/locations/print_barcodes/?' . http_build_query(['chunk' => $chunk] + $_GET),
                    'wind' . $chunk,
                    "PDF ($chunk of $pages). Please disable your pop-up blocker and click the PRINT BARCODES button again");
            echo Html::closeWindow();
        } else
            LocationsModel::printBarcodes($locations, $this->warehouse_label, $_GET['top'], $_GET['left'], $_GET['right'], $_GET['down'], $_GET['in_row'], $_GET['in_col'], $chunk, $pages);
    }

    /** @throws */
    public function getAssignment()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $id = Request::getPositiveId($_POST, 'location_id');
        if (!$id)
            return $this->response = ['error' => 'Location is not found'];
        $SKUs = SKUPreffLocs::getSKUsByLocation($id);
        if (empty($SKUs))
            return $this->response = ['error' => 'SKUs are not found'];
        return $this->response = ['success' => json_encode($SKUs)];
    }

    /** @throws */
    public function bookLocations()
    {
        $back = '/locations/?back=' . urldecode($_GET['back'] ?? '');
        $suppliers = $this->getSuppliers(['warehouse_id' => $this->warehouse_id]);
        if (empty($suppliers)) {
            View::addFlash('book_locations', 'danger', 'Suppliers are not found');
            throw new RedirectException($back);
        }
        if (empty($_GET['supplier_id']) || empty($suppliers[$_GET['supplier_id']]))
            $_GET['supplier_id'] = key($suppliers);
        if (empty($_GET['type_id']) || !Location::isStorage($_GET['type_id']))
            $_GET['type_id'] = Location::ST;
        $this->disableWarehouseChange();
        $data = [
            'suppliers' => $suppliers,
            'locations' => array_map(function (Location $location) {
                return [
                    'id' => $location->getId(),
                    'name' => $location->getLocationReal($this->warehouse_label),
                    'state' => $location->isLocked() ? 'danger' : 'success'
                ];
            }, BookLocations::getLocations($this->warehouse_id, $_GET['supplier_id'], $_GET['type_id'])),
            'ranges' => BookRangeLocations::getRanges($this->warehouse_id, $_GET['supplier_id'], $_GET['type_id']),
        ];
        $this->render($data, 'warehouse/book_locations');
    }

    /** @throws */
    public function getRanges()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        header("Content-type: application/json; charset=utf-8");
        $type_id = $_GET['type_id'] ?? '';
        if (!$type_id || !Location::isStorage($type_id))
            return $this->response = ['error' => 'Wrong type of location'];
        $ranges = LocationsModel::getRanges($this->warehouse_id, $type_id);
        if (empty($ranges))
            return $this->response = ['error' => 'Ranges are not found'];
        return $this->response = ['success' => $ranges];
    }

    /** @throws */
    public function addRange()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        header("Content-type: application/json; charset=utf-8");
        $type_id = $_POST['type_id'] ?? '';
        if (!$type_id || !Location::isStorage($type_id))
            return $this->response = ['error' => 'Wrong type of location'];
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        if (!$supplier_id)
            return $this->response = ['error' => 'Wrong supplier'];
        $zone = $_POST['zone'] ?? '';
        if (!$zone)
            return $this->response = ['error' => 'Wrong zone'];
        [$res, $msg] = LocationsModel::addRange($this->warehouse_id, $supplier_id, $type_id, $zone, $_POST['column'] ?? '', $this->user->getId());
        if (!$res)
            return $this->response = ['error' => $msg];
        if ($msg)
            View::addFlash('book_locations', 'success', $msg);
        return $this->response = ['success' => 'done'];
    }

    /** @throws */
    public function removeRange()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        header("Content-type: application/json; charset=utf-8");
        $type_id = $_POST['type_id'] ?? '';
        if (!$type_id || !Location::isStorage($type_id))
            return $this->response = ['error' => 'Wrong type of location'];
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        if (!$supplier_id)
            return $this->response = ['error' => 'Wrong supplier'];
        $zone = $_POST['zone'] ?? '';
        if (!$zone)
            return $this->response = ['error' => 'Wrong zone'];
        [$res, $msg] = LocationsModel::removeRange($this->warehouse_id, $supplier_id, $type_id, $zone, $_POST['column'] ?? '', $this->user->getId());
        if (!$res)
            return $this->response = ['error' => $msg];
        return $this->response = ['success' => $msg];
    }

    /** @throws */
    public function removeLocation()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        header("Content-type: application/json; charset=utf-8");
        $type_id = $_POST['type_id'] ?? '';
        if (!$type_id || !Location::isStorage($type_id))
            return $this->response = ['error' => 'Wrong type of location'];
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        if (!$supplier_id)
            return $this->response = ['error' => 'Wrong supplier'];
        $location_id = $_POST['location_id'] ?? 0;
        $location = LocationsModel::getById($location_id);
        if (!$location_id || !$location || $location->getWarehouse() != $this->warehouse_id || $location->getTypeId() != $type_id)
            return $this->response = ['error' => 'Wrong location'];
        [$res, $msg] = LocationsModel::removeLocation($this->warehouse_id, $this->warehouse_label, $supplier_id, $type_id, $location, $this->user->getId());
        if (!$res)
            return $this->response = ['error' => $msg];
        return $this->response = ['success' => $msg];
    }

    /** @throws */
    public function importLocations()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        header("Content-type: application/json; charset=utf-8");
        $type_id = $_POST['type_id'] ?? '';
        if (!$type_id || !Location::isStorage($type_id))
            return $this->response = ['error' => 'Wrong type of location'];
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        if (!$supplier_id)
            return $this->response = ['error' => 'Wrong supplier'];
        if (empty($_FILES['filename']))
            return $this->response = ['error' => 'Wrong data'];
        [$res, $msg] = LocationsModel::importLocations($this->warehouse_id, $this->warehouse_label, $supplier_id, $type_id, $_FILES['filename'], $this->user->getId());
        if (!$res)
            return $this->response = ['error' => $msg];
        return $this->response = [
            'success' => $msg,
            'ranges' => BookRangeLocations::getRanges($this->warehouse_id, $supplier_id, $type_id),
            'locations' => array_map(function (Location $location) {
                return [
                    'id' => $location->getId(),
                    'name' => $location->getLocationReal($this->warehouse_label),
                    'state' => $location->isLocked() ? 'danger' : 'success'
                ];
            }, BookLocations::getLocations($this->warehouse_id, $supplier_id, $type_id)),
        ];
    }

    public function scan()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $barcode = Request::get('barcode');
        if (empty($barcode))
            return $this->jsonResponse(['error' => 'Barcode is not found']);
        $suppliers = json_decode($_POST['suppliers'] ?? '', true);
        $location = LocationsModel::getByScan($this->warehouse_id, $barcode);
        if (empty($location))
            return $this->jsonResponse(['error' => 'Location is not found'], 404);
        if ($location->isLocked())
            return $this->jsonResponse(['error' => 'Location is locked'], 400);
        if (!empty($suppliers)) {
            $booker = SKULocations::getBooker($location->getId());
            if (!empty($booker) && !in_array($booker, $suppliers))
                return $this->jsonResponse(['error' => 'Location is not assigned to this supplier'], 403);
        }
        $from = Request::get('from');
        if (!empty($from)) {
            if ($from == $location->getId())
                return $this->jsonResponse(['error' => 'Can\'t move to the same location'], 400);
            $from_location = LocationsModel::getById($from);
            if (empty($from_location) || $from_location->getWarehouse() != $this->warehouse_id
                || !$from_location->isActive()
                || !$from_location->isMoveable()
            )
                return $this->jsonResponse(['error' => 'Origin location is wrong'], 404);
            $res = Movements::canPalletBeMoved($from_location, $location);
            if ($res !== true)
                return $this->jsonResponse(['error' => str_replace('Pallet Merge', 'Pallet movement', $res)], 400);
        }

        $loc_array = $location->toArray();
        $loc_array['name'] = $location->getLocation($this->warehouse_label);
        $includeSKUs = Request::get('include_skus');
        $data = ['success' => true, 'location' => $loc_array];
        if ($includeSKUs) {
            $data['SKUs'] = SKULocations::getSKUsFromLocation($location->getId());
            foreach ($data['SKUs'] as &$SKU)
                $SKU['photo_url'] = $SKU['photo'] && SKU::showPhotoSKU($SKU['photo']) ? SKU::showPhotoSKU(urlencode($SKU['photo'])) : null;
        }
        $forMovement = Request::get('for_movement');
        if ($forMovement) {
            // $available_locs = SKULocations::getAvailableLocationsForAllSKUsInLocation($this->warehouse_id, $location);
            // foreach ($available_locs as $type_id => $locs)
            //     $available_locs[$type_id] = [
            //         'name' => TypeLocation::getNameLocation($type_id),
            //         'locations' => $locs,
            //     ];
            // $available_locs[Location::PA] = [
            //     'name' => TypeLocation::getNameLocation(Location::PA),
            // ];
            // $available_locs[Location::DH] = [
            //     'name' => TypeLocation::getNameLocation(Location::DH),
            // ];
            // $data['destinations'] = $available_locs;
            if (LocationsModel::isExpiredLocation($location) || LocationsModel::isCancelLocation($location))
                return $this->jsonResponse(['error' => 'Location can not be used for movement'], 400);
            $data['destinations'] = 'scan';
        }
        return $this->jsonResponse($data);
    }
}
