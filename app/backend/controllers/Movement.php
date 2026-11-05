<?php

namespace app\backend\controllers;

use app\backend\models\Cart;
use app\backend\models\Locations;
use app\backend\models\MovementHistories;
use app\backend\models\MovementRequestHistories;
use app\backend\models\MovementRequests;
use app\backend\models\Movements;
use app\backend\models\SKULocations;
use app\backend\models\SKUBarcodes;
use app\backend\models\SKUs;
use app\core\entities\Location;
use app\core\entities\Movement as MovementEntity;
use app\core\entities\MovementRequest;
use app\core\entities\SKU;
use app\core\exceptions\RedirectException;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\helpers\Filter;
use app\core\statical\WithDate;
use app\backend\models\SKUPreffLocs;
use app\core\traits\CommonAjaxRequests;

class Movement extends AuthController
{
    use CommonAjaxRequests;
    const DEFAULT_FILTERS = [
        'order_by' => Filter::NOT_SELECTED,
        'comment_filters' => Filter::NOT_SELECTED,
        'type_from' => Filter::NOT_SELECTED,
        'type_to' => Filter::NOT_SELECTED,
        'status' => Filter::NOT_SELECTED,
        'date_created_from' => Filter::NOT_SELECTED,
        'date_created_to' => Filter::NOT_SELECTED,
        'type' => Filter::NOT_SELECTED,
    ];

    /** @throws RedirectException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->mobile = true;
    }

    private function getMovementRoute(): string
    {
        $route = 'index';
        if ($this->isPalletMovement())
            $route = 'createPalletMovement';
        $filters = [];
        if ($this->isRequest()) {
            $route = 'requests';
            if (isset($_SESSION[Movements::FILTER_REQUEST_PARAM]))
                parse_str(urldecode($_SESSION[Movements::FILTER_REQUEST_PARAM]), $filters);
        } elseif (isset($_SESSION[Movements::FILTER_MOVEMENT_PARAM]))
            parse_str(urldecode($_SESSION[Movements::FILTER_MOVEMENT_PARAM]), $filters);
        return $this->route($route, null, $filters);
    }

    private function isPalletMovement(): bool
    {
        return !empty($_GET['is_pallet']) && $_GET['is_pallet'] == 1;
    }

    private function isRequest(): bool
    {
        return !empty($_GET['mode']) && $_GET['mode'] === Movements::CREATE_REQUEST;
    }

    public function changeType()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');

        if ($_POST['id'] && $_POST['type']) {
            Movements::updateAuto(['id' => (int) $_POST['id'], 'type' => (int) $_POST['type']]);
            return $this->response = ['result' => 'success', 'message' => 'The type was changed'];
        }
        return $this->response = ['result' => 'error', 'message' => 'Data is not valid'];
    }

    /** @throws RedirectException */
    public function index()
    {
        if (!empty($_GET['clear']) && $_GET['clear'] == 1) {
            $_SESSION['SKU'] = null;
            $_SESSION[Location::DH] = 0;
            $_SESSION[Location::PA] = 0;
        }
        Movements::updateMovements((int)$this->warehouse_id);
        if ($this->user->hasManagerAccess()) {
            $this->movementAdmin();
            return;
        }
        $cart = Cart::getCart($this->user);
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        
        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS, !empty($_GET['clear']));
        $_SESSION[Movements::FILTER_MOVEMENT_PARAM] = http_build_query($filters);
        
        $raw_data = Movements::getMovementByWorker($this->warehouse_id, $this->user->getId(), $filters);
        foreach ($raw_data as &$record)
            if ($record['priority'] == MovementEntity::EXPIRED_PRIORITY)
                $record['numbers'] = SKULocations::getBatchesExpired($record['loc_from_id'], $record['SKU_id']);
        unset($record);
        $data = [
            'movements' => $raw_data,
            'comment_filters' => [Filter::NOT_SELECTED => 'Select comment type'] + MovementEntity::COMMENT_FILTERS,
            'cart' => $cart,
            'weight' => ($weight[Cart::NUM_MOVE]['weight'] ?? 0) + ($weight[Cart::NUM_EXPIRED]['weight'] ?? 0),
            'back' => $this->getMovementRoute(),
            'filters' => $filters
        ];
        $this->render($data, 'movement/movement_worker');
    }

    /** @throws */
    private function movementAdmin()
    {
        $this->mobile = false;
        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS, !empty($_GET['clear']));
        $filters['c'] = (int)($_GET['c'] ?? 1);
        $raw_data = Movements::getByFilters($this->warehouse, $filters);
        $movements_filters = $_GET;
        $_SESSION[Movements::FILTER_MOVEMENT_PARAM] = http_build_query($movements_filters);
        if (isset($movements_filters['c']))
            unset($movements_filters['c']);
        $records = $raw_data['records'];
        if (!empty($records) && !empty($this->time_diff))
            array_walk($records, function(&$record) {
                $record['create_date'] = $this->handleTimezone($record['create_date']);
            });
        $data = [
            'movements' => $records,
            'total_count' => $raw_data['total'],
            'total_pages' => ceil($raw_data['total'] / Movements::LIMIT_PER_PAGE),
            'current_page' => $raw_data['current_page'],
            'movements_filters' => http_build_query($movements_filters),
            'suppliers' => $this->available_suppliers,
            'filters' => $filters
        ];
        $this->render($data, 'movement/movement_admin');
    }

    /** @throws RedirectException */
    public function createMovement()
    {
        ini_set('memory_limit', '512M');
        $previous_movement = [];
        if (!empty($_GET['id'])) {
            $barcodes = SKUBarcodes::getBySKUId((int)$_GET['id']);
            $SKU = SKUs::getById((int)$_GET['id']);
            $previous_movement = [
                'id' => $SKU->getId(),
                'supplier_id' => $SKU->getSupplierId(),
                'name' => $SKU->getName(),
                'photo' => $SKU->getPhoto(),
                'barcodes' => $barcodes ?? [],
                'type' => $_GET['type'],
                'qty' => $_GET['qty'],
                'loc_from_id' => $_GET['loc_from_id'],
                'loc_from' => $_GET['loc_from']
            ];
        }
        $this->disableWarehouseChange();
        $res = Locations::getDHLocation($this->warehouse_id);
        if ($res !== true) {
            View::addFlash('movement', 'danger', $res);
            throw new RedirectException('/');
        }
        $res = Locations::getPALocation($this->warehouse_id);
        if ($res !== true) {
            View::addFlash('movement', 'danger', $res);
            throw new RedirectException('/');
        }
        $data = [
            'mode' => $_GET['mode'] ?? Movements::CREATE_MOVEMENT,
            'quantity' => 1,
            'back' => $this->getMovementRoute(),
            'previous_movement' => View::toJs($previous_movement)
        ];
        $_SESSION['__hm'] = sha1('__ha');
        $this->render($data, 'movement/create_movement');
    }

    public function createPalletMovement()
    {
        $this->disableWarehouseChange();
        $res = Locations::getDHLocation($this->warehouse_id);
        if ($res !== true) {
            View::addFlash('movement', 'danger', $res);
            throw new RedirectException('/');
        }
        $res = Locations::getPALocation($this->warehouse_id);
        if ($res !== true) {
            View::addFlash('movement', 'danger', $res);
            throw new RedirectException('/');
        }
        $data = [
            'back' => $this->getMovementRoute()
        ];
        $_SESSION['__hm'] = sha1('__ha');
        $this->render($data, 'movement/create_pallet_movement');
    }

    /** @throws RedirectException */
    public function saveMovement()
    {
        if (empty($_SESSION['__hm']) || $_SESSION['__hm'] !== sha1('__ha'))
            throw new RedirectException('/');
        if ($_GET['loc_from_id'] == Location::PA)
            $loc_from_id = $_SESSION[Location::PA];
        elseif (Location::isDH($_GET['loc_from_id']))
            $loc_from_id = $_SESSION[Location::DH];
        else
            $loc_from_id = $_GET['loc_from_id'];
        if ($_GET['loc_to_id'] == Location::PA)
            $loc_to_id = $_SESSION[Location::PA];
        elseif (Location::isDH($_GET['loc_to_id']))
            $loc_to_id = $_SESSION[Location::DH];
        else
            $loc_to_id = $_GET['loc_to_id'];
        $location_from = Locations::getById($loc_from_id);
        $location_to = Locations::getById($loc_to_id);
        if (isset($_GET['type_location'])) {
            $type_location = addslashes($_GET['type_location']);
            if (in_array($type_location, MovementRequest::ALL_TYPES)) {
                $SKU = SKUs::getById($_GET['id']);
                return $this->saveRequestMovement(MovementRequest::isFrom($type_location) ? $location_from : $location_to, $type_location, $SKU, $_GET['qty']);
            }
            unset($_GET['type_location']);
        }
        if (!$location_from) {
            View::addFlash('movement', 'danger', 'Origin location not found');
            throw new RedirectException($this->getMovementRoute());
        }
        if (!$location_to) {
            View::addFlash('movement', 'danger', 'Destination location not found');
            throw new RedirectException($this->getMovementRoute());
        }
        if ($location_from->PR() && !($location_to->notCounted() || $location_to->cart())) {
            View::addFlash('movement', 'danger', 'Movement is impossible. SKU can not be taken or put');
            throw new RedirectException($this->getMovementRoute());
        }
        if ($this->isPalletMovement())
            return $this->savePalletMovement($location_from, $location_to);
        $SKU = SKUs::getById($_GET['id']);
        if (!$SKU) {
            View::addFlash('movement', 'danger', 'SKU not found');
            throw new RedirectException($this->getMovementRoute());
        }
        if (SKUPreffLocs::checkExistPreffLoc((int)$location_to->getWarehouse(), (int)$SKU->getId(), (int)$location_to->getId(), $location_to->getTypeId()) === false) {
            View::addFlash('movement', 'danger', MovementEntity::ERROR_NOT_LOC_IN_SKU);
            throw new RedirectException($this->getMovementRoute());
        }
        if ($error = Movements::validationMovements($SKU, $location_from, $location_to, $_GET['qty'])) {
            View::addFlash('movement', 'danger', $error);
            throw new RedirectException($this->getMovementRoute());
        }
        Movements::insertMovement(
            $_GET['id'],
            $_GET['qty'],
            $loc_from_id,
            $loc_to_id,
            $this->user->getId(),
            '',
            0,
            MovementEntity::NEW_MOVEMENT,
            $_GET['type']
        );
        throw new RedirectException( '/movement/create_movement?' . http_build_query($_GET));
    }

    private function savePalletMovement(Location $location_from, Location $location_to)
    {
        if (!$location_from->isMoveable()) {
            View::addFlash('movement', 'danger', 'Location is not moveable');
            throw new RedirectException($this->getMovementRoute());
        }
        if (!$location_from->isActive() || !$location_to->isActive()) {
            View::addFlash('movement', 'danger', 'Location cannot be used');
            throw new RedirectException($this->getMovementRoute());
        }
        if ($location_from->getId() === $location_to->getId()) {
            View::addFlash('movement', 'danger', 'The same location');
            throw new RedirectException($this->getMovementRoute());
        }
        $movement = Movements::createPalletMovement($location_from, $location_to, $this->user);
        if (!$movement || !empty($movement['error'])) {
            $message = !empty($movement['error']) ? $movement['error'] : 'Error moving pallet';
            View::addFlash('movement', 'danger', $message);
            throw new RedirectException($this->getMovementRoute());
        }
        View::addFlash('movement', 'success', 'Pallet has been moved');
        throw new RedirectException($this->getMovementRoute());
    }

    private function saveRequestMovement(?Location $location, string $type_location, ?SKU $SKU, int $qty)
    {
        if (!$location) {
            View::addFlash('movement request', 'danger', 'Source or destination location must be selected');
            throw new RedirectException($this->getMovementRoute());
        }
        if (!$location->isActive()) {
            View::addFlash('movement request', 'danger', 'Location cannot be used');
            throw new RedirectException($this->getMovementRoute());
        }
        if (!$SKU) {
            View::addFlash('movement request', 'danger', 'SKU not found');
            throw new RedirectException($this->getMovementRoute());
        }
        MovementRequests::createRequest($location, $type_location, $SKU, $qty, $this->user);
        View::addFlash('movement request', 'success', 'Movement request created');
        throw new RedirectException($this->getMovementRoute());
    }

    /** @throws RedirectException */
    public function deleteMovement()
    {
        if (empty($_POST))
            throw new RedirectException('/');
        $id = Request::getPositiveId($_POST);
        if (!$id) {
            View::addFlash('movement', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        Movements::delMovement($id, $this->user);
        View::addFlash('movement', 'warning', 'Movement deleted');
        throw new RedirectException($this->getMovementRoute());
    }

    public function getSKUData()
    {
        if (empty($_POST['barcode']))
            return $this->jsonResponse(['error' => 'Barcode is not found']);
        if (empty($_POST['supplier_id'])) {
            $parts = explode('_', $_POST['barcode']);
            $SKU = SKUs::getByBarcodeAndSupplier($parts[0], $parts[1]);
        } else {
            $SKU = SKUs::getByBarcodeAndSupplier($_POST['barcode'], $_POST['supplier_id']);
        }
        if (empty($SKU))
            return $this->jsonResponse(['error' => 'SKU not found']);
        $locs = SKULocations::getLocationWithSKU($this->warehouse_id, $SKU->getId());
        $locs_to = SKULocations::getLocationsFree($this->warehouse_id, $SKU);
        $locs_processing = Locations::getNonCountedTypesLocations($this->warehouse_id);
        $locs_trolleys  = Locations::getAllTrolleys($this->warehouse_id);
        return $this->jsonResponse([
            'photo' => "/image/public?path=" . $SKU->getPhoto(),
            'locs' => $locs,
            'locs_to' => $locs_to,
            'locs_processing' => $locs_processing,
            'locs_trolleys' => $locs_trolleys
        ]);
    }

    /** @throws RedirectException */
    public function restoreManuallyRemovedList()
    {
        if (!$this->user->hasManagerAccess())
            throw new RedirectException($this->getMovementRoute());
        $barcode = '';
        $movements = [];
        $total_count = 0;
        $current_page = 1;
        if (!empty($_GET['barcode'])) {
            $barcode = trim($_GET['barcode']);
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode($barcode);
            if (!empty($SKU_ids)) {
                list($movements, $total_count, $current_page) = MovementHistories::getManuallyRemovedList($SKU_ids, $this->warehouse_id, $this->warehouse_label, $_GET['c'] ?? 1);
                if (!empty($movements) && !empty($this->time_diff))
                    array_walk($movements, function (&$movement) {
                        $movement['create_date'] = $this->handleTimezone($movement['create_date']);
                        $movement['remove_date'] = $this->handleTimezone($movement['remove_date']);
                    });
            } else
                View::addFlash('restore_movement', 'danger', 'SKU not found');
        }
        $data = [
            'barcode' => $barcode,
            'movements' => $movements,
            'total_count' => $total_count,
            'total_pages' => ceil($total_count / MovementHistories::LIMIT_PER_PAGE),
            'current_page' => $current_page,
            'back' => $this->getMovementRoute()
        ];
        $this->render($data, 'movement/movement_restore');
    }

    public function restoreManuallyRemoved()
    {
        $id = Request::getPositiveId($_POST, 'movement_history_id');
        if (!$this->user->hasManagerAccess() || empty($id))
            return $this->response = ['result' => 'error', 'error' => 'Access denied'];
        $merge = !empty($_POST['merge']);
        $res = MovementHistories::handleRestore($id, $this->user, $merge);
        if (!$res[0] && $merge) {
            View::addFlash('restore_movement', 'danger', 'Merge rejected');
            return $this->response = ['result' => 'error'];
        }
        if (!$res[0] && !$res[1])
            return $this->response = ['result' => 'error', 'message' => $res[2]];
        if (!$res[0] && $res[1])
            return $this->response = ['result' => 'merge'];
        if ($res[0]) {
            View::addFlash('restore_movement', 'success', 'Success ' . (!$merge ? 'restored' : 'merged') . ' movement');
            return $this->response = ['result' => 'success'];
        }
        return $this->response = ['result' => 'error', 'error' => 'Error data'];
    }

    /** @throws RedirectException */
    public function doMovement()
    {
        if (!empty($_GET['clear']))
            if ($_GET['clear'] == 1) {
                $_SESSION['SKU'] = null;
                $_SESSION[Location::DH] = 0;
                $_SESSION[Location::PA] = 0;
            }
        if (!empty($_SESSION['SKU'])) {
            $this->movementSKU();
            return;
        }
        $res = Locations::getDHLocation($this->warehouse_id);
        if ($res !== true) {
            View::addFlash('movement', 'danger', $res);
            throw new RedirectException('/');
        }
        $res = Locations::getPALocation($this->warehouse_id);
        if ($res !== true) {
            View::addFlash('movement', 'danger', $res);
            throw new RedirectException('/');
        }
        $this->render([
            'back' => $this->getMovementRoute()
        ], 'movement/movement');
    }

    /** @throws RedirectException */
    public function movementSKU()
    {
        if (empty($_POST)) {
            if (!empty($_SESSION['SKU']))
                $SKU = $_SESSION['SKU'];
            else
                throw new RedirectException($this->getMovementRoute());
        } else {
            $SKU = SKUs::getByBarcodeAndSupplier($_POST['barcode'], $_POST['supplier_id']);
            $_SESSION['SKU'] = $SKU;
        }
        if (empty($SKU)) {
            View::addFlash('movement', 'danger', 'SKU not found');
            throw new RedirectException('/movement/do_movement');
        }
        $cart = Cart::getCart($this->user);
        $locs = SKULocations::getLocationWithSKU($this->warehouse_id, $SKU->getId(), $cart->getId());
        $locs_to = SKULocations::getLocationsFree($this->warehouse_id, $SKU);

        $location_from = !empty($_GET['loc_from_id']) ? Locations::getById($_GET['loc_from_id']) : null;

        $data = [
            'SKU' => $SKU,
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()),
            'cart' => $cart,
            'to_cart' => !empty($cart),
            'quantity' => 1,
            'locs' => $locs,
            'locs_to' => $locs_to,
            'location_from' => $location_from ? $location_from->getLocationReal($this->warehouse_label) : ''
        ];

        $this->render($data, 'movement/movement_SKU');
    }

    /** @throws RedirectException */
    public function movementDo()
    {
        if (empty($_GET))
            throw new RedirectException($this->getMovementRoute());
        $id = Request::getPositiveId();
        $loc_from_id = empty($_GET['loc_from_id']) ? 0 : (int)$_GET['loc_from_id'];
        $loc_to_id = empty($_GET['loc_to_id']) ? 0 : (int)$_GET['loc_to_id'];
        $qty = empty($_GET['qty']) ? 0 : (int)$_GET['qty'];
        $location_from = $loc_from_id ? Locations::getById($loc_from_id) : null;
        $location_to = $loc_to_id ? Locations::getById($loc_to_id) : null;
        $SKU = $_SESSION['SKU'] ?? null;
        if (empty($SKU))
            $SKU = SKUs::getById($id);
        if (!$SKU) {
            View::addFlash('movement', 'danger', 'SKU not found');
            throw new RedirectException($this->getMovementRoute());
        }
        if (!$location_to) {
            View::addFlash('movement', 'danger', 'Destination location not found');
            throw new RedirectException($this->getMovementRoute());
        }
        if (!$location_from) {
            View::addFlash('movement', 'danger', 'Source location not found');
            throw new RedirectException($this->getMovementRoute());
        }
        if (SKUPreffLocs::checkExistPreffLoc((int)$location_to->getWarehouse(), (int)$SKU->getId(), (int)$location_to->getId(), $location_to->getTypeId()) === false) {
            View::addFlash('movement', 'danger', MovementEntity::ERROR_NOT_LOC_IN_SKU);
            throw new RedirectException($this->getMovementRoute());
        }
        SKULocations::handleBatchMovement($SKU, $location_from, $location_to, function () {
            View::addFlash('movement', 'danger', 'SKU batches in this location cannot be mixed');
            throw new RedirectException($this->getMovementRoute());
        });
        if ($error = Movements::validationMovements($SKU, $location_from, $location_to, $qty)) {
            View::addFlash('movement', 'danger', $error);
            throw new RedirectException($this->getMovementRoute());
        }
        $res = SKULocations::createMovement($location_from, $location_to, $SKU, -1, $qty, 'movement', $this->user->getId(),
            Location::isForReservation($location_from, $location_to));

        if ($location_to->DH()) {
            $location_to = Locations::getByName(Locations::getWarehouseById($location_to->getId()), LOC_DH_M[0], LOC_DH_M[1], LOC_DH_M[2], LOC_DH_M[3]);
        }

        if (!$res[0])
            View::addFlash('movement', 'danger', $res[1]);
        else
            View::addFlash('movement', 'success', sprintf("SKU (%s) in the amount of %d moved to the location %s", SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()), $qty, $location_to->getLocation($this->warehouse_label)));

        throw new RedirectException('/movement/movement_SKU?'. http_build_query($_GET));
    }

    /** @throws */
    public function movementLoc()
    {
        if (!$_POST)
            throw new RedirectException('/');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['cart_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getMovementRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $raw_data = Movements::getByFilters($this->warehouse, ['loc_from_id' => $_POST['id'], 'statuses' => MovementEntity::WORKER_STATUSES]);
        $data = [
            'movements' => $raw_data['records'],
            'loc_from' => Locations::getById($_POST['id']),
            'cart' => $cart,
            'weight' => ($weight[Cart::NUM_MOVE]['weight'] ?? 0) + ($weight[Cart::NUM_EXPIRED]['weight'] ?? 0),
            'back' => $this->getMovementRoute()
        ];
        $this->render($data, 'movement/movement_bar');
    }

    /** @throws */
    public function movementBar()
    {
        if (!$_POST)
            throw new RedirectException('/');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['cart_id']);
        if (empty($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getMovementRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $raw_data = Movements::getByFilters($this->warehouse, ['SKU_id' => $_POST['id'], 'statuses' => MovementEntity::WORKER_STATUSES]);
        $data = [
            'movements' => $raw_data['records'],
            'SKU' => SKUs::getById((int)$_POST['id']),
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString((int)$_POST['id']),
            'cart' => $cart,
            'weight' => ($weight[Cart::NUM_MOVE]['weight'] ?? 0) + ($weight[Cart::NUM_EXPIRED]['weight'] ?? 0),
            'back' => $this->getMovementRoute()
        ];
        $this->render($data, 'movement/movement_loc');
    }

    /** @throws */
    public function movementGet()
    {
        if (!$_POST) {
            View::addFlash('movement', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $filters = [
            'SKU_id' => $_POST['SKU_id'],
            'loc_from_id' => $_POST['loc_id'],
            'loc_to_id' => $_POST['loc_to_id'],
        ];
        if (!empty($_POST['id']))
            $filters['completed'] = $this->user->getId();
        else
            $filters['statuses'] = MovementEntity::WORKER_STATUSES;
        $raw_data = Movements::getByFilters($this->warehouse, $filters);
        if (empty($raw_data['records'])) {
            View::addFlash('movement', 'danger', 'Movements not found');
            throw new RedirectException($this->getMovementRoute());
        }
        $movement = $raw_data['records'][0];
        Movements::take2Work($movement['id'], $this->user->getId());
        $cart = Locations::getById($_POST['cart_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getMovementRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $location_from = Locations::getById($filters['loc_from_id']);
        if (Location::isDH($location_from->getTypeId())) {
            $consists = ['reserved' => $movement['quantity'], 'replenish' => 0];
        } else {
            $consists = SKULocations::getConsistsSKUInLocation($filters['SKU_id'], $filters['loc_from_id']);
            if (empty($consists)) {
                View::addFlash('movement', 'danger', 'Source location is empty');
                throw new RedirectException($this->getMovementRoute());
            }
        }
        if (!empty($this->time_diff))
            $movement['create_date'] = $this->handleTimezone($movement['create_date']);
        $movement['create_date'] = WithDate::toFrontFormat($movement['create_date']);
        $data = [
            'movement' => $movement,
            'cart' => $cart,
            'weight' => ($weight[Cart::NUM_MOVE]['weight'] ?? 0) + ($weight[Cart::NUM_EXPIRED]['weight'] ?? 0),
            'consists' => $consists,
            'actual' => 1,
        ];
        $this->render($data, 'movement/movement_phase_A');
    }

    /** @throws RedirectException */
    public function movementClear()
    {
        if (!$_GET) {
            View::addFlash('movement', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        Movements::clearMovement(0, true, $_GET['id'], $this->user, 'clear');
        throw new RedirectException($this->getMovementRoute());
    }

    /** @throws RedirectException */
    public function movementPhaseA()
    {
        if (!$_POST) {
            View::addFlash('movement', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $_POST = array_map('intval', $_POST);
        $locFrom = Locations::getById($_POST['loc_from_id']);
        $locTo = Locations::getById($_POST['loc_to_id']);
        $cart = Cart::getCart($this->user);
        $SKU = SKUs::getById($_POST['SKU_id']);
        $res = SKULocations::createMovement(
            $locFrom, $cart, $SKU, -1, $_POST['quantity'], 'Movement to trolley', $this->user->getId(),
            Location::isForReservation($locFrom, $cart), Locations::isExpiredLocation($locTo)
        );
        if (!$res[0])
            View::addFlash('movement', 'danger', $res[1]);
        else {
            Movements::createMovementTrolley(
                $this->warehouse,
                $_POST['SKU_id'],
                $_POST['quantity'],
                $_POST['qty'] != $_POST['quantity'],
                $_POST['loc_to_id'],
                $_POST['id'],
                $cart,
                $this->user
            );
            View::addFlash('movement', 'success', 'Done');
        }
        throw new RedirectException($this->getMovementRoute());
    }

    /** @throws */
    public function movementCart()
    {
        if (!$_POST)
            throw new RedirectException('/');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['cart_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getMovementRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $movement_data = [
            'loc_from_id' => $_POST['cart_id'],
            'statuses' => MovementEntity::WORKER_STATUSES,
        ];
        if (!empty($_POST['SKU_id']))
            $movement_data['SKU_id'] = $_POST['SKU_id'];
        $raw_data = Movements::getByFilters($this->warehouse, $movement_data);
        $data = [
            'movements' => $raw_data['records'],
            'loc_from' => $cart,
            'weight' => ($weight[Cart::NUM_MOVE]['weight'] ?? 0) + ($weight[Cart::NUM_EXPIRED]['weight'] ?? 0),
            'back' => $this->getMovementRoute(),
        ];
        if (!empty($_POST['SKU_id'])) {
            $data['SKU'] = SKUs::getById($_POST['SKU_id']);
            $data['SKU_barcode'] = SKUBarcodes::getBarcodesBySKUIdString($_POST['SKU_id']);
        }
        $this->render($data, 'movement/movement_' . (empty($_POST['SKU_id']) ? 'bar_B' : 'dest'));
    }

    /** @throws */
    public function movementLocB()
    {
        if (!$_POST)
            throw new RedirectException('/');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['loc_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getMovementRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $movement_data = [
            'loc_from_id' => $_POST['loc_id'],
            'loc_to_id' => $_POST['loc_to_id'],
            'statuses' => MovementEntity::WORKER_STATUSES,
        ];
        $raw_data = Movements::getByFilters($this->warehouse, $movement_data);
        $data = [
            'movements' => $raw_data['records'],
            'loc_from' => $cart,
            'loc_to' => $_POST['loc_to_id'],
            'weight' => ($weight[Cart::NUM_MOVE]['weight'] ?? 0) + ($weight[Cart::NUM_EXPIRED]['weight'] ?? 0),
            'back' => $this->getMovementRoute(),
        ];
        $this->render($data, 'movement/movement_loc_B');
    }

    /** @throws */
    public function movementGetB()
    {
        if (!$_POST) {
            View::addFlash('movement', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $filters = [
            'SKU_id' => $_POST['SKU_id'],
            'loc_from_id' => $_POST['loc_id'],
            'loc_to_id' => $_POST['loc_to_id'],
            'completed' => $this->user->getId(),
        ];
        $raw_data = Movements::getByFilters($this->warehouse, $filters);
        if (empty($raw_data['records'])) {
            View::addFlash('movement', 'danger', 'Movements not found');
            throw new RedirectException($this->getMovementRoute());
        }
        $movement = $raw_data['records'][0];
        Movements::take2Work($movement['id'], $this->user->getId());
        $cart = Cart::getCart($this->user);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getMovementRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $consists = SKULocations::getConsistsSKUInLocation($filters['SKU_id'], $filters['loc_from_id']);
        if (empty($consists)) {
            View::addFlash('movement', 'danger', 'Source location is empty');
            throw new RedirectException($this->getMovementRoute());
        }
        if (!empty($this->time_diff))
            $movement['create_date'] = $this->handleTimezone($movement['create_date']);
        $movement['create_date'] = WithDate::toFrontFormat($movement['create_date']);
        $data = [
            'movement' => $movement,
            'cart' => $cart,
            'weight' => ($weight[Cart::NUM_MOVE]['weight'] ?? 0) + ($weight[Cart::NUM_EXPIRED]['weight'] ?? 0),
            'consists' => $consists,
            'actual' => 1,
        ];
        $this->render($data, 'movement/movement_phase_B');
    }

    /** @throws RedirectException */
    public function movementPhaseB()
    {
        if (!$_POST) {
            View::addFlash('movement', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }

        $_POST = array_map('intval', $_POST);
        $SKU = SKUs::getById($_POST['SKU_id']);
        $locFrom = Locations::getById($_POST['loc_from_id']);
        $locTo = Locations::getById($_POST['loc_to_id']);

        if ($locTo->storage()) {
            $checkEmptyMaxCapacity = SKULocations::hasSkuWithEmptyMaxCapacity($this->warehouse_id, $locTo);
            if ($checkEmptyMaxCapacity) {
                View::addFlash('movement', 'danger',
                    'Movement is impossible. There are items without specified dimensions in the destination location');
                throw new RedirectException("/movement/movement_clear/?id={$_POST['id']}");
            }
            $qty0 = SKULocations::getCountFreeInLocation($this->warehouse_id, $SKU, $locTo);
            if (empty($qty0)) {
                View::addFlash('movement', 'danger', "Movement is impossible. Not enough space");
                throw new RedirectException("/movement/movement_clear/?id={$_POST['id']}");
            }
            if ($qty0 < $_POST['quantity']) {
                View::addFlash('movement', 'danger', "Movement is impossible. Take $qty0 items maximum");
                throw new RedirectException("/movement/movement_clear/?id={$_POST['id']}");
            }
        }
        if (!$SKU) {
            View::addFlash('movement', 'danger', 'SKU not found');
            throw new RedirectException($this->getMovementRoute());
        }
        if (!$locTo) {
            View::addFlash('movement', 'danger', 'Destination location not found');
            throw new RedirectException($this->getMovementRoute());
        }
        if (!$locFrom) {
            View::addFlash('movement', 'danger', 'Source location not found');
            throw new RedirectException($this->getMovementRoute());
        }
        SKULocations::handleBatchMovement($SKU, $locFrom, $locTo, function () {
            View::addFlash('movement', 'danger', 'SKU batches in this location cannot be mixed');
            throw new RedirectException($this->getMovementRoute());
        });
        $res = SKULocations::createMovement(
            $locFrom, $locTo, $SKU, -1, $_POST['quantity'], 'Movement from trolley', $this->user->getId(),
            Location::isForReservation($locFrom, $locTo, true), Locations::isExpiredLocation($locTo)
        );
        if (!$res[0])
            View::addFlash('movement', 'danger', $res[1]);
        else {
            Movements::clearMovement($_POST['quantity'], $_POST['qty'] != $_POST['quantity'], $_POST['id'], $this->user, 'clear for phase B');
            View::addFlash('movement', 'success', 'Done');
        }
        throw new RedirectException($this->getMovementRoute());
    }

    /** @throws */
    public function movementDest()
    {
        if (!$_POST)
            throw new RedirectException('/');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['loc_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getMovementRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $movement_data = [
            'loc_from_id' => $_POST['loc_id'],
            'SKU_id' => $_POST['SKU_id'],
            'statuses' => MovementEntity::WORKER_STATUSES,
        ];
        $raw_data = Movements::getByFilters($this->warehouse, $movement_data);
        $data = [
            'movements' => $raw_data['records'],
            'loc_from' => $cart,
            'weight' => ($weight[Cart::NUM_MOVE]['weight'] ?? 0) + ($weight[Cart::NUM_EXPIRED]['weight'] ?? 0),
            'SKU' => SKUs::getById($_POST['SKU_id']),
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString($_POST['SKU_id']),
            'back' => $this->getMovementRoute()
        ];
        $this->render($data, 'movement/movement_dest');
    }

    public function requests()
    {
        $this->disableWarehouseChange();
        Movements::updateMovements((int)$this->warehouse_id);
        $this->mobile = false;
        $filters = $_GET;
        $raw_data = MovementRequests::getByFilters($this->warehouse_id, $filters);
        $_SESSION[Movements::FILTER_REQUEST_PARAM] = http_build_query($filters);
        $movements_filters = $filters;
        if (isset($movements_filters))
            unset($movements_filters['c']);
        $data = [
            'requests' => $raw_data['records'],
            'total_count' => $raw_data['total'],
            'total_pages' => ceil($raw_data['total'] / Movements::LIMIT_PER_PAGE),
            'current_page' => $raw_data['current_page'],
            'movements_filters' => http_build_query($movements_filters),
            'back' => $this->getMovementRoute(),
        ];
        $this->render($data, 'movement/movement_requests');
    }

    /** @throws RedirectException */
    public function cancelRequest()
    {
        if (empty($_POST))
            throw new RedirectException('/');
        $id = Request::getPositiveId($_POST);
        if (!$id) {
            View::addFlash('movement request', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        MovementRequests::cancelRequest($id, $this->user);
        View::addFlash('movement request', 'warning', 'Movement cancelled');
        throw new RedirectException($this->getMovementRoute());
    }

    /** @throws */
    public function getMovementData()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $id = Request::getPositiveId($_POST, 'id');
        $data = MovementRequests::getRequest($id);
        if (!$data)
            return $this->response = ['data' => 'Request is not found'];
        $data['photo'] = !empty($data['photo']) ? SKU::showPhotoSKU(urlencode($data['photo'])) : '';
        $locs = SKULocations::getLocationWithSKU($this->warehouse_id, $data['SKU_id']);
        if (MovementRequest::isFrom($data['type_location'])) {
            if (!Location::isDH($data['type_id'])) {
                if (empty($locs[$data['type_id']]))
                    return $this->response = ['data' => 'Source is not found'];
                $locs_type = $locs[$data['type_id']];
                if (empty($locs_type[$data['loc_name']]) || $locs_type[$data['loc_name']][0] !== $data['location_id'])
                    return $this->response = ['data' => 'Source is wrong'];
                $data['max_quantity'] = $locs_type[$data['loc_name']][1];
            }
            $locs_to = SKULocations::getLocationsFree($this->warehouse_id, new SKU(['id' => $data['SKU_id'], 'max_capacity' => $data['max_capacity']]));
            $locs_processing = Locations::getNonCountedTypesLocations($this->warehouse_id);
            $locs_trolleys = Locations::getAllTrolleys($this->warehouse_id);
            unset($data['max_capacity']);
            return $this->jsonResponse([
                'data' => $data,
                'locs' => $locs,
                'locs_to' => $locs_to,
                'locs_processing' => $locs_processing,
                'locs_trolleys' => $locs_trolleys,
            ]);
        }
        return $this->response = [
            'data' => $data,
            'locs' => $locs
        ];
    }

    public function getHistoryData()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $id = Request::getPositiveId($_POST, 'id');
        $data = MovementRequestHistories::getByRequestId($id);
        if (empty($data))
            return $this->response = ['error' => 'History of request is empty'];
        return $this->response = ['success' => $data];
    }

    public function createFromRequest()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$_POST || empty($_POST['id']) || empty($_POST['loc_id']) || empty($_POST['qty']) ||
            empty($_POST['mode']) || $_POST['mode'] !== Movements::CREATE_REQUEST)
            return $this->response = ['error' => 'Invalid information'];
        $request = MovementRequests::getById((int)$_POST['id']);
        if (!$request)
            return $this->response = ['error' => 'Invalid request'];
        $qty = (int)$_POST['qty'];
        if ($qty <= 0)
            return $this->response = ['error' => 'Quantity can be more than 0'];
        if ($qty > ($request->getQuantity() - $request->getAcceptedQuantity()))
            return $this->response = ['error' => "Quantity cannot be more than {$request->getQuantity()}"];
        $SKU = SKUs::getById($request->getSKUId());
        if (!$SKU)
            return $this->response = ['error' => 'SKU is not found'];
        $loc_id = $_POST['loc_id'];
        if ($loc_id === Location::DH) {
            $location = Locations::getByDiscrepancyHoldSTLocation($this->warehouse_id);
            if (!$location)
                return $this->response = ['error' => 'Invalid ' . Location::DH . ' location. Contact system administrator'];
            $location = $location[array_keys($location)[0]];
        } elseif ($loc_id === Location::PA) {
            $location = Locations::getByPALocation($this->warehouse_id);
            if (!$location)
                return $this->response = ['error' => 'Invalid ' . Location::PA . ' location. Contact system administrator'];
            $location = $location[array_keys($location)[0]];
        } else
            $location = Locations::getById((int)$loc_id);
        if ($request->from()) {
            $loc_from = Locations::getById($request->getLocationId());
            $loc_to = $location;
        } else {
            $loc_to = Locations::getById($request->getLocationId());
            $loc_from = $location;
        }
        if (!$loc_to)
            return $this->response = ['error' => 'Destination location is not found'];
        if (!$loc_from)
            return $this->response = ['error' => 'Source location is not found'];
        if ($loc_from->getId() === $loc_to->getId())
            return $this->response = ['error' => 'Same origin and destination location'];
        if ($loc_from->PR() && !($loc_to->notCounted() || $loc_to->cart()))
            return $this->response = ['error' => 'Movement is impossible. SKU can not be taken or put'];
        if (SKUPreffLocs::checkExistPreffLoc($this->warehouse_id, $SKU->getId(), $loc_to->getId(), $loc_to->getTypeId()) === false)
            return $this->response = ['error' => MovementEntity::ERROR_NOT_LOC_IN_SKU];
        if ($error = Movements::validationMovements($SKU, $loc_from, $loc_to, $qty))
            return $this->response = ['error' => $error];
        [, $movement_id] = Movements::insertMovement($SKU->getId(), $qty, $loc_from->getId(), $loc_to->getId(), $this->user->getId(),
            "Create movement from request #{$request->getId()}", 0, MovementEntity::NEW_MOVEMENT,
            $loc_from->PR() || $loc_from->PR() ? MovementEntity::TYPE_UNRESTRICTED : MovementEntity::TYPE_STANDARD
        );
        MovementRequests::processingRequest($request, $movement_id, $qty, $this->user);
        View::addFlash('movement request', 'success', 'Done');
        $_GET['mode'] = $_POST['mode'];
        return $this->response = ['success' => $this->getMovementRoute()];
    }
}
