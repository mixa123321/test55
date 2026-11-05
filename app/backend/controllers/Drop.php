<?php

namespace app\backend\controllers;

use app\backend\models\ShippingServices;
use app\core\db\DBException;
use app\core\dbal\SafeMysql;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\helpers\Debug;
use app\core\models\Batches;
use app\core\models\OrderLocations;
use app\core\models\OrderLocationTemporaries;
use app\core\helpers\View;
use app\core\helpers\Filter;
use app\core\exceptions\RedirectException;
use app\backend\models\Cart;
use app\backend\models\Locations;
use app\backend\models\Orders;
use app\backend\models\OrderSKUs;
use app\backend\models\OrderHistories;
use app\backend\models\Movements_;
use app\backend\models\SKUs;
use app\backend\models\SKULocations;
use app\backend\models\SKUHistories;
use app\backend\models\Pick as PickModel;
use app\backend\models\Drop as DropModel;
use app\backend\models\Pack as PackModel;
use app\backend\models\Users;
use app\backend\models\SKUBarcodes;
use app\core\models\Warehouses;

class Drop extends AuthController
{
    const DEFAULT_FILTERS = [
        'services' => Filter::NOT_SELECTED,
        'floor' => Filter::NOT_SELECTED
    ];

    /** @throws RedirectException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->mobile = $this->user->isRoleWorker();
    }

    /** @throws */
    public function droppingS()
    {
        $this->dropping('single', empty($_GET['cont']) ? '' : $_GET['cont']);
    }

    /** @throws */
    public function droppingM()
    {
        $this->dropping('multi', empty($_GET['cont']) ? '' : $_GET['cont']);
    }

    /** @throws */
    public function droppingL()
    {
        $this->dropping('large', empty($_GET['cont']) ? '' : $_GET['cont']);
    }

    /** @throws */
    private function dropping($mode, $cont)
    {
        $cart = $sort_services = null;
        $weight = 0;
        $is_mezzanine = (bool)(Warehouses::getById($this->warehouse_id))->getMezzanine() ?? false;
        $mezzanine = null;
        
        $floors = $is_mezzanine ? array_combine(Warehouses::$available_floors, Warehouses::$available_floors) : [];
        $sort_services = ShippingServices::getSequentialCollectionTimes($this->warehouse_id);
        
        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS, !empty($_GET['clear']), [
            'floor' => array_keys($floors)
        ]);
        
        if ($mode === 'multi' && $is_mezzanine && !empty($filters['floor']))
            $mezzanine = $filters['floor'] == Warehouses::$available_floors[1];
            
        if ($this->user->isRoleWorker()) {
            $cart = Cart::getCart($this->user);
            $modes = Cart::getCartInfo($cart);
            if (!empty($modes['none']) || empty($modes[$mode])) {
                if (!empty($cont))
                    throw new RedirectException('/cart');
                View::addFlash('dropping', 'warning', "There's nothing to $mode drop from this trolley");
                throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
            }
            $weight = $modes[$mode];
            list($SKUs, ) = SKULocations::getDroppingByWorker($mode, $cart->getId(), $mezzanine, $filters['services']);
        } else {
            $SKUs = SKULocations::getDropping($this->user->isRoleManager() ? Users::getAllByParentId($this->warehouse_id, $this->user->getId())
                : -1,
                $this->warehouse_id,
                $mode,
                true,
                $mezzanine,
                $filters['services']);
            $c = empty($_GET['c']) ? 1 : (int)$_GET['c'];
            $c = $c < 1 ? 1 : $c;
            $total_count = count($SKUs);
            $total_pages = ceil($total_count / DropModel::LIMIT_PER_PAGE);
            if ($c > $total_pages)
                $c = 1;
            $SKUs = array_slice($SKUs, DropModel::LIMIT_PER_PAGE * ($c - 1), DropModel::LIMIT_PER_PAGE, true);
        }
        
        if ($is_mezzanine) {
            $floors = [Filter::NOT_SELECTED => 'Select floor'] + $floors;
        }
        
        $data = [
            'mode' => $mode,
            'SKUs' => $SKUs,
            'weight' => $weight,
            'cart' => $cart,
            'is_mezzanine' => $is_mezzanine,
            'floors' => $floors,
            'total_count' => $total_count ?? null,
            'total_pages' => $total_pages ?? null,
            'current_page' => $c ?? null,
            'sort_services' => $sort_services,
            'filters' => $filters
        ];
        $this->render($data, 'dropping/dropping_' . (!$this->user->isRoleWorker() ? 'admin' : substr($mode,0,1)));
    }

    /** @throws */
    public function droppingTable()
    {
        $cart = Cart::getCart($this->user);
        if (empty($_POST['search']) || empty($_POST['mode']) || empty($cart)) {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $mode = addslashes($_POST['mode']);
        $back = '/drop/dropping_' . substr($mode, 0, 1);
        if ($mode != PackModel::PACKING_SINGLE && $mode != PackModel::PACKING_LARGE) {
            View::addFlash('dropping', 'danger', 'You cannot drop this trolley');
            throw new RedirectException("/cart/dropping/?back=$back");
        }
        if (empty(SKULocations::getQuantityInLocation($cart->getId()))) {
            View::addFlash('dropping', 'danger', 'Trolley is empty');
            throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
        }
        $modes = Cart::getCartInfo($cart);

        if (!empty($modes['none']) || empty($modes[$mode])) {
            View::addFlash('dropping', 'warning', "There's nothing to $mode drop from this trolley");
            throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
        }
        $weight = $modes[$mode];
        if ($mode != $_POST['mode']) {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $tables = $mode === PackModel::PACKING_SINGLE ? Locations::getTable($this->warehouse_id)
                                     : Locations::getLargeTable($this->warehouse_id);
        $search = addslashes($_POST['search']);
        $l = Locations::getByScan($this->warehouse_id, htmlentities($search));
        $i = -1;
        foreach ($tables as $key => $table)
            if (!is_null($l) && $table['id'] == $l->getId()) {
                $i = $key;
                break;
            }
        if ($i == -1) {
            View::addFlash('dropping', 'danger', 'Packing table not found or busy');
            throw new RedirectException($back);
        }
        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS);
        list($SKUs, ) = SKULocations::getDroppingByWorker($mode, $cart->getId(), null, $filters['services']);
        $leave = (int)($tables[$i]['cart'] == -1
                    && !empty($this->user->getCartId()))
                        && !array_key_exists($mode === PackModel::PACKING_SINGLE ? PackModel::PACKING_LARGE : PackModel::PACKING_SINGLE, $modes);
        $data = [
            'SKUs' => $SKUs,
            'weight' => $weight,
            'cart' => $cart,
            'mode' => $mode,
            'location' => new Location($tables[$i]),
            'leave' => $leave,
        ];
        $this->render($data, 'dropping/dropping_table');
    }

    /** @throws */
    public function droppingUnload()
    {
        if (empty($_POST['id']) || empty($_POST['mode'])) {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $mode = addslashes($_POST['mode']);
        if ($mode != 'single' && $mode != 'large') {
            View::addFlash('dropping', 'danger', 'You cannot unload this trolley');
            throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
        }
        $cart = Cart::getCart($this->user);
        $modes = Cart::getCartInfo($cart);
        if (!empty($modes['none']) || empty($modes[$mode])) {
            View::addFlash('dropping', 'warning', "There's nothing to $mode drop from this trolley");
            throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
        }
        if (empty(SKULocations::getQuantityInLocation($cart->getId()))) {
            View::addFlash('dropping', 'danger', 'Trolley is empty');
            throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
        }
        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS);
        $location_id = (int)$_POST['id'];
        $ords = $SKUs = [];
        list(, $orders) = SKULocations::getDroppingByWorker($mode, $cart->getId(), null, $filters['services']);
        $status = '2' . PickModel::$mode_val[$mode][0];
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                $successes = [];
                foreach ($orders as $id => $order) {
                    list(, $SKU_id, $batch_id) = explode('_', $id);
                    $SKUs[$SKU_id] = 1;
                    if ($batch_id == '.')
                        $batch_id = '';
                    foreach ($order as $order_id => $quantity) {
                        OrderLocationTemporaries::addSKU($SKU_id, $quantity, $order_id, $location_id);
                        OrderLocations::reserveLocation($order_id, $location_id);
                        $move_id = Movements_::setDropping($location_id, $order_id, $SKU_id, $batch_id, $quantity, $this->user->getId());
                        SKUHistories::addDropping($cart->getId(), $location_id, $order_id, $SKU_id, $batch_id, $quantity, $move_id, $this->user->getId(), $this->warehouse_id);
                        PickModel::addOnTable($SKU_id, $batch_id, $quantity, $status, $order_id, $location_id, $cart->getId(), $this->user->getId());
                        $successes[] = "SKU (#$SKU_id" . (empty($batch_id) ? '' : ", Batch#: $batch_id") . "), quantity $quantity, order #$order_id, is unloaded to packing station";
                        if ($mode === 'single')
                            Orders::changeStatus($order_id, Order::AWAITING_PACK, $this->user->getId());
                        if ($mode !== 'single')
                            $ords[$order_id] = 1;
                    }
                }
                View::addFlash('dropping','success', $successes);
                foreach ($ords as $order_id => $tmp)
                    if (OrderSKUs::isCompleted($order_id))
                        Orders::changeStatus($order_id, Order::AWAITING_PACK, $this->user->getId());
                foreach ($SKUs as $SKU_id => $tmp)
                    SKUHistories::updateStock($SKU_id, $this->warehouse_id);
                $mysql->commit();
            } catch (\Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++))
                    continue;
                View::addFlash('dropping', 'danger', 'Server error');
                Debug::sendDebugInfo('droppingUnload', var_export([$e->getCode(), $e->getMessage()], 1));
            }
            break;
        }
        $modes = Cart::getCartInfo($cart);
        if (!empty($modes['none']) || empty($modes[$mode]))
            throw new RedirectException('/cart');
        throw new RedirectException('/drop/dropping_' . substr($mode, 0, 1));
    }

    /** @throws */
    public function droppingLeave()
    {
        if (empty($_POST['id']) || empty($_POST['mode'])) {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $mode = addslashes($_POST['mode']);
        if ($mode != 'single' && $mode != 'large') {
            View::addFlash('dropping', 'danger', 'You cannot leave this trolley');
            throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
        }
        $cart = Cart::getCart($this->user);
        if (empty($cart)) {
            View::addFlash('dropping', 'danger', 'You do not have a trolley');
            throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
        }
        try {
            Cart::leaveTrolleyNearPackingTable($cart, $this->user, $this->warehouse, $mode);
        } catch (\Exception $e) {
            switch ($e->getCode()) {
                case 1:
                    View::addFlash('dropping', 'warning', $e->getMessage());
                    throw new RedirectException('/drop/dropping_' . substr($mode, 0, 1));
                case 2:
                    View::addFlash('dropping', 'danger', $e->getMessage());
                    throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
                default:
                    View::addFlash('dropping', 'danger', 'Server error');
                    Debug::sendDebugInfo('droppingLeave', var_export([$e->getCode(), $e->getMessage()], 1));
                    throw new RedirectException('/drop/dropping_' . substr($mode, 0, 1));
            }
        }
        throw new RedirectException('/cart');
    }

    /** @throws */
    public function droppingGet()
    {
        $data = !empty($_POST) ? $_POST : $_GET;
        if (empty($data['search']) || empty($data['mode']) || $data['mode'] != 'multi') {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException(empty($data['mode']) ? '/' : ('/drop/dropping_' . substr($data['mode'], 0, 1)));
        }
        $mode = addslashes($data['mode']);
        $filters = Filter::processFilters($this->warehouse_id, $data, self::DEFAULT_FILTERS);
        $back = '/drop/dropping_' . substr($mode, 0, 1) . '?' . http_build_query(['filter' => $filters]);
        $cart = Cart::getCart($this->user);
        if (empty(SKULocations::getQuantityInLocation($cart->getId()))) {
            View::addFlash('dropping', 'danger', 'Trolley is empty');
            throw new RedirectException("/cart/dropping/?back=$back");
        }
        $modes = Cart::getCartInfo($cart);
        if (!empty($modes['none']) || empty($modes[$mode])) {
            View::addFlash('dropping', 'warning', "There's nothing to $mode drop from this trolley");
            throw new RedirectException('/cart/dropping/?back=/drop/dropping_' . substr($mode, 0, 1));
        }
        $search = $data['search'];
        $l = Locations::getByScan($this->warehouse_id, htmlentities($search));
        $SKU_id = (int)$data['SKU_id'];
        $batch_id = (int)$data['batch_id'];
        $mezzanine = null;
        if (!empty($filters['floor']) && $filters['floor'] != Filter::NOT_SELECTED && in_array($filters['floor'], Warehouses::$available_floors))
            $mezzanine = $filters['floor'] === Warehouses::$available_floors[1];
        list($SKUall, ) = SKULocations::getDroppingByWorker($mode, $cart->getId(), $mezzanine, $filters['services']);
        $loc = false;
        $bar = false;
        $SKUs = $SKUall;
        foreach ($SKUall as $key => $SKU) {
            if (!is_null($l) && $SKU['location_id'] == $l->getId()) {
                $loc = true;
                continue;
            }
            if (in_array($search, $SKU['barcodes']))
                if ($SKU_id == $SKU['id'] && $batch_id == $SKU['batch_id']) {
                    $bar = true;
                    continue;
                }
            unset($SKUs[$key]);
        }
        if (empty($SKUs)) {
            View::addFlash('dropping', 'danger', "Barcode or location not found");
            throw new RedirectException($back);
        }
        $loc_name = Location::getLocationName($this->warehouse_label, $SKUall[0]['type_id'], $SKUall[0]['zone'], $SKUall[0]['column'], $SKUall[0]['row']);
        if ($loc && $l->getId() != $SKUall[0]['location_id'])
            View::addFlash('dropping', 'warning', "It's recommended to select the first location ($loc_name) in the list");
        if ($bar && $SKUall[0]['barcode'] != $search && count($SKUall) > 1)
            View::addFlash('dropping', 'warning', "It's recommended to select a product from the first location ($loc_name) in the list");
        $data = [
            'SKUs' => array_values($SKUs),
            'mode' => $mode,
            'cart' => $cart,
            'back' => $back,
            'is_multi_dropping' => (bool)$this->warehouse->getMultiDropping()
        ];
        $this->render($data, 'dropping/dropping_' . ($loc ? 'loc' : 'bar'));
    }

    public function droppingMultiBar()
    {
        $back = $_POST['back'];
        $cart = Cart::getCart($this->user);
        $params = parse_url($back);
        $mode = addslashes($_POST['mode']);
        $message_success = [];

        parse_str($params['query'] ?? '', $params);
        $filters = Filter::processFilters($this->warehouse_id, $params, self::DEFAULT_FILTERS);

        if (empty(SKULocations::getQuantityInLocation($cart->getId()))) {
            View::addFlash('dropping', 'danger', 'Trolley is empty');
            throw new RedirectException("/cart/dropping/?back=$back");
        }
        $modes = Cart::getCartInfo($cart);
        if (!empty($modes['none']) || empty($modes[$mode])) {
            View::addFlash('dropping', 'warning', "There's nothing to $mode drop from this trolley");
            throw new RedirectException("/cart/dropping/?back=$back");
        }

        $mezzanine = null;
        if (!empty($filters['floor']) && $filters['floor'] != Filter::NOT_SELECTED && in_array($filters['floor'], Warehouses::$available_floors))
            $mezzanine = $filters['floor'] === Warehouses::$available_floors[1];

        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        try {
            foreach ($_POST['checkSKU'] as $item) {
                if (isset($item['sku'])) {
                    $SKU_id = $item['sku'];
                    $quantity = $item['quantity'];
                    $loc_id = $_POST['location_id'];
                    $batch_id = (int) ($item['batch_id'] ?? 0);
                    $batch = Batches::getById($batch_id);

                    list(, $orders) = SKULocations::getDroppingByWorker($mode, $cart->getId(), $mezzanine, $filters['services']);
                    $index = $loc_id . '_' . $SKU_id . '_' . (empty($batch_id) ? '.' : $batch_id);

                    if (empty($orders[$index])) {
                        View::addFlash('picking', 'danger', "No orders or more than one found for dropping!");
                        throw new RedirectException($back);
                    }
                    $orders = $orders[$index];
                    $order_id = array_keys($orders)[0];

                    OrderLocationTemporaries::addSKU($SKU_id, $item['quantity'], $order_id, $loc_id);
                    if (!PickModel::addDropping($SKU_id, $batch_id, $quantity, true, $order_id, $loc_id, $cart->getId(), $this->user->getId())) {
                        $mysql->rollback();
                        View::addFlash('dropping', 'danger', 'Operation is not complete');
                        throw new RedirectException($back);
                    }
                    $message_success[] = "SKU (#$SKU_id" . (empty($batch_id) ? '' : ", Batch#: $batch_id") . "), quantity $quantity, is dropped for order #$order_id";
                    $move_id = Movements_::setDropping($loc_id, $order_id, $SKU_id, $batch_id, $quantity, $this->user->getId());
                    SKUHistories::addDropping($cart->getId(), $loc_id, $order_id,  $SKU_id, $batch_id, $quantity, $move_id, $this->user->getId(), $this->warehouse_id);
                    OrderHistories::droppedSKU($order_id, SKUBarcodes::getBarcodesBySKUIdString($SKU_id), empty($batch) ? '' : $batch->getInfo(), $quantity, Locations::getById($loc_id)->getLocation($this->warehouse_label), $this->user->getId());
                    if (OrderSKUs::isCompleted($order_id)) {
                        SKULocations::setCompleted($order_id, $this->user->getId());
                        Orders::changeStatus($order_id, Order::AWAITING_PACK, $this->user->getId());
                    }
                    SKUHistories::updateStock($SKU_id, $this->warehouse_id);
                }
            }

            View::addFlash('dropping', 'success', $message_success);
        } catch (RedirectException $e) {
            throw $e;
        } catch (\Exception $e) {
            $mysql->rollback();
            View::addFlash('dropping', 'danger', 'Operation is not complete. Try again');
            Debug::sendDebugInfo('Dropping Confirmation', var_export([$e->getCode(), $e->getMessage()], 1));
            throw new RedirectException($back);
        }
        $mysql->commit();

        throw new RedirectException($back . '&cont=1');
    }

    /** @throws */
    public function droppingBar()
    {
        $params = parse_url($_POST['back'] ?? '');
        parse_str($params['query'] ?? '', $params);
        $mode = addslashes($_POST['mode'] ?? '');
        $back = $_POST['back'] ?: ('/drop/dropping_' . substr($mode, 0, 1));
        if (empty($_POST['search']) || empty($_POST['loc']) || $mode != 'multi' || empty($_POST['SKU_id']) ) {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException(empty($mode) ? '/' : $back);
        }
        $cart = Cart::getCart($this->user);
        if (empty(SKULocations::getQuantityInLocation($cart->getId()))) {
            View::addFlash('dropping', 'danger', 'Trolley is empty');
            throw new RedirectException("/cart/dropping/?back=$back");
        }
        $modes = Cart::getCartInfo($cart);
        if (!empty($modes['none']) || empty($modes[$mode])) {
            View::addFlash('dropping', 'warning', "There's nothing to $mode drop from this trolley");
            throw new RedirectException('/cart/dropping/?back=' . $back);
        }
        $location_obj = Locations::getById(addslashes($_POST['loc']));
        if (empty($location_obj)) {
            View::addFlash('dropping', 'danger', 'Location not found');
            throw new RedirectException('/');
        }
        $SKU_id = (int)$_POST['SKU_id'];
        $batch_id = (int)$_POST['batch_id'];
        $filters = Filter::processFilters($this->warehouse_id, $params, self::DEFAULT_FILTERS);
        $mezzanine = null;
        if (!empty($filters['floor']) && $filters['floor'] != Filter::NOT_SELECTED && in_array($filters['floor'], Warehouses::$available_floors))
            $mezzanine = $filters['floor'] === Warehouses::$available_floors[1];
        list($SKUs, ) = SKULocations::getDroppingByWorker($mode, $cart->getId(), $mezzanine, $filters['services']);
        foreach ($SKUs as $key => $SKU) {
            if ($SKU['location_id'] != $location_obj->getId())
                unset($SKUs[$key]);
            if ($SKU_id != $SKU['id'] || $batch_id != $SKU['batch_id'])
                unset($SKUs[$key]);
        }
        if (empty($SKUs)) {
            View::addFlash('dropping', 'danger', 'Barcode not found');
            throw new RedirectException("/drop/dropping_get/?mode=$mode&search={$location_obj->getId()}&SKU_id=&batch_id=&filter[floor]={$filters['floor']}&" . http_build_query(['filter' => $filters]));
        }
        if (count($SKUs) != 1) {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $SKU = $SKUs[array_keys($SKUs)[0]];
        $data = [
            'SKU' => $SKU,
            'mode' => $mode,
            'back' => $back
        ];
        $this->render($data, 'dropping/dropping_SKU');
    }

    /** @throws */
    public function droppingLoc()
    {
        $params = parse_url($_POST['back'] ?? '');
        parse_str($params['query'] ?? '', $params);
        if (empty($_POST['search']) || empty($_POST['mode']) || empty($_POST['SKU_id']) || $_POST['mode'] != 'multi') {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException(empty($_POST['mode']) ? '/' : ('/drop/dropping_' . substr($_POST['mode'], 0, 1)));
        }
        $cart = Cart::getCart($this->user);
        $mode = addslashes($_POST['mode']);
        $back = $_POST['back'] ?: ('/drop/dropping_' . substr($mode, 0, 1));
        if (empty(SKULocations::getQuantityInLocation($cart->getId()))) {
            View::addFlash('dropping', 'danger', 'Trolley is empty');
            throw new RedirectException("/cart/dropping/?back=$back");
        }
        $modes = Cart::getCartInfo($cart);
        if (!empty($modes['none']) || empty($modes[$mode])) {
            View::addFlash('dropping', 'warning', "There's nothing to $mode drop from this trolley");
            throw new RedirectException('/cart/dropping/?back=' . $back);
        }
        $search = addslashes($_POST['search']);
        $SKU_obj = SKUs::getById((int)$_POST['SKU_id']);
        if (empty($SKU_obj)) {
            View::addFlash('dropping', 'danger', 'SKU not found');
            throw new RedirectException('/');
        }
        $batch_id = (int)$_POST['batch_id'];
        $filters = Filter::processFilters($this->warehouse_id, $params, self::DEFAULT_FILTERS);
        $back_scan = "/drop/dropping_get/?mode=$mode&search=" . SKUBarcodes::getOneBySKUId($SKU_obj->getId()) . "&SKU_id={$SKU_obj->getId()}&batch_id=$batch_id&" . http_build_query(['filter' => $filters]);
        $l = Locations::getByScan($this->warehouse_id, htmlentities($search));
        if (empty($l)) {
            View::addFlash('dropping', 'danger', 'Location not found');
            throw new RedirectException($back_scan);
        }
        $mezzanine = null;
        if (!empty($filters['floor']) && $filters['floor'] != Filter::NOT_SELECTED && in_array($filters['floor'], Warehouses::$available_floors))
            $mezzanine = $filters['floor'] === Warehouses::$available_floors[1];
        list($SKUs, ) = SKULocations::getDroppingByWorker($mode, $cart->getId(), $mezzanine, $filters['services']);
        foreach ($SKUs as $key => $SKU) {
            if ($SKU['location_id'] != $l->getId())
                unset($SKUs[$key]);
            if ($SKU_obj->getId() != $SKU['id'] || $batch_id != $SKU['batch_id'])
                unset($SKUs[$key]);
        }
        if (empty($SKUs)) {
            View::addFlash('dropping', 'danger', 'Location not found');
            throw new RedirectException($back_scan);
        }
        if (count($SKUs) != 1) {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $data = [
            'SKU' => $SKUs[array_keys($SKUs)[0]],
            'mode' => $mode,
            'back' => $back
        ];
        $this->render($data, 'dropping/dropping_SKU');
    }

    /** @throws */
    public function droppingConf()
    {
        if (empty($_POST['location_id']) || empty($_POST['SKU_id']) ||
            empty($_POST['quantity']) || empty($_POST['qty']) || empty($_POST['mode']) || $_POST['mode'] != 'multi') {
            View::addFlash('dropping', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $qty = (int)$_POST['quantity'];     // worker
        $q = (int)$_POST['qty'];            // in location
        $mode = addslashes($_POST['mode']);
        $back = $_POST['back'] ?? '';
        if ($qty < 1 || $qty > $q) {
            View::addFlash('dropping','danger','Items quantity cannot be less than 1 and greater than the total amount of items');
            throw new RedirectException($back);
        }
        $cart = Cart::getCart($this->user);
        if (empty(SKULocations::getQuantityInLocation($cart->getId()))) {
            View::addFlash('dropping', 'danger', 'Trolley is empty');
            throw new RedirectException("/cart/dropping/?back=$back");
        }
        $modes = Cart::getCartInfo($cart);
        if (!empty($modes['none']) || empty($modes[$mode])) {
            View::addFlash('dropping', 'warning', "There's nothing to $mode drop from this trolley");
            throw new RedirectException("/cart/dropping/?back=$back");
        }
        $loc_id = (int)$_POST['location_id'];
        $SKU_id = (int)$_POST['SKU_id'];
        $batch_id = (int)$_POST['batch_id'];
        $batch = Batches::getById($batch_id);
        $params = parse_url($back);
        parse_str($params['query'] ?? '', $params);
        $filters = Filter::processFilters($this->warehouse_id, $params, self::DEFAULT_FILTERS);
        $mezzanine = null;
        if (!empty($filters['floor']) && $filters['floor'] != Filter::NOT_SELECTED && in_array($filters['floor'], Warehouses::$available_floors))
            $mezzanine = $filters['floor'] === Warehouses::$available_floors[1];
        list(, $orders) = SKULocations::getDroppingByWorker($mode, $cart->getId(), $mezzanine, $filters['services']);
        $index = $loc_id . '_' . $SKU_id . '_' . (empty($batch_id) ? '.' : $batch_id);
        if (empty($orders[$index])) {
            View::addFlash('picking', 'danger', "No orders or more than one found for dropping!");
            throw new RedirectException($back);
        }
        $orders = $orders[$index];
        $order_id = array_keys($orders)[0];
        $quan = $orders[$order_id];
        $quantity = $quan == $qty ? $quan : $qty;
        $num = 0;
        $mysql = new SafeMysql();
        while (true) {
            $mysql->beginTransaction();
            try {
                OrderLocationTemporaries::addSKU($SKU_id, $quantity, $order_id, $loc_id);
                if (!PickModel::addDropping($SKU_id, $batch_id, $quantity, $quan == $qty, $order_id, $loc_id, $cart->getId(), $this->user->getId())) {
                    $mysql->rollback();
                    View::addFlash('dropping', 'danger', 'Operation is not complete');
                    throw new RedirectException($back);
                }
                View::addFlash('dropping',
                    $quan == $qty ? 'success' : 'warning',
                    "SKU (#$SKU_id" . (empty($batch_id) ? '' : ", Batch#: $batch_id") . "), quantity $quantity, is dropped for order #$order_id". ($quan == $qty ? '' : ' partially'));
                $move_id = Movements_::setDropping($loc_id, $order_id, $SKU_id, $batch_id, $quantity, $this->user->getId());
                SKUHistories::addDropping($cart->getId(), $loc_id, $order_id,  $SKU_id, $batch_id, $quantity, $move_id, $this->user->getId(), $this->warehouse_id);
                OrderHistories::droppedSKU($order_id, SKUBarcodes::getBarcodesBySKUIdString($SKU_id), empty($batch) ? '' : $batch->getInfo(), $quantity, Locations::getById($loc_id)->getLocation($this->warehouse_label), $this->user->getId());
                if (OrderSKUs::isCompleted($order_id)) {
                    SKULocations::setCompleted($order_id, $this->user->getId());
                    Orders::changeStatus($order_id, Order::AWAITING_PACK, $this->user->getId());
                }
                SKUHistories::updateStock($SKU_id, $this->warehouse_id);
            }
            catch (\Exception $e) {
                $mysql->rollback();
                if ($e instanceof DBException && $e->isDeadlock($num++))
                    continue;
                View::addFlash('dropping', 'danger', 'Operation is not complete. Try again');
                Debug::sendDebugInfo('Dropping Confirmation', var_export([$e->getCode(), $e->getMessage()], 1));
                throw new RedirectException($back);
            }
            $mysql->commit();
            break;
        }
        throw new RedirectException($back . '&cont=1');
    }
}
