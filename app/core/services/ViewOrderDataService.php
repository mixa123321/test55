<?php

namespace app\core\services;

use app\backend\models\Deliveries as DeliveriesModel;
use app\backend\models\OrderDocs;
use app\backend\models\OrderHistories;
use app\backend\models\Orders as OrdersModel;
use app\backend\models\OrderSKUs;
use app\backend\models\Packages as PackagesModel;
use app\backend\models\Printers as PrintersModel;
use app\backend\models\Shippings;
use app\backend\models\SKULocations;
use app\backend\models\SKUs as SKUsModel;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\Users;
use app\core\entities\Address;
use app\core\entities\Delivery;
use app\core\entities\Location;
use app\core\entities\Order;
use app\core\entities\Package;
use app\core\entities\SKU;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Debug;
use app\core\helpers\View;
use app\core\models\Batches;
use app\core\models\Bundles;
use app\core\models\OrderPackageReturns;
use app\core\models\OrdersOriginalDespatched;
use app\core\models\OrdersSubstitutions;
use app\core\models\Returns;
use app\core\models\Shops;
use app\core\models\SuppliersWarehouses;
use app\core\models\Warehouses;
use app\core\services\archiving\ArchivingOrderService;
use Exception;

class ViewOrderDataService
{

    /** @var Order|null  */
    private $order;
    /** @var Warehouse|null  */
    private $warehouse;

    private $is_archived = false;
    private $SKUs = [];

    private $archive_path = null;
    private $archive_data = [];


    /**
     * @throws Exception
     */
    public function __construct(Order $order, Warehouse $warehouse = null)
    {
        $this->order = $order;
        if (empty($warehouse))
            $warehouse = Warehouses::getById($order->getWarehouseId());
        if (empty($warehouse))
            throw new Exception('Warehouse not found');
        $this->warehouse = $warehouse;
        if ($order->isHashed() && ArchivingOrderService::orderIsArchived($order->getId())) {
            $this->is_archived = true;
            try {
                $this->archive_data = ArchivingOrderService::getViewData($order->getId());
            } catch (Exception $exception) {
                $this->archive_data = [];
                Debug::sendDebugInfo('ArchivingOrder', 'View order: ' . $exception->getMessage());
            }
        }
        $this->SKUs = $this->getSKUs();
    }

    public function __destruct()
    {
        $file = $this->archive_path . DIRECTORY_SEPARATOR . ArchivingOrderService::getViewDataFileName();
        if (is_file($file))
            unlink($file);
    }

    private function getSKUs(): array
    {
        $SKUs = OrderSKUs::getSKUs($this->order->getId());
        if ($this->is_archived)
            return $this->archive_data['SKUs'] ?? [];
        return $SKUs;
    }

    public function getFullData(): array
    {
        return array_merge($this->getMainData(), $this->getAdditionData());
    }

    private function getDelivery(): array
    {
        if ($this->order->despatched())
            $delivery = OrdersOriginalDespatched::getDelivery($this->order->getId());
        if (empty($delivery))
            $delivery = DeliveriesModel::getById($this->order->getDeliveryId());

        if (empty($delivery) && $this->is_archived)
            $delivery = new Delivery($this->archive_data['delivery'] ?? []);

        $delivery_address = new Address($delivery ? $delivery->toArray() : []);
        $delivery_address = $this->order->getIsHash() ? $delivery_address->toHash() : $delivery_address->toString();

        return [$delivery, $delivery_address];
    }

    private function combineHistory(): array
    {
        $history = OrderHistories::getByOrderId($this->order->getId());
        if ($this->is_archived) {
            $a_history = [];
            foreach ($this->archive_data['history'] ?? [] as $a_item)
                $a_history[$a_item['id']] = $a_item;
            foreach ($history as $item) {
                if (array_key_exists($item['id'], $a_history))
                    continue;
                $a_history[$item['id']] = $item;
            }
            krsort($a_history);
            $history = array_values($a_history);
        }
        $short_ship = !$this->is_archived ? OrderHistories::getOrderShortship($history, $this->warehouse) : ($this->archive_data['short_ship'] ?? []);
        $risk_messages = '';
        if ($this->is_archived) {
            $risk_messages = $this->archive_data['risk_messages'] ?? '';
        } else {
            if ($this->order->pendingApproval() && Shops::isRisk($this->order->getShopId()))
                $risk_messages = OrderHistories::getOrderRisk($history);
        }
        return [$history, $short_ship, $risk_messages];
    }

    public function getMainData(): array
    {
        if (!$this->is_archived) {
            $packages = OrdersModel::getPackages($this->order->getId());
            foreach ($packages as $package_id => $package) {
                $shipping_obj = Shippings::getById($package['shipment_id']);
                $packages[$package_id]['shipping'] = $shipping_obj ? $shipping_obj->toArray() : [];
            }
            $shipping_data = [];
            foreach (Shippings::getShippingDataByOrderId($this->order->getId()) as $shipping_res)
                $shipping_data[$shipping_res['package_name']][] = $shipping_res;
            $return_ids = OrderPackageReturns::getReturns(array_keys($packages), true);
            $returns = [];
            foreach ($return_ids as $return_id)
                $returns[$return_id['order_package_id']][] = $return_id['return_id'];
            foreach ($packages as $package_id => &$package) {
                if (!empty($returns[$package_id]))
                    foreach ($returns[$package_id] as $return_id) {
                        $package['return_id'] = $return_id;
                        $package['returns'] = Returns::getById($return_id)->toArray();
                        break;
                    }
            }
            $has_return = OrdersModel::hasReturnASNs($this->order->getId());
            $returns = $has_return ? OrdersModel::getReturns($this->order->getId()) : [];
            $shipping_errors = Shippings::getShippingErrors($this->order->getId());
            $has_order_bundles = Bundles::hasOrderBundles($this->order->getId());
            $has_order_substitutions = OrdersSubstitutions::hasSubstitutions($this->order->getId());
        }
        list($delivery, $delivery_address) = $this->getDelivery();
        list ($history, $short_ship, $risk_messages) = $this->combineHistory();
        return [
            'delivery' => $delivery,
            'delivery_address' => $delivery_address,
            'SKUs' => $this->SKUs,
            'packages' => $packages ?? ($this->archive_data['packages'] ?? []),
            'shipping_data' => $shipping_data ?? ($this->archive_data['shipping_data'] ?? []),
            'returns' => $returns ?? ($this->archive_data['returns'] ?? []),
            'history' => $history,
            'doc' => OrderDocs::getFirst($this->order->getId()),
            'shipping_errors' => $shipping_errors ?? ($this->archive_data['shipping_errors'] ?? []),
            'risk_messages' => $risk_messages,
            'has_order_bundles' => $has_order_bundles ?? ($this->archive_data['has_order_bundles'] ?? []),
            'has_order_substitutions' => $has_order_substitutions ?? ($this->archive_data['has_order_substitutions'] ?? []),
            'short_ship' => $short_ship
        ];
    }

    public function getAdditionData(): array
    {
        $SKU_locations = [];
        $skus_to_search = [];
        $skus_info = [];
        $supplier = SuppliersModel::getById($this->order->getSupplierId())->toArray();
        if (!empty($this->SKUs)) {
            $SKU_locations = SKULocations::getByParams(Arr::map($this->SKUs, 'id', 'id'), $this->order->getId());
            //get batch info for despatched orders
            if ($this->order->despatched() && !$this->warehouse->isVirtual()) {
                $despatched_items = OrdersOriginalDespatched::getItems($this->order->getId());
                if (!empty($despatched_items))
                    foreach ($this->SKUs as $sku)
                        foreach ($despatched_items as $despatched_sku)
                            if ($sku['id'] == $despatched_sku['SKU_id']) {
                                $batch = Batches::getById($despatched_sku['batch_id']);
                                if ($batch !== null)
                                    $SKU_locations[$sku['id']][] = ['batch_number' => 'Batch#: ' . View::toHtml($batch->getNumber()) . "<br/>Exp#: " . date('jS M Y', strtotime($batch->getExpired())) . " ({$despatched_sku['quantity']} units)"];
                            }
            }
        }

        $supplier_skus = SKUsModel::getLeftBySupplier($this->warehouse->getId(), $supplier['id'], [SKU::ACTIVE_NUM, SKU::UNEXPECTED_NUM, SKU::NOT_MAPPED_NUM]);
        foreach ($supplier_skus as $SKU) {
            $skus_to_search[] = [
                'value' => $SKU['barcode'],
                'label' => $SKU['barcode'] . " / " . $SKU['name'] . " / " . $SKU['customer_ref'],
                'active' => $SKU['state']
            ];
            $skus_info[$SKU['barcode']] = $SKU;
        }

        $percent = '';
        if ($this->order->getStage() == Order::STAGE_PICKING) {
            $percent = [0, 0, 0];
            foreach ($SKU_locations as $SKU_location_arr)
                foreach ($SKU_location_arr as $SKU_location)
                    if (Location::isStorage($SKU_location['type_id']))
                        $percent[0] += $SKU_location['quantity'];
                    elseif (Location::isCart($SKU_location['type_id']))
                        $percent[1] += $SKU_location['quantity'];
                    elseif (in_array($SKU_location['type_id'], [Location::TP, Location::LP, Location::OB]))
                        $percent[2] += $SKU_location['quantity'];
            $tmp = array_sum($percent);
            $percent = array_map(function ($el) use ($tmp) {
                return ($tmp ? round($el / $tmp * 100, 1) : 0) . '%';
            }, $percent);
            $percent = "Src: <b>{$percent[0]}</b>; Trl: <b>{$percent[1]}</b>; Dst: <b>{$percent[2]}</b>";
        }

        list($printer_def, $printer_list) = PrintersModel::getSelectPrinters($this->warehouse->getId());
        ['name' => $name, 'special' => $special] = Shippings::getServiceParamsById($this->order->getShippingServicesId());
        return [
            'SKU_locations' => $SKU_locations,
            'skus_to_search' => $skus_to_search,
            'skus_info' => $skus_info,
            'supplier' => $supplier,
            'package_types' => Arr::map(PackagesModel::getAllPackages($supplier['id'], $this->warehouse->getId(), Package::STATE_ACTIVE), 'id', 'name'),
            'users' => Arr::map(Users::getAll($this->warehouse), 'id', 'login'),
            'printer_def' => $printer_def,
            'printer_list' => $printer_list,
            'percent' => $percent,
            'shipping_service_name' => $name,
            'special' => $special,
            'packing_slip_type' => SuppliersWarehouses::getPackingSlipType($this->order->getWarehouseId(), $this->order->getSupplierId()),
        ];
    }

}