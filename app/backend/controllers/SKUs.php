<?php

namespace app\backend\controllers;

use app\backend\models\Sizes as SizesModel;
use app\backend\models\SKUHistories;
use app\backend\models\SKULocations;
use app\backend\models\SKUs as SKUsModel;
use app\backend\models\SKUWarehouses;
use app\core\models\config\SKUsConfig;
use app\backend\models\Suppliers;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\Bundles as BundlesModel;
use app\backend\models\SKUBarcodes;
use app\core\channels\magento\MagentoConnector;
use app\core\channels\shopify\ShopifyConnector;
use app\core\channels\shopify\ShopifyDataProcessor;
use app\core\channels\woocommerce\WooCommerceConnector;
use app\core\dbal\SafeMysql;
use app\core\entities\SKU;
use app\core\entities\File;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\File as FileHelper;
use app\core\helpers\Html;
use app\core\helpers\Request;
use app\core\helpers\status\OrderStatus;
use app\core\helpers\View;
use app\core\models\Shops;
use app\core\models\SKUChannelSuppliers;
use app\core\models\Warehouses;
use app\core\Queue;
use app\core\services\SKUsBulkService;
use app\core\statical\Countries;
use app\core\statical\Currencies;
use app\core\models\Channels as ChannelsModel;
use app\core\models\OrderSKUSerialNumbers;
use app\backend\models\Pagination;

class SKUs extends AuthController
{
    /** @throws RedirectException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        if ($this->user->isRoleWorker())
            $this->available_suppliers = SuppliersModel::getByFilters(
                $this->logistic_company->getId(),
                ['warehouse_id' => $this->warehouse_id],
            );
        parent::checkSupplierAccess();
    }

    public function index()
    {
        $_SESSION[SKUsModel::FILTER_PARAM] = http_build_query($_GET);
        $filters = $_GET;
        $supplier_read_only = !empty($_GET['back']);
        if ($supplier_read_only)
            $this->disableWarehouseChange();
        if ($this->user->isRoleSupplierUser()) {
            $id = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId());
            $id = $id['id'] ?? 0;
            $filters['supplier_id'] = $id;
            $back = '';
        } else {
            $id = Request::getPositiveId($_GET, 'supplier_id');
            $back = addslashes(!empty($_GET['back']) ? $_GET['back'] : '');
        }
        $available = 0;
        if (isset($_GET['available']))
            $available = in_array($_GET['available'], ['on', '1']) ? 1 : 0;
        $filters['available'] = $available;
        $allocated = 0;
        if (isset($_GET['allocated']))
            $allocated = in_array($_GET['allocated'], ['on', '1']) ? 1 : 0;
        $filters['allocated'] = $allocated;
        $backorder_replenish = 0;
        if (isset($_GET['backorder_replenish']))
            $backorder_replenish = in_array($_GET['backorder_replenish'], ['on', '1']) ? 1 : 0;
        $filters['backorder_replenish'] = $backorder_replenish;
        $suppliers = !$this->user->isRoleManager()
            ? SuppliersModel::getSuppliers4SKUs($this->warehouse, $this->user)
            : SuppliersModel::getByFilters($this->logistic_company->getId(), ['warehouse_id' => $this->warehouse_id]);
        $supplier_ids = array_keys($suppliers);
        if (empty($filters['supplier_id']) ||
            (!empty($filters['supplier_id']) && !in_array($filters['supplier_id'], array_keys($suppliers)) && !$this->user->isRoleSupplierUser()))
            $filters['supplier_id'] = $supplier_ids;
        $filters_SKU_ids = SKUsModel::getSKUsIdsByFilters((int)$this->warehouse_id, $filters);
        $pagination = new Pagination($filters['c'] ?? 1);
        $pagination->setTotalCount(SKUsModel::getAvailableTotalByFilterV2((int)$this->warehouse_id, $filters, $filters_SKU_ids));
        $SKUs = SKUsModel::getAvailableByFilterV2($this->warehouse_id, $filters, $filters_SKU_ids, $pagination->getOffset(), $pagination->getPerPage());
        SKULocations::addQtyToSKUsArray($SKUs, (int)$this->warehouse_id);
        unset($filters['c']);
        $batches = $clear_DH = $is_notmapped = $is_connection = [];
        if (!empty($SKUs)) {
            $sku_ids = Arr::column($SKUs, 'id', false);
            $batches = SKULocations::getBatches($this->warehouse_id, $sku_ids);
            $clear_DH = SKULocations::getCountSKUinDH($this->warehouse_id, $sku_ids);
            $is_notmapped = SKULocations::getNotMapped($sku_ids);
            $is_connection = Shops::getConnections($this->warehouse_id, $sku_ids);
            if ($this->warehouse_virtual)
                $SKU_warehouses = SKULocations::getVirtual($this->warehouse_id, $sku_ids);
        }
        $data = [
            'back' => $back,
            'supplier' => SuppliersModel::getById($id),
            'SKUs' => $SKUs,
            'total_count' => $pagination->getTotalCount(),
            'total_pages' => $pagination->getPageCount(),
            'current_page' => $pagination->getCurrentPage(),
            'current_filters' => http_build_query($_GET),
            'currencies' => Currencies::getCodes(),
            'suppliers' => $suppliers,
            'statuses' => ['-1' => 'Any'] + SKU::getStateNames(),
            'available' => $available,
            'allocated' => $allocated,
            'backorder_replenish' => $backorder_replenish,
            'batches' => $batches ?? null,
            'clear_DH' => $clear_DH ?? null,
            'is_notmapped' => $is_notmapped ?? null,
            'is_connection' => $is_connection ?? null,
            'SKU_warehouses' => $SKU_warehouses ?? null,
            'supplier_read_only' => $supplier_read_only,
            'SKUs_with_serial_number' => OrderSKUSerialNumbers::getSKUsBySKUs($sku_ids ?? [], (int)$this->warehouse_id),
            'SKUs_with_movements' => SKUHistories::getSKUsWithEvent($sku_ids ?? [], SKUHistories::EVENT_MOVEMENT, $this->warehouse_id, '%' . SKUHistories::REASON_RECONCILIATION . '%'),
        ];
        $this->render($data, 'SKUs/skus');
    }

    /** @throws */
    public function printBarcodes()
    {
        $filters = $_GET;
        if ($this->user->isRoleSupplierUser())
            $filters['supplier_id'] = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())['id'];
        if ($this->user->isRoleSA())
            $suppliers = SuppliersModel::getSuppliers($this->logistic_company->getId(), 0, 0);
        else
            $suppliers = SuppliersModel::getSuppliers($this->logistic_company->getId(), $this->warehouse_id, $this->user->getId());
        $supplier_ids = array_keys($suppliers);
        if (empty($filters['supplier_id']))
            $filters['supplier_id'] = $supplier_ids;
        $chunk = !empty($_GET['chunk']) ? (int)$_GET['chunk'] : -1;
        $pagination = new Pagination($_GET['chunk'] ?? 1, LIMIT_BARCODES_PDF);
        $filters_SKU_ids = SKUsModel::getSKUsIdsByFilters((int)$this->warehouse_id, $filters);
        $pagination->setTotalCount(SKUsModel::getAvailableTotalByFilterV2((int)$this->warehouse_id, $filters, $filters_SKU_ids));
        $SKUs = SKUsModel::getAvailableByFilterV2($this->warehouse_id, $filters, $filters_SKU_ids, $pagination->getOffset(), $pagination->getPerPage());
        if (empty($SKUs)) {
            View::addFlash('SKUs', 'danger', 'SKU not found');
            throw new RedirectException($_GET['back']);
        }
        if ($pagination->getPageCount() > 1 && $chunk === -1) {
            // $filters (built from $_GET above) is merged into an array and passed through http_build_query()
            // below, which percent-encodes every value; the taint tracker loses precision across the array `+`
            // merge before reaching the sanitizer call.
            for ($chunk = 1; $chunk <= $pagination->getPageCount(); $chunk++)
                echo Html::openWindow( // nosemgrep: php-echoed-request-project-sanitizers
                    '/SKUs/print_barcodes/?' . http_build_query(['chunk' => $chunk] + $filters),
                    'wind' . $chunk,
                    "PDF ($chunk of {$pagination->getPageCount()}). Please disable your pop-up blocker and click the PRINT ({$pagination->getTotalCount()} BARCODES) button again");
            echo Html::closeWindow();
        } else
            SKUsModel::printBarcodes($SKUs, $chunk, $pagination->getPageCount());
    }

    /** @throws */
    public function export()
    {
        $count = !empty($_GET['count']) ? intval($_GET['count']) : 0;
        unset($_GET['count']);
        $limit = SKUsModel::LIMIT_FLASH_GENERATE_REPORT;
        $filters = $_GET;
        if ($this->user->isRoleSupplierUser())
            $filters['supplier_id'] = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())['id'];
        $filters['warehouse_id'] = $this->warehouse_id;
        $suppliers = SuppliersModel::getSuppliers4SKUs($this->warehouse, $this->user);
        $supplier_ids = array_keys($suppliers);
        if (empty($filters['supplier_id']))
            $filters['supplier_id'] = $supplier_ids;
        if ($count <= $limit)
            $count = SKUsModel::getTotalCountReportCSV($filters);
        if ($count <= $limit) {
            set_time_limit(0);
            header('Content-Encoding: UTF-8');
            header('Content-Type: application/excel; charset=UTF-8');
            header(sprintf('Content-Disposition: attachment; filename="%s"', SKUsModel::getReportName()));
            SKUsModel::getAllAsCSV($this->warehouse_id, $filters, $this->user);
        } else {
            unset($filters['back']);
            $user_job_id = Queue::addJobToGenerateReportCSV([
                'user_id' => $this->user->getId(),
                'filters' => $filters,
                'addition' => $this->warehouse_id,
                'count' => $count
            ], self::class);
            if ($user_job_id) {
                $_SESSION['user_queue'] = true;
                $_SESSION['user_queue_flash'] = true;
                View::addFlash('upload_file', 'warning', 'Quantity of SKUs more than ' . $limit .
                    '. The report will be processed in the queue. Job #' . $user_job_id);
            } else
                View::addFlash('upload_file', 'danger', "Server error");
            throw new RedirectException("/SKUs/?" . (!empty($_SESSION[SKUsModel::FILTER_PARAM]) ? $_SESSION[SKUsModel::FILTER_PARAM] : ''));
        }
    }

//    public function exportCustom()
//    {
//        unset($_GET['count']);
//        $filters = $_GET;
//        if ($this->user->isRoleSupplierUser())
//            $filters['supplier_id'] = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId())['id'];
//        if ($this->user->isRoleSA())
//            $suppliers = SuppliersModel::getSuppliers(0, 0);
//        else
//            $suppliers = SuppliersModel::getSuppliers($this->warehouse_id, $this->user->getId());
//        $supplier_ids = array_keys($suppliers);
//        if (empty($filters['supplier_id']))
//            $filters['supplier_id'] = $supplier_ids;
//        SKUsModel::setHeadline([
//            'id', 'name', 'barcode', 'gs1', 'cost_value', 'cost_currency', 'market_value', 'market_currency',
//            'width', 'height', 'depth', 'weight', 'hs_code', 'season', 'customer_ref', 'customer_ref2', 'unique_customer_ref',
//            'state', 'available', 'allocated', 'backorder_replenish', 'photo_src', 'batches',
//        ]);
//        set_time_limit(0);
//        header('Content-Encoding: UTF-8');
//        header('Content-Type: application/excel; charset=UTF-8');
//        header(sprintf('Content-Disposition: attachment; filename="%s"', SKUsModel::getReportName()));
//        SKUsModel::getAllAsCSV($this->warehouse_id, $filters, $this->user);
//    }

    /** @throws */
    public function lockSku()
    {
        $id = Request::getPositiveId();
        $lock = isset($_GET['lock']) ? $_GET['lock'] : 0;
        $back = '/SKUs/?' . (!empty($_SESSION[SKUsModel::FILTER_PARAM]) ? $_SESSION[SKUsModel::FILTER_PARAM] : '');
        if (!$id) {
            View::addFlash('SKUs', 'danger', 'SKU not found');
        } else {
            if ($this->user->hasManagerAccess())
                $SKU = SKUsModel::getById($id);
            else
                $SKU = SKUsModel::getByIdAndUser($id, $this->user->getId());
            if ($SKU === null) {
                View::addFlash('SKUs', 'danger', 'SKU not found');
                throw new RedirectException($back);
            }
            $lock = (int)!$lock;
            $SKU->setState($lock);
            $SKU->save(['id', 'state']);
            SKUHistories::setState($SKU->getId(), $this->user->getId(), $lock, $this->warehouse_id);
        }
        throw new RedirectException($back);
    }

    /** @throws */
    public function unmappingSku()
    {
        $id = Request::getPositiveId();
        $is_notmapped = SKULocations::getNotMapped([$id]);
        $back = '/SKUs/?'.(!empty($_SESSION[SKUsModel::FILTER_PARAM]) ? $_SESSION[SKUsModel::FILTER_PARAM] : '');
        if (!$id) {
            View::addFlash('SKUs', 'danger', 'SKU not found');
            throw new RedirectException($back);
        }
        if (!empty($is_notmapped[$id])) {
            View::addFlash('SKUs', 'danger', 'SKU is already exists in stock');
            throw new RedirectException($back);
        }
        if ($this->user->hasManagerAccess()) {
            $SKU = SKUsModel::getById($id);
        } else {
            $SKU = SKUsModel::getByIdAndUser($id, $this->user->getId());
        }
        if (empty($SKU)) {
            View::addFlash('SKUs', 'danger', 'SKU not found');
            throw new RedirectException($back);
        }

        if (!empty($bundles = BundlesModel::getBySKU($SKU->getId()))) {
            $bundle_msg = 'SKU removed from bundle' . (count($bundles) > 1 ? 's' : '') . ': ';
            foreach ($bundles as $i => $bundle) {
                $count_left_SKUs = BundlesModel::deleteSKU($bundle['id'], $SKU->getId(), $this->user->getId());
                $bundle_msg .=  ($i > 0 ? ', ' : '') . $bundle['name'] . (empty($count_left_SKUs) ? (' (no SKUs left)') : '');
            }
            View::addFlash('bundle_SKUs', 'warning', $bundle_msg);
        }

        $SKU->setState(SKU::NOT_MAPPED_NUM);
        $SKU->save(['id', 'state']);
        SKUHistories::setState($SKU->getId(), $this->user->getId(), SKU::NOT_MAPPED_NUM, $this->warehouse_id);
        throw new RedirectException($back);
    }

    /** @throws */
    public function mappingSku()
    {
        if (empty($_GET) || empty($_GET['id']) || empty($_GET['supplier'])) {
            throw new RedirectException('/');
        }
        $id = Request::getPositiveId();
        $merge_barcode = addslashes($_GET['lock']);
        $back = '/SKUs/?'.(!empty($_SESSION[SKUsModel::FILTER_PARAM]) ? $_SESSION[SKUsModel::FILTER_PARAM] : '');

        $SKU = SKUsModel::getById($id);
        if (empty($SKU)) {
            View::addFlash('SKUs', 'danger', 'SKU is not found');
            throw new RedirectException($back);
        }
        if (empty($merge_barcode)) {
            $SKU->setState(SKU::ACTIVE_NUM);
            $SKU->save(['id', 'state']);
            SKUHistories::setState($SKU->getId(), $this->user->getId(), SKU::ACTIVE_NUM, $this->warehouse_id);
            View::addFlash('SKUs', 'success', 'SKU is mapped');
            throw new RedirectException($back);
        }
        $supplier_id = $SKU->getSupplierId();
        $is_notmapped = SKULocations::getNotMapped([$id]);
        if (!empty($is_notmapped[$id])) {
            View::addFlash('SKUs', 'danger', 'SKU is already exists in stock');
            throw new RedirectException($back);
        }
        $existing_sku = SKUsModel::getByBarcodeAndSupplier($merge_barcode, $supplier_id);
        if (empty($existing_sku) || $existing_sku->getId() == $id) {
            View::addFlash('SKUs', 'danger', 'SKU for merge is wrong');
            throw new RedirectException($back);
        }
        try {
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            $result = SKUsModel::merge($existing_sku->getId(), $id, $this->user->getId(), $this->warehouse);
            $mysql->commit();
        } catch (\Exception $e) {
            $mysql->rollback();
            View::addFlash('SKUs', 'danger', $e->getMessage());
            throw new RedirectException($back);
        }
        if (!empty($result['error'])) {
            View::addFlash('SKUs', 'danger', $result['error']);
        } else {
            View::addFlash('SKUs', 'success', "SKU merged with " . SKUBarcodes::getBarcodesBySKUIdString($existing_sku->getId()));
        }
        throw new RedirectException($back);
    }

    public function quickMerge()
    {
        if (!Request::isAjax())
            throw new RedirectException('/');
        $barcode = $_POST['barcode'] ?? '';
        $selectedDiff = $_POST['diff'] ?? [];
        if (empty($barcode))
            return $this->jsonResponse(['error' => 'Enter barcode for merge']);
        $id = Request::getPositiveId($_POST, 'id');
        $supplier_id = Request::getPositiveId($_POST, 'supplier');
        $SKU = SKUsModel::getById($id);
        if (empty($SKU) || $SKU->getSupplierId() != $supplier_id)
            return $this->jsonResponse(['error' => 'SKU not found']);
        $existing_sku = SKUsModel::getByBarcodeAndSupplier($barcode, $supplier_id);
        if (empty($existing_sku) || $existing_sku->getId() == $id)
            return $this->jsonResponse(['error' => 'SKU for merge is wrong']);
        $in_stock = SKULocations::getQuantityInStockLocations([$id]);
        if (!empty($in_stock[$id]))
            return $this->jsonResponse(['error' => 'SKU is already exists in stock']);
        if (($diff = SKUsModel::getDifference($existing_sku, $SKU)) && empty($selectedDiff))
            return $this->jsonResponse(['error' => 'SKUs have different values, choose the correct one', 'diff' => $diff]);
        try {
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            $result = SKUsModel::merge($existing_sku->getId(), $id, $this->user->getId(), $this->warehouse, $selectedDiff);
            if (!empty($_POST['calculate_max_capacity']))
                $this->calculateMaxCapacity($existing_sku);
            $mysql->commit();
        } catch (\Exception $e) {
            $mysql->rollback();
            return $this->jsonResponse(['error' => $e->getMessage()]);
        }
        if (!empty($result['error']))
            return $this->jsonResponse(['error' => $result['error']]);
        $barcodes = SKUBarcodes::getBarcodesBySKUIdString($existing_sku->getId());
        if (!empty($_POST['flash']))
            View::addFlash('SKUs', 'success', 'SKU merged with ' . $barcodes);
        return $this->jsonResponse(['success' => 'SKU merged with ' . $barcodes]);
    }

    private function calculateMaxCapacity(SKU $SKU): void
    {
        $freshSKU = SKUsModel::getById($SKU->getId());
        $sizes = SizesModel::getAllSizesName($this->warehouse_id);
        $capacity = [];
        $volume = $freshSKU->getWidth() * $freshSKU->getHeight() * $freshSKU->getDepth();
        if ($volume)
            foreach ($sizes as $key => $size)
                $capacity[$key] = (int) ($size[1] / $volume);
        $freshSKU->setMaxCapacity($capacity);
        $freshSKU->save(['id', 'max_capacity']);
    }

    public function quickAddBarcode()
    {
        if (!Request::isAjax())
            throw new RedirectException('/');
        $barcode = $_POST['barcode'] ?? '';
        if (empty($barcode))
            return $this->jsonResponse(['error' => 'Enter barcode for adding']);
        $id = Request::getPositiveId($_POST, 'id');
        $supplier_id = Request::getPositiveId($_POST, 'supplier');
        $SKU = SKUsModel::getById($id);
        if (empty($SKU) || $SKU->getSupplierId() != $supplier_id)
            return $this->jsonResponse(['error' => 'SKU not found']);
        $existing_sku = SKUsModel::getByBarcodeAndSupplier($barcode, $supplier_id);
        if ($existing_sku)
            return $this->jsonResponse(['error' => 'This barcode already exists']);
        SKUBarcodes::add($SKU->getId(), [$barcode]);
        return $this->jsonResponse(['success' => 'This barcode was added']);
    }

    /** @throws */
    public function editSku()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $back = empty($_GET['back']) ? '/SKUs' : str_replace('^', '?', str_replace('^^', '&', $_GET['back']));
        if ($this->user->hasManagerAccess())
            $SKU = SKUsModel::getById($id);
        else
            $SKU = SKUsModel::getByIdAndUser($id, $this->user->getId());
        if (empty($SKU)) {
            View::addFlash('SKUs', 'danger', 'SKU not found');
            throw new RedirectException($back);
        }
        $supplier_id = intval($SKU->getSupplierId());
        $addition = empty($_GET['addition']) ? [] : json_decode(base64_decode($_GET['addition']), true);
        if (!empty($addition))
            $SKU = new SKU($addition);
        $min_stock_levels = empty($_GET['min_stock_levels']) ? [] : json_decode(base64_decode($_GET['min_stock_levels']), true);
        if (empty($min_stock_levels))
            $min_stock_levels = Arr::map(SKUWarehouses::getMinStockLevels($SKU->getId()), 'warehouse_id', 'value');
        $channel = ChannelsModel::getByName(ShopifyConnector::INTEGRATION_NAME);
        if (empty($channel)) {
            View::addFlash('SKUs', 'danger', 'Channel Shopify is not configured');
            throw new RedirectException($back);
        }
        /** @var Shops[] $shops */
        $shops = ChannelsModel::getUserChannels([
            'channel_id' => $channel['id'],
            'supplier_id' => $supplier_id,
        ]);
        if (empty($shops))
            $shop_id = 0;
        else {
            $ids = array_unique(array_map(function (Shops $item) {
                return $item->getShop()->getId();
            }, $shops));
            if (count($ids) == 1)
                $shop_id = $shops[0]->getShop()->getId();
            else {
                $primary_shop_id = $shops[0]->getChannelSupplier()->getPrimaryShopId();
                if (in_array($primary_shop_id, $ids))
                    $shop_id = $primary_shop_id;
                else
                    $shop_id = 0;
            }
        }
        $data = [
            'SKU' => $SKU,
            'supplier_id' => $supplier_id,
            'min_stock_level_data' => json_encode(SKUsModel::getMinStockLevels($this->user, $this->warehouse, $supplier_id, $min_stock_levels)),
            'supplier' => SuppliersModel::getById($SKU->getSupplierId()),
            'back' => (empty($_GET['back']) && !empty($_SESSION[SKUsModel::FILTER_PARAM])) ? "/SKUs/?{$_SESSION[SKUsModel::FILTER_PARAM]}" : $back,
            'currencies' => Currencies::getCodes(),
            'countries' => Countries::getAll(),
            'shop_id' => $shop_id,
            'inventory_ids' => $shop_id ? SKUChannelSuppliers::getShopifyVariants($SKU->getId(), $shop_id) : [],
            'bundles_names' => array_column(BundlesModel::getBySKU($SKU->getId()), 'name'),
            'barcodes' => empty($_GET['barcodes']) ? SKUBarcodes::getBySKUId($SKU->getId()) : json_decode(base64_decode($_GET['barcodes']), true),
            'cost_center' => SKUWarehouses::getCostCentre($SKU->getId(), $this->warehouse_id),
            // same as $SKU above: prefer the just-submitted value from a failed save over the stored one,
            // since ns_pid/s_pid aren't part of `$addition`'s SKU reconstruction
            'ns_pid' => $addition['ns_pid'] ?? SKUsConfig::getNsPid($SKU->getId()),
            's_pid' => $addition['s_pid'] ?? SKUsConfig::getSPid($SKU->getId()),
        ];
        $this->render($data, 'SKUs/add_sku');
    }

    /** @throws */
    public function editConnections()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $back = empty($_GET['back']) ? '/SKUs' : str_replace('^', '?', str_replace('^^', '&', $_GET['back']));
        if ($this->user->hasManagerAccess())
            $SKU = SKUsModel::getById($id);
        else
            $SKU = SKUsModel::getByIdAndUser($id, $this->user->getId());
        if ($SKU === null) {
            View::addFlash('SKUs', 'danger', 'SKU not found');
            throw new RedirectException($back);
        }
        $connected_channels = SKUsModel::getChannelsConnected($id);
        $supplier_channels = Shops::getBySupplierId($SKU->getSupplierId());
        $data = [
            'SKU' => $SKU,
            'back' => (empty($_GET['back']) && !empty($_SESSION[SKUsModel::FILTER_PARAM])) ? "/SKUs/?{$_SESSION[SKUsModel::FILTER_PARAM]}" : $back,
            'connected_channels' => $connected_channels,
            'supplier_channels' => $supplier_channels,
        ];
        $this->render($data, 'SKUs/connections');
    }

    public function getProduct()
    {
        if (empty($_POST) || empty($_POST['shop_id']) || empty($_POST['channel']) || empty($_POST['product_id']))
            return $this->response = json_encode(['error' => 'Data is not correct']);
        $shop_id = $_POST['shop_id'];
        $channel = Shops::getChannelName($shop_id);
        $processor_class = ChannelsModel::getProcessorName(['name' => $channel]);
        $processor = new $processor_class();
        try {
            $product_info = $processor->getProductInfo($shop_id, $_POST['product_id'], $_POST['variant_id']);
        } catch (\Exception $e) {
            return $this->response = json_encode(['error' => $e->getMessage()]);
        }
        switch ($_POST['channel']) {
            case ShopifyConnector::INTEGRATION_NAME:
                if (empty($_POST['variant_id']) && !empty($product_info['variants']))
                    return $this->response = json_encode(['error' => 'SKU is not found']);
                return $this->response = json_encode([
                    'inventory_item_id' => $product_info['inventory_item_id'],
                    'product_info' => 'SKU: <b>' . View::toHtml($product_info['sku']) . '</b><br/>Created: ' . View::toHtml($product_info['created_at']) . '<br/>Updated: ' . View::toHtml($product_info['updated_at'])
                ]);
            case WooCommerceConnector::INTEGRATION_NAME:
                return $this->response = json_encode([
                    'product_info' => 'SKU: <b>' . View::toHtml($product_info['sku']) . '</b><br/>Created: ' . View::toHtml($product_info['date_created_gmt']) . '<br/>Modified: ' . View::toHtml($product_info['date_modified_gmt'])
                ]);
            case MagentoConnector::INTEGRATION_NAME:
                return $this->response = json_encode([
                    'product_info' => 'SKU: <b>' . View::toHtml($product_info['sku']) . '</b><br/>Created: ' . View::toHtml($product_info['created_at']) . '<br/>Updated: ' . View::toHtml($product_info['updated_at'])
                ]);
        }
        return $this->response = json_encode(['error' => 'Integration is under construction']);
    }

    public function addProduct()
    {
        if (empty($_POST) || empty($_POST['channel']) || empty($_POST['SKU_id']) || empty($_POST['shop_id']) || empty($_POST['product_id']))
            return '';
        $external_id = SKUChannelSuppliers::getSqlExternalId($_POST['channel'], $_POST['product_id'], $_POST['variant_id'], $_POST['inventory_id']);
        $SKU_id = SKUChannelSuppliers::getByExternalShopId($external_id, $_POST['shop_id']);
        if (empty($SKU_id)) {
            SKUChannelSuppliers::addConnectionSKU($_POST['SKU_id'], $_POST['shop_id'], $external_id, $this->user->getId(), $this->warehouse_id);
            return $this->response = json_encode(['insert']);
        }
        if ($SKU_id == $_POST['SKU_id'])
            return $this->response = json_encode(['exist']);
        return $this->response = json_encode(['res' => 'duplicate', 'id' => $SKU_id]);
    }

    /** @throws */
    public function addingSku()
    {
        if (empty($_POST['supplier_id'])) {
            $supplier = SuppliersModel::getSupplierByUser($this->warehouse_id, $this->user->getId());
            if (empty($supplier['id'])) {
                View::addFlash('SKU_saved', 'danger', "Not found supplier for this user");
                throw new RedirectException('/SKUs');
            }
            $supplierId = $supplier['id'];
        } else
            $supplierId = $_POST['supplier_id'];
        $_POST['SKU']['gs1'] = (int)!empty($_POST['SKU']['gs1']);
        $_POST['SKU']['serial_number'] = (int)!empty($_POST['SKU']['serial_number']);
        $_POST['SKU']['ignore_WBE'] = (int)!empty($_POST['SKU']['ignore_WBE']);
        $_POST['SKU']['hs_code'] = trim($_POST['SKU']['hs_code'] ?? '');
        $_POST['barcodes'] = array_values(array_filter($_POST['barcodes'] ?? [], function ($item) {
            return !empty(trim($item));
        }));
        $redirect_data = '&addition='. base64_encode(json_encode($_POST['SKU']))
            . '&barcodes=' . base64_encode(json_encode($_POST['barcodes'] ?? []))
            . '&min_stock_levels=' . base64_encode(json_encode($_POST['min_stock_level'] ?? []));
        if (empty($_POST['SKU']['id'])) {
            $redirect_url = '/SKUs/add_sku/?supplier_id='.$supplierId . $redirect_data;
            unset($_POST['SKU']['id']);
        } else
            $redirect_url = '/SKUs/edit_sku/?id='.(int)$_POST['SKU']['id'].(empty($_POST['back']) ? '' : ('&back='.str_replace('&',
                        '^^', str_replace('?', '^', $_POST['back']))))
                                . $redirect_data;
        $SKU_data = $_POST['SKU'];
        $SKU_data['supplier_id'] = $supplierId;
        $back = empty($_POST['back']) ? '/SKUs' : $_POST['back'];
        if (!empty($SKU_data['state']) && SKU::isUnexpected($SKU_data['state']))
            $SKU_data['state'] = SKU::ACTIVE_NUM;
        $errors = [];
        if (!empty($_FILES['photo']) && !empty($_FILES['photo']['tmp_name']))
            if (!FileHelper::isRasterImage($_FILES['photo']['tmp_name']))
                $errors[] = ['message' => 'SKU photo is not image'];
        $min_stock_levels = $_POST['min_stock_level'] ?? [];
        if (!empty($min_stock_levels))
            foreach ( $min_stock_levels as $min_stock_level_value)
                if (intval($min_stock_level_value) < 0) {
                    $errors[] = ['message' => 'Min stock level error'];
                    break;
                }
        if (empty($errors)) {
            if (!empty($_FILES['photo']) && !empty($_FILES['photo']['tmp_name'])) {
                $photo = SKUsModel::savePhoto($_FILES['photo']['tmp_name'], !empty($SKU_data['id']) ? "{$SKU_data['id']}_{$SKU_data['supplier_id']}" : null);
                if ($photo)
                    $SKU_data['photo'] = $photo;
                else
                    View::addFlash('SKU_saved', 'danger', 'SKU photo error');
            } elseif (!empty($_POST['null_photo']) && $_POST['null_photo'] === 'TRUE')
                $SKU_data['photo'] = null;
            list($res, $errors) = SKUsModel::addSKU($SKU_data, $_POST['barcodes'] ?? [], $this->warehouse_id, $this->user->getId(), true, $min_stock_levels);
            if ($res) {
                View::addFlash('SKU_saved', 'success', 'SKU data saved');
                throw new RedirectException((empty($_GET['back']) && !empty($_SESSION[SKUsModel::FILTER_PARAM])) ? "/SKUs/?{$_SESSION[SKUsModel::FILTER_PARAM]}" : $back);
            }
        }
        View::addFlash('SKU_saved', 'danger', Arr::column($errors, 'message'));
        throw new RedirectException($redirect_url);
    }

    /** @throws */
    public function addSku()
    {
        $this->disableWarehouseChange();
        $supplier_id = intval($_GET['supplier_id'] ?? 0);
        $back = "/SKUs/?".(!empty($_SESSION[SKUsModel::FILTER_PARAM]) ? $_SESSION[SKUsModel::FILTER_PARAM] : '');
        if (!$supplier_id) {
            View::addFlash('SKU_create', 'danger', 'To create a SKU, you need to select a company in the filter first!');
            throw new RedirectException($back);
        }
        $addition = empty($_GET['addition']) ? [] : json_decode(base64_decode($_GET['addition']), true);
        $min_stock_levels = empty($_GET['min_stock_levels']) ? [] : json_decode(base64_decode($_GET['min_stock_levels']), true);
        $data = [
            'SKU' => new SKU($addition),
            'supplier_id' => $supplier_id,
            'supplier_read_only' => empty($_GET['ro']) ? false : (bool) $_GET['ro'],
            'back' => $back,
            'currencies' => Currencies::getCodes(),
            'sizes' => SizesModel::getAllSizesName($this->warehouse_id),
            'suppliers' => !$this->user->isRoleManager()
                                ? SuppliersModel::getSuppliers4SKUs($this->warehouse, $this->user)
                                : SuppliersModel::getByFilters($this->logistic_company->getId(), ['warehouse_id' => $this->warehouse_id]),
            'min_stock_level_data' => json_encode(SKUsModel::getMinStockLevels($this->user, $this->warehouse, $supplier_id, $min_stock_levels)),
            'countries' => Countries::getAll(),
            'bundles_names' => [],
            'barcodes' => empty($_GET['barcodes']) ? [] : json_decode(base64_decode($_GET['barcodes']), true),
            // ns_pid/s_pid aren't SKU entity fields, so they don't survive `new SKU($addition)` above -
            // read them back from the raw addition payload so a validation error doesn't wipe them
            'ns_pid' => $addition['ns_pid'] ?? '',
            's_pid' => $addition['s_pid'] ?? '',
        ];
        $this->render($data, 'SKUs/add_sku');
    }

    public function getSuppliersByBarcode()
    {
        $barcode = $_POST['barcode'];
        $suppliers_need = json_decode($_POST['suppliers'], 1);
        if (empty($barcode))
            return $this->response = json_encode(['error']);
        $suppliers = SKUsModel::getSuppliersByBarcode($barcode, $this->warehouse_id);
        if (!empty($suppliers_need))
            foreach ($suppliers as $key => $supplier)
                if (!in_array($supplier['id'], $suppliers_need))
                    unset($suppliers[$key]);
        $res = [];
        foreach ($suppliers as $supplier)
            $res[] = $supplier;
        return $this->response = json_encode($res);
    }

    public function getSKUBatchByBarcode()
    {
        $barcode = $_POST['barcode'];
        $locations_need = json_decode($_POST['locations'], 1);
        $suppliers_need = json_decode($_POST['suppliers'], 1);
        $batches_need = json_decode($_POST['batches'], 1);
        $type = $_POST['type'];
        $reason = $_POST['reason'];
        if (empty($barcode)) {
            $this->response = json_encode(['error']);
        } else {
            $SKUs = SKULocations::getSKUBatchByBarcode($barcode, $locations_need, $suppliers_need, $batches_need, $type, $reason);
            if ($SKUs === false)
                $this->response = json_encode(['error']);
            else
                $this->response = json_encode($SKUs);
        }
    }

    public function getOrdersBySKU()
    {
        $SKU_id = $_POST['SKU_id'];
        $order_id = $_POST['order_id'];
        if (empty($SKU_id))
            $this->response = json_encode(['error']);
        else {
            $suppliers =  array_keys($this->available_suppliers);
            if (empty($suppliers) && $this->user->isRoleWorker())
                $suppliers = array_map(function ($supplier) {
                    return $supplier['id'];
                }, Suppliers::getByFilters($this->logistic_company->getId(), ['warehouse_id' => $this->warehouse_id]));
            $orders = SKULocations::getOrdersBySKU($this->warehouse_id, $SKU_id, $order_id, $suppliers);
            foreach ($orders as $key => $order)
                $orders[$key]['status'] = OrderStatus::get($order['status']);
            $this->response = json_encode($orders);
        }
    }

    /** @throws */
    public function measure()
    {
        $this->disableWarehouseChange();
        $this->mobile = true;
        $current = (int)($_GET['c'] ?? 1);
        $search = '';
        if (isset($_POST['search']))
            $search = htmlentities($_POST['search']);
        elseif (isset($_GET['s']))
            $search = addslashes($_GET['s']);
        $id = Request::getPositiveId();
        $sid = empty($_GET['sid']) ? '' : $_GET['sid'];
        $measure = empty($_GET['measure']) ? '' : $_GET['measure'];
        $status = empty($_GET['status']) ? '' : $_GET['status'];
        $unassigned = empty($_GET['unassigned']) ? '' : $_GET['unassigned'];
        $present_in_orders = empty($_GET['present_in_orders']) ? '' : $_GET['present_in_orders'];
        $back = "{$_GET['back']}/?s=".urlencode($search)."&c=$current&sid=$sid&measure=$measure&status=$status&unassigned=$unassigned&present_in_orders=$present_in_orders";
        $SKU = SKUsModel::getById($id);
        if ($SKU === null) {
            View::addFlash('SKU_not_found', 'danger', 'SKU not found.');
            throw new RedirectException($back);
        }
        $data = [
            'SKU' => $SKU,
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()),
            'sizes' => SizesModel::getAllSizesName($this->warehouse_id),
            'back' => $back,
            'redirect' => $_SERVER['REQUEST_URI']
        ];

        $this->render($data, 'SKUs/measure');
    }

    /** @throws */
    public function measureChange()
    {
        $dataSKU = $_POST['SKU'];
        $back = $_POST['back'];
        $SKU = new SKU($dataSKU);
        $errors = SKUsModel::validate($SKU);
        $warnings = [];
        foreach ($errors as $key => $error)
            if ($error['field'] === 'hs_code') { // Поле hs_code делаем не обязательным, нужно для интеграций. И просто рекомендуем заполнить/проверить
                unset($errors[$key]);
                $warnings[] = $dataSKU['barcode'] . ' ' . $error['message'] . '. Please check';
            }
        if (empty($errors)) {
            $errors = SKUsModel::validateSKUCapacity($this->warehouse_id, $SKU);
            if (empty($errors)) {
                $fields = ['id', 'max_capacity', 'width', 'height', 'depth', 'weight'];
                if (!empty($_FILES['photo']) && !empty($_FILES['photo']['tmp_name'])) {
                    if (!FileHelper::isRasterImage($_FILES['photo']['tmp_name']))
                        $errors[] = ['message' => 'SKU photo is not image'];
                    $photo = SKUsModel::savePhoto($_FILES['photo']['tmp_name'], "{$dataSKU['id']}_{$dataSKU['supplier_id']}");
                    if ($photo) {
                        $SKU->setPhoto($photo);
                        $fields[] = 'photo';
                    }
                    else
                        View::addFlash('SKU_saved', 'danger', 'SKU photo error');
                } elseif (!empty($_POST['null_photo']) && $_POST['null_photo'] === 'TRUE') {
                    $SKU->setPhoto(null);
                    $fields[] = 'photo';
                }
                $sizes = SizesModel::getAllSizesName($this->warehouse_id);
                $SKU_origin = SKUsModel::getById($SKU->getId());
                $capacity = $SKU_origin->getMaxCapacity();
                $changes = [];
                $getChange = function ($param) use ($SKU, $SKU_origin, &$changes) {
                    $method = 'get' . ucfirst($param);
                    if (method_exists($SKU, $method) && method_exists($SKU_origin,
                            $method) && $SKU_origin->{$method}() != $SKU->{$method}()) {
                        $changes[$param] = [
                            $SKU_origin->{$method}(), $SKU->{$method}()
                        ];
                    }
                };
                $getChange('width');
                $getChange('height');
                $getChange('depth');
                $getChange('weight');
                $getChange('photo');
                foreach ($SKU->getMaxCapacity() as $size_id => $cap) {
                    if (!empty($capacity[$size_id]) && $capacity[$size_id] != $cap) {
                        $changes['max_capacity'][$sizes[$size_id][0]] = [
                            $capacity[$size_id], $cap
                        ];
                    }
                    $capacity[$size_id] = $cap;
                }
                $SKU->setMaxCapacity($capacity);
                $SKU->save($fields);
                if (!empty($changes))
                    SKUHistories::updateMeasure($SKU->getId(), $this->user->getId(), $changes, $this->warehouse_id);
                View::addFlash(
                    'measure_success',
                    'success',
                    sprintf('Measure was completed successfully for SKU %s', SKUBarcodes::getBarcodesBySKUIdString($SKU_origin->getId()))
                );
                foreach ($warnings as $warning)
                    View::addFlash('measure_warning', 'warning', $warning);
                throw new RedirectException($back);
            }
        }
        View::addFlash('measure', 'danger', Arr::column($errors, 'message'));
        $redirect = addslashes($_POST['redirect']);
        throw new RedirectException($redirect);
    }

    /** @throws */
    public function viewSku()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        unset($_GET['id']);
        $back = empty($_GET['back']) ? '' : str_replace('^', '?', str_replace('^^', '&', $_GET['back']));
        preg_match('/\W+minified?/', $back, $minified);
        unset($_GET['back']);
        if (empty($minified)) {
            if (empty($_GET['loc'])) {
                $back .= ('/?'.http_build_query($_GET));
            } else {
                $bb = empty($_GET['bb']) ? '' : $_GET['bb'];
                unset($_GET['bb']);
                $back .= ('/?'.http_build_query($_GET)."&back=$bb");
            }
        }
        $SKU = SKUsModel::getById($id);
        if (empty($SKU)) {
            View::addFlash('SKUs', 'danger', "SKU isn't found");
            throw new RedirectException($back);
        }
        $data = [
            'SKU' => $SKU,
            'SKU_barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()),
            'back' => $back,
        ];
        $this->render($data, 'SKUs/view_sku');
    }

    /** @throws */
    public function externalLink()
    {
        $id = Request::getPositiveId($_GET, 'SKU_id');
        if ($this->user->hasManagerAccess()) {
            $SKU = SKUsModel::getById($id);
        } else {
            $SKU = SKUsModel::getByIdAndUser($id, $this->user->getId());
        }
        $back = empty($_GET['back']) ? '/SKUs' : str_replace('^', '&', $_GET['back']);
        if ($SKU === null) {
            View::addFlash('SKUs', 'danger', 'SKU not found or you don\'t have an access to it');
            throw new RedirectException($back);
        }
        $res = SKUsModel::getProductExternalLink($_GET['channel'], $id, $_GET['shop_id'], $_GET['product_id'], $_GET['variant_id']);
        if (!$res[0]) {
            View::addFlash('SKUs', 'danger', $res[1]);
            throw new RedirectException('/main/close');
        }
        header("Location: {$res[1]}", true, 302);
        exit;
    }

    public function getInventory()
    {
        if (empty($_POST) || empty($_POST['shop_id']) || empty($_POST['SKU_id']) || empty($_POST['inventory_ids']))
            return '';
        /** @var SKU $SKU */
        $SKU = SKUsModel::getById($_POST['SKU_id']);
        if (empty($SKU))
            return $this->response = ['error' => 'SKU is not found'];
        $processor = new ShopifyDataProcessor();
        /** @var Shops $shop */
        $shop = ChannelsModel::getUserChannel([
            'shop_id' => $_POST['shop_id'],
            'warehouse_id' => $this->warehouse_id,
        ]);
        if (empty($shop))
            return $this->response = ['error' => 'Shop is not active'];
        $processor->setUserChannel($shop);
        $processor->setConnector(new ShopifyConnector($shop->getShop()->getCredentials()));
        $processor->setSupplier(Suppliers::getById($shop->getChannelSupplier()->getSupplierId()));
        $inventories = $processor->getConnector()->getInventoryItems($_POST['inventory_ids']);
        if (empty($inventories))
            return $this->response = ['error' => 'Inventory is not found'];
        $res = [];
        foreach ($inventories as $inventory)
            $res[$inventory['id']] = [
                'new_hs_code' => $inventory['harmonized_system_code'] ?: '',
                'new_cost' => $inventory['cost'] ?: 0,
                'new_country_id' => $inventory['country_code_of_origin'] ?? '',
            ];
        return $this->response = ['success' => json_encode([
            'old_hs_code' => $SKU->getHsCode(),
            'old_cost' => $SKU->getCostValue(),
            'old_country_id' => $SKU->getCountryId(),
            'new_data' => $res,
        ])];
    }

    public function setInventory()
    {
        if (empty($_POST) || empty($_POST['SKU_id']))
            return '';
        /** @var SKU $SKU */
        $SKU = SKUsModel::getById($_POST['SKU_id']);
        if (empty($SKU))
            return $this->response = ['error' => 'SKU is not found'];
        $SKU->setHsCode($_POST['hs_code']);
        $SKU->setCostValue($_POST['cost']);
        $SKU->setCountryId($_POST['country_id']);
        $SKU->save(['id', 'hs_code', 'cost_value', 'country_id']);
        SKUHistories::setChannelInventory($SKU->getId(), $this->user->getId(), $_POST, $this->warehouse_id);
        View::addFlash('SKUs', 'success', 'Inventory is updated');

        return $this->response = ['success'];
    }

    /** @throws */
    public function changeConnectedSkus()
    {
        $id = Request::getPositiveId($_POST);
        $shop_id = $_POST['shop_id'];
        $product_id = $_POST['product_id'];
        $variant_id = $_POST['variant_id'] ?? 0;
        $SKU_channel_id = $_POST['sku_channel_id'];
        if ($this->user->hasManagerAccess()) {
            $SKU = SKUsModel::getById($id);
        } else {
            $SKU = SKUsModel::getByIdAndUser($id, $this->user->getId());
        }
        if (empty($SKU)) {
            View::addFlash('SKUs', 'danger', 'SKU not found or you don\'t have an access to it');
            throw new RedirectException(empty($_POST['back']) ? '/SKUs' : $_POST['back']);
        }
        $back = '/SKUs/edit_connections/?id='.(int) $SKU->getId().(empty($_POST['back']) ? '' : ('&back='.str_replace('&',
                    '^^', str_replace('?', '^', $_POST['back']))));
        if (empty($shop_id) || empty($product_id)) {
            View::addFlash('SKUs', 'danger', 'Please fill up all required data');
            throw new RedirectException($back);
        }
        $res = SKUsModel::changeConnectedSKU($SKU_channel_id, $SKU->getId(), $shop_id, $product_id, $variant_id, $this->user->getId(), $this->warehouse_id);
        View::addFlash('SKUs', $res[0] ? 'success' : 'danger', $res[1]);
        throw new RedirectException($back);
    }

    /** @throws */
    public function deleteConnectedSkus()
    {
        $id = Request::getPositiveId($_POST);
        $SKU_id = (int) $_POST['SKU_id'];
        $back = "/SKUs/edit_connections/?id=$SKU_id".(empty($_POST['back']) ? '' : ('&back='.str_replace('&', '^^',
                    str_replace('?', '^', $_POST['back']))));
        SKUsModel::deleteConnectedSKU($SKU_id, $id, $this->user->getId(), $this->warehouse_id);
        View::addFlash('SKUs', 'warning', 'Channel for SKU was deleted!');
        throw new RedirectException($back);
    }

    public function clearSKUInDH()
    {
        if (!empty($_POST) && !empty($_POST['SKU_id'])) {
            $SKU_id = $_POST['SKU_id'];
            $locs = SKULocations::clearSKUinDH($this->warehouse_id, $SKU_id);
            if (!empty($locs)) {
                SKUHistories::clearDH($SKU_id, -array_sum(Arr::column($locs, 'Units')), $locs, $this->warehouse_id);
                $this->response = json_encode('success');
                return '';
            }
        }
        return $this->response = json_encode('error');
    }

    public function getFromReality()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $SKU_id = Request::getPositiveId($_POST, 'SKU_id');
        if ($SKU_id <= 0)
            return $this->response = ['error' => 'SKU is not found'];
        $SKU = SKUsModel::getById($SKU_id);
        if (empty($SKU))
            return $this->response = ['error' => 'SKU is not found'];
        if (!$this->warehouse_virtual)
            return $this->response = ['error' => 'Warehouse is not virtual'];
        try {
            $processor = Warehouses::getVirtualProcessor($this->warehouse_id, $SKU->getSupplierId());
            if (empty($processor))
                return $this->response = ['error' => 'Supplier is not found in warehouse'];
            $SKU_reality = $processor->getSKU($SKU->getId());
            if (empty($SKU_reality))
                return $this->response = ['error' => 'SKU is not found in warehouse'];
        } catch (\Exception $e) {
            return $this->response = ['error' => $e->getMessage()];
        }
        return $this->response = ['success' => $SKU_reality];
    }

    public function checkUsages()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA() || empty(SKUsModel::getById($SKU_id = Request::getPositiveId($_POST, 'SKU_id'))))
            return $this->response = ['error'];
        return $this->response = SKUsModel::usages($SKU_id);
    }

    /** @throws */
    public function hardRemove()
    {
        $back = '/SKUs/?'.(!empty($_SESSION[SKUsModel::FILTER_PARAM]) ? $_SESSION[SKUsModel::FILTER_PARAM] : '');
        if (!$this->user->isRoleSA()) {
            View::addFlash('SKUs hard remove', 'danger', 'Access denied');
            throw new RedirectException($back);
        }
        $SKU_id = Request::getPositiveId($_POST, 'SKU_id');
        if (empty($SKU_id) || empty(SKUsModel::getById($SKU_id))) {
            View::addFlash('SKUs hard remove', 'danger', 'SKU not found');
            throw new RedirectException($back);
        }
        $usage_res = SKUsModel::usages($SKU_id);
        if ($usage_res['result']) {
            list($result, $cleared) = SKUsModel::hardRemove($SKU_id, $this->user->getId(), $this->warehouse_id);
            if (!$result)
                View::addFlash('SKUs hard remove', 'danger', 'Remove failed');
            else {
                View::addFlash('SKUs hard remove', 'danger', "SKU #{$SKU_id} removed");
                if (!empty($cleared))
                    View::addFlash('SKUs hard remove', 'warning', $cleared);
            }
        } else
            View::addFlash('SKUs hard remove', 'danger', $usage_res['usages']);

        throw new RedirectException($back);
    }

    public function bulkEdit()
    {
        $data = [
            'fields' => SKUsBulkService::$fields,
            'back' => '/SKUs/?'.(!empty($_SESSION[SKUsModel::FILTER_PARAM]) ? $_SESSION[SKUsModel::FILTER_PARAM] : ''),
            'example' => 'bulk_SKUs_edit_example',
        ];
        $this->render($data, 'SKUs/bulk_edit');
    }

    public function bulkPreview()
    {
        if (!empty($_FILES['CSV_file'])) {
            $bulk = new SKUsBulkService(new File($_FILES['CSV_file']));
            $error = $bulk->getError();
            if (!empty($error))
                return $this->response = ['error' => $error];

            list($open, $res) = $bulk->getHeadCSV();
            return $this->response = [$open ? 'result': 'error' => $open ? $res : $bulk->getError()];
        }
        return $this->response = ['error' => 'File not loaded'];
    }

    public function bulkStore()
    {
        if (!empty($_FILES['CSV_file'])) {
            $bulk = new SKUsBulkService(new File($_FILES['CSV_file']));
            $error = $bulk->getError();
            if (!empty($error))
                return $this->response = ['error' => $error];
            $bulk->prepareData($_POST);
            $bulk->setUser($this->user);
            $bulk->setWarehouseId($this->warehouse_id);
            list($open, $res) = $bulk->execute();
            if ($open && is_null($res)) {
                $message = $bulk->getMessage();
                if (!empty($message)) {
                    $_SESSION['user_queue'] = true;
                    $_SESSION['user_queue_flash'] = true;
                    View::addFlash(
                        'SKU_bulk_update',
                        'warning',
                        $message);
                }
            }
            return $this->response = [$open ? 'result': 'error' => $open ? $res : $bulk->getError()];
        }
        return $this->response = ['error' => 'File not found'];
    }

    public function getSerialNumbers()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $SKU_id = Request::getPositiveId($_POST, 'SKU_id');
        $page = Request::getPositiveId($_POST, 'page');
        $page_size = 10;
        if (!$SKU_id || !$page)
            return $this->response = json_encode(['error' => 'Wrong data']);
        $serial_numbers = OrderSKUSerialNumbers::getBySKUId($SKU_id, $this->warehouse, ($page - 1) * $page_size, $page_size);
        $count = OrderSKUSerialNumbers::getCountBySKUId($SKU_id, $this->warehouse);
        return $this->response = json_encode(['data' => ['serial_numbers' => $serial_numbers, 'pages' => ceil($count / $page_size), 'page' => $page]]);
    }

    public function getPhoto()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_GET['id']))
            return $this->jsonResponse(['error' => 'SKU ID is required']);
        
        $sku_id = (int)$_GET['id'];
        $sku = SKUsModel::getById($sku_id);
        
        if (empty($sku))
            return $this->jsonResponse(['error' => 'SKU not found']);
        
        $photo = $sku->getPhoto();
        if (empty($photo))
            return $this->jsonResponse(['error' => 'No photo available']);
        
        return $this->jsonResponse([
            'sku_id' => $sku_id,
            'photo' => "/image/public?path=" . $sku->getPhoto()
        ]);
    }
}
