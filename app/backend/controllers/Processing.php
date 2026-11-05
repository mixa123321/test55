<?php

namespace app\backend\controllers;

use app\backend\models\Cart;
use app\backend\models\Locations;
use app\backend\models\Movements;
use app\backend\models\Pagination;
use app\backend\models\Processings;
use app\backend\models\SKUBarcodes;
use app\backend\models\SKULocations;
use app\backend\models\SKUs;
use app\core\entities\Location;
use app\core\entities\Movement;
use app\core\exceptions\RedirectException;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\statical\WithDate;

class Processing extends AuthController
{
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->mobile = true;
    }

    private function getProcessingRoute(): string
    {
        $filters = [];
        if (!empty($_SESSION[Processings::FILTER_PARAM]))
            parse_str(urldecode($_SESSION[Processings::FILTER_PARAM]), $filters);
        return $this->route('index', null, $filters);
    }

    public function index()
    {
        Movements::updateUnrestictedMovements($this->warehouse_id);
        if ($this->user->hasManagerAccess()) {
            $this->processingAdmin();
            return;
        }
        $cart = Cart::getCart($this->user);
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $raw_data = Movements::getUnrestrictedMovementByWorker($this->warehouse_id, $this->user->getId());
        $data = [
            'processings' => $raw_data,
            'cart' => $cart,
            'weight' => $weight[Cart::NUM_MOVE]['weight'] ?? 0,
            'back' => $this->getProcessingRoute()
        ];
        $this->render($data, 'processing/processing_worker');
    }

    private function processingAdmin()
    {
        $this->mobile = false;
        $filters = $_GET;
        $raw_data = Processings::getByFilters($this->warehouse, $filters);
        $_SESSION[Processings::FILTER_PARAM] = http_build_query($filters);
        $processings_filters = $filters;
        if (isset($processings_filters))
            unset($processings_filters['c']);
        $records = $raw_data['records'];
        if (!empty($records) && !empty($this->time_diff))
            array_walk($records, function(&$record) {
                $record['create_date'] = $this->handleTimezone($record['create_date']);
            });
        $data = [
            'processings' => $records,
            'total_count' => $raw_data['total'],
            'total_pages' => ceil($raw_data['total'] / Pagination::LIMIT_PER_PAGE_BIG),
            'current_page' => $raw_data['current_page'],
            'processings_filters' => http_build_query($processings_filters),
        ];
        $this->render($data, 'processing/processing_admin');
    }

    /** @throws RedirectException */
    public function deleteMovement()
    {
        if (empty($_POST))
            throw new RedirectException('/forbidden');
        $id = Request::getPositiveId($_POST);
        if (!$id) {
            View::addFlash('processing', 'danger', 'Invalid information');
            throw new RedirectException('/forbidden');
        }
        Movements::delMovement($id, $this->user);
        View::addFlash('processing', 'warning', 'Movement deleted');
        throw new RedirectException($this->getProcessingRoute());
    }

    /** @throws */
    public function createMovement()
    {
        $this->disableWarehouseChange();
        $res = Locations::getDHLocation($this->warehouse_id);
        if ($res !== true) {
            View::addFlash('processing', 'danger', $res);
            throw new RedirectException('/');
        }
        $res = Locations::getPALocation($this->warehouse_id);
        if ($res !== true) {
            View::addFlash('processing', 'danger', $res);
            throw new RedirectException('/');
        }
        $SKUs = SKUs::getAll($this->warehouse_id);
        $data = [
            'SKUs' => $SKUs,
            'quantity' => 1,
            'available_skus' => View::toJs(Movements::getSKUs4Search($SKUs)),
            'skus_info' => View::toJs(Movements::getInfoSKUs($SKUs)),
            'back' => $this->getProcessingRoute()
        ];
        $_SESSION['__hp'] = sha1('__ha');
        $this->render($data, 'processing/create_movement');
    }

    /** @throws */
    public function isProcessing()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_GET) || empty($_GET['SKU_id']) || empty($_GET['qty']) || empty($_GET['loc_from']) || empty($_GET['loc_to']))
            return $this->response = ['error' => 'Invalid data'];
        $SKU = SKUs::getById((int)$_GET['SKU_id']);
        if (empty($SKU))
            return $this->response = ['error' => 'SKU is not found'];
        $qty = (int)$_GET['qty'];
        if ($qty <= 0)
            return $this->response = ['error' => 'Quantity must be greater than 0'];
        $loc_from = Locations::getByScan($this->warehouse_id, addslashes($_GET['loc_from']));
        if (is_null($loc_from))
            return $this->response = ['error' => 'Origin location is not found'];
        $loc_to = Locations::getByScan($this->warehouse_id, addslashes($_GET['loc_to']));
        if (is_null($loc_to))
            return $this->response = ['error' => 'Destination location is not found'];
        if ($loc_from->getId() == $loc_to->getId())
            return $this->response = ['error' => 'Locations must be different'];

        if (!($loc_from->allowed() || $loc_from->PR()) || !$loc_to->notCounted()) {
            return $this->response = ['error' => 'Processing movement cannot be created for these locations'];
        }

        $quantity = SKULocations::getQuantity($SKU->getId(), $loc_from->getId());
        if (!$quantity)
            return $this->response = ['error' => 'SKU is not found in origin location'];
        if ($quantity < $qty)
            return $this->response = ['error' => "There are not enough SKUs in the origin location. In location only $quantity unit(s)"];
        $res = Movements::insertUnrestrictedMovement($SKU->getId(), $qty, $loc_from->getId(), $loc_to->getId(), $this->user->getId());
        if ($res !== true)
            return $this->response = ['error' => $res];
        return $this->response = ['success' => $quantity];
    }

    /** @throws */
    public function processingLoc()
    {
        if (!$_POST)
            throw new RedirectException('/forbidden');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['cart_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getProcessingRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $raw_data = Processings::getByFilters($this->warehouse, ['loc_from_id' => $_POST['id'], 'statuses' => [Movement::NEW_MOVEMENT]]);
        $data = [
            'processings' => $raw_data['records'],
            'loc_from' => Locations::getById($_POST['id']),
            'cart' => $cart,
            'weight' => $weight[Cart::NUM_MOVE]['weight'] ?? 0,
            'back' => $this->getProcessingRoute()
        ];
        $this->render($data, 'processing/processing_bar');
    }

    /** @throws */
    public function processingBar()
    {
        if (!$_POST)
            throw new RedirectException('/forbidden');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['cart_id']);
        if (empty($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getProcessingRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $raw_data = Processings::getByFilters($this->warehouse, ['SKU_id' => $_POST['id'], 'statuses' => [Movement::NEW_MOVEMENT]]);
        $data = [
            'processings' => $raw_data['records'],
            'SKU' => SKUs::getById((int)$_POST['id']),
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString((int)$_POST['id']),
            'cart' => $cart,
            'weight' => $weight[Cart::NUM_MOVE]['weight'] ?? 0,
            'back' => $this->getProcessingRoute()
        ];
        $this->render($data, 'processing/processing_loc');
    }

    /** @throws */
    public function processingGet()
    {
        if (!$_POST) {
            View::addFlash('processing', 'danger', 'Invalid information');
            throw new RedirectException('/forbidden');
        }
        $filters = [
            'SKU_id' => $_POST['SKU_id'] ?? 0,
            'loc_from_id' => $_POST['loc_id'],
            'loc_to_id' => $_POST['loc_to_id'],
        ];
        if (!empty($_POST['id']))
            $filters['completed'] = $this->user->getId();
        else
            $filters['statuses'] = [Movement::NEW_MOVEMENT];
        $raw_data = Processings::getByFilters($this->warehouse, $filters);
        if (empty($raw_data['records'])) {
            View::addFlash('processing', 'danger', 'Movements not found');
            throw new RedirectException($this->getProcessingRoute());
        }
        $movement = $raw_data['records'][0];
        Movements::take2Work($movement['id'], $this->user->getId());
        $cart = Locations::getById($_POST['cart_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getProcessingRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $consists = SKULocations::getConsistsSKUInLocation($filters['SKU_id'], $filters['loc_from_id']);
        if (empty($consists)) {
            View::addFlash('processing', 'danger', 'Source location is empty');
            throw new RedirectException($this->getProcessingRoute());
        }
        if (!empty($this->time_diff))
            $movement['create_date'] = $this->handleTimezone($movement['create_date']);
        $movement['create_date'] = WithDate::toFrontFormat($movement['create_date']);
        $data = [
            'movement' => $movement,
            'cart' => $cart,
            'weight' => $weight[Cart::NUM_MOVE]['weight'] ?? 0,
            'consists' => $consists,
            'actual' => 1,
        ];
        $this->render($data, 'processing/processing_phase_A');
    }

    /** @throws RedirectException */
    public function processingClear()
    {
        if (!$_GET) {
            View::addFlash('processing', 'danger', 'Invalid information');
            throw new RedirectException('/forbidden');
        }
        Movements::clearMovement(0, true, $_GET['id'], $this->user, 'clear');
        throw new RedirectException($this->getProcessingRoute());
    }

    /** @throws RedirectException */
    public function processingPhaseA()
    {
        if (!$_POST) {
            View::addFlash('processing', 'danger', 'Invalid information');
            throw new RedirectException('/forbidden');
        }
        $_POST = array_map('intval', $_POST);
        $loc_from = Locations::getById($_POST['loc_from_id']);
        $cart = Cart::getCart($this->user);
        $SKU = SKUs::getById($_POST['SKU_id']);
        $res = SKULocations::createUnrestrictedMovement($loc_from, $cart, $SKU, $_POST['quantity'], 'Processing movement to trolley', $this->user->getId());
        if (!$res[0])
            View::addFlash('processing', 'danger', $res[1]);
        else {
            Movements::createUnrestrictedMovementTrolley(
                $_POST['SKU_id'],
                $_POST['quantity'],
                $_POST['qty'] != $_POST['quantity'],
                $_POST['loc_to_id'],
                $_POST['id'],
                $cart,
                $this->user
            );
            View::addFlash('processing', 'success', 'Done');
        }
        throw new RedirectException($this->getProcessingRoute());
    }

    /** @throws */
    public function processingCart()
    {
        if (!$_POST)
            throw new RedirectException('/forbidden');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['cart_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getProcessingRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $movement_data = [
            'loc_from_id' => $_POST['cart_id'],
            'statuses' => [Movement::NEW_MOVEMENT],
        ];
        if (!empty($_POST['SKU_id']))
            $movement_data['SKU_id'] = $_POST['SKU_id'];
        $raw_data = Processings::getByFilters($this->warehouse, $movement_data);
        $data = [
            'processings' => $raw_data['records'],
            'loc_from' => $cart,
            'weight' => $weight[Cart::NUM_MOVE]['weight'] ?? 0,
            'back' => $this->getProcessingRoute(),
        ];
        if (!empty($_POST['SKU_id'])) {
            $data['SKU'] = SKUs::getById($_POST['SKU_id']);
            $data['SKU_barcode'] = SKUBarcodes::getBarcodesBySKUIdString($_POST['SKU_id']);
        }
        $this->render($data, 'processing/processing_' . (empty($_POST['SKU_id']) ? 'bar_B' : 'dest'));
    }

    /** @throws */
    public function processingDest()
    {
        if (!$_POST)
            throw new RedirectException('/forbidden');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['loc_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getProcessingRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $movement_data = [
            'loc_from_id' => $_POST['loc_id'],
            'SKU_id' => $_POST['SKU_id'],
            'statuses' => [Movement::NEW_MOVEMENT],
        ];
        $raw_data = Processings::getByFilters($this->warehouse, $movement_data);
        $data = [
            'processings' => $raw_data['records'],
            'loc_from' => $cart,
            'weight' => $weight[Cart::NUM_MOVE]['weight'] ?? 0,
            'SKU' => SKUs::getById($_POST['SKU_id']),
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString($_POST['SKU_id']),
            'back' => $this->getProcessingRoute()
        ];
        $this->render($data, 'processing/processing_dest');
    }

    /** @throws */
    public function processingLocB()
    {
        if (!$_POST)
            throw new RedirectException('/forbidden');
        /** @var Location $cart */
        $cart = Locations::getById($_POST['loc_id']);
        if (is_null($cart))
            throw new RedirectException('/cart/movement/?back=' . $this->getProcessingRoute());
        $weight = SKULocations::getWeight($this->warehouse_id, $cart->getId());
        $movement_data = [
            'loc_from_id' => $_POST['loc_id'],
            'loc_to_id' => $_POST['loc_to_id'],
            'statuses' => [Movement::NEW_MOVEMENT],
        ];
        $raw_data = Processings::getByFilters($this->warehouse, $movement_data);
        $data = [
            'processings' => $raw_data['records'],
            'loc_from' => $cart,
            'loc_to' => $_POST['loc_to_id'],
            'weight' => $weight[Cart::NUM_MOVE]['weight'] ?? 0,
            'back' => $this->getProcessingRoute(),
        ];
        $this->render($data, 'processing/processing_loc_B');
    }

    /** @throws */
    public function processingGetB()
    {
        if (!$_POST) {
            View::addFlash('processing', 'danger', 'Invalid information');
            throw new RedirectException('/forbidden');
        }
        $filters = [
            'SKU_id' => $_POST['SKU_id'],
            'loc_from_id' => $_POST['loc_id'],
            'loc_to_id' => $_POST['loc_to_id'],
            'completed' => $this->user->getId(),
        ];
        $raw_data = Processings::getByFilters($this->warehouse, $filters);
        if (empty($raw_data['records'])) {
            View::addFlash('processing', 'danger', 'Movements not found');
            throw new RedirectException($this->getProcessingRoute());
        }
        $movement = $raw_data['records'][0];
        Movements::take2Work($movement['id'], $this->user->getId());
        $consists = SKULocations::getConsistsSKUInLocation($filters['SKU_id'], $filters['loc_from_id']);
        if (empty($consists)) {
            View::addFlash('processing', 'danger', 'Source location is empty');
            throw new RedirectException($this->getProcessingRoute());
        }
        if (!empty($this->time_diff))
            $movement['create_date'] = $this->handleTimezone($movement['create_date']);
        $movement['create_date'] = WithDate::toFrontFormat($movement['create_date']);
        $data = [
            'movement' => $movement,
            'consists' => $consists,
            'actual' => 1,
        ];
        $this->render($data, 'processing/processing_phase_B');
    }

    /** @throws RedirectException */
    public function processingPhaseB()
    {
        if (!$_POST) {
            View::addFlash('processing', 'danger', 'Invalid information');
            throw new RedirectException('/forbidden');
        }
        $_POST = array_map('intval', $_POST);
        $SKU = SKUs::getById($_POST['SKU_id']);
        if (!$SKU) {
            View::addFlash('processing', 'danger', 'SKU not found');
            throw new RedirectException($this->getProcessingRoute());
        }
        $locTo = Locations::getById($_POST['loc_to_id']);
        if (!$locTo) {
            View::addFlash('processing', 'danger', 'Destination location not found');
            throw new RedirectException($this->getProcessingRoute());
        }
        $locFrom = Locations::getById($_POST['loc_from_id']);
        if (!$locFrom) {
            View::addFlash('processing', 'danger', 'Source location not found');
            throw new RedirectException($this->getProcessingRoute());
        }
        $res = SKULocations::createUnrestrictedMovement($locFrom, $locTo, $SKU, $_POST['quantity'], 'Processing movement from trolley', $this->user->getId());
        if (!$res[0])
            View::addFlash('processing', 'danger', $res[1]);
        else {
            Movements::clearMovement($_POST['quantity'], $_POST['qty'] != $_POST['quantity'], $_POST['id'], $this->user, 'clear for phase B');
            View::addFlash('processing', 'success', 'Done');
        }
        throw new RedirectException($this->getProcessingRoute());
    }
}