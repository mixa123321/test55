<?php

namespace app\backend\controllers;

use app\backend\models\Carriers;
use app\backend\models\Bundles as BundlesModel;
use app\backend\models\Deliveries as DeliveriesModel;
use app\backend\models\Deliveries;
use app\backend\models\OrderHistories;
use app\backend\models\Orders as OrdersModel;
use app\backend\models\OrderSKUs;
use app\backend\models\OrdersOriginalDespatched;
use app\backend\models\Printers as PrintersModel;
use app\backend\models\SKULocations;
use app\backend\models\SKUs as SKUsModel;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\Pack as PackModel;
use app\backend\models\WebhookHistories;
use app\backend\models\ShippingServices as ShippingServicesModel;
use app\backend\models\OrderDocs;
use app\core\dbal\SafeMysql;
use app\core\entities\Address;
use app\core\entities\Delivery;
use app\core\entities\Order;
use app\core\entities\OrderDoc;
use app\core\entities\OrderQueue;
use app\core\entities\Shipping;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\OrdersOriginalRequest;
use app\core\models\OrdersSubstitutions;
use app\core\models\OrderQueue as OrderQueueModel;
use app\core\models\PackingSlips;
use app\backend\models\Shippings;
use app\core\models\OrderCategories;
use app\core\models\SuppliersWarehouses;
use app\core\models\WarehouseCarriers;
use app\core\models\Warehouses;
use app\core\models\OrderSKUSerialNumbers;
use app\core\models\config\OrdersConfig;
use app\core\Queue;
use app\core\services\archiving\ArchivingOrderService;
use app\core\services\ViewOrderDataService;
use app\core\statical\Countries;
use app\core\statical\Currencies;
use app\core\statical\Sources;
use app\core\statical\WithDate;
use app\core\helpers\File;

class Orders extends AuthController
{
    public function index()
    {
        if (empty($_GET))
            $_GET['date_from'] = date("Y-m-d", strtotime('-7 day'));
        ///++ Order Search Refinement
        if (!empty($_GET['search_focus']))
            if (!empty($_GET['global_search']) || !empty($_GET['id']) || !empty($_GET['ref']))
                unset($_GET['date_from'], $_GET['date_to'], $_GET['date_type']);
        ///-- Trello
        $filters = $_GET;
        $filters_sup['warehouse_id'] = $filters['warehouse_id'] = $this->warehouse_id;
        $filtersAgg = OrdersModel::resolveAggregateParams($filters, [
            'date_to' => $_GET['date_to'] ?? null,
            'date_from' => $_GET['date_from'] ?? null
        ]);
        $_SESSION[OrdersModel::FILTER_PARAM] = http_build_query($_GET);
        if ($this->user->isRoleSupplierAdmin())
            $filters_sup['admin_ids'] = [$this->user->getId()];
        elseif ($this->user->isRoleSupplierUser()) {
            $filters_sup['user_ids'] = [$this->user->getId()];
            $filtersAgg['supplier_id'] = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())['id'];
        }
        $suppliers = Arr::map(SuppliersModel::getByFilters($this->logistic_company->getId(), $filters_sup), 'id', 'name');
        if (empty($filtersAgg['supplier_id']) || (!empty($filtersAgg['supplier_id']) && !in_array($filtersAgg['supplier_id'], array_keys($suppliers))))
            $filtersAgg['supplier_id'] = array_keys($suppliers);
        $shipping_services = Arr::map(ShippingServicesModel::getByWarehouse($this->warehouse_id), 'id', 'value');
        if (!empty($filtersAgg['shipping_services_id'])
            && $filtersAgg['shipping_services_id'] !== 'all'
            && !array_key_exists($filtersAgg['shipping_services_id'], $shipping_services))
                $filtersAgg['shipping_services_id'] = 'all';
        $raw_data = OrdersModel::getAll($filtersAgg);
        foreach ($raw_data['records'] as $key => $order) {
            $address = new Address($order);
            $raw_data['records'][$key]['address'] = $raw_data['records'][$key]['is_hash'] ? $address->toHash() : $address->toString();
            if (!empty($this->time_diff)) {
                $raw_data['records'][$key]['create_date'] = $this->handleTimezone($order['create_date']);
                if (!empty($order['despatched_date']))
                    $raw_data['records'][$key]['despatched_date'] = $this->handleTimezone($order['despatched_date']);
            }
        }
        $docs = OrderDocs::getIdsByOrders(Arr::column($raw_data['records'], 'order_id'));
        OrderHistories::isOrdersRisk($raw_data['records']);
        $current_filters = $filtersAgg;
        if(isset($current_filters))
            unset($current_filters['c']);
        $statuses = OrdersModel::getStatuses($this->warehouse_id, $this->models_tokens);
        $vars = [
            'orders' => $raw_data['records'],
            'docs' => $docs,
            'statuses' => $statuses,
            'shipping_services' => ['all' => 'all', Shipping::COLLECTION_BY_CUSTOMER_ID => Shipping::COLLECTION_BY_CUSTOMER] + $shipping_services,
            'total_count' => $raw_data['total'],
            'total_pages' => ceil($raw_data['total'] / OrdersModel::LIMIT_PER_PAGE),
            'current_page' => $raw_data['current_page'],
            'suppliers' => (count($suppliers) > 1 ? ['all'] : []) + $suppliers,
            'countries' => ['all'] + Countries::getAll(),
            'sources' => ['all' => 'All'] + Sources::getAll(),
            'current_filters' => http_build_query($current_filters),
            'date_types' => OrdersModel::dateTypes($this->models_tokens),
            'subwarehouses' => $this->warehouse_virtual ? Shippings::getVirtualWarehouses(Arr::column($raw_data['records'], 'order_id')) : [],
            'exclude_ids' => OrdersConfig::readConfigsByIdsAndParams(Arr::column($raw_data['records'], 'order_id'), [OrdersConfig::TYPE_EXCLUDE_ORDER_STATUS]),
        ];
        $this->render($vars, 'orders/orders');
    }

    /** @throws */
    public function minified()
    {
        if (!$this->user->isRoleWorker())
            throw new RedirectException('/orders');
        $this->mobile = true;
        $back_url = '/orders/minified';
        $order_id = Request::getPositiveId();
        if (isset($_GET['id']) && ((string)$order_id !== $_GET['id'] || $order_id === 0)) {
            View::addFlash('Orders', 'danger', 'Scanning error');
            throw new RedirectException($back_url);
        }
        $order = null;
        $carrier = null;
        if (!empty($order_id)) {
            $order = OrdersModel::getById($order_id);
            if (empty($order)) {
                View::addFlash('Orders', 'danger', 'Order not found');
                throw new RedirectException($back_url);
            }
            if ((int)$order->getWarehouseId() !== (int)$this->user->getWarehouseId()) {
                View::addFlash('Orders', 'danger', 'Access denied');
                throw new RedirectException($back_url);
            }
            $carriers = WarehouseCarriers::getAvailableCarrierList($this->warehouse_id);
            if (empty($carriers)) {
                View::addFlash('Orders', 'danger', 'Carriers are not found');
                throw new RedirectException($back_url);
            }
            $carrier_id = (empty($order->getCarrierId()) || !in_array($order->getCarrierId(), Arr::column($carriers, 'id'))) ? $carriers[0]['id'] : $order->getCarrierId();
            $carrier = Carriers::getById($carrier_id);
        }
        $orderDetails = null;
        $printed_matter = 0;
        if (!empty($order)) {
            $orderDetails = $this->getViewOrderData($order);
            if (empty($orderDetails)) {
                View::addFlash('Orders', 'danger', 'Server error');
                throw new RedirectException($back_url);
            }
            $printed_matter =  OrdersConfig::getOrderPrintedMatter($order->getId());
        }

        $this->render([
            'order' => $order,
            'orderDetails' => $orderDetails,
            'shipping_services' => !empty($order) ?Shippings::handleShippingServicesByWarehouse($this->warehouse_id) : [],
            'carrier' => $carrier,
            'eori_number' => OrdersConfig::getOrderEoriNumber($order_id),
            'vat_number' => OrdersConfig::getOrderVatNumber($order_id),
            'ioss_number' => OrdersConfig::getOrderIossNumber($order_id),
            'tax_id' => OrdersConfig::getOrderTaxId($order_id),
            'printed_matter' => $printed_matter,
       ], 'orders/orders_minified');
    }

   public function changePurchaseOrderNumbers()
    {
        if (empty($_POST) || !isset($_POST['purchase_order_number']) || !isset($_POST['order_id'])) {
            $this->response = json_encode(['error' => 'Purchase Order Number is missing']);
            return;
        }
        $order_id = (int)$_POST['order_id'];
        $purchase_order_number = trim($_POST['purchase_order_number']);
        $order = OrdersModel::getById($order_id);
        if (empty($order) || !OrdersModel::isAccess($order, $this->user, array_keys($this->available_suppliers))) {
            $this->response = json_encode(['error' => 'Order not found or access denied']);
            return;
        }

        if (empty($this->available_suppliers[$order->getSupplierId()])) {
            $this->response = json_encode(['error' => 'Order not found']);
            return;
        }
        $order->setPurchaseOrderNumber($purchase_order_number);
        $order->save(['id', 'purchase_order_number']);
        $this->response = json_encode(['success']);
    }

    private function getViewOrderData(Order $order): array
    {
        try {
            $view_service = new ViewOrderDataService($order, $this->warehouse);
            $order_details = $view_service->getFullData();
            if (empty($order_details))
                throw new \Exception();
            $order_details['serial_numbers'] = OrderSKUSerialNumbers::getAllByOrderIdGroupSKUId((int)$order->getId(), $this->warehouse);
            // start TIMEZONE
            if (!empty($this->time_diff)) {
                $order->setCreateDate($this->handleTimezone($order->getCreateDate()));
                if (!empty($order->getDespatchedDate()))
                    $order->setDespatchedDate($this->handleTimezone($order->getDespatchedDate()));
                $packages = $order_details['packages'] ?? [];
                if (!empty($packages))
                    foreach ($packages as &$package)
                        if (!empty($package['shipping']))
                            $package['shipping']['create_date'] = $this->handleTimezone($package['shipping']['create_date']);
                $order_details['packages'] = $packages;
                $shipping_data = $order_details['shipping_data'] ?? [];
                if (!empty($shipping_data))
                    foreach ($shipping_data as &$shipping_package) {
                        if (empty($shipping_package))
                            continue;
                        foreach ($shipping_package as &$shipping_item)
                            $shipping_item['create_date'] = $this->handleTimezone($shipping_item['create_date']);
                    }
                $order_details['shipping_data'] = $shipping_data;
                $history = $order_details['history'] ?? [];
                if (!empty($history))
                    array_walk($history, function(&$item) {
                        $item['create_date'] = $this->handleTimezone($item['create_date']);
                    });
                $order_details['history'] = $history;
            }
            // end TIMEZONE
            return $order_details;
        } catch (\Exception $exception) {
            return [];
        }
    }

    /** @throws */
    public function add($delivery = null, $quantities = null)
    {
//        if (!$this->user->isClientSide())
//            throw new RedirectException('/');
        if (!empty($_GET['data'])) {
            $data = json_decode(base64_decode($_GET['data']), true);
            $quantities = empty($data['quantity']) ? [] : $data['quantity'];
            $delivery = $data['delivery'];
            $order = $data['order'];
        } else {
            $delivery = is_null($delivery) ? [] : $delivery->toArray();
            if (empty($_POST['order']))
                $order = (new Order(['DDP' => Shippings::TYPE_TERM_DDU]))->toArray();
            else
                $order = $_POST['order'];
        }
        try {
            list(
                'suppliers' => $suppliers,
                'users' => $users,
                'supplier_id' => $supplier_id,
                'user_id' => $user_id,
            ) = OrdersModel::getAvailableSuppliersAndUsersForOrdersCreation(
                $this->warehouse,
                $this->logistic_company,
                $this->user,
                $order['supplier_id'] ?? '',
                $order['user_id'] ?? ''
            );
        } catch (\Exception $e) {
            View::addFlash('Orders', 'danger', $e->getMessage());
            throw new RedirectException('/orders');
        }
        $SKUs = SKUsModel::getLeftBySupplier($this->warehouse_id, $supplier_id);
        $all_bundles = Arr::maps(BundlesModel::getAll(null, ['supplier_id' => $supplier_id], true)['records'], 'id', ['name', 'sku_list', 'cost_value']);
        $SKUs_to_search = [];
        $SKUs_info = [];
        $bundles_info = [];
        $qties = [];
        foreach ($SKUs as $SKU) {
            $SKUs_to_search[] = [
                'value' => $SKU['barcode'],
                'label' => $SKU['barcode'] . " / " . $SKU['name'] . " / " . $SKU['customer_ref'],
                'active' => $SKU['state'],
                'type' => 'SKU',
            ];
            $SKUs_info[$SKU['barcode']] = $SKU;
            if (!empty($quantities[$SKU['id']]))
                $qties[$SKU['barcode']] = ['quantity' => $quantities[$SKU['id']]];
        }
        foreach ($all_bundles as $bundle_id => $bundle) {
            $SKUs_to_search[] = [
                'value' => $bundle['name'],
                'label' => $bundle['name'],
                'active' => 1,
                'type' => 'bundle'
            ];
            $bundle_SKUs = array_filter(BundlesModel::getBundleSKUs($bundle_id), function($SKU) {
                return $SKU['state'] != 0;
            });
            $bundles_info[$bundle_id] = [
                'name' => $bundle['name'],
                'show_name' => "<b>{$bundle['name']}</b>",
                'bundle_id' => $bundle_id,
                'SKUs' => array_map(function($bundle_SKU) {
                    return ['id' => $bundle_SKU['id'], 'quantity' => $bundle_SKU['quantity']];
                }, Arr::maps($bundle_SKUs, 'barcode', ['id', 'quantity', 'state'])),
                'quantity' => 1,
                'price' => $bundle['cost_value'],
            ];
        }

        $order_bundles = [];
        foreach ($all_bundles as $bundle_id => $bundle)
            if (!empty($quantities['bundle_' . $bundle_id])) {
                $bundle_SKUs = array_filter(BundlesModel::getBundleSKUs($bundle_id), function($SKU) {
                    return $SKU['state'] != 0;
                });
                if (empty($bundle_SKUs))
                    continue;
                $quantity = intval($quantities['bundle_' . $bundle_id]);
                $order_bundles[$bundle_id] = [
                    'name' => "<b>{$bundle['name']}</b>",
                    'SKUs' => array_map(function($bundle_SKU) {
                        return ['id' => $bundle_SKU['id'], 'quantity' => $bundle_SKU['quantity']];
                    }, Arr::maps($bundle_SKUs, 'barcode', ['id', 'quantity', 'state'])),
                    'quantity' => $quantity,
                    'price' => (float)array_reduce($bundle_SKUs, function($price, $bundle_SKU) use ($quantity) {
                        return $price + ($bundle_SKU['quantity'] * $bundle_SKU['market_value']);
                    }, 0)
                ];
            }

        $back_url = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        $carriers = WarehouseCarriers::getAvailableCarrierList($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('Orders', 'danger', 'Carriers are not found');
            throw new RedirectException($back_url);
        }
        $default_shipping_service = Warehouses::getDefaultShippingService($this->warehouse_id);
        $carrier_id = $_POST['order']['carrier_id'] ??
                      (empty($default_shipping_service) || empty($default_shipping_service['carrier_id']) ? $carriers[0]['id'] : $default_shipping_service['carrier_id']);
        $countries = Countries::getAll();
        unset($countries['GB']);

        $data = [
            'suppliers' => Arr::columnAssoc($suppliers, 'name'),
            'available_skus' => View::toJs($SKUs_to_search),
            'skus_info' => View::toJs($SKUs_info),
            'bundles_info' => View::toJs($bundles_info),
            'quantities' => View::toJs($qties),
            'order_bundles' => View::toJs($order_bundles),
            'countries' => $countries,
            'in_date' => empty($this->time_diff) ? time() : strtotime($this->handleTimezone(date(WithDate::DATE_SQL_FORMAT))),
            'supplier_id' => $supplier_id,
            'users' => Arr::columnAssoc($users, 'login'),
            'user_id' => $user_id,
            'delivery' => $delivery,
            'carrier_id' => $carrier_id,
            'carriers' => Arr::map($carriers, 'id', 'title'),
            'order' => $order,
            'back' => $back_url,
            'shipping_service_id' => $_POST['order']['shipping_services_id'] ??
                                     (empty($default_shipping_service) || empty($default_shipping_service['shipping_service_id']) ? 0 : $default_shipping_service['shipping_service_id']),
            'shipping_services' => Shippings::handleShippingServicesByWarehouse($this->warehouse_id),
            'order_categories' => OrderCategories::getCategories(),
            'printed_matter' => $_POST['order']['config'][OrdersConfig::TYPE_ORDER_PRINTED_MATTER] ?? 0,
            'eori_number' => $_POST['order']['config'][OrdersConfig::TYPE_ORDER_EORI_NUMBER] ?? '',
            'vat_number' => $_POST['order']['config'][OrdersConfig::TYPE_ORDER_VAT_NUMBER] ?? '',
            'ioss_number' => $_POST['order']['config'][OrdersConfig::TYPE_ORDER_IOSS_NUMBER] ?? '',
            'tax_id' => $_POST['order']['config'][OrdersConfig::TYPE_ORDER_TAX_ID] ?? '',
        ];
        $this->render($data, 'orders/add_order');
    }

    /** @throws */
    public function csvReport()
    {
        $count = !empty($_GET['count']) ? intval($_GET['count']) : 0;
        unset($_GET['count']);
        $limit =  OrdersModel::LIMIT_FLASH_GENERATE_REPORT;
        if ($count <= $limit)
            $count = OrdersModel::getTotalCountReportCSV($_GET);
        if ($count <= $limit) {
            set_time_limit(0);
            header('Content-Encoding: UTF-8');
            header('Content-Type: application/excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . OrdersModel::getReportName() . '"');
            OrdersModel::getAllAsCSV($this->warehouse, $_GET ?? []);
            return;
        } else {
            $user_job_id = Queue::addJobToGenerateReportCSV([
                'user_id' => $this->user->getId(),
                'filters' => $_GET,
                'addition' => ['warehouse_id' => $this->warehouse_id],
                'count' => $count
            ], self::class);
            if ($user_job_id) {
                $_SESSION['user_queue'] = true;
                $_SESSION['user_queue_flash'] = true;
                View::addFlash('upload_file', 'warning', 'Quantity of orders more than ' . $limit .
                    '. The report will be processed in the queue. Job #' . $user_job_id);
            } else
                View::addFlash('upload_file', 'danger', "Server error");
            throw new RedirectException("/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : ''));
        }
    }

    /* Edit Order delivery address. Accessible via ViewOrder for cancelled orders; SA/Manager also Order::canEditDeliveryAtPacking(). EORI, VAT, IOSS numbers, TAX ID: SA/Manager when Order::canEditTaxNumbers() */
    public function saveDelivery()
    {
        if (empty($_POST)) {
            $this->response = json_encode(['error']);
            return;
        }
        $order_id = $_POST['order_id'];
        $eori_number = trim($_POST['eori_number'] ?? '');
        $vat_number = trim($_POST['vat_number'] ?? '');
        $ioss_number = trim($_POST['ioss_number'] ?? '');
        $tax_id = trim($_POST['tax_id'] ?? '');
        unset($_POST['eori_number'], $_POST['vat_number'], $_POST['ioss_number'], $_POST['tax_id']);
        $order = OrdersModel::getById($order_id);
        if ($order === null) {
            $this->response = json_encode(['error']);
            return;
        }
        if (empty($this->available_suppliers[$order->getSupplierId()])) {
            $this->response = json_encode(['error']);
            return;
        }
        $can_edit_address = $order->canEdit() || ($this->user->hasManagerAccess() && $order->canEditDeliveryAtPacking());
        $can_edit_tax_numbers = $this->user->hasManagerAccess() && $order->canEditTaxNumbers();
        if (!$can_edit_address && !$can_edit_tax_numbers) {
            $this->response = json_encode(['error']);
            return;
        }
        if (!$can_edit_address) {
            $this->saveTaxNumbers($order, $eori_number, $vat_number, $ioss_number, $tax_id);
            $this->response = json_encode([$order->getDeliveryId()]);
            return;
        }
        $original_delivery = DeliveriesModel::getById($order->getDeliveryId());
        unset($_POST['order_id']);

        if (!empty(OrdersModel::getOrdersByDelivery($_POST['id'], $order_id)))
            unset($_POST['id']);

        $delivery = new Delivery($_POST);
        $errors = DeliveriesModel::validate($delivery, $this->warehouse_virtual, true);
        if (!empty($errors)) {
            $this->response = json_encode(['error']);
            return;
        }
        $address_original = ($original_delivery !== null) ? new Address($original_delivery->toArray()) : new Address();
        $address_changed = new Address($delivery->toArray());

        OrderHistories::saveDelivery($order_id, $this->user->getId(), $address_original, $address_changed);
        Queue::addJobToUpdateOrderSearch(['order_id' => $order_id]);
        $id = DeliveriesModel::handleDeliveryReturningId($delivery, $this->user->getId(), $order);
        if ($can_edit_tax_numbers)
            $this->saveTaxNumbers($order, $eori_number, $vat_number, $ioss_number, $tax_id);
        if (!empty($id)) {
            if ((int)$id !== (int)$order->getDeliveryId()) {
                $order->setDeliveryId($id);
                $order->save(['id', 'delivery_id']);
            }
            $this->response = json_encode([$id]);
            return;
        }
        $this->response = json_encode(['none']);
    }

    private function saveTaxNumbers(Order $order, string $eori_number, string $vat_number, string $ioss_number, string $tax_id)
    {
        OrderHistories::saveTaxNumbers($order->getId(), $this->user->getId(),
            OrdersConfig::getOrderEoriNumber($order->getId()), $eori_number,
            OrdersConfig::getOrderVatNumber($order->getId()), $vat_number,
            OrdersConfig::getOrderIossNumber($order->getId()), $ioss_number,
            OrdersConfig::getOrderTaxId($order->getId()), $tax_id);
        OrdersConfig::saveConfigs([
            OrdersConfig::orderEoriNumber($order->getId(), $eori_number),
            OrdersConfig::orderVatNumber($order->getId(), $vat_number),
            OrdersConfig::orderIossNumber($order->getId(), $ioss_number),
            OrdersConfig::orderTaxId($order->getId(), $tax_id),
        ]);
    }

    /* Edit Order references. Accessible via ViewOrder for cancelled orders */
    public function saveRef()
    {
        if (empty($_POST) || empty($_POST['ref'])) {
            $this->response = json_encode(['error' => 'Customer reference must be filled up']);
            return;
        }
        $order_id = $_POST['order_id'];
        $purchase_order_number = trim($_POST['purchase_order_number']);
        $order = OrdersModel::getById($order_id);
        if (empty($order) || !OrdersModel::isAccess($order, $this->user, array_keys($this->available_suppliers))) {
            $this->response = json_encode(['error' => 'Order not found or access denied']);
            return;
        }
        if (!$order->canEdit() && !$order->canEditPurchaseOrderNumber()) {
            $this->response = json_encode(['error' => 'Cannot edit this order']);
            return;
        }
        if (empty($this->available_suppliers[$order->getSupplierId()])) {
            $this->response = json_encode(['error' => 'Order not found']);
            return;
        }
        if ($order->getRef() == $_POST['ref'] && $order->getRef2() == $_POST['ref2'] && $order->getPurchaseOrderNumber() == $purchase_order_number) {
            $this->response = json_encode(['success']);
            return;
        }
        $_POST['ref'] = trim($_POST['ref']);
        if (mb_ereg('\s+', $_POST['ref'])) {
            $this->response = json_encode(['error' => 'Spaces not allowed in customer ref']);
            return;
        }
        $existing_order = OrdersModel::getByRef($_POST['ref'], $order->getSupplierId());
        if ($existing_order !== null && $existing_order->getId() != $order->getId()) {
            $this->response = json_encode(['error' => 'This Customer reference already exists']);
            return;
        }
        OrderHistories::saveRef($order_id, $this->user->getId(), $order->getRef(), $_POST['ref'], $order->getRef2(), $_POST['ref2'], $order->getPurchaseOrderNumber(), $purchase_order_number);
        Queue::addJobToUpdateOrderSearch(['order_id' => $order_id]);
        $order->setRef($_POST['ref']);   
        $order->setRef2($_POST['ref2']);
        $order->setPurchaseOrderNumber($purchase_order_number);
        $order->save(['id', 'ref', 'ref2', 'purchase_order_number']);
        $this->response = json_encode(['success']);
    }

    public function updateOrderDoc()
    {
        if (empty($_POST) || empty($_POST['docMethod']) || !in_array($_POST['docMethod'], ['FILE', 'URL', 'REMOVE', 'RESET']))
            return $this->response = json_encode(['result' => 'error', 'message' => 'Not selected']);
        $order_id = Request::getPositiveId($_POST, 'order_id');
        $order = OrdersModel::getById($order_id);
        if ($order === null)
            return $this->response = json_encode(['result' => 'error', 'message' => 'Order not found']);
        if (!$order->canEdit() && !$order->awaitingUploadDocuments())
            return $this->response = json_encode(['result' => 'error', 'message' => 'Cannot edit this order']);
        if (empty($this->available_suppliers[$order->getSupplierId()]))
            return $this->response = json_encode(['result' => 'error', 'message' => 'Order not found']);

        switch ($_POST['docMethod']) {
            case 'REMOVE':
                OrderDocs::removeDocFromOrder($order);
                View::addFlash('orderDoc_saved', 'success', 'Doc removed');
                return $this->response = json_encode(['result' => 'success']);
            case 'FILE':
                if (empty($_FILES['doc_file']))
                    return $this->response = json_encode(['result' => 'error', 'message' => 'File not selected']);
                $res = OrderDocs::updateFile($order, $_FILES['doc_file'], $this->user->getId());
                View::addFlash('orderDoc_saved', $res[0] ? 'success' : 'danger', $res[1]);
                return $this->response = json_encode(['result' => 'success']);
            case 'URL':
                $exist_doc = OrderDocs::getByOrderId($order->getId());
                if (!empty($exist_doc) && $exist_doc->getDocumentUrl() === $_POST['document_url']) {
                    return $this->response = json_encode(['result' => 'error', 'message' => 'Document url not changed']);
                }
                OrderDocs::updateDocumentUrl($order, $_POST['document_url'], $this->user->getId());
                View::addFlash('orderDoc_saved', 'success', 'Document url updated');
                return $this->response = json_encode(['result' => 'success']);
            case 'RESET':
                $exist_doc = OrderDocs::getByOrderId($order->getId());
                if ($order->canEdit() || $order->awaitingUploadDocuments()) {
                    if ($exist_doc->isFail() && empty($exist_doc->getName()) && !empty($exist_doc->getDocumentUrl())) {
                        $exist_doc->setStatus(OrderDoc::STATUS_NEW);
                        $exist_doc->save(['id', 'status']);
                        OrderHistories::resetUploadDoc($order->getId(), $exist_doc->getDocumentUrl(), $this->user->getId());
                        View::addFlash('orderDoc_saved', 'success', 'The file can be uploaded again');
                        return $this->response = json_encode(['result' => 'success']);
                    } else
                        return $this->response = json_encode(['result' => 'error', 'message' => 'File can`t be uploaded again']);
                } else
                    return $this->response = json_encode(['result' => 'error', 'message' => 'Not accessed to order']);
        }
        return $this->response = json_encode(['result' => 'error', 'message' => 'error']);
    }

    /* Edit Order shipping service. Accessible via ViewOrder for cancelled orders */
    public function saveShippingService()
    {
        if (empty($_POST))
            return $this->response = json_encode(['error' => 'Data not found']);
        if (empty($_POST) || !isset($_POST['carrier_id']) || !isset($_POST['service_id']))
            return $this->response = json_encode(['error' => 'Shipping service must be selected']);
        if (!isset($_POST['DDP']) || empty(Shippings::TERMS[$_POST['DDP']]))
            return $this->response = json_encode(['error' => 'DDP error']);
        $order_id = $_POST['order_id'];
        $order = OrdersModel::getById($order_id);
        if ($order === null)
            return $this->response = json_encode(['error' => 'Order not found']);
        if (!$order->canEdit())
            return $this->response = json_encode(['error' => 'Cannot edit this order']);
        if (empty($this->available_suppliers[$order->getSupplierId()]))
            return $this->response = json_encode(['error' => 'Order not found']);
        if ($order->getShippingServicesId() == $_POST['service_id'] && $order->getDDP() == $_POST['DDP'])
            return $this->response = json_encode(['success']);

        if ($order->getShippingServicesId() != $_POST['service_id']) {
            $shipping_services = (Warehouses::isCollectionByCustomer($this->warehouse_id)
                    ? [0 => ['id' => Shipping::COLLECTION_BY_CUSTOMER_ID, 'carrier_id' => $order->getCarrierId()]]
                    : []
                ) + Shippings::getAllServicesByWarehouseId($this->warehouse_id);
            if (empty($shipping_services) || !isset($shipping_services[$_POST['service_id']]))
                return $this->response = json_encode(['error' => 'Shipping service doesn\'t exist']);
            OrderHistories::saveShippingService(
                $order_id,
                $this->user->getId(),
                Shippings::getServiceParamsById($order->getShippingServicesId())['name'],
                Shippings::getServiceParamsById($_POST['service_id'])['name']);
            $order->setCarrierId($shipping_services[$_POST['service_id']]['carrier_id']);
            $order->setShippingServicesId($_POST['service_id']);
            $order->save(['id', 'carrier_id', 'shipping_services_id']);
        }
        if ($order->getDDP() != $_POST['DDP']) {
            OrderHistories::saveDDP(
                $order->getId(),
                $this->user->getId(),
                Shippings::TERMS[$order->getDDP()] ?? 'Default',
                Shippings::TERMS[$_POST['DDP']] ?? 'Default'
            );
            $order->setDDP($_POST['DDP']);
            $order->save(['id', 'DDP']);
        }
        return $this->response = json_encode(['success']);
    }

    /* Edit Order SKUs. Accessible via ViewOrder for cancelled orders */
    public function saveSkus()
    {
        if (empty($_POST) || empty($_POST['quantity']))
            return $this->response = json_encode(['error' => 'No SKUs info provided']);
        $order_id = $_POST['order_id'];
        $order = OrdersModel::getById($order_id);
        if ($order === null)
            return $this->response = json_encode(['error' => 'Order not found']);
        if (!$order->canEdit())
            return $this->response = json_encode(['error' => 'Cannot edit this order']);
        if (empty($this->available_suppliers[$order->getSupplierId()]))
            return $this->response = json_encode(['error' => 'Access denied']);
        $SKUs_qty = $_POST['quantity'];
        $SKUs_price = $_POST['price'];
        $SKUs = [];
        foreach ($SKUs_qty as $SKU_id => $SKU_qty) {
            $SKU_qty = (int)$SKU_qty;
            if ($SKU_qty < 1 || !isset($SKUs_price[$SKU_id]) || trim($SKUs_price[$SKU_id]) === '' || (float)$SKUs_price[$SKU_id] < 0) {
                return $this->response = json_encode(['error' => 'Error SKUs data']);
            }
            $SKU_price = (float)$SKUs_price[$SKU_id];
            $SKUs[$SKU_id] = [
                'qty' => $SKU_qty,
                'price' => $SKU_price
            ];
        }
        $og_order_skus = OrderSKUs::getSKUsByOrder($order->getId());
        if ($order->query() && array_diff_key($SKUs, $og_order_skus))
            return $this->response = json_encode(['error' => 'It is prohibited to add new products']);
        if ($order->query())
            foreach ($SKUs as $SKU_id => $SKU) {
                if ($SKU['qty'] > $og_order_skus[$SKU_id]['quantity'])
                    return $this->response = json_encode(['error' => 'It is prohibited to add new products']);
            }
        $og_order_skus_string = '';
        foreach ($og_order_skus as $og_order_sku)
            $og_order_skus_string .= "#{$og_order_sku['SKU_id']} ({$og_order_sku['quantity']} items, price: {$og_order_sku['price']}) ";
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        list($add, $lock_ids, ) = OrderSKUs::replaceSKUs($order_id, $SKUs);
        if (!$add)
            return $this->response = json_encode(['error' => 'All SKUs is locked']);
        $SKU_lock_ids = Arr::map($lock_ids,'id', 'barcode');
        $result_order_skus_string = '';
        foreach ($SKUs as $SKU_id => $SKU_data) {
            if (in_array($SKU_id, array_keys($SKU_lock_ids))) {
                OrderHistories::lockedSKUs($order->getId(), $SKU_id, $SKU_lock_ids[$SKU_id], $order->getUserId());
            } else {
                $result_order_skus_string .= "#$SKU_id (" . $SKU_data['qty'] . " items" . " , price: " . $SKU_data['price'] . ") ";
            }
        }
        if ($og_order_skus_string != $result_order_skus_string)
            OrderHistories::addCommentOrder($order->getId(), $order->getUserId(), "SKUs changed from:<br> {$og_order_skus_string} <br>to<br>{$result_order_skus_string}");
        $mysql->commit();
        return $this->response = json_encode(['success']);
    }

    public function shortShip()
    {
        if (empty($_POST) || empty($_POST['order_id']))
            return $this->response = json_encode(['error' => 'No order info provided']);
        $order = OrdersModel::getById((int) $_POST['order_id']);
        if (empty($order))
            return $this->response = json_encode(['error' => 'Order not found']);
        if (empty($this->available_suppliers[$order->getSupplierId()]))
            return $this->response = json_encode(['error' => 'Access denied']);
        if (!$order->backorder())
            return $this->response = json_encode(['error' => 'Order status not "backorder"']);
        $SKUs = SKULocations::getByOrder($order->getId());
        if (empty($SKUs))
            return $this->response = json_encode(['error' => 'SKUs not found']);
        $success = OrdersModel::shortShipOrder($order, $SKUs, $this->user);
        if ($success)
            $this->response = json_encode(['success']);
        else
            $this->response = json_encode(['error' => 'Short Ship is impossible']);
    }

    public function getOriginalRequest()
    {
        $id = Request::getPositiveId();
        $this->response = json_encode(['id' => $id, 'success']);
        if (!empty($id)) {
            $origin = OrdersOriginalRequest::getOriginalRequest($id);
            if (!is_null($origin)) {
                $this->response = json_encode([
                    'request' => json_decode($origin['request'], true),
                    'response' => json_decode($origin['response'], true),
                ]);
                return;
            }
        }
        $this->response = json_encode(['error' => 'not found']);
    }

    /** @throws */
    public function priority()
    {
        if ($this->warehouse_virtual)
            throw new RedirectException('/forbidden');
        $id = Request::getPositiveId($_POST);
        $back = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        if (!$id) {
            View::addFlash('Orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        $order = OrdersModel::getById($id);
        if ($order === null) {
            View::addFlash('Orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        OrdersModel::priority($order, $this->user);
        View::addFlash('Orders', 'success', 'Order prioritized successfully');
        throw new RedirectException($back);
    }

    /** @throws */
    public function approve()
    {
        $id = Request::getPositiveId($_POST);
        $back = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        if (!$id) {
            View::addFlash('Orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        $order = OrdersModel::getById($id);
        if ($order === null) {
            View::addFlash('Orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        OrdersModel::approve($order, $this->user);
        View::addFlash('Orders', 'success', 'Order approved');
        throw new RedirectException($back);
    }

    /** @throws */
    public function cancel()
    {
        $id = Request::getPositiveId($_POST);
        $back = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        if (!$id) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        $order = OrdersModel::getById($id);
        if ($order === null) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        $res = OrdersModel::cancelOrder($order, $this->user->getId());
        if (!$res[0]) {
            View::addFlash('orders', 'danger', "Order '{$order->getRef()}' cannot be cancelled. {$res[1]}!");
            throw new RedirectException($back);
        }
        View::addFlash('orders', 'success', 'Order cancelled' . (!empty($res[1]) ? ". {$res[1]}" : ''));
        throw new RedirectException($back);
    }

    /** @throws */
    public function resume()
    {
        $id = Request::getPositiveId($_POST);
        $back = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        if (!$id) {
            View::addFlash('orders', 'danger', 'Order is not found');
            throw new RedirectException($back);
        }
        $order = OrdersModel::getById($id);
        if (empty($order)) {
            View::addFlash('orders', 'danger', 'Order is not found');
            throw new RedirectException($back);
        }
        if (!$order->expired()) {
            View::addFlash('orders', 'danger', 'Order cannot be resumed');
            throw new RedirectException($back);
        }
        $res = OrdersModel::cancelOrder($order, $this->user->getId());
        if (!$res[0]) {
            View::addFlash('orders', 'danger', "Order '{$order->getRef()}' cannot be cancelled. {$res[1]}!");
            throw new RedirectException($back);
        }
        View::addFlash('orders', 'success', 'Order fixed' . (!empty($res[1]) ? ". {$res[1]}" : ''));
        OrdersModel::recreate($order, $this->user);
        throw new RedirectException($back);
    }

    /** @throws */
    public function forceCancel()
    {
        if (!$this->user->isRoleSA() || empty($_POST)) {
            View::addFlash('cancel', 'danger', 'Access denied');
            throw new RedirectException("/");
        }
        $order = OrdersModel::getById($_POST['id']);
        if (empty($order)) {
            View::addFlash('cancel', 'danger', 'Order not found');
            throw new RedirectException("/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : ''));
        }
        $res = OrdersModel::cancelOrder($order, $this->user->getId(), false);
        if (!$res[0])
            View::addFlash('cancel', 'danger', "Order #{$_POST['id']} cannot be cancelled. {$res[1]}");
        else
            View::addFlash('cancel', 'success', "Order #{$_POST['id']} cancelled" . (!empty($res[1]) ? ". {$res[1]}" : ''));
        throw new RedirectException("/orders/view_order?id={$_POST['id']}");
    }

    public function bulkCancel()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = ['error' => 'Access denied'];
        $numbers = OrdersModel::parseIds($_POST['order_ids']);
        if (empty($numbers))
            return $this->response = ['error' => 'Orders are not found'];
        $not_cancelled = [];
        $cancelled = [];
        $orders = [];
        foreach ($numbers as $order_id) {
            $orders[$order_id] = OrdersModel::getById($order_id);
            if (empty($orders[$order_id]) || !OrdersModel::isAccess($orders[$order_id], $this->user, array_keys($this->available_suppliers)))
                $not_cancelled[] = $order_id;
            else
                $cancelled[] = $order_id;
        }
        if (!empty($not_cancelled))
            return $this->response = ['error' => 'Orders ' . implode(', ', $not_cancelled) . " aren't found or access denied", 'edit' => implode(', ', $cancelled)];
        $user_job_id = Queue::addJobToOrderBulkCancel([
            'user_id' => $this->user->getId(),
            'warehouse_id' => $this->warehouse_id,
            'order_ids' => $cancelled,
        ]);
        if (empty($user_job_id))
            return $this->response = ['error' => 'Queue is not created'];
        $_SESSION['user_queue'] = true;
        $_SESSION['user_queue_flash'] = true;
        View::addFlash(
            'orders',
            'warning',
            'Orders bulk cancel will be processed in the queue. Job #' . $user_job_id);
        return $this->response = ['success'];
    }

    /** @throws */
    public function recreate()
    {
        $id = Request::getPositiveId($_POST);
        $back = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        if (!$id) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        $order = OrdersModel::getById($id);
        if ($order === null) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        OrdersModel::recreate($order, $this->user);
        View::addFlash('orders', 'success', 'Order recreated');
        throw new RedirectException($back);
    }

    /** @throws */
    public function continueOrder()
    {
        $id = Request::getPositiveId($_POST);
        $back = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        if (!$id) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        $order = OrdersModel::getById($id);
        if ($order === null) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        $res = OrdersModel::cancelOrder($order, $this->user->getId());
        if (!$res[0]) {
            View::addFlash('orders', 'danger', "Order '{$order->getRef()}' cannot be cancelled. {$res[1]}!");
            throw new RedirectException($back);
        }
        View::addFlash('orders', 'success', 'Order fixed' . (!empty($res[1]) ? ". {$res[1]}" : ''));
        OrdersModel::recreate($order, $this->user);
        throw new RedirectException($back);
    }

    public function queryOrder()
    {
        $id = Request::getPositiveId($_POST);
        $back = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        if (!$this->user->isRoleSA() && !$this->user->isRoleManager()) {
            View::addFlash('orders', 'danger', 'Permission deny');
            throw new RedirectException($back);
        }
        if (!$id) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        $order = OrdersModel::getById($id);
        if ($order === null) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        if (!Order::canQuery($order->getStatus())) {
            View::addFlash('orders', 'danger', "Order '{$order->getRef()}' cannot be query.");
            throw new RedirectException($back);
        }
        View::addFlash('orders', 'success', 'Order query');
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        OrdersConfig::saveConfigs([OrdersConfig::orderStatusBeforeQuery($order->getId(), $order->getStatus())]);
        OrdersModel::changeStatus($order->getId(), Order::QUERY, $this->user->getId());
        $mysql->commit();
        throw new RedirectException($back);
    }

    public function revertQueryOrder()
    {
        $id = Request::getPositiveId($_POST);
        $back = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        if (!$this->user->isRoleSA() && !$this->user->isRoleManager()) {
            View::addFlash('orders', 'danger', 'Permission deny');
            throw new RedirectException($back);
        }
        if (!$id) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        $order = OrdersModel::getById($id);
        if ($order === null) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back);
        }
        if (!Order::isQuery($order->getStatus())) {
            View::addFlash('orders', 'danger', "Order '{$order->getRef()}' cannot be query.");
            throw new RedirectException($back);
        }
        if ($error = OrdersModel::reinstateOrder($order, $this->user->getId())) {
            View::addFlash('orders', 'danger', $error);
            throw new RedirectException($back);
        }
        View::addFlash('orders', 'success', 'Order query');
        throw new RedirectException($back);
    }

    /** @throws */
    public function detailOrder()
    {
        $back = empty($_GET['back']) ? 'oldest_orders' : addslashes($_GET['back']);
        $this->viewOrder("/additional/$back/?num_orders={$_GET['num_orders']}", $_GET['id']);
    }

    /** @throws */
    public function retryVirtual()
    {
        if (empty($_GET) || empty($_GET['order_id'])) {
            View::addFlash('orders', 'danger', 'Invalid information');
            throw new RedirectException("/");
        }
        $order = OrdersModel::getById($_GET['order_id']);
//        if ($order === null || !OrdersModel::isErrorVirtual($order)) {
        if ($order === null) {
            View::addFlash('orders', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        if ($order->errorVirtual())
            OrdersModel::retryVirtual($order, $this->user->getId());
        $this->viewOrder('/orders/?' . ($_SESSION[OrdersModel::FILTER_PARAM] ?? ''), $_GET['order_id']);
    }

    /** @throws */
    public function viewOrder($back = null, $id = null)
    {
        $this->disableWarehouseChange();
        if (empty($back)) {
            $back_url = "/orders?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
            $id = Request::getPositiveId();
        } else
            $back_url = $back;
        $order = OrdersModel::getById($id);
        if (empty($order)) {
            View::addFlash('Orders', 'danger', 'Order not found');
            throw new RedirectException($back_url);
        }
        if (!OrdersModel::isAccess($order, $this->user, array_keys($this->available_suppliers))) {
            View::addFlash('Orders', 'danger', 'Access denied');
            throw new RedirectException($back_url);
        }
        $carriers = WarehouseCarriers::getAvailableCarrierList($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('Orders', 'danger', 'Carriers are not found');
            throw new RedirectException($back_url);
        }
        $carrier_id = (empty($order->getCarrierId()) || !in_array($order->getCarrierId(), Arr::column($carriers, 'id'))) ? $carriers[0]['id'] : $order->getCarrierId();
        $orderDetails = $this->getViewOrderData($order);
        if (empty($orderDetails)) {
            View::addFlash('Orders', 'danger', 'Server error');
            throw new RedirectException($back_url);
        }
        $SKU_locations = $orderDetails['SKU_locations'] ?? [];
        $countries = Countries::getAll();
        unset($countries['GB']);
        $can_create = $this->hasAccessToRoute('orders/add');
        $data = [
            'order' => $order,
            'available_skus' => View::toJs($orderDetails['skus_to_search'] ?? []),
            'skus_info' => View::toJs($orderDetails['skus_info'] ?? []),
            'order_skus_info' => View::toJs(Arr::maps($orderDetails['SKUs'] ?? [], 'barcode', ['name', 'quantity', 'price'])),
            'locations_users' => Arr::map($orderDetails['users'] ?? [], 'cart_id', 'login'),
            'users' => $orderDetails['users'] ?? [],
            'SKU_locations' => $SKU_locations,
            'SKUs' => $orderDetails['SKUs'] ?? [],
            'packages' => $orderDetails['packages'] ?? [],
            'carriers' => Arr::map($carriers, 'id', 'title'),
            'carrier' => Carriers::getById($carrier_id),
            'currencies' => Currencies::getCodes(),
            'delivery_address' => $orderDetails['delivery_address'] ?? '',
            'supplier' => $orderDetails['supplier'] ?? [],
            'countries' => $countries,
            'back' => $back_url,
            'shipping_data' => $orderDetails['shipping_data'] ?? [],
            'shipping_errors' => $orderDetails['shipping_errors'] ?? [],
            'risk_messages' => $orderDetails['risk_messages'] ?? [],
            'shipping_services' => Shippings::handleShippingServicesByWarehouse($this->warehouse_id),
            'shipping_service_name' => $orderDetails['shipping_service_name'] ?? '',
            'special' => $orderDetails['special'] ?? '',
            'printed_matter' => OrdersConfig::getOrderPrintedMatter($order->getId()),
            'eori_number' => OrdersConfig::getOrderEoriNumber($order->getId()),
            'vat_number' => OrdersConfig::getOrderVatNumber($order->getId()),
            'ioss_number' => OrdersConfig::getOrderIossNumber($order->getId()),
            'tax_id' => OrdersConfig::getOrderTaxId($order->getId()),
            'package_types' => $orderDetails['package_types'] ?? [],
            'returns' => $orderDetails['returns'] ?? [],
            'history' => $orderDetails['history'] ?? [],
            'delivery' => $orderDetails['delivery'] ?? null,
            'is_short_ship' => array_sum(array_map(function ($el) use ($SKU_locations) {
                    return empty($SKU_locations[$el['id']]) ? 0 : 1;
                }, $orderDetails['SKUs'] ?? [])) > 0,
            'printers' => $orderDetails['printer_list'] ?? [],
            'printer_def' => $orderDetails['printer_def'] ?? null,
            'doc' => $orderDetails['doc'] ?? null,
            'has_order_bundles' => $orderDetails['has_order_bundles'] ?? false,
            'has_order_substitutions' => $orderDetails['has_order_substitutions'] ?? false,
            'percent' => $orderDetails['percent'] ?? null,
            'packing_slip_type' => $orderDetails['packing_slip_type'] ?? null,
            'subwarehouse' => $this->warehouse_virtual ? Shippings::getVirtualWarehouses([$order->getId()]) : [],
            'short_ship' => $orderDetails['short_ship'],
            'serial_numbers' => $orderDetails['serial_numbers'],
            'can_create' => $can_create,
            'can_create_similar' => $can_create && $this->hasAccessToRoute('orders/similar')
        ];
        $this->render($data, 'orders/view_order');
    }

    public function charge()
    {
        /** @var Order $order */
        $order = OrdersModel::getById($_POST['order_id']);
        $order->setBillingDescriptionPreparation($_POST['desc_preparation']);
        $order->setBillingChargePreparation($_POST['preparation']);
        $order->setBillingDescriptionShipping($_POST['desc_shipping']);
        $order->setBillingChargeShipping($_POST['shipping']);
        $order->setBillingDescriptionAdditional($_POST['desc_additional']);
        $order->setBillingChargeAdditional($_POST['additional']);
        $order->save(['id', 'billing_description_preparation', 'billing_charge_preparation',
                            'billing_description_shipping', 'billing_charge_shipping',
                            'billing_description_additional', 'billing_charge_additional']);
    }

    /** @throws */
    public function addingOrder()
    {
        if ($_GET || !$_POST) {
            View::addFlash('addingOrder', 'danger', 'Data error');
            throw new RedirectException('/orders');
        }
        if (!empty($_POST['mode']) && $_POST['mode'] == 2)
            throw new RedirectException('/upload/SKUs_order/?back=/orders/add&data=' . base64_encode(json_encode($_POST)));
        if (!empty($_POST['mode']) && $_POST['mode'] == 3)
            throw new RedirectException('/orders/add/?data=' . base64_encode(json_encode($_POST)));
        $new_del = (bool)($_POST['new_del'] ?? true);
        if (!$new_del)
            $new_del = empty(Deliveries::getById($_POST['delivery']['id']));

        // start prepare SKUs
        $quantities = empty($_POST['quantity']) ? [] : $_POST['quantity'];
        $bundle_quantities = $_POST['bundle_quantity'] ?? $_POST['quantity_from_bundle'] ?? [];
        $bundle_prices = $_POST['bundle_price'] ?? $_POST['price_from_bundle'] ?? [];
        $prices = empty($_POST['price']) ? [] : $_POST['price'];

        $SKUs = [];
        $bundles_to_insert = [];
        foreach ($bundle_quantities ?? [] as $bundle_id => $bundle_quantity) {
            if ((int)$bundle_quantity <= 0 || !BundlesModel::isBundleFromSupplier($_POST['order']['supplier_id'] ?? 0, $bundle_id))
                continue;
            $bundle_SKUs = BundlesModel::getBundleSKUs($bundle_id);
            $bundle_sales_price = round((float)$bundle_prices[$bundle_id] ?? 0, 2);

            $price_bundle = array_sum(array_map(function ($el) {
                return $el['quantity'] * $el['market_value'];
            }, $bundle_SKUs));
            $koeff = 0;
            if ($price_bundle)
                $koeff = $bundle_sales_price / $price_bundle;
            foreach ($bundle_SKUs as $SKU_data)
                BundlesModel::accumulationBundleSKU((int)$SKU_data['id'], $bundle_id, $SKUs, $SKU_data['quantity'] * $bundle_quantity, $SKU_data['market_value'] * $koeff);
            $bundles_to_insert[$bundle_id] = [
                'bundle_id' => $bundle_id,
                'quantity' => $bundle_quantity,
                'price' => $bundle_sales_price
            ];
            $bundle_quantities['bundle_' . $bundle_id] = $bundle_quantity;
            unset($bundle_quantities[$bundle_id]);
        }

        foreach ($quantities as $SKU_id => $quantity) {
            if ($quantity <= 0)
                continue;
            if (empty($SKUs[$SKU_id]))
                $SKUs[$SKU_id] = [
                    'qty' => (int)$quantity,
                    'price' => 0
                ];
            else
                $SKUs[$SKU_id]['qty'] += (int)$quantity;
        }

        foreach ($prices as $SKU_id => $price)
            if (!empty($SKUs[$SKU_id])) {
                $price = round((float)$price, 2);
                $price = max($price, 0);
                $SKUs[$SKU_id]['price'] = $price;
            }

        if (!empty($bundles_to_insert))
            BundlesModel::walkSKUsWithBundles($SKUs);

        $quantities += $bundle_quantities;

        $delivery_data = $_POST['delivery'];
        $new_order = $_POST['order'];
        $new_order['warehouse_id'] = $this->warehouse_id;
        if ($new_del) {
            unset($delivery_data['id']);
            $delivery = new Delivery($delivery_data);
            $errors = DeliveriesModel::validate($delivery, $this->warehouse_virtual);
            if (!empty($errors)) {
                View::addFlash('delivery_saved', 'danger', Arr::column($errors, 'message'));
                $this->add($delivery, $quantities);
                return;
            }
            $new_order['delivery_id'] = DeliveriesModel::handleDeliveryReturningId($delivery, $this->user->getId());
        } else
            $new_order['delivery_id'] = $delivery_data['id'];

        $new_order['create_date'] = time();
        $new_order['type_create'] = Sources::SOURCE_WEB;
        $new_order['shop_id'] = 0;
        $new_order['status'] = Order::NEW_NUM;
        $warehouse = Warehouses::getById($this->warehouse_id);
        $new_order['currency'] = Currencies::getCurrency($warehouse->getCurrencyId());
        if ($new_order['user_id'] != $this->user->getId())
            $new_order['actual_user_login'] = $this->user->getLogin();
        $res = OrdersModel::createOrder($new_order, $_FILES['docs'], $SKUs);
        View::addFlash('order_saved', $res[0] ? 'success' : 'danger', $res[1]['order']);
        if (!$res[0]) {
            $this->add(DeliveriesModel::getById($new_order['delivery_id']), $quantities);
            return;
        }
        if ($res[2] instanceof Order) {
            if (!empty($bundles_to_insert))
                foreach ($bundles_to_insert as $bundle)
                    BundlesModel::addToOrder($res[2]->getId(), $bundle['bundle_id'], $bundle['quantity'], $res[2]->getUserId());
            OrdersModel::creationOrder($res[2], $res[3], $res[2]->getUserId());
            View::addFlash('order_saved', 'success', 'Order created successfully');
        }
        throw new RedirectException('/orders');
    }

    /** @throws */
    public function markDespatched()
    {
        if (empty($_POST)) {
            View::addFlash('Orders', 'danger', 'Invalid information');
            throw new RedirectException('/orders');
        }
        $order = OrdersModel::getById($_POST['id']);
        if (empty($order) || !OrdersModel::isAccess($order, $this->user, array_keys($this->available_suppliers))) {
            View::addFlash('orders', 'danger', 'Order not found or access denied');
            throw new RedirectException('/orders');
        }
        if ($this->warehouse_virtual) {
            if ((!$order->packing() && !$order->packed()) || empty(Shippings::getShippingDataByOrderId($order->getId()))) {
                View::addFlash('orders', 'danger', 'Invalid information');
                throw new RedirectException('/orders');
            }
            if (!$order->despatched()) {
                $order->setDespatchedDate(time());
                $order->save(['id', 'despatched_date']);
                OrdersModel::changeStatus($order->getId(), Order::DESPATCHED, $this->user->getId());
            }
        } else {
            if (!$order->printingDocumentsIssue() && !$order->printingDocuments()) {
                View::addFlash('orders', 'danger', 'Invalid information');
                throw new RedirectException('/orders');
            }
            $mode = OrderHistories::getMode($order->getId());
            if ($mode === false) {
                View::addFlash('orders', 'danger', 'Invalid information');
                throw new RedirectException('/orders');
            }
            PackModel::printingDocsConfirm($order, $this->user, $mode);
        }
        View::addFlash('orders', 'success', 'Order marked as despatched');
        throw new RedirectException("/orders/view_order?id={$_POST['id']}");
    }

    public function printRetainMe()
    {
        $source_id = empty($_GET['source_id']) ? PrintersModel::getDefault($this->warehouse_id) : PrintersModel::getById($_GET['source_id'])->getSourceId();
        $result = PackingSlips::draw(Request::getPositiveId(), $source_id);
        die($result['retain_me'] ? 'success' : 'fail');
    }

    /** @throws */
    public function similar()
    {
        $is_return = (int)($_POST['RASN_id'] ?? 0);
        if ($is_return && !$this->user->isRoleSupplierUser() && !$this->user->isRoleSA()) {
            View::addFlash('orders', 'danger', 'Order cannot be created');
            throw new RedirectException('/');
        }
        $id = Request::getPositiveId($_POST);
        if ($is_return) {
            $back_url = '/return_ASNs/view_rasn?id=' . $is_return;
        } else {
            $back_url = '/orders/?' . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        }
        $order = OrdersModel::getById($id);
        if (empty($order)) {
            View::addFlash('orders', 'danger', 'Order not found');
            throw new RedirectException($back_url);
        }
        if ($is_return && $order->getWarehouseId() != $this->warehouse_id)
            $order->setWarehouseId($this->warehouse_id);
        elseif (!$is_return && $order->getWarehouseId() != $this->warehouse_id) {
            View::addFlash('orders', 'danger', 'Order cannot be created');
            throw new RedirectException('/');
        }
        if ($order->despatched())
            $delivery = OrdersOriginalDespatched::getHandledDelivery($order);
        if (empty($delivery))
            $delivery = DeliveriesModel::getById($order->getDeliveryId());
        $id = $order->getSupplierId();
        $SKUs = SKUsModel::getLeftBySupplier($this->warehouse_id, $id);
        $all_bundles = Arr::maps(BundlesModel::getAll(null, ['supplier_id' => $order->getSupplierId()], true)['records'], 'id', ['name', 'sku_list', 'cost_value']);
        $skus_to_search = [];
        $skus_info = [];
        $bundles_info = [];
        foreach ($SKUs as $SKU) {
            $skus_to_search[] = [
                'value' => $SKU['barcode'],
                'label' => $SKU['barcode'] . " / " . $SKU['name'] . " / " . $SKU['customer_ref'],
                'active' => $SKU['state'],
                'type' => 'SKU',
            ];
            $skus_info[$SKU['barcode']] = $SKU;
        }
        foreach ($all_bundles as $bundle_id => $bundle) {
            $skus_to_search[] = [
                'value' => $bundle['name'],
                'label' => $bundle['name'],
                'active' => 1,
                'type' => 'bundle'
            ];
            $bundle_SKUs = array_filter(BundlesModel::getBundleSKUs($bundle_id), function($SKU) {
                return $SKU['state'] != 0;
            });
            $bundles_info[$bundle_id] = [
                'name' => $bundle['name'],
                'show_name' => "<b>{$bundle['name']}</b>",
                'bundle_id' => $bundle_id,
                'SKUs' => array_map(function($bundle_SKU) {
                    return ['id' => $bundle_SKU['id'], 'quantity' => $bundle_SKU['quantity']];
                }, Arr::maps($bundle_SKUs, 'barcode', ['id', 'quantity', 'state'])),
                'quantity' => 1,
                'price' => $bundle['cost_value'],
            ];
        }
        $order->setRef("{$order->getRef()}_" . ($is_return ? 'return' : 'copy'));
        $carriers = WarehouseCarriers::getAvailableCarrierList($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('Orders', 'danger', 'Carriers are not found');
            throw new RedirectException($back_url);
        }
        $carrier_id = $order->getCarrierId() ?: $carriers[0]['id'];
        try {
            list(
                'suppliers' => $suppliers,
                'users' => $users,
                'supplier_id' => $supplier_id,
                'user_id' => $user_id,
            ) = OrdersModel::getAvailableSuppliersAndUsersForOrdersCreation(
                $this->warehouse,
                $this->logistic_company,
                $this->user,
                $order->getSupplierId(),
                $order->getUserId(),
            );
        } catch (\Exception $e) {
            View::addFlash('Orders', 'danger', $e->getMessage());
            throw new RedirectException($back_url);
        }
        $data = [
            'available_skus' => View::toJs($skus_to_search),
            'skus_info' => View::toJs($skus_info),
            'bundles_info' => View::toJs($bundles_info),
            'quantities' => View::toJs(OrderSKUs::getSKUs4Similar($order)),
            'countries' => Countries::getAll(),
            'in_date' => empty($this->time_diff) ? time() : strtotime($this->handleTimezone(date(WithDate::DATE_SQL_FORMAT))),
            // leave only order's supplier
            'suppliers' => Arr::columnAssoc([$suppliers[$supplier_id]], 'name'),
            'supplier_id' => $supplier_id,
            'users' => Arr::columnAssoc($users, 'login'),
            'user_id' => $user_id,
            'delivery' => $delivery->toArray(),
            'order' => $order->toArray(),
            'back' => $back_url,
            'carrier_id' => (string)$carrier_id,
            'carriers' => Arr::map($carriers, 'id', 'title'),
            'shipping_service_id' => (string)$order->getShippingServicesId(),
            'shipping_services' => Shippings::handleShippingServicesByWarehouse($this->warehouse_id),
            'order_categories' => OrderCategories::getCategories(),
            'printed_matter' => OrdersConfig::getOrderPrintedMatter($order->getId()),
            'eori_number' => OrdersConfig::getOrderEoriNumber($order->getId()),
            'vat_number' => OrdersConfig::getOrderVatNumber($order->getId()),
            'ioss_number' => OrdersConfig::getOrderIossNumber($order->getId()),
            'tax_id' => OrdersConfig::getOrderTaxId($order->getId()),
        ];
        $this->render($data, 'orders/add_order');
    }

    public function excludeOrder()
    {
        if (empty($_POST)) {
            View::addFlash('Orders', 'danger', 'Invalid information');
            throw new RedirectException('/orders');
        }
        $order_id = $_POST['id'];
        $comment = $_POST['comment'];
        $status = (int)$_POST['status'];
        $back = "/orders/?" . (!empty($_SESSION[OrdersModel::FILTER_PARAM]) ? $_SESSION[OrdersModel::FILTER_PARAM] : '');
        $exclude_comment = OrdersConfig::excludeOrderComment($order_id, $comment);
        $exclude_status = OrdersConfig::excludeOrderStatus($order_id, $status);
        OrdersConfig::saveConfigs([$exclude_comment, $exclude_status]);
        OrderHistories::addCommentOrder($order_id, $this->user->getId(), "Order was " . ($status ? 'excluded from' : 'included to') . " billing with comment: $comment");
        View::addFlash('Orders', 'success', 'Order is successfully ' . ($status ? 'ex' : 'in') . 'cluded');
        throw new RedirectException($back);
    }

    /** @throws */
    public function getPackingSlip()
    {
        $source_id = empty($_GET['source_id']) ? PrintersModel::getDefault($this->warehouse_id) : PrintersModel::getById($_GET['source_id'])->getSourceId();
        $result = PackingSlips::draw(Request::getPositiveId(), $source_id);
        if (isset($result['retain_me'])) {
            if($result['retain_me'])
                View::addFlash('Orders', 'success', 'Retain.me slip requested successfully');
            else
                View::addFlash('Orders', 'danger', 'Retain.me slip request failed');
            throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    /** @throws */
    public function getDocFile()
    {
        $order_id = (int)$_GET['id'] ?? 0;
        $order = OrdersModel::getById($order_id);
        if (!empty($order)) {
            $doc = OrderDocs::getByOrderId($order->getId());
            if (!empty($doc) && $doc->existFile()) {
                $path = $doc->getFullPath();
                $filenameArr = explode('_', $doc->getName());
                array_shift($filenameArr);
                $filename = implode('_', $filenameArr);
                File::outputStoredDocument($path, $filename);
            }
            return;
        }
        throw new RedirectException($_SERVER['HTTP_REFERER']);
    }

    public function changeCurrency()
    {
        $order_id = Request::getPositiveId($_POST, 'id');
        $currency = $_POST['currency'];
        if (empty($order_id) || empty($currency) || !Currencies::isCurrency($currency)) {
            $this->response = ['error' => 'Invalid information'];
            return;
        }
        $res = OrdersOriginalRequest::getOriginalRequest($order_id);
        $response_info = json_decode($res['response'], true);
        $old_currency = $response_info['currency'];
        $response_info['currency'] = $currency;
        OrdersOriginalRequest::changeResponse($order_id, json_encode($response_info));
        $order = OrdersModel::getById($order_id);
        $order->setCurrency($currency);
        $order->save(['id', 'currency']);
        OrderHistories::addCommentOrder($order_id, $this->user->getId(),"Currency changed from: $old_currency to $currency");
        $this->response = ['success'];
        return;
    }

    public function getBundles()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!empty($order = OrdersModel::getById(Request::getPositiveId($_POST, 'order_id')))) {
            if ($order->isHashed() && ArchivingOrderService::orderIsArchived($order->getId())) {
                try {
                    $order_bundles = ArchivingOrderService::getBundleSKUs($order->getId());
                    return $this->response = ['result' => 'success', 'records' => $order_bundles];
                } catch (\Exception $e) {
                    return $this->response = ['result' => 'error'];
                }
            } else {
                $order_bundles = BundlesModel::getOrderBundleSKUs($order->getId());
                return $this->response = ['result' => 'success', 'records' => $order_bundles];
            }
        }
        return $this->response = ['result' => 'error'];
    }

    public function getSubstitutions()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!empty($order = OrdersModel::getById(Request::getPositiveId($_POST, 'order_id')))) {
            if ($order->isHashed() && ArchivingOrderService::orderIsArchived($order->getId())) {
                try {
                    $order_substitutions = ArchivingOrderService::getSubstitutionsSKUs($order->getId());
                    return $this->response = ['result' => 'success', 'records' => $order_substitutions];
                } catch (\Exception $e) {
                    return $this->response = ['result' => 'error'];
                }
            } else {
                $order_substitutions = OrdersSubstitutions::getOrderSubstitutionsSKUs($order->getId());
                return $this->response = ['result' => 'success', 'records' => $order_substitutions];
            }

        }
        return $this->response = ['result' => 'error'];
    }

    public function getFromReality()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $order_id = Request::getPositiveId($_POST, 'order_id');
        if ($order_id <= 0)
            return $this->response = ['error' => 'Order is not found'];
        $order = OrdersModel::getById($order_id);
        if (empty($order))
            return $this->response = ['error' => 'Order is not found'];
        if (!$this->warehouse_virtual)
            return $this->response = ['error' => 'Warehouse is not virtual'];
        try {
            $processor = Warehouses::getVirtualProcessor($this->warehouse_id, $order->getSupplierId());
            if (empty($processor))
                return $this->response = ['error' => 'Supplier is not found in warehouse'];
            $order_reality = $processor->getOrder($order->getId());
            if (empty($order_reality))
                return $this->response = ['error' => 'Order is not found in warehouse'];
        } catch (\Exception $e) {
            return $this->response = ['error' => $e->getMessage()];
        }
        return $this->response = ['success' => $order_reality];
    }

    public function getWebhookHistory()
    {
        $order_id = Request::getPositiveId($_POST, 'order_id');
        if ($order_id <= 0)
            return $this->response = ['error' => 'Order is not found'];
        $order = OrdersModel::getById($order_id);
        if (empty($order))
            return $this->response = ['error' => 'Order is not found'];
        if (!$this->warehouse_virtual)
            return $this->response = ['error' => 'Warehouse is not virtual'];
        $webhook_lst = Arr::maps(WebhookHistories::getExternalByOrderId($order_id), 'id', ['name', 'status', 'create_date', 'login']);
        if (empty($webhook_lst))
            return $this->response = ['error' => 'Webhooks are not found'];
        array_walk($webhook_lst, function(&$webhook) {
            $webhook['status'] = WebhookHistories::badgeStatus($webhook['status']);
        });
        return $this->response = ['success' => $webhook_lst];
    }

    public function collectionTime()
    {
        $shipping_services = ShippingServicesModel::getStatistics($this->warehouse, $_GET['date_despatch'] ?? date('Y-m-d'));
        $data = [
            'shipping_services' => $shipping_services,
            'total_count' => array_sum(array_column($shipping_services, 'total')),
            'back' => $_GET['back'] ?? '',
        ];
        $this->render($data, 'orders/collection_time');
    }

    public function processingQueue()
    {
        $raw_data = OrdersModel::getProcessingQueue($this->warehouse_id, $_GET);
        $order_ids = array_column($raw_data['records'], 'order_id');
        $priorities = Arr::map(OrderQueueModel::getByIds($order_ids), 'order_id', 'priority');
        foreach ($raw_data['records'] as $key => $order) {
            $address = new Address($order);
            $raw_data['records'][$key]['address'] = $address->toShortString();
            if (!empty($this->time_diff)) {
                $raw_data['records'][$key]['create_date'] = $this->handleTimezone($order['create_date']);
                if (!empty($order['despatched_date']))
                    $raw_data['records'][$key]['despatched_date'] = $this->handleTimezone($order['despatched_date']);
            }
            $raw_data['records'][$key]['priority_order'] = $priorities[$order['order_id']] ?? 0;
        }
        $current_filters = $_GET;
        if(isset($current_filters))
            unset($current_filters['c']);
        $data = [
            'total_count' => $raw_data['total'],
            'orders' => $raw_data['records'],
            'back' => $_GET['back'] ?? '',
            'current_filters' => http_build_query($current_filters),
            'total_pages' => ceil($raw_data['total'] / OrdersModel::LIMIT_PER_PAGE),
            'current_page' => $raw_data['current_page'],
            'sources' => Sources::getAll(),
            'suppliers' => SuppliersWarehouses::getPriorityOrders($this->warehouse_id),
            'statuses' => ['all'] + OrderQueue::STATUSES,
        ];
        $this->render($data, 'orders/processing_queue');
    }

    /** @throws */
    public function updateOrdersQueue()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST) || empty($_POST['order_id']))
            return $this->response = ['error' => '/forbidden'];
        (new OrderQueue($_POST))->save();
        return $this->response = ['success'];
    }

    /** @throws */
    public function updateSuppliersQueue()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST) || empty($_POST['priority']))
            return $this->response = ['error' => 'Access denied'];
        if (!SuppliersWarehouses::setPriorityOrders($_POST['priority']))
            return $this->response = ['error' => 'Update error'];
        return $this->response = ['success'];
    }
}
