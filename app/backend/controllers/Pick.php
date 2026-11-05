<?php

namespace app\backend\controllers;

use app\backend\models\Suppliers as SuppliersModel;
use app\core\entities\Location;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\helpers\Filter;
use app\core\exceptions\RedirectException;
use app\backend\models\Cart;
use app\backend\models\Drop;
use app\backend\models\Locations;
use app\backend\models\SKUs;
use app\backend\models\SKULocations;
use app\backend\models\Pick as PickModel;
use app\backend\models\Users;
use app\core\models\Warehouses;
use app\backend\models\ShippingServices;
use app\core\entities\User;

class Pick extends AuthController
{
    const DEFAULT_FILTERS = [
        'mezzanine' => 0,
        'client' => Filter::NOT_SELECTED,
        'zones' => Filter::NOT_SELECTED,
        'services' => Filter::NOT_SELECTED,
        'floor' => Filter::NOT_SELECTED,
    ];

    /** @throws RedirectException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->mobile = $this->user->isRoleWorker();
    }

    public function pickingS()
    {
        $cart = null;
        $weight = 0;
        $weight_all = 0;
        if ($this->user->isRoleWorker()) {
            $cart = Cart::getCart($this->user);
            $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
            $weight_all = array_sum(Arr::column($weight, 'weight'));
            if (count($weight) && !empty($weight[Cart::NUM_SINGLE]))
                $weight = $weight[Cart::NUM_SINGLE];
        }
        $this->picking(PickModel::PICKING_SINGLE, $cart, $weight, $weight_all);
    }

    public function pickingM()
    {
        $cart = null;
        $weight = 0;
        $weight_all = 0;
        if ($this->user->isRoleWorker()) {
            $cart = Cart::getCart($this->user);
            $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
            $weight_all = array_sum(Arr::column($weight, 'weight'));
            if (count($weight) && !empty($weight[Cart::NUM_MULTI]))
                $weight = $weight[Cart::NUM_MULTI];
        }
        $this->picking(PickModel::PICKING_MULTI, $cart, $weight, $weight_all,
            $this->user->isRoleWorker() ? $this->user->getId() : ($this->user->isRoleManager() ? Users::getAllByParentId($this->warehouse_id, $this->user->getId()) : User::ID__SYSTEM),
            !empty($_GET['loc']) ? $_GET['loc'] : ''
        );
    }

    public function pickingL()
    {
        $cart = null;
        $weight = 0;
        $weight_all = 0;
        if ($this->user->isRoleWorker()) {
            $cart = Cart::getCart($this->user);
            $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
            $weight_all = array_sum(Arr::column($weight, 'weight'));
            if (count($weight) && !empty($weight[Cart::NUM_LARGE]))
                $weight = $weight[Cart::NUM_LARGE];
        }
        $this->picking(PickModel::PICKING_LARGE, $cart, $weight, $weight_all);
    }

    /** @throws */
    private function picking($mode, $cart, $weight, $weight_all, $for_users = null, $take_loc = '')
    {
        if (!empty($take_loc) && !empty($cart)) {
            $this->pickingGet($take_loc, $mode, $cart->getId());
            return;
        }
        if ($mode == PickModel::PICKING_LARGE)
            $type_ids[Location::ST] = 'Storage';
        $type_ids[Location::PF] = 'Pick Face';
        $_GET['type_id'] = $_GET['type_id'] ?? ($mode == PickModel::PICKING_LARGE ? Location::ST : Location::PF);
        $_GET['conf'] = $_GET['conf'] ?? 0;
        $suppliers = SuppliersModel::getSuppliersForPicking($this->warehouse_id, $mode, $_GET['type_id']);
        $floors = array_combine(Warehouses::$available_floors, Warehouses::$available_floors);
        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS, !empty($_GET['clear']), [
            'client' => array_keys($suppliers),
            'floor' => array_keys($floors),
        ]);
        $warehouse = Warehouses::getById($this->warehouse_id);
        $is_mezzanine = (bool)$warehouse->getMezzanine();
        $mezzanine = null;
        if ($is_mezzanine && $this->user->hasManagerAccess() && !empty($filters['floor']))
            $mezzanine = $filters['floor'] == Warehouses::$available_floors[1];
        list($SKUs, ) = SKULocations::getLeftByOrder($this->warehouse_id, $mode, $for_users, $mezzanine, $filters['services'], $filters['client']);
        $sort_services = ShippingServices::getSequentialCollectionTimes($this->warehouse_id);
        $loc_zones = [];
        if ($this->user->isRoleWorker()) {
            $mezzanine_value = empty($filters['mezzanine']) ? 0 : 1;
            list($loc_zones, $movements) = PickModel::getLocsZonePicking($warehouse, $SKUs, $_GET['type_id'], $mezzanine_value);
            $zones = Filter::clearZone($filters['zones']);
            if ($_GET['conf']) {
                $hasMovements = false;
                if ($zones != Filter::NOT_SELECTED) {
                    foreach ($zones as $z)
                        if (!empty($movements[$z])) {
                            $hasMovements = true;
                            break;
                        }
                }
                if (!$hasMovements)
                    $filters['zones'] = $zones = Filter::NOT_SELECTED;
            }
            if ($zones != Filter::NOT_SELECTED) {
                $zones_exist = false;
                foreach ($zones as $z)
                    if (!empty($loc_zones[$z])) {
                        $zones_exist = true;
                        break;
                    }
                if (!$zones_exist)
                    $filters['zones'] = $zones = Filter::NOT_SELECTED;
            }
            if ($zones != Filter::NOT_SELECTED) {
                foreach ($SKUs as $key => $SKU) {
                    if (!in_array($SKU['zone'], $zones))
                        unset($SKUs[$key]);
                }
            }
        } else {
            $c = empty($_GET['c']) ? 1 : (int)$_GET['c'];
            $c = $c < 1 ? 1 : $c;
            $total_count = count($SKUs);
            $total_pages = ceil($total_count / PickModel::LIMIT_PER_PAGE);
            if ($c > $total_pages)
                $c = 1;
            $SKUs = array_slice($SKUs, PickModel::LIMIT_PER_PAGE * ($c - 1), PickModel::LIMIT_PER_PAGE, true);
        }
        $floors = [Filter::NOT_SELECTED => 'Select floor'] + $floors;
        $data = [
            'SKUs' => $SKUs,
            'suppliers_list' => [Filter::NOT_SELECTED => ['name' => 'Select client']] + $suppliers,
            'mode' => $mode,
            'weight' => $weight,
            'weight_all' => $weight_all,
            'cart' => $cart,
            'is_mezzanine' => $is_mezzanine,
            'zones' => $loc_zones,
            'type_ids' => $type_ids,
            'total_count' => $total_count ?? null,
            'total_pages' => $total_pages ?? null,
            'current_page' => $c ?? null,
            'sort_services' => $sort_services,
            'filters' => $filters,
            'floors' => $floors,
        ];
        $this->render($data, 'picking/picking' . (!$this->user->isRoleWorker() ? '_admin' : ''));
    }

    /** @throws */
    public function pickingGet($search = null, $mode = null, $cart_id = 0, $back = null)
    {
        if ($back === null)
            $back = (empty($_POST['mode']) || empty($_POST['back'])) ? '/' : ('/pick/picking_' . substr($_POST['mode'],0,1) . "/?{$_POST['back']}");
        if ($search === null || $mode === null) {
            if (empty($_POST['search'])) {
                View::addFlash('picking', 'danger', 'Invalid information');
                throw new RedirectException($back);
            }
            $mode = $_POST['mode'];
            $cart_id = (int)$_POST['cart_id'];
            $search = addslashes($_POST['search']);
            $SKU_id = (int)$_POST['SKU_id'];
            $batch_id = (int)$_POST['batch_id'];
        } else
            $SKU_id = 0;
        $params = [];
        parse_str(explode('?', $back)[1], $params);
        $filters = Filter::processFilters($this->warehouse_id, $params, self::DEFAULT_FILTERS);
        list($SKUall, ) = SKULocations::getLeftByOrder($this->warehouse_id, $mode, $this->user->getId(), $filters['mezzanine'], $filters['services'], $filters['client']);
        if (empty($SKUall)) {
            View::addFlash('picking', 'danger', "Invalid information for $mode picking");
            throw new RedirectException($back);
        }
        if (!is_array($filters['zones']))
            $filters['zones'] = [$filters['zones']];
        $zones = !empty(array_intersect(array_column($SKUall, 'zone'), $filters['zones'])) ? Filter::clearZone($filters['zones']) : Filter::NOT_SELECTED;
        foreach ($SKUall as $key => $SKU) {
            if (($SKU['mezzanine'] != $filters['mezzanine']) ||
                ($SKU['type_id'] != $params['type_id']))
                unset($SKUall[$key]);
            if ($zones == Filter::NOT_SELECTED)
                continue;
            if (!in_array($SKU['zone'], $zones))
                unset($SKUall[$key]);
        }
        $SKUall = array_values($SKUall);
        $l = Locations::getByScan($this->warehouse_id, htmlentities($search));
        $loc = false;
        $bar = false;
        $SKUs = $SKUall;
        $SKU_zone = '';
        foreach ($SKUall as $key => $SKU) {
            if (!is_null($l) && $SKU['location_id'] == $l->getId()) {
                $SKU_zone = $SKU['zone'];
                $loc = true;
                if (!Locations::isItemsAisles($SKU_zone, $SKU['type_id'], $SKU['mezzanine'], $this->warehouse_id)) {
                    View::addFlash('picking', 'danger', "$SKU_zone zone is not allowed");
                    throw new RedirectException($back);
                }
                continue;
            }
            if (in_array($search, $SKU['barcodes']))
                if ($SKU_id == $SKU['id'] && $batch_id == $SKU['batch_id']) {
                    if (!empty($SKU['locked'])) {
                        View::addFlash('picking', 'danger', "SKU " . $SKU['barcode'] . " is locked");
                        throw new RedirectException($back);
                    }
                    $bar = true;
                    continue;
                }
            unset($SKUs[$key]);
        }
        if (empty($SKUs)) {
            View::addFlash('picking', 'danger', "Invalid information for $mode picking");
            throw new RedirectException($back);
        }
        if ($zones == Filter::NOT_SELECTED) {
            if ($loc)
                PickModel::savePick($this->user, $this->warehouse, $params['type_id'], $SKU_zone, $this->warehouse->getMezzanine() ? $filters['mezzanine'] : null);
        } else {
            $zone_to_save = $zones[0];
            PickModel::savePick($this->user, $this->warehouse, $params['type_id'], $zone_to_save, $this->warehouse->getMezzanine() ? $filters['mezzanine'] : null);
        }
        $loc_name = Location::getLocationName($this->warehouse_label, $SKUall[0]['type_id'], $SKUall[0]['zone'], $SKUall[0]['column'], $SKUall[0]['row']);
        if ($loc && $SKUall[0]['location_id'] != $l->getId())
            View::addFlash('picking', 'warning', "It's recommended to select the first location ($loc_name) in the list");
        if ($bar && $SKUall[0]['barcode'] != $search && count($SKUall) > 1)
            View::addFlash('picking', 'warning', "It's recommended to select a product from the first location ($loc_name) in the list");
        $data = [
            'SKUs' => array_values($SKUs),
            'mode' => $mode,
            'cart_id' => $cart_id,
            'is_mezzanine' => $this->warehouse->getMezzanine(),
            'back' => $_POST['back'],
            'location' => $l,
        ];
        $this->render($data, 'picking/picking_' . ($loc ? 'loc' : 'bar'));
    }

    public function checkPick($mode = '', $type = '', $data = [], &$SKUs = [], &$SKUall = [])
    {
        $async = Request::isAjax();
        $mode = empty($mode) ? addslashes($_POST['mode']) : $mode;
        $type = empty($type) ? addslashes($_POST['type']) : $type;
        $response = function($error, $back = '') use ($async, $type) {
            if ($async)
                return $this->response = ['result' => 'error', 'message' => $error];
            else {
                View::addFlash('picking ' . $type, 'danger', $error);
                throw new RedirectException($back);
            }
        };
        $search = empty($data['search']) ? addslashes($_POST['search'] ?? '') : $data['search'];
        $location_id = empty($data['location_id']) ? (int)($_POST['loc'] ?? '') : $data['location_id'];
        $batch_id = (int)($_POST['batch_id'] ?? '');
        $SKU_id = (int)($_POST['SKU_id'] ?? '');
        if (empty($_POST))
            return $response('Empty request', '/');
        $back = str_replace('^', '&', addslashes($_POST['back']));
        parse_str($back, $params);
        $filters = Filter::processFilters($this->warehouse_id, $params, self::DEFAULT_FILTERS);
        $back = empty($_POST['mode']) ? '/' : ('/pick/picking_' . substr($_POST['mode'],0,1) . "?$back");
        if (empty($mode) || !in_array($type, ['loc', 'bar']) || empty($SKU_id) || ($type === 'loc' && empty($_POST['search'])) || ($type === 'bar' && empty($_POST['loc'])))
            return $response('Invalid information', $back);
        list($SKUall, ) = SKULocations::getLeftByOrder($this->warehouse_id, $mode, $this->user->getId(), $filters['mezzanine'], $filters['services'], $filters['client']);
        $SKUs = $SKUall;
        $l = Locations::getByScan($this->warehouse_id, htmlentities($search));
        foreach ($SKUall as $key => $SKU) {
            if ($type === 'loc') {
                if (is_null($l) || $SKU['location_id'] != $l->getId())
                    unset($SKUs[$key]);
                elseif (!Locations::isItemsAisles($SKU['zone'], $SKU['type_id'], $SKU['mezzanine'], $this->warehouse_id)) {
                    $error = "Zone is not allowed. Zone: " . $SKU['zone'];
                    return $this->response = ['result' => 'error', 'message' => $error];
                }
                if ($SKU_id != $SKU['id'] || $batch_id != $SKU['batch_id'])
                    unset($SKUs[$key], $SKUall[$key]);
            }
            if ($type === 'bar') {
                if ($SKU['location_id'] != $location_id)
                    unset($SKUs[$key]);
                if ($SKU['id'] != $SKU_id || $SKU['batch_id'] != $batch_id)
                    unset($SKUs[$key]);
            }
        }
        if (empty($SKUs))
            return $response("Invalid information for $mode picking", $back);
        if ($async)
            return $this->response = ['result' => 'success'];
        return true;
    }

    /** @throws RedirectException */
    public function pickingLoc()
    {
        unset($_SESSION['pickingConf']);
        // pick whole pallet
        if (!empty($_GET['loc_id'])) {
            $back = str_replace('^', '&', addslashes($_GET['back'] ?? ''));
            parse_str($back, $params);
            $filters = Filter::processFilters($this->warehouse_id, $params, self::DEFAULT_FILTERS);
            $mode = PickModel::PICKING_LARGE;
            if (strpos($back, '/picking_s') !== false)
                $mode = PickModel::PICKING_SINGLE;
            elseif (strpos($back, '/picking_m') !== false)
                $mode = PickModel::PICKING_MULTI;
            $filters['client'] = Filter::NOT_SELECTED;
            $params['filter'] = $filters;
            $back = '/pick/picking_' . substr($mode, 0, 1) . "?" . http_build_query($params);
            if (!$this->user->hasWorkerAccess())
                throw new RedirectException($back);
            $loc = Locations::getById((int)$_GET['loc_id']);
            if ($loc === null || $loc->getWarehouse() != $this->warehouse_id) {
                View::addFlash('picking', 'danger', 'Selected location doesn\'t exist in the system!');
                throw new RedirectException($back);
            }
            if ($loc->isLocked() || !$loc->isMoveable()) {
                View::addFlash('picking', 'danger', "Location {$loc->getAlias()} can not be taken");
                throw new RedirectException($back);
            }
            // take location as a cart
            if (!Cart::takeCart($this->user->getId(), $loc->getId(), $this->warehouse_id)) {
                View::addFlash('picking', 'danger', "Location {$loc->getAlias()} is busy");
                throw new RedirectException($back);
            }
            $res = PickModel::pickSKUsFromLoc(
                $this->user, 
                $this->warehouse, 
                $loc, 
                $loc, 
                $mode, 
                $filters['mezzanine'], 
                $filters['services']
            );
            if (!empty($res['successes'])) {
                View::addFlash('picking', 'success', $res['successes']);
            }
            if (!empty($res['errors']))
                View::addFlash('picking', 'danger', $res['errors']);
            if ($res['result'] !== false) {
                View::addFlash('picking', 'success', "Location {$loc->getAlias()} is taken successfully");
                $loc->setState(Location::DETACHED);
                $loc->save(['id', 'state']);
                throw new RedirectException(Drop::getDroppingUrlByMode($mode));
            } else {
                View::addFlash('picking', 'danger', "Location {$loc->getAlias()} can not be taken");
                Cart::freeCart($this->user->getId());
                throw new RedirectException($back);
            }
        }
        $mode = addslashes($_POST['mode']);
        $search = addslashes($_POST['search']);
        $cart_id = (int)$_POST['cart_id'];
        $SKUs = [];
        $SKUall = [];
        $this->checkPick($mode, 'loc', ['search' => $search], $SKUs, $SKUall);
        $SKU = array_values($SKUs)[0];
        PickModel::savePick($this->user, $this->warehouse, $SKU['type_id'], $SKU['zone'], $this->warehouse->getMezzanine() ? $SKU['mezzanine'] : null);
        $pos = array_keys($SKUall)[0];
        $l = Locations::getByScan($this->warehouse_id, htmlentities($search));
        if ($SKUall[$pos]['location_id'] != $l->getId()) {
            $loc_name = Location::getLocationName($this->warehouse_label, $SKUall[$pos]['type_id'], $SKUall[$pos]['zone'], $SKUall[$pos]['column'], $SKUall[$pos]['row']);
            View::addFlash('picking', 'warning', "It's recommended to select the first location ($loc_name) in the list");
        }
        $data = [
            'SKU' => $SKU,
            'mode' => $mode,
            'cart_id' => $cart_id,
            'actual' => 1,
            'back' => addslashes($_POST['back']),
        ];
        $this->render($data, 'picking/picking_SKU');
    }

    /** @throws RedirectException */
    public function pickingBar()
    {
        unset($_SESSION['pickingConf']);
        if (empty($_POST))
            throw new RedirectException('/');
        $back = empty($_POST['mode']) ? '/' : ('/pick/picking_' . substr($_POST['mode'],0,1) . "?" . str_replace('^', '&', addslashes($_POST['back'])));
        if (empty($_POST['mode']) || empty($_POST['loc']) || empty($_POST['SKU_id'])) {
            View::addFlash('picking', 'danger', 'Invalid information');
            throw new RedirectException($back);
        }
        $mode = addslashes($_POST['mode']);
        $SKUs = [];
        $SKUall = [];
        $size = (int)$_POST['size'];
        $this->checkPick($mode, 'bar', ['location_id' => (int)$_POST['loc']], $SKUs, $SKUall);
        $warehouse = Warehouses::getById($this->warehouse_id);
        $SKU = array_values($SKUs)[0];
        if (!empty($SKU['locked'])) {
            View::addFlash('picking', 'danger', "SKU " . $SKU['barcode'] . " is locked");
            throw new RedirectException($back);
        }
        PickModel::savePick($this->user, $warehouse, $SKU['type_id'], $SKU['zone'], $warehouse->getMezzanine() ? $SKU['mezzanine'] : null);
        $data = [
            'SKU' => $SKU,
            'mode' => $mode,
            'size' => $size,
            'cart_id' => (int)$_POST['cart_id'],
            'actual' => 1,
            'back' => addslashes($_POST['back']),
        ];
        $this->render($data, 'picking/picking_SKU');
    }

    /** @throws RedirectException */
    public function pickingConf()
    {
        if (empty($_POST['mode'])) {
            View::addFlash('picking', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $mode = $_POST['mode'];
        $query = json_decode($_POST['query'] ?? 'false');
        $back = str_replace('^', '&', addslashes($_POST['back']));
        parse_str($back, $params);
        $filters = Filter::processFilters($this->warehouse_id, $params, self::DEFAULT_FILTERS);
        $back = '/pick/picking_' . substr($mode, 0, 1) . "?$back";
        $loc_id = (int)$_POST['location_id'];
        $loc_obj = Locations::getById($loc_id);
        if ($loc_obj === null) {
            View::addFlash('picking', 'danger', "Selected location doesn't exist in the system!");
            throw new RedirectException($back);
        }
        $SKU_id = (int)$_POST['SKU_id'];
        $SKU_obj = SKUs::getById($SKU_id);
        if ($SKU_obj === null) {
            View::addFlash('picking', 'danger', "Selected SKU doesn't exist in the system!");
            throw new RedirectException($back);
        }
        $cart_id = (int)$_POST['cart_id'];
        if (!empty($_SESSION['pickingConf'])) {
            $this->pickingGet($loc_obj->getLocation($this->warehouse_label), $mode, $cart_id, $back);
            return;
        }
        $cart = Locations::getById($cart_id);
        if (empty($cart)) {
            View::addFlash('picking', 'danger', 'You don\'t have a cart');
            throw new RedirectException('/cart/picking?back=' . $back);
        }
        $SKU_info = [[
            'SKU_id' => $SKU_id,
            'SKU' => $SKU_obj,
            'batch_id' => (int)$_POST['batch_id'],
            'quantity' => (int)$_POST['quantity'],
            'total_quantity' => (int)$_POST['qty'],
            'size' => (int)$_POST['size'],
            'query' => (bool)$query,
        ]];
        $result = PickModel::pickSKUsFromLoc($this->user, $this->warehouse, $loc_obj, $cart, $mode, $filters['mezzanine'], $filters['services'], $SKU_info);
        if (!empty($result['successes']))
            View::addFlash('picking', 'success', $result['successes']);
        if (!empty($result['errors']))
            View::addFlash('picking', 'danger', $result['errors']);
        if ($result['result'] === false) {
            View::addFlash('picking', 'danger', $result['message']);
            throw new RedirectException($back);
        }
        $sizes = $result['result'];
        if (!$query && !empty($sizes[$SKU_id])) {
            $_SESSION['pickingConf'] = true;
            $this->pickingGet($loc_obj->getLocation($this->warehouse_label), $mode, $cart_id, $back);
            return;
        }
        // check if 
        if (isset($filters['client']) && $filters['client'] != Filter::NOT_SELECTED) {
            $params['type_id'] = $params['type_id'] ?? ($mode == PickModel::PICKING_LARGE ? Location::PF : Location::ST);
            $suppliers = SuppliersModel::getSuppliersForPicking($this->warehouse_id, $mode, $params['type_id']);
            $filters['client'] = array_filter($filters['client'], function($client) use ($suppliers) {
                return !empty($suppliers[$client]);
            });
            if (empty($filters['client'])) {
                $filters['client'] = Filter::NOT_SELECTED;
                $params['filter'] = $filters;
                $back = '/pick/picking_' . substr($mode, 0, 1) . '?' . http_build_query($params);
            }
        }
        throw new RedirectException("$back&conf=1" . (!empty($_POST['new_loc']) ? ('&loc=' . $_POST['new_loc']) : ''));
    }
}
