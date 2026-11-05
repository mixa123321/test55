<?php

namespace app\backend\controllers;

use app\backend\models\Carriers;
use app\backend\models\Cart as CartModel;
use app\backend\models\Deliveries;
use app\backend\models\Locations as LocationsModel;
use app\backend\models\Movements_;
use app\backend\models\OrderDocs;
use app\backend\models\Orders;
use app\backend\models\OrderSKUs;
use app\backend\models\OrderSKUs as OrderSKUsModel;
use app\backend\models\Pack as PackModel;
use app\backend\models\Packages;
use app\backend\models\Pick as PickModel;
use app\backend\models\Printers as PrintersModel;
use app\backend\models\ShippingServices;
use app\backend\models\SKUBarcodes;
use app\backend\models\SKUHistories;
use app\backend\models\SKULocations;
use app\backend\models\SupplierWarehouses;
use app\backend\models\Users;
use app\core\dbal\SafeMysql;
use app\core\entities\Carrier;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\Package;
use app\core\entities\ShippingService;
use app\core\helpers\Debug;
use app\core\helpers\HTTP;
use app\core\models\config\WarehousesConfig;
use app\core\models\OrderPackageReturns;
use app\core\models\Returns;
use app\core\entities\SKU;
use app\core\entities\OrderSKUSerialNumber;
use app\core\entities\Queue;
use app\core\entities\SectionBinding;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Html;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\helpers\Filter;
use app\core\models\OrderLocations;
use app\core\models\OrderLocationTemporaries;
use app\core\models\PackingSlips;
use app\core\models\ReturnCarriers;
use app\core\models\Shippings;
use app\core\models\Suppliers;
use app\core\models\SuppliersWarehouses;
use app\core\models\Warehouses;
use app\core\models\OrderSKUSerialNumbers;
use app\core\models\SKUs;
use app\core\models\Queues;
use app\core\services\shipping\Shipping;
use app\core\Storage;
use app\core\helpers\Defends;
use app\core\models\config\OrdersConfig;
use app\core\models\SectionBindings;
use app\core\helpers\FileWrapper;
use app\core\statical\WithDate;

class Pack extends AuthController
{
    const DEFAULT_FILTERS = [
        'mezzanine' => 0,
        'services' => Filter::NOT_SELECTED,
        'floor' => Filter::NOT_SELECTED,
        'clients' => Filter::NOT_SELECTED,
        'despatch_date' => Filter::NOT_SELECTED
    ];

    private static function parseDespatchDateFilter($despatch_date): ?\DateTime
    {
        return is_string($despatch_date) ? (\DateTime::createFromFormat(WithDate::DATE_SIMPLE_FORMAT, $despatch_date) ?: null) : null;
    }

    /** @throws */
    public function all()
    {
        $this->admin(Order::AWAITING_PACK_NUM);
    }

    /** @throws */
    public function packed()
    {
        $this->admin(Order::$allowed_for_packed_statutes);
    }

    /** @throws */
    private function admin($status)
    {
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('pack', 'danger', 'Carriers are not found');
            throw new RedirectException('/');
        }
        $users = $this->user->isRoleManager() ? Users::getAllByParentId($this->warehouse_id, $this->user->getId()) : 0;
        $is_mezzanine = (bool)(Warehouses::getById($this->warehouse_id))->getMezzanine() ?? false;
        $floors = array_combine(Warehouses::$available_floors, Warehouses::$available_floors);
        
        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS);
        $mezzanine = null;
        if ($is_mezzanine && !empty($filters['floor']) && $filters['floor'] != Filter::NOT_SELECTED)
            $mezzanine = $filters['floor'] === Warehouses::$available_floors[1];
        
        $OBs = SectionBindings::getPlacesByPackers($this->warehouse_id, $users);
        $orders = Orders::getPacking($this->warehouse_id, $carriers, $status, $_GET['packing_type'] ?? '', $mezzanine, $filters['services'], $filters['clients']);
        $sort_services = ShippingServices::getSequentialCollectionTimes($this->warehouse_id);
        foreach ($orders as $key => $ord) {
            $loc = $ord['location_id'];
            if (!empty($OBs[$loc]))
                $orders[$key]['packer'] = $OBs[$loc];
        }
        $c = empty($_GET['c']) ? 1 : (int)$_GET['c'];
        $c = $c < 1 ? 1 : $c;
        $total_count = count($orders);
        $total_pages = ceil($total_count / PackModel::LIMIT_PER_PAGE);
        if ($c > $total_pages)
            $c = 1;
        $orders = array_slice($orders, PackModel::LIMIT_PER_PAGE * ($c - 1), PackModel::LIMIT_PER_PAGE, true);
        $data = [
            'orders' => $orders,
            'status' => $status,
            'is_mezzanine' => $is_mezzanine,
            'filters' => $filters,
            'floors' => $floors,
            'total_count' => $total_count,
            'total_pages' => $total_pages,
            'current_page' => $c,
            'sort_services' => $sort_services,
            'suppliers' => $this->getSuppliers()
        ];
        $this->render($data, 'packing/packing_all');
    }

    /** Starting point for Single Pack */
    /** @throws */
    public function packingS()
    {
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('pack', 'danger', 'Carriers are not found');
            throw new RedirectException('/');
        }
        $table_id = 0;
        $TPs = SectionBindings::getTables($this->user->getId(), $this->warehouse_id);
        if (!empty($_GET['table_id']))
            $table_id = (int)$_GET['table_id'];
        elseif (!empty($_SESSION[PackModel::SINGLE_TABLE_ID]))
            $table_id = (int)$_SESSION[PackModel::SINGLE_TABLE_ID];
        elseif (!empty($TPs))
            $table_id = (int)$TPs[0]['id'];
        $exist_table = !empty($table_id) && in_array($table_id, array_map(function($TP) { return $TP['id']; }, $TPs));
        if (empty($table_id) || !$exist_table)
            View::addFlash('pack', 'danger', 'Error packing table');

        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS);
        if (empty($_GET['filter']))
            $filters['despatch_date'] = Filter::NOT_SELECTED;
        $orders = Orders::getOrdersForPacking($carriers, [$table_id], false, null, $filters['services'], null, $filters['clients'], false, self::parseDespatchDateFilter($filters['despatch_date']));
        $order_ids_in_print = Queues::getOrderIdsBulkPrintInProcess($this->warehouse_id);
        foreach ($order_ids_in_print as $order_id)
            unset($orders[$order_id]);

        $sort_services = ShippingServices::getSequentialCollectionTimes($this->warehouse_id);
        list($printer_def, $printer_list) = PrintersModel::getSelectPrinters($this->warehouse_id);
        $user_printer = $this->user->getRetainMePrinter();
        if (!empty($_GET['printer_def']) && !empty($printer_list[$_GET['printer_def']]))
            $printer_def = (int)$_GET['printer_def'];
        else if (!empty($user_printer) && !empty($printer_list[$user_printer]))
            $printer_def = $user_printer;
        $carts_near_table = CartModel::getCartsNearTable($this->warehouse_id, $table_id, SectionBinding::TYPE_BIND_SINGLE_TABLE, $orders);
        $cart = CartModel::getCart($this->user);
        $data = [
            'orders' => $orders,
            'orders_skus' => $orders ? OrderSKUs::getSKUsByOrders(array_keys($orders)) : [],
            'tables' => Arr::map($TPs, 'id', 'name'),
            'table_id' => (string)$table_id,
            'users' => Users::getAll($this->warehouse),
            'printers' => $printer_list,
            'printer_def' => $printer_def,
            'carts' => $carts_near_table,
            'can_unhook_cart' => empty($this->user->getCartId()),
            'cart' => $cart,
            'filters' => $filters,
            'sort_services' => $sort_services,
            'suppliers' => $this->getSuppliers()
        ];
        $_SESSION[PackModel::SINGLE_TABLE_ID] = $table_id;
        $_SESSION[PackModel::SINGLE_ORDERS] = $data;
        $_SESSION['packing_mode'] = PackModel::PACKING_SINGLE;
        $_SESSION['pack_services'] = $filters['services'] ?? null;
        $this->render($data, 'packing/packing_s');
    }

    public function packingB()
    {
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('pack', 'danger', 'Carriers are not found');
            throw new RedirectException('/');
        }
        $table_id = 0;
        $TPs = SectionBindings::getTables($this->user->getId(), $this->warehouse_id);
        $LPs = SectionBindings::getLTables($this->user->getId(), $this->warehouse_id);
        $tables = array_merge($TPs, $LPs);
        $large_loc_ids = array_column($LPs, 'id');

        if (!empty($_GET['table_id']))
            $table_id = (int)$_GET['table_id'];
        elseif (!empty($_SESSION[PackModel::SINGLE_TABLE_ID]))
            $table_id = (int)$_SESSION[PackModel::SINGLE_TABLE_ID];
        elseif (!empty($tables))
            $table_id = (int)$tables[0]['id'];
        $exist_table = !empty($table_id) && in_array($table_id, array_map(function($TP) { return $TP['id']; }, $tables));
        if (empty($table_id) || !$exist_table) {
            View::addFlash('pack', 'danger', 'Error packing table');
            throw new RedirectException('/');
        }
        $hide = (bool)($_GET['hide'] ?? false);
        $SKU_id = (int)($_GET['SKU_id'] ?? 0);
        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS);
        if (empty($_GET['filter']))
            $filters['despatch_date'] = Filter::NOT_SELECTED;
        $orders = Orders::getOrdersForPacking($carriers, [$table_id], false, null, -1, PackModel::getAllowedPackingStatuses(), [], in_array($table_id, $large_loc_ids), self::parseDespatchDateFilter($filters['despatch_date']));
        $order_ids_in_print = Queues::getOrderIdsBulkPrintInProcess($this->warehouse_id);
        foreach ($order_ids_in_print as $order_id)
            unset($orders[$order_id]);

        $statuses = $hide ? PackModel::getSuccessPackingStatuses() : PackModel::getAllowedPackingStatuses();
        $orders_skus = $orders ? OrderSKUs::getSKUsByOrders(array_keys($orders)) : [];
        foreach ($orders as $key => $order)
            if (!in_array($order['status'], $statuses))
                unset($orders[$key]);

        if (!$SKU_id && $orders_skus)
            $SKU_id = (int)current($orders_skus)['id'];
        $SKUs_filter = [];
        foreach ($orders_skus as $order_id => $data) {
            $SKUs_filter[$data['id']] = $data['barcode'];
            if ((int)$data['id'] !== $SKU_id)
                unset($orders_skus[$order_id]);
        }
        $orders = array_intersect_key($orders, $orders_skus);
        $carrier_ids = array_filter(array_unique(array_column($orders, 'carrier_id')));
        $shippings = [];
        foreach ($carrier_ids as $carrier_id) {
            [$shipping, $carrier] = Shipping::getShipping($this->logistic_company, $carrier_id);
            $shippings[$carrier_id] = [$shipping, $carrier];
        }
        $max_weight = null;
        foreach ($orders as $order) {
            $shipping = $shippings[$order['carrier_id']];
            [$max_weight_order, ] = PackModel::getMaxWeight(
                $shipping[1],
                $shipping[0]['getMaxWeight'],
                new ShippingService(['name' => $order['shipping_service'], 'max_weight' => $order['max_weight']]),
            );
            if (!empty($max_weight_order) && $max_weight_order > 0)
                if (is_null($max_weight) || $max_weight > $max_weight_order)
                    $max_weight = $max_weight_order;
        }
        $packages = [];
        $SKU = new SKU();
        if ($SKU_id) {
            $SKU = SKUs::getById($SKU_id);
            $packages = self::getPackages($this->warehouse_id, $SKU->getSupplierId());
            $SKUs_filter[$SKU_id] = SKUBarcodes::getBarcodesBySKUIdString($SKU_id);
            if ($packages === null) {
                View::addFlash('packing', 'danger', 'Packages not found');
                throw new RedirectException('/');
            }
        }
        $data = [
            'orders' => $orders,
            'orders_skus' => $orders_skus,
            'tables' => Arr::map($tables, 'id', 'name'),
            'table_id' => (string)$table_id,
            'users' => Users::getAll($this->warehouse),
            'print_limit' => PackModel::BULK_PACK_LIMIT,
            'SKUs_filter' => $SKUs_filter,
            'SKU' => $SKU,
            'max_weight' => $max_weight,
            'packages' => $packages,
            'hide' => $hide,
            'filters' => $filters,
        ];
        $_SESSION[PackModel::SINGLE_TABLE_ID] = $table_id;
        $_SESSION[PackModel::SINGLE_ORDERS] = $data;
        $_SESSION['packing_mode'] = PackModel::PACKING_BULK;
        $this->render($data, 'packing/packing_b');
    }

    public function addBulkQueue()
    {
        $back = '/pack/packing_b?' . http_build_query($_GET);
        $order_ids = $_POST['order_ids'] ?? '';
        if (!$order_ids) {
            View::addFlash('packing', 'danger', 'Uknown error. POST parameters');
            throw new RedirectException($back);
        }
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('packing', 'danger', 'Carriers are not found');
            throw new RedirectException($back);
        }
        if (empty($_SESSION['packing_mode'])) {
            View::addFlash('packing', 'danger', 'Try again');
            throw new RedirectException($back);
        }
        $order_ids = json_decode($order_ids);
        if (!$order_ids) {
            View::addFlash('packing', 'danger', 'Uknown error. Wrong json');
            throw new RedirectException($back);
        }
        if (count($order_ids) > PackModel::BULK_PACK_LIMIT) {
            View::addFlash('packing', 'danger', 'Print limit ' . PackModel::BULK_PACK_LIMIT);
            throw new RedirectException($back);
        }
        $length = $_POST['length'] ?? 0;
        $width = $_POST['width'] ?? 0;
        $height = $_POST['height'] ?? 0;
        $weight = $_POST['weight'] ?? 0;
        $package_type_id = $_POST['package_type_id'] ?? 0;
        if (!$length || !$width || !$height || !$weight || !$package_type_id) {
            View::addFlash('packing', 'danger', 'Pack error');
            throw new RedirectException($back);
        }
        $all_defends = [];
        foreach ($order_ids as $order_id) {
            $defends = (new Defends())->setType(Defends::TYPE_ORDERS)->setKey((string)$order_id);
            if ($defends->lockDate() === Defends::LOCK_NOT_UPDATE) {
                foreach ($all_defends as $defend)
                    $defend->unLock();
                View::addFlash('packing', 'danger', 'Orders conflict. Please try again.');
                throw new RedirectException($back);
            }
            $all_defends[] = $defends;
        }
        foreach ($order_ids as $order_id) {
            if (!PackModel::assignUser($order_id, $this->user->getId(), PackModel::resolveOrderPackingLockTime($_SESSION['packing_mode']))) {
                $assigned_user_login = PackModel::getAssignedUser($order_id);
                View::addFlash('packing', 'danger', "User $assigned_user_login is already packing this order");
                throw new RedirectException($back);
            }
        }

        $order_ids_in_print = Queues::getOrderIdsBulkPrintInProcess($this->warehouse_id);
        if (array_intersect($order_ids_in_print, $order_ids)) {
            foreach ($all_defends as $defend)
                $defend->unLock();
            View::addFlash('packing', 'danger', 'Orders conflict. Please try again.');
            throw new RedirectException($back);
        }
        $json_data = Queues::createBulkPrintJsonData($order_ids, $_GET, $length, $width, $height, $weight, $package_type_id);
        $id = Queues::insertOne((new Queue())->setStatus(Queue::STATUS_NEW)->setType(Queue::TYPE_BULK_PRINT)->setUserId((int)$this->user->getId())->setWarehouseId($this->warehouse_id)->setJsonData($json_data));
        foreach ($all_defends as $defend)
            $defend->unLock();
        throw new RedirectException('/queues/bulk?id=' . $id);
    }

    /** Starting point for multi pack */
    /** @throws */
    public function packingM()
    {
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('pack', 'danger', 'Carriers are not found');
            throw new RedirectException('/');
        }
        $is_mezzanine = (bool)(Warehouses::getById($this->warehouse_id))->getMezzanine() ?? false;
        $floors = array_combine(Warehouses::$available_floors, Warehouses::$available_floors);
        
        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS);
        if (empty($_GET['filter']))
            $filters['despatch_date'] = Filter::NOT_SELECTED;
        $mezzanine = null;
        if ($is_mezzanine && !empty($filters['floor']) && $filters['floor'] != Filter::NOT_SELECTED)
            $mezzanine = $filters['floor'] === Warehouses::$available_floors[1];

        $OBs = SectionBindings::getPlacesByPackerMulti($this->user->getId(), $this->warehouse_id);
        $orders = Orders::getOrdersForPacking($carriers, $OBs, true, $mezzanine, $filters['services'], null, $filters['clients'], false, self::parseDespatchDateFilter($filters['despatch_date']));

        $sort_services = ShippingServices::getSequentialCollectionTimes($this->warehouse_id);
        $TPs = SectionBindings::getTables($this->user->getId(), $this->warehouse_id);
        list($printer_def, $printer_list) = PrintersModel::getSelectPrinters($this->warehouse_id);
        $user_printer = $this->user->getRetainMePrinter();
        if (!empty($user_printer) && !empty($printer_list[$user_printer]))
            $printer_def = $user_printer;
        $data = [
            'orders' => $orders,
            'TPs' => count($TPs),
            'users' => Users::getAll($this->warehouse),
            'printers' => $printer_list,
            'printer_def' => $printer_def,
            'is_mezzanine' => $is_mezzanine,
            'floors' => $floors,
            'filters' => $filters,
            'sort_services' => $sort_services,
            'suppliers' => $this->getSuppliers(),
        ];
        $_SESSION['packing_mode'] = PackModel::PACKING_MULTI;
        $_SESSION['packing_multi_floor'] = $filters['floor'];
        $_SESSION['pack_services'] = $filters['services'] ?? null;
        $this->render($data, 'packing/packing_m');
    }

    /** Starting point for multi pack */
    /** @throws */
    public function packingL()
    {
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('pack', 'danger', 'Carriers are not found');
            throw new RedirectException('/');
        }

        $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS, empty($_GET['filter']));
        $LPs = SectionBindings::getLTables($this->user->getId(), $this->warehouse_id);
        $table_id = empty($_GET['table_id']) ? (empty($LPs) ? 0 : $LPs[0]['id']) : $_GET['table_id'];
        $LPOrders = SectionBindings::getPlacesByPackerLarge($this->user->getId(), $this->warehouse_id, $table_id);
        $orders = Orders::getOrdersForPacking($carriers, array_keys($LPOrders), false, null, $filters['services'], null, $filters['clients'], false, self::parseDespatchDateFilter($filters['despatch_date']));
        $sort_services = ShippingServices::getSequentialCollectionTimes($this->warehouse_id);
        list($printer_def0, $printer_list) = PrintersModel::getSelectPrinters($this->warehouse_id);
        $printer_def =  empty($_GET['printer_def']) ? $printer_def0 : (int)$_GET['printer_def'];
        $user_printer = $this->user->getRetainMePrinter();
        if (!empty($user_printer) && !empty($printer_list[$user_printer]))
            $printer_def = $user_printer;
        $carts_near_table = CartModel::getCartsNearTable($this->warehouse_id, $table_id, SectionBinding::TYPE_BIND_LARGE_TABLE, $orders);
        $cart = CartModel::getCart($this->user);
        $data = [
            'orders' => $orders,
            'tables' => Arr::map($LPs, 'id', 'name'),
            'table_id' => $table_id,
            'users' => Users::getAll($this->warehouse),
            'printers' => $printer_list,
            'printer_def' => $printer_def,
            'carts' => $carts_near_table,
            'can_unhook_cart' => empty($this->user->getCartId()),
            'cart' => $cart,
            'filters' => $filters,
            'sort_services' => $sort_services,
            'suppliers' => $this->getSuppliers(),
        ];
        $_SESSION['packing_mode'] = PackModel::PACKING_LARGE;
        $_SESSION['pack_services'] = $filters['services'] ?? null;
        $this->render($data, 'packing/packing_l');
    }

    /** Packing process. Operators create boxes for skus here. Single mode orders skip this step and go directly to packingConf */
    /** @throws */
    public function process()
    {
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('packing', 'danger', 'Carriers are not found');
            throw new RedirectException('/');
        }
        if (empty($_SESSION['packing_mode'])) {
            View::addFlash('packing', 'danger', 'Try again');
            throw new RedirectException('/');
        }
        $mode = substr($_SESSION['packing_mode'], 0, 1);
        $back = PackModel::errorUrl($_SESSION['packing_mode']);
        if (empty($_GET['printer_def'])) {
            View::addFlash('packing', 'danger', 'Retain me printer not found');
            throw new RedirectException($back);
        }
        $order_id = null;
        if ($mode === 's') {
            // SINGLE MODE
            $single_error = false;
            if (empty($_SESSION[PackModel::SINGLE_ORDERS])) {
                View::addFlash('packing', 'danger', 'Operation failed. Please re-scan the barcode again');
                $single_error = true;
            }
            if (empty($_GET['sku_barcode'])) {
                View::addFlash('packing', 'danger', 'Empty barcode');
                $single_error = true;
            }
            if ($single_error)
                throw new RedirectException($back);
            $sku_barcode = trim($_GET['sku_barcode']);
            $order_ids = [];
            foreach ($_SESSION[PackModel::SINGLE_ORDERS]['orders_skus'] as $id => $val)
                if (in_array($sku_barcode, $val['barcodes']))
                    $order_ids[] = $id;
            foreach ($_SESSION[PackModel::SINGLE_ORDERS]['orders'] as $order)
                if (in_array($order['id'], $order_ids) && !Order::isPrintingDocumentsIssue($order['status'])) {
                    if (PackModel::isActualPacker($order['id'], $this->user->getId(), PackModel::SINGLE_PACKING_LOCK_TIME))
                        continue;
                    $order_id = $order['id'];
                    break;
                }
            if (!empty(Orders::getPackages($order_id))) {
                View::addFlash('packing', 'warning',
                    "Please make sure you didn't leave the previous box without the label on it");
                throw new RedirectException(
                    '/pack/printing_documents?mode=np&id=' . (int)$order_id . '&source_id=' . (int)$_GET['printer_def']
                );
            }
            // END SINGLE MODE
        } else {

            // MULTI || LARGE
            if (!empty($_GET['location_id'])) {
                $scan_location_id = trim($_GET['location_id']);
                $pack_services = $_SESSION['pack_services'] ?? -1;
                if ($mode === 'm') {
                    $mezzanine = null;
                    if (!empty($_GET['floor']) && in_array($_GET['floor'], Warehouses::$available_floors))
                        $mezzanine = $_GET['floor'] === Warehouses::$available_floors[1];
                    $l = LocationsModel::getByScan($this->warehouse_id, htmlentities($scan_location_id));
                    if (!$l || !$l->OB() || (!is_null($mezzanine) && $l->getMezzanine() != (int)$mezzanine)) {
                        View::addFlash('packing', 'danger', 'Order bin not found');
                        throw new RedirectException($back);
                    }
                    $order_tmp = Orders::getByLocation($l->getId());
                    if ($pack_services != -1 && !in_array($order_tmp['shipping_services_id'], $pack_services)) {
                        View::addFlash('packing', 'danger', 'Order bin not found');
                        throw new RedirectException($back);
                    }
                    $order_id = !empty($order_tmp['id']) ? $order_tmp['id'] : 0;
                } else {

                    $table_id = Request::getPositiveId(null, 'table_id');
                    if (empty($table_id)) {
                        $LPs = SectionBindings::getLTables($this->user->getId(), $this->warehouse_id);
                        $table_id = empty($LPs) ? 0 : $LPs[0]['id'];
                    }
                    $filters = Filter::processFilters($this->warehouse_id, $_GET, self::DEFAULT_FILTERS);
                    $LPOrders = SectionBindings::getPlacesByPackerLarge($this->user->getId(), $this->warehouse_id, $table_id);
                    $large_orders = Orders::getOrdersForPacking($carriers, array_keys($LPOrders), false, null, $filters['services'], null, $filters['clients']);
                    $order_tmp = Orders::getByIdOrReference($scan_location_id, $this->warehouse_id);
                    if (empty($order_tmp)) {
                        $order_tmp = Orders::getBySKUBarcode($scan_location_id, array_keys($large_orders), $this->warehouse_id);
                    }
                    $order_id = !empty($order_tmp) ? $order_tmp->getId() : 0;

                    if (empty($large_orders[$order_id])) {
                        View::addFlash('packing', 'danger', 'Order not found');
                        throw new RedirectException($back);
                    }
                }
            } else
                $order_id = Request::getPositiveId($_GET, 'order_id');
            // END MULTI || LARGE
        }
        $order = Orders::getPackingOrderById($order_id, $carriers);
        if (empty($order)) {
            View::addFlash('packing', 'danger', "Order not found");
            throw new RedirectException($back);
        }
        $current_status = (int)$order['status'];
        if (!Order::isAwaitingPack($current_status) && !Order::isPacking($current_status)) {
            View::addFlash('packing', 'danger', "Order is not ready to be packed. Current status is '" . Order::getStateName($current_status) . "'");
            throw new RedirectException($back);
        }
        if (!PackModel::assignUser($order_id, $this->user->getId(), PackModel::resolveOrderPackingLockTime($_SESSION['packing_mode']))) {
            $assigned_user_login = PackModel::getAssignedUser($order_id);
            View::addFlash('packing', 'danger', "User $assigned_user_login is already packing this order");
            throw new RedirectException($back);
        }
        if ($mode === 's') {
            $order_ids_in_print = Queues::getOrderIdsBulkPrintInProcess($this->warehouse_id);
            if (!empty($order_ids_in_print[$order['id']])) {
                View::addFlash('packing', 'danger', 'Order already exist in bulk pack');
                throw new RedirectException($back);
            }
        }
        if (Order::isAwaitingPack($current_status)) {
            $orders_for_unload = [];
            $remove_by_cart_id = true;
            $packing_mode = $_SESSION['packing_mode'] ?? '';
            $table_id = $_GET['table_id'] ?? null;
            if (in_array($packing_mode, [PackModel::PACKING_SINGLE, PackModel::PACKING_LARGE]) && $table_id && ($cart = CartModel::getCardInTrolleyByOrderId((int)$order['id']))) {
                // do not auto unhook pallet
                if ($cart['type_id'] != Location::CA)
                    $remove_by_cart_id = false;
                $all_orders_for_unload = SKULocations::getSKUsToUnload($cart['id'], $packing_mode);
                foreach ($all_orders_for_unload as $location_id_unload => $orders)
                    foreach ($orders as $order_id_unload => $quantity) {
                        if ($order_id_unload == $order['id'])
                            $orders_for_unload[$location_id_unload][$order_id_unload] = $quantity;
                        else
                            $remove_by_cart_id = false;
                    }
            }
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            if ($orders_for_unload) //Перед тем как начинать паковать заказ, выгружаем его на стол, если нужно
                self::writePackingUnload($orders_for_unload, (int)$table_id, (int)$cart['id'], $packing_mode, $remove_by_cart_id);
            Orders::changeStatus($order_id, Order::PACKING, $this->user->getId());
            $mysql->commit();
        }
        $SKUs = OrderLocationTemporaries::getSKUs($order_id);
        $packages = self::getPackages($this->warehouse_id, $order['sid']);
        if ($packages === null) {
            View::addFlash('packing', 'danger', 'Packages not found');
            throw new RedirectException($back);
        }
        $supplier = Suppliers::getById($order['sid']);
        $packing_slip_type = SuppliersWarehouses::getPackingSlipType($this->warehouse_id, $supplier->getId(), $order['shop_id']);
        $to_print_packing_slip = $packing_slip_type == PackingSlips::TYPE_GENERIC || $packing_slip_type == PackingSlips::TYPE_VOKE;
        $printer_def = (int)($_GET['printer_def'] ?? null);
        $shipping_service = ShippingServices::getById($order['shipping_services_id'] ?? null);
        /** @var Carrier $carrier */
        [$shipping, $carrier] = Shipping::getShipping($this->logistic_company, $order['carrier_id']);
        if (empty($carrier)) {
            View::addFlash('packing', 'danger', 'Carrier is not found');
            throw new RedirectException($back);
        }
        [$max_weight, $weight_message] = PackModel::getMaxWeight($carrier, $shipping['getMaxWeight'], $shipping_service);

        $requiresParcelCategory = $shipping['requiresParcelCategory']();
        $parcelCategories = $shipping['getParcelCategoryList']();

        $data = [
            'order' => $order,
            'printer_def' => $printer_def,
            'location' => "$this->warehouse_label-{$order['type_id']}-{$order['zone']}-{$order['column']}-{$order['row']}",
            'account' => $order['name'],
            'SKUs' => $SKUs,
            'packages' => $packages,
            'mode' => $mode,
            'sku_data' => View::toJs(array_map(function ($SKU) {
                return [
                    'id' => $SKU['SKU_id'],
                    'name' => $SKU['name'],
                    'barcode' => $SKU['barcode'],
                    'barcodes' => $SKU['barcodes'],
                    'qty' => $SKU['qty'],
                    'weight' => $SKU['weight'],
                    'serial_number' => (int)$SKU['serial_number'],
                    'photo' => !empty($SKU['photo']) ? (SKU::showPhotoSKU(urlencode($SKU['photo'])) ?: COMMON_IMG . DIRECTORY_SEPARATOR . 'photo.png') : COMMON_IMG . DIRECTORY_SEPARATOR . 'photo.png',
                ] ;
            }, $SKUs)),
            'max_weight' => $max_weight,
            'weight_message' => $weight_message,
            'to_print_packing_slip' => $to_print_packing_slip,
            'regexp_serial_number' => Suppliers::REGEXP_SERIAL_NUMBER_FOR_SUPPLIERS[$order['supplier_id']] ?? '',
            'packing_slip_url' => HTTP::getDomain() . "/orders/get_packing_slip?id=$order_id&source_id=" . $printer_def,
            'back' => $back,
            'requiresParcelCategory' => $requiresParcelCategory,
            'parcelCategories' => $parcelCategories,
        ];
        $this->render($data, 'packing/packing_parcel');
    }

    private static function getPackages(int $warehouse_id, int $supplier_id): ?array
    {
        $packages = Packages::getAllPackages($supplier_id, $warehouse_id, Package::STATE_ACTIVE);
        if (empty($packages))
            return null;
        $default_package_id = Packages::getDefaultPackageId($supplier_id, $warehouse_id);
        $warehouse_name = (Warehouses::getById($warehouse_id))->getName() ?? '-';
        array_walk($packages, function (&$package) use ($default_package_id, $warehouse_name) {
            $package['length'] = $package['x'] ?? 0.01;
            $package['width'] = $package['y'] ?? 0.01;
            $package['height'] = $package['z'] ?? 0.01;
            unset($package['x'], $package['y'], $package['z']);
            $package['is_default'] = $package['id'] == $default_package_id;
            if (empty($package['supplier_id']))
                $package['name'] .= ' (' . $warehouse_name . ')';
            $package['box_weight'] = (float)$package['box_weight'];
        });
        if (!in_array(true, array_column($packages, 'is_default')))
            $packages[0]['is_default'] = true;
        return $packages;
    }

    /** awaiting pack/packing -> printing documents */
    /** @throws RedirectException */
    public function packingConf()
    {
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('packing', 'danger', 'Carriers are not found');
            throw new RedirectException('/');
        }
        if (empty($_SESSION['packing_mode'])) {
            View::addFlash('packing', 'danger', 'Try again');
            throw new RedirectException('/');
        }
        if (empty($_POST)) {
            View::addFlash('packing', 'danger', 'Not found data');
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        $provided_data = $_POST;
        if ($_SESSION['packing_mode'] === 'single') {
            $SKUs = OrderLocationTemporaries::getSKUs($provided_data['order_id']);
            if (empty($SKUs)) {
                View::addFlash('packing', 'danger', "Not found SKU for order");
                throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
            }
            $this->addSKUsToProvidedDataSingle($provided_data, $SKUs);
        }
        $order_id = (int)$provided_data['order_id'];
        $order = Orders::getById($order_id);
        if (!$order || !in_array($order->getCarrierId(), $carriers) || $order->getWarehouseId() != $this->warehouse_id) {
            View::addFlash('packing', 'danger', "Order not found");
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        if (!$order->packing() && !$order->awaitingPack()) {
            View::addFlash('packing', 'danger', "Order is not at packing stage");
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        if (PackModel::isActualPacker($order_id, $this->user->getId(), PackModel::resolveOrderPackingLockTime($_SESSION['packing_mode']))) {
            $assigned_user_login = PackModel::getAssignedUser($order_id);
            View::addFlash('packing', 'danger', "User $assigned_user_login is already packing this order");
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        $provided_data['carrier_id'] = $order->getCarrierId();
        $provided_data['shipping_service_id'] = $order->getShippingServicesId();
        
        $validation_result = PackModel::validatePackingConfirm($provided_data);
        if ($validation_result !== true) {
            View::addFlash('packing', 'danger', $validation_result);
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }

        $regexp_serial_number = Suppliers::REGEXP_SERIAL_NUMBER_FOR_SUPPLIERS[$order->getSupplierId()] ?? '';
        $SKUs = OrderSKUs::getSKUs($order_id);
        $serial_numbers_uniq = [];
        foreach ($SKUs as $SKU) {
            if (!$SKU['serial_number'])
                continue;
            if (!isset($_POST['serial_numbers'][$SKU['id']]) || (int)$SKU['quantity'] !== count($_POST['serial_numbers'][$SKU['id']])) {
                View::addFlash('packing', 'danger', "Did not add all serial numbers");
                throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
            }
            foreach ($_POST['serial_numbers'][$SKU['id']] as $number) {
                if (isset($serial_numbers_uniq[$number])) {
                    View::addFlash('packing', 'danger', "Duplicate serial numbers " . $number);
                    throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
                }
                if ($regexp_serial_number && !mb_ereg($regexp_serial_number, $number)) {
                    View::addFlash('packing', 'danger', "Wrong serial number format");
                    throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
                }
                $serial_numbers_uniq[$number] = true;
            }
        }
        $serial_numbers = $_POST['serial_numbers'] ?? [];
        $serial_numbers_box = $_POST['serial_numbers_box'] ?? [];
        $serial_numbers_objects = [];
        foreach ($serial_numbers as $SKU_id => $numbers)
            foreach ($numbers as $number) {
                $box_id = (int)$serial_numbers_box[$number];
                $serial_numbers_objects[$box_id][] = (new OrderSKUSerialNumber)->setOrderId($order_id)->setSKUId((int)$SKU_id)->setSerialNumber($number);
            }
        $defends = (new Defends())->setType(Defends::TYPE_ORDERS)->setKey((string)$order_id);
        if ($defends->lockDate() === Defends::LOCK_NOT_UPDATE) {
            $defends->unLock();
            View::addFlash('packing', 'danger', "Order locked. Please try again.");
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        $confirm_result = PackModel::packingConfirm($provided_data, $this->user, $this->warehouse_id, $SKUs, $serial_numbers_objects);
        $defends->unLock();
        if ($confirm_result) {
//            Orders::changeStatus($order_id, Order::PRINTING_DOCUMENTS, $this->user->getId(), "Packing started (printing documents)");
            $order = Orders::getPackingOrderById($order_id, $carriers);

            if (!Order::isPrintingDocuments($order['status'])) {
                Debug::sendDebugInfo('packingConf', json_encode([
                    'result' => $confirm_result,
                    'id' => $order_id,
                    'status' => $order['status'],
                ]), Debug::TEST);
//                Slack::sendMessage("*packingConf* Order#" . $order_id . " status: " . $order['status'] . "\n `" . json_encode($order) . '`');
            }

            throw new RedirectException(
                '/pack/printing_documents?mode=p&id=' . $order_id . '&source_id=' . (int)$provided_data['printer_def']
            );
        }
        View::addFlash('packing', 'danger', 'Error occurred while confirming packaging...');
        throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
    }

    public function cancel()
    {
        $order = Orders::getById(Request::getPositiveId($_POST, 'order_id'));
        if (empty($order) || !in_array($order->getStatus(), Order::$allowed_for_printing_statutes))
            return $this->response = ['error' => 'Cannot run the unpack operation on the selected order'];
        [$shipping, ] = Shipping::getShipping($this->logistic_company, $order->getCarrierId());
        $res = PackModel::unpack($order, $this->user, $shipping['cancelShipment']);
        if (!$res[0])
            return $this->response = ['error' => $res[1]];
        PackModel::unAssignUser($order->getId());
        return $this->response = ['success' => $res[1] ?? '-'];
    }

    /** Operator prints labels and scans tracking numbers */
    /** @throws */
    public function printingDocuments()
    {
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('packing', 'danger', 'Carriers are not found');
            throw new RedirectException('/');
        }
        if (empty($_SESSION['packing_mode'])) {
            View::addFlash('packing', 'danger', 'Try again');
            throw new RedirectException('/');
        }
        $redirect = PackModel::errorUrl($_SESSION['packing_mode']);
        if (empty($_GET['source_id'])) {
            View::addFlash('packing', 'danger', 'Retain me printer not found');
            throw new RedirectException($redirect);
        }
        $order_id = Request::getPositiveId();
        $order = Orders::getPackingOrderById($order_id, $carriers);
        if (empty($order)) {
            View::addFlash('packing', 'danger', 'Order not found');
            throw new RedirectException($redirect);
        }
        $packages_all = Packages::getAllPackages($order['sid'], $this->warehouse_id, Package::STATE_ACTIVE);
        if (empty($packages_all)) {
            View::addFlash('packing', 'danger', 'Packages not found');
            throw new RedirectException($redirect);
        }
        $warehouse_name = $this->warehouse->getName() ?? '-';
        array_walk($packages_all, function (&$package) use ($warehouse_name) {
            if (empty($package['supplier_id']))
                $package['name'] .= ' (' . $warehouse_name . ')';
        });
        if (PackModel::isActualPacker($order_id, $this->user->getId(), PackModel::resolveOrderPackingLockTime($_SESSION['packing_mode']))) {
            View::addFlash('packing', 'danger', "You were not assigned to this packing");
            throw new RedirectException($redirect);
        }
        $defends = (new Defends())->setType(Defends::TYPE_ORDERS)->setKey((string)$order_id);
        if ($defends->lockDate() === Defends::LOCK_NOT_UPDATE) {
            $defends->unLock();
            View::addFlash('packing', 'danger', "Order locked. Please try again.");
            throw new RedirectException($redirect);
        }
        if (!in_array($order['status'], Order::$allowed_for_printing_statutes)) {
            Debug::sendDebugInfo('Packing error', json_encode([
                'id' => $order['id'],
                'status' => $order['status'],
                'user' => $this->user->getLogin(),
                'warehouse' => $this->warehouse->getName(),
                'from' => $_GET['mode'],
                'message' => 'Order is not packed yet to print documents'
            ]), Debug::TEST);
            $defends->unLock();
            View::addFlash('packing', 'danger', 'Order is not packed yet to print documents');
            throw new RedirectException($redirect);
        }
        $consignee = Deliveries::getById($order['delivery_id']);
        if ($consignee === null) {
            $defends->unLock();
            View::addFlash('packing', 'danger', 'Cannot create shipping label. Consignee address is empty');
            throw new RedirectException($redirect);
        }
//        $supplier = Suppliers::getSupplierByUser($this->warehouse_id, $order['order_user_id']);
        $supplier = Suppliers::getSupplierById($this->warehouse_id, $order['supplier_id']);
        if ($supplier === null) {
            $defends->unLock();
            View::addFlash('packing', 'danger', 'Company account may be blocked. Please contact system administrator');
            throw new RedirectException($redirect);
        }
        $supplier_warehouses = SupplierWarehouses::getWarehouses($supplier['id']);
        if (empty($supplier_warehouses) || empty($supplier_warehouses[$this->warehouse_id])) {
            $defends->unLock();
            View::addFlash('packing', 'danger', 'Supplier is not found in warehouse');
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        $return_carrier = null;
        if ($supplier_warehouses[$this->warehouse_id]['return_label']) {
            $return_carrier = ReturnCarriers::getBySupplierWarehouseId($supplier_warehouses[$this->warehouse_id]['id']);
            if (empty($return_carrier)) {
                $defends->unLock();
                View::addFlash('packing', 'danger', 'Return Carrier is not found');
                throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
            }
            $countries = $return_carrier->getCountries();
            if (!empty($countries) && !in_array($consignee->getCountryId() == 'UK' ? 'GB' : $consignee->getCountryId(), $countries))
                $return_carrier = null;
        }
        $packages = Orders::getPackages($order_id);
        if (empty($packages)) {
            Debug::sendDebugInfo('Packing error', json_encode([
                'id' => $order['id'],
                'status' => $order['status'],
                'user' => $this->user->getLogin(),
                'warehouse' => $this->warehouse->getName(),
                'from' => $_GET['mode'],
                'message' => 'Packages not found'
            ]), Debug::TEST);
            $defends->unLock();
            View::addFlash('packing', 'danger', 'Packages not found');
            throw new RedirectException($redirect);
        }
        if (!empty($return_carrier)) {
            $return_ids = OrderPackageReturns::getReturns(array_keys($packages));
            foreach ($packages as $package_id => &$package) {
                $package['return_id'] = $return_ids[$package_id] ?? 0;
            } unset ($package);
        }
        $is_single = count($packages) === 1;
        [$shipping, $carrier, $shipping_class] = Shipping::getShipping($this->logistic_company, $order['carrier_id']);
        //Automatically change service for Multibox Shipments
        $order_config = [];
        if ($shipping_class === Shipping::CLASS_SHIPPING_AB && !$is_single) {
            if (((int)(ShippingServices::getByCode(PackModel::MULTI_BOX_CHANGE_SHIPPING_SERVICE_FROM)['id'] ?? 0) === (int)$order['shipping_services_id'])
                && $new_shipping = ShippingServices::getByCode(PackModel::MULTI_BOX_CHANGE_SHIPPING_SERVICE_TO)
            ) {
                [$shipping, $carrier, $shipping_class] = Shipping::getShipping($this->logistic_company, $new_shipping['carrier_id']);
                $order_config[] = OrdersConfig::despatchedCarrierId($order_id, $carrier->getId());
                $order_config[] = OrdersConfig::shippingServiceId($order_id, $new_shipping['id']);
                $order['shipping_services_id'] = $new_shipping['id'];
                $order['carrier_id'] = $carrier->getId();
            }
        }
        Orders::createShipping($this->user->getId(), $shipping, $supplier, $consignee, $this->warehouse, $order, $packages, $shipping_class);
        if (Order::isPrintingDocuments($order['status']))
            OrdersConfig::saveConfigs($order_config);
        $shipping_data = [];
        foreach (Shippings::getShippingDataByOrderId($order_id) as $shipping_res) {
            $shipping_res['create_date'] = $this->handleTimezone($shipping_res['create_date']);
            $shipping_data[$shipping_res['package_name']][] = $shipping_res;
        }
        $return_data = [];
        if (!empty($return_carrier))
            $return_data = Returns::getReturnDataByOrderId($order_id);
        $error_instructions = '';
        if (Order::isPrintingDocumentsIssue($order['status']))
            $error_instructions = "You can either unpack boxes or return back to packing other orders.";
        $retain_me_slip_error = '';
        if (SuppliersWarehouses::getPackingSlipType($this->warehouse_id, $supplier['id'], $order['shop_id']) === PackingSlips::TYPE_RETAIN_ME) {
            $order_obj = Orders::getById($order_id);
            $result = PackingSlips::callRetainMe([
                'order' => $order_obj,
                'supplier' => Suppliers::getById($supplier['id']),
                'delivery' => $consignee,
                'skus' => OrderSKUsModel::getSKUs($order_id),
                'requests' => ['print'],
                'source_id' => PrintersModel::getById($_GET['source_id'])->getSourceId(),
            ]);
            if(!$result)
                $retain_me_slip_error = 'Retain.me packing slip print failed.';
            else {
                $order_obj->addPackingSlipPrint();
                $order_obj->save(['id', 'packing_slip_print']);
            }
        }
        $source_id = (int)$_GET['source_id'];
        $user_printer = $this->user->getRetainMePrinter();
        if ($user_printer != $source_id) {
            $this->user->setRetainMePrinter($source_id);
            $this->user->save(['id', 'settings']);
        }
        $defends->unLock();
        $order_info = [
            'id' => $order_id,
            'reference' => $order['ref'],
            'status' => $order['status'],
            'shipping_data' => $shipping_data,
            'return_data' => $return_data,
            'error_instructions' => $error_instructions,
            'back_url' => $redirect,
            'shipping_services_id' => $order['shipping_services_id'],
            'packing' => $order['packing'],
            'orderDoc' => OrderDocs::getFirst($order_id)
        ];
        $data = [
            'order_info' => $order_info,
            'carrier' => $carrier,
            'packages' => $packages,
            'package_types' => Arr::map($packages_all, 'id', 'name'),
            'packing_slip_type' => SuppliersWarehouses::getPackingSlipType($this->warehouse_id, $supplier['id'], $order['shop_id']),
            'packing_slip_url' => HTTP::getDomain() . "/orders/get_packing_slip?id=" . (int)$order_id . "&source_id=$source_id",
            'retain_me_slip_error' => $retain_me_slip_error,
            'serial_numbers' => OrderSKUSerialNumbers::getByOrderIdsWithOrdersPackagesId([$order_id])[$order_id] ?? [],
            'source_id' => $source_id,
            'timer_print_pdf' => (WarehousesConfig::getTimerStatus($this->warehouse_id) ? WarehousesConfig::getTimerPrintPdf($this->warehouse_id) * 1000 : null),
        ];
        $this->render($data, 'packing/packing_printing_documents');
    }

    /** Changes status from printing documents to despatched. Final packing stage */
    /** @throws */
    public function confirmPrinting()
    {
        $carriers = Carriers::getProcessing($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('packing', 'danger', 'Carriers are not found');
            throw new RedirectException('/');
        }
        if (empty($_SESSION['packing_mode'])) {
            View::addFlash('packing', 'danger', 'Try again');
            throw new RedirectException('/');
        }
        $order_id = Request::getPositiveId($_POST, "order_id");
        $order = Orders::getById($order_id);
        if (empty($order) || !in_array($order->getCarrierId(), $carriers)) {
            View::addFlash('packing', 'danger', "Order not found");
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        if (!$order->printingDocuments()) {
            View::addFlash('packing', 'danger', "Order is not in the printing documents stage");
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        if ($order->despatched()) {
            View::addFlash('packing', 'danger', "Order is already despatched");
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        if (PackModel::isActualPacker($order_id, $this->user->getId(), PackModel::resolveOrderPackingLockTime($_SESSION['packing_mode']))) {
            View::addFlash('packing', 'danger', "You were not assigned to this packing");
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        $defends = (new Defends())->setType(Defends::TYPE_ORDERS)->setKey((string)$order_id);
        if ($defends->lockDate() === Defends::LOCK_NOT_UPDATE) {
            $defends->unLock();
            View::addFlash('packing', 'danger', "Order locked. Please try again.");
            throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
        }
        PackModel::printingDocsConfirm($order, $this->user, $_SESSION['packing_mode']);
        $defends->unLock();
        View::addFlash('packing', 'success', "Order has been successfully packed!");
        throw new RedirectException(PackModel::errorUrl($_SESSION['packing_mode']));
    }

    /** @throws */
    public function packingUnload()
    {
        $back = $_GET['back'];
        $mode = $_GET['mode'];
        $cart_id = (int)$_GET['cart_id'];
        $table_id = (int)$_GET['table_id'];
        if ($mode != PackModel::PACKING_SINGLE && $mode != PackModel::PACKING_LARGE) {
            View::addFlash('packingUnload', 'danger', 'You cannot unload this trolley');
            throw new RedirectException($back);
        }
        $cart = LocationsModel::getById($cart_id);
        if (!$cart) {
            View::addFlash('packingUnload', 'danger', 'Cart not found');
            throw new RedirectException($back);
        }
        $table = LocationsModel::getById($table_id);
        if (!$table) {
            View::addFlash('packingUnload', 'danger', 'Table not found');
            throw new RedirectException($back);
        }
        if (empty(SKULocations::getQuantityInLocation($cart->getId()))) {
            View::addFlash('packingUnload', 'danger', 'Trolley is empty');
            throw new RedirectException($back);
        }
        $orders = SKULocations::getSKUsToUnload($cart->getId(), $mode);
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        self::writePackingUnload($orders, (int)$table->getId(), (int)$cart->getId(), $mode, !$cart->isDetached());
        $mysql->commit();
        View::addFlash('packingUnload', 'success', "SKUs were successfully dropped at table {$table->getLocation($this->warehouse_label)}");
        throw new RedirectException($back);
    }

    private function writePackingUnload(array $orders, int $table_id, int $cart_id, string $mode, bool $remove_by_cart_id = true)
    {
        if (!$orders)
            return;
        $ords = $SKUs = [];
        $successes = [];
        foreach ($orders as $id => $order) {
            list(, $SKU_id, $batch_id) = explode('_', $id);
            $SKUs[$SKU_id] = 1;
            if ($batch_id == '.')
                $batch_id = '';
            foreach ($order as $order_id => $quantity) {
                OrderLocationTemporaries::addSKU($SKU_id, $quantity, $order_id, $table_id);
                OrderLocations::reserveLocation($order_id, $table_id);
                $move_id = Movements_::setDropping($table_id, $order_id, $SKU_id, $batch_id, $quantity, $this->user->getId());
                SKUHistories::addDropping($cart_id, $table_id, $order_id, $SKU_id, $batch_id, $quantity, $move_id, $this->user->getId(), $this->warehouse_id);
                PickModel::addOnTable($SKU_id, $batch_id, $quantity, 31, $order_id, $table_id, $cart_id, $this->user->getId());
                $successes[] = "SKU (#$SKU_id" . (empty($batch_id) ? '' : ", Batch#: $batch_id") . "), quantity $quantity, order #$order_id, is unloaded to packing station";
                if ($mode === PackModel::PACKING_SINGLE)
                    Orders::changeStatus($order_id, Order::AWAITING_PACK, $this->user->getId());
                else
                    $ords[$order_id] = 1;
            }
        }
        if (!empty($successes)) {
            if ($remove_by_cart_id)
                SectionBindings::removeByCartId($cart_id);
            View::addFlash('packingUnload','success', $successes);
        }
        foreach ($ords as $order_id => $tmp)
            if (OrderSKUs::isCompleted($order_id))
                Orders::changeStatus($order_id, Order::AWAITING_PACK, $this->user->getId());
        foreach ($SKUs as $SKU_id => $tmp)
            SKUHistories::updateStock($SKU_id, $this->warehouse_id);
    }

    public function checkRequestFile()
    {
        $file = !empty($_POST['filename']) ? urldecode($_POST['filename']) : '';
        return $this->response = [!empty(Storage::getFile(SHIPPING_LABELS_LOG_STORAGE, $file)) ? 'success' : 'error'];
    }

    public function getRequestFile()
    {
        $file = !empty($_GET['filename']) ? urldecode($_GET['filename']) : '';
        $file_path = Storage::getFile(SHIPPING_LABELS_LOG_STORAGE, $file);
        if (!empty($file_path)) {
            header('Content-Encoding: UTF-8');
            header('Content-Type: application/txt; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $file);
            echo stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($file_path)))->getStream($file_path));
        } else
            echo Html::closeWindow();
    }

    private function addSKUsToProvidedDataSingle(array &$provided_data, array $SKUs): void
    {
        $single_SKU = array_values($SKUs)[0];
        $provided_data['box'][key($provided_data['box'])]['SKUs'][$single_SKU['SKU_id']] = 1;
    }
}
