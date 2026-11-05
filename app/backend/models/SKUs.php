<?php

namespace app\backend\models;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\FileJob;
use app\core\entities\Order;
use app\core\entities\SKU;
use app\core\models\config\SKUsConfig;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\entities\UserJob;
use app\core\helpers\Arr;
use app\core\helpers\File;
use app\core\helpers\formatters\FormatterCSV;
use app\core\helpers\Validator;
use app\core\models\Warehouses;
use app\core\interfaces\ReportCSV as InterfaceReportCSV;
use app\core\traits\ReportCSV as TraitReportCSV;
use app\core\helpers\FileWrapper;

class SKUs extends \app\core\models\SKUs implements InterfaceReportCSV
{
    use TraitReportCSV;

    const FILTER_PARAM = 'sku_all_filters';

    private const REPORT_FOLDER_NAME = 'SKU_reports';
    private const REPORT_NAME = 'Fulfilment_SKU_list';

    static $csv_headline = [];

    public static function selectView($SKU, $priority, $next_day, $standard)
    {
        return (!empty($SKU['priority']) && $SKU['priority']) ? $priority
                                                              : ((!empty($SKU['next_day']) && $SKU['next_day']) ? $next_day
                                                                                                                : $standard);
    }

    public static function viewProcessingStatus($SKU)
    {
        return '<span class="w-100 badge badge-' . self::selectView($SKU, 'purple">high', 'nextday">next day', 'secondary">standard') . '</span>';
    }

    public static function viewProcessingBorder($SKU)
    {
        return 'border-' . self::selectView($SKU, 'purple background-purple', 'nextday', 'info');
    }

    public static function viewProcessingText($SKU)
    {
        return 'text-' . self::selectView($SKU, 'purple', 'nextday', 'info');
    }

    public static function viewPackingText($SKU)
    {
        return self::selectView($SKU, ' class="text-purple font-weight-bold"', ' class="text-nextday font-weight-bold"', '');
    }

    public static function setHeadline($headline)
    {
        self::$csv_headline = $headline;
    }

    public static function validate(SKU $SKU, $owner = false, $uniq = true): array
    {
        $rules = [
            'name' => [
                'val' => $SKU->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 255]]
            ],
            'gs1' => [
              'val' => $SKU->getGs1(),
              'rules' => [Validator::TYPE_INT => [0, 1]]
            ],
            'market_value' => [
                'val' => $SKU->getMarketValue(),
                'rules' => [Validator::TYPE_DOUBLE => [0, 1000000000]]
            ],
            'market_currency' => [
                'val' => $SKU->getMarketCurrency(),
                'rules' => [Validator::TYPE_STRING => [1, 5]]
            ],
            'cost_value' => [
                'val' => $SKU->getCostValue(),
                'rules' => [Validator::TYPE_DOUBLE => [0, 1000000000]]
            ],
            'cost_currency' => [
                'val' => $SKU->getCostCurrency(),
                'rules' => [Validator::TYPE_STRING => [1, 5]]
            ],
            'width' => [
                'val' => $SKU->getWidth(),
                'rules' => [Validator::TYPE_DOUBLE => [0.0001, 100000]]
            ],
            'height' => [
                'val' => $SKU->getHeight(),
                'rules' => [Validator::TYPE_DOUBLE => [0.0001, 100000]]
            ],
            'depth' => [
                'val' => $SKU->getDepth(),
                'rules' => [Validator::TYPE_DOUBLE => [0.0001, 100000]]
            ],
            'weight' => [
                'val' => $SKU->getWeight(),
                'rules' => [Validator::TYPE_DOUBLE => [0.0001, 100000]]
            ],
            'hs_code' => [
                'val' => $SKU->getHSCode(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [6, 12]]
            ],
            'season' => [
                'val' => $SKU->getSeason(),
                'rules' => [Validator::TYPE_STRING => [0, 100]]
            ],
            'customer_ref' => [
                'val' => $SKU->getCustomerRef(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'unique_customer_ref' => [
                'val' => $SKU->getUniqueCustomerRef(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'customer_ref2' => [
                'val' => $SKU->getCustomerRef2(),
                'rules' => [Validator::TYPE_STRING => [0, 65535]]
            ],
            'photo' => [
                'val' => $SKU->getPhoto(),
                'rules' => [Validator::TYPE_STRING => [0, 255]]
            ],
            'photo_src' => [
                'val' => $SKU->getPhotoSrc(),
                'rules' => [Validator::TYPE_STRING => [0, 2100]]
            ],
        ];
        if (empty($SKU->getId()))
            $rules['customer_ref']['rules'][Validator::REQUIRED] = 1;
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        if ($uniq)
            return self::validateUniq($SKU, $validator);
        return [];
    }

    public static function validateUniq(SKU $SKU, Validator $validator = null): array
    {
        if (is_null($validator))
            $validator = new Validator();
        if (!empty($SKU->getId()) && null === SKUs::getById($SKU->getId()))
            $validator->addError("SKU_not_found", "SKU not found");
        if (!empty($SKU->getUniqueCustomerRef())) {
            $SKU_by_unique_ref = SKUs::getByUniqueCustomerRefAndSupplier($SKU->getUniqueCustomerRef(), $SKU->getSupplierId());
            if ($SKU_by_unique_ref && $SKU_by_unique_ref->getId() != $SKU->getId())
                $validator->addError("not_unique",
                    "This unique customer ref is already used in SKU #{$SKU_by_unique_ref->getId()}.",
                    'unique_customer_ref');
        }
        return $validator->getErrors();
    }

    public static function validateSKUCapacity($warehouse_id, SKU $SKU): array
    {
        $warehouse_id = (int) $warehouse_id;
        $requestedSkuCapacity = $SKU->getMaxCapacity();
        $sizesId = array_keys($requestedSkuCapacity);
        $SKULocations = SKULocations::getSKULocationsBySizes($warehouse_id, $SKU->getId(), $sizesId);
        $errors = [];
        foreach ($SKULocations as $row)
            if ((int) $requestedSkuCapacity[$row['size_id']] < (int) $row['quantity']) {
                $message = sprintf(
                    'This SKU is already stored in a quantity of %d at location %s (%s).',
                    $row['quantity'],
                    $row['location_name'],
                    $row['size_name']
                );
                $errors[] = [
                    'code' => 'SKU_is_already_stored',
                    'message' => $message,
                    'field' => 'max_capacity'
                ];
            }
        return $errors;
    }

    public static function loadPhoto()
    {
        $SKUs = (new SafeMysql())->fetchAll("SELECT id, supplier_id, photo, photo_src
                                  FROM SKUs
                                  WHERE (TRIM(photo) = '' OR photo IS NULL) AND TRIM(photo_src)<>''");
        foreach ($SKUs as $SKU_data) {
            $filename = File::downloadUrlToFile($SKU_data['photo_src'], SKUS_STORAGE,
                self::getPhoto("{$SKU_data['id']}_{$SKU_data['supplier_id']}"), true);
            if ($filename) {
                $SKU = new SKU($SKU_data);
                $SKU->setPhoto($filename);
                $SKU->save(['id', 'photo']);
            }
        }
    }

    public static function getPhoto($unique)
    {
        // $unique is always "{id}_{supplier_id}" or a uniqid() string, but savePhoto()/
        // savePhotoFromDisk() build it from raw request data ($_POST['SKU']['id']/['supplier_id'])
        // with no numeric cast, and it ends up as the filename appended after the (safe,
        // hash-derived) subdirectories below - so without this it's a path-traversal-on-write
        // vector, not just a cosmetic filename issue.
        $unique = preg_replace('/[^A-Za-z0-9_]/', '', (string)$unique);
        return self::createPhotoPathWithDecodeDirs($unique) . DIRECTORY_SEPARATOR . $unique;
    }

    public static function addSKU($product, array $barcodes, $warehouse_id, $user_id, $validate = false, $min_stock_levels = [])
    {
        $barcodes = array_map('trim', $barcodes);
        $barcodes = array_filter($barcodes);
        $SKU = new SKU($product);
        if ($validate && $errors = array_merge(SKUs::validate($SKU), SKUBarcodes::validate($SKU, $barcodes)))
            return [false, $errors];
        $update = $SKU->getId();
        $changes = $update ? SKUs::getChanges($SKU->getId(), $SKU) : $product;
        $current_barcodes = $update ? SKUBarcodes::getBySKUId($SKU->getId()) : [];
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $SKU->save(array_keys($product));
        if (!empty($changes) || array_diff($current_barcodes, $barcodes) || array_diff($barcodes, $current_barcodes))
            SKUHistories::updateParams($SKU->getId(), $user_id, $changes + ['barcodes' => $barcodes], $warehouse_id);
        SKUBarcodes::delete($SKU->getId());
        SKUBarcodes::add($SKU->getId(), $barcodes);
        if (!empty($min_stock_levels)) {
            foreach ($min_stock_levels as $w_id => $min_stock_level) {
                $min_stock_level = (int)$min_stock_level;
                $old_min_stock_level = SKUWarehouses::getMinStockLevel($SKU, $w_id);
                if ($old_min_stock_level !== $min_stock_level) {
                    SKUWarehouses::setMinStockLevel(abs($min_stock_level), $SKU, $w_id);
                    SKUHistories::updateMinStockLevel($SKU->getId(), $user_id, $w_id, $old_min_stock_level, $min_stock_level);
                }
            }
        }
        SKUWarehouses::setCostCentre($SKU->getId(), $warehouse_id, $product['cost_centre'] ?? '');
        SKUsConfig::saveConfigs([
            SKUsConfig::nsPid($SKU->getId(), trim($product['ns_pid'] ?? '')),
            SKUsConfig::sPid($SKU->getId(), trim($product['s_pid'] ?? '')),
        ]);
        if (!Warehouses::isVirtual($warehouse_id)) {
            $locs = SKUPreffLocs::getLocationsAutoAssign($SKU->getSupplierId());
            if ($locs) {
                SKUPreffLocs::assignments($SKU->getId(), $locs);
                SKUHistories::addAutoAssigns($SKU->getId(), $locs, $user_id);
            }
        }
        $mysql->commit();
        if (Warehouses::isVirtual($warehouse_id))
            try {
                $processor = Warehouses::getVirtualProcessor($warehouse_id, $product['supplier_id']);
                if (empty($processor))
                    return [false, [['code' => 'virtual_warehouse', 'message' => 'Supplier is not found for warehouse']]];
                $processor->addSKU(['id' => $SKU->getId()] + $product, $update ? $changes : false);
            } catch (\Exception $e) {
                return [false, [['code' => 'virtual_warehouse', 'message' => $e->getMessage()]]];
            }
        return [true, $SKU->getId()];
    }

    public static function savePhoto($tmpName, $unique = null)
    {
        File::convertImageSize($tmpName);
        $unique = is_null($unique) ? uniqid('', false) : $unique;
        $photo = self::getPhoto($unique);
        $photoPath = File::normalizePath(SKUS_STORAGE . $photo);
        if (File::moveUploadedFile($tmpName, $photoPath))
            return $photo;
        return false;
    }

    public static function savePhotoFromDisk($tmpName, $unique)
    {
        $unique = is_null($unique) ? uniqid('', false) : $unique;
        $photo = self::getPhoto($unique);
        $photoPath = SKUS_STORAGE . $photo;
        File::convertImageSize($tmpName);
        try {
            (new FileWrapper(FileWrapper::getContainerByPath($photoPath)))->writeFilePath($photoPath, $tmpName);
        } catch (\Exception $e) {
            return false;
        }
        return $photo;
    }

    public static function createPhotoPathWithDecodeDirs($photoPath)
    {
        $decodeName = md5($photoPath);
        $subDirFirst = substr($decodeName, 0, 2);
        $subDirFirstFull = SKUS_STORAGE . $subDirFirst;
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($subDirFirstFull)))->handleDir($subDirFirstFull);
        $subDirSecond = substr($decodeName, 3, 2);
        $subDirSecondFull = SKUS_STORAGE . $subDirFirst . DIRECTORY_SEPARATOR . $subDirSecond;
        $file_wrapper->handleDir($subDirSecondFull);
        return $subDirFirst . DIRECTORY_SEPARATOR . $subDirSecond;
    }

    public static function getSuppliersByBarcode($barcode, $warehouse_id)
    {
        $warehouse_id = (int) $warehouse_id;
        $suppliers = (new SafeMysql())->fetchAll("
                SELECT sup.id, sup.name, sup.warehouses
                FROM SKUs s
                    JOIN suppliers sup ON s.supplier_id = sup.id
                    JOIN SKU_barcodes sb ON s.id = sb.SKU_id AND sb.barcode = :barcode", [
                        'barcode' => $barcode,
        ]);
        if (empty($suppliers))
            return [];
        foreach ($suppliers as $key => $supplier) {
            $warehouses = json_decode($supplier['warehouses']);
            if (!in_array($warehouse_id, $warehouses))
                unset($suppliers[$key]);
        }
        return $suppliers;
    }

    /** @throws */
    public static function getAllAsCSV($warehouse_id, array $filters, User $user): void
    {
        $locations = Warehouses::getLocations($warehouse_id);
        $filters_SKU_ids = self::getSKUsIdsByFilters((int)$warehouse_id, $filters);
        $csv = new FormatterCSV;
        $csv->addHeader(self::handleHeadlineCSV([$user->getId(), $locations]));
        $raw_result = self::getAvailableByFilterV2((int)$warehouse_id, $filters, $filters_SKU_ids, 0, 5000);
        SKULocations::addQtyToSKUsArray($raw_result, (int)$warehouse_id);
        while (!empty($raw_result)) {
            self::handleDataToCSV([$raw_result, $locations], $csv, $warehouse_id);
            $last_sku_id = intval(end($raw_result)['id']);
            $filters['max_item_id'] = $last_sku_id;
            $raw_result = self::getAvailableByFilterV2((int)$warehouse_id, $filters, $filters_SKU_ids, 0, 5000);
            SKULocations::addQtyToSKUsArray($raw_result, (int)$warehouse_id);
        }
    }

    static function getTotalCountReportCSV(array $filters): int
    {
        return self::getAvailableTotalByFilterV2((int)$filters['warehouse_id'], $filters, self::getSKUsIdsByFilters((int)$filters['warehouse_id'], $filters));
    }

    public static function getAvailableTotalByFilterV2(int $warehouse_id, array $filters, ?array $filters_SKU_ids): int
    {
        if ($filters_SKU_ids === null)
            return 0;
        $forIntegrations = self::getJoinForIntegrations($warehouse_id, $filters['product_id'] ?? null, $filters['variant_id'] ?? null);
        $params = $where = [];
        if ($filters_SKU_ids) {
            $where[] = "s.id IN (:ids)";
            $params['ids'] = $filters_SKU_ids;
        }
        SKULocations::setFiltersForAvailable($filters, $params, $where, 's', true);
        $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
        $sql = "SELECT count(*) FROM SKUs s $forIntegrations $where_sql";
        return (int)(new SafeMysql())->fetchScalar($sql, $params);
    }

    static function getParamsHeadline(UserJob $userJob)
    {
        $addition = json_decode($userJob->getAddition(), true);
        $locations = Warehouses::getLocations($addition['addition']);
        return [$userJob->getUserId(), $locations];
    }

    static function processCSV(UserJob $userJob, $raw_result, $output, $addition)
    {
        list($user_id, $locations) = self::getParamsHeadline($userJob);
        self::handleHeadlineCSV([$user_id, $locations]);
        self::handleDataToCSV([$raw_result, $locations], $output, $addition);
    }

    public static function handleReportCSV($user_job_id, $max_item_id = 0): array
    {
        /**
         * @var FileJob $fileJob
         * @var UserJob $userJob
         * @var FormatterCSV $csv
         */
        try {
            list($userJob, $fileJob, $csv) = self::initReportCSV($user_job_id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
        $addition = json_decode($userJob->getAddition(), true);
        $warehouse_id = (int)$addition['addition'];
        $filters = (!empty($max_item_id) ? ['max_item_id' => (int)$max_item_id] : []) + ($addition['filters'] ?? []);
        $filters_SKU_ids = self::getSKUsIdsByFilters($warehouse_id, $filters);
        $raw_result = self::getAvailableByFilterV2($warehouse_id, $filters, $filters_SKU_ids, 0, self::LIMIT_GENERATE_REPORT + 1, 's.*, s.id AS item_id, s.depth AS length');
        SKULocations::addQtyToSKUsArray($raw_result, (int)$warehouse_id);
        $next = false;
        if (!empty($raw_result))
            $next = self::processStep($raw_result, $csv, $userJob);
        if (!$next || empty($raw_result)) {
            self::transferToCloud($fileJob);
            self::doneUserJob($userJob, $fileJob);
        }
        return ['success' => 'Handled part of SKUs report CSV for user job #' . $user_job_id];
    }

    /** @throws */
    static function handleDataToCSV(array $raw_result, FormatterCSV $csv, $addition = null)
    {
        list($raw_result, $locations) = $raw_result;
        SKUBarcodes::addBarcodesToArray($raw_result);
        $SKU_ids = Arr::column($raw_result, 'id', false);
        $SKU_warehouses = [];
        if (!empty($locations))
            $SKU_warehouses = SKULocations::getVirtual($addition, $SKU_ids);
        $batches = SKULocations::getBatches($addition, $SKU_ids);
        foreach ($raw_result as $row) {
            $row['batches'] = '';
            if (!empty($batches[$row['id']]))
                foreach ($batches[$row['id']] as $batch) {
                    $expired = date('jS M Y', strtotime($batch['expired']));
                    $row['batches'] .= "#{$batch['batch_number']} ({$batch['quantity']}) {$expired}. ";
                }
            if (!empty($SKU_warehouses) && !empty($SKU_warehouses[$row['id']]))
                foreach ($SKU_warehouses[$row['id']] as $name => $quantity)
                    $row[$name] = $quantity;
            $res = [];
            foreach (self::$csv_headline as $title)
                $res[$title] = $row[$title] ?? '';
            $csv->addRow($res);
        }
    }

    public static function getHeadlineCSV(): array
    {
        return [
            'id', 'name', 'barcode', 'gs1', 'cost_value', 'cost_currency', 'market_value', 'market_currency',
            'length', 'width', 'height', 'weight', 'hs_code', 'season', 'customer_ref', 'customer_ref2', 'unique_customer_ref',
            'state', 'available', 'allocated', 'backorder_replenish', 'photo_src', 'batches',
        ];
    }

    static function handleHeadlineCSV($params): array
    {
        self::$csv_headline = self::getHeadlineCSV();
        list($user_id, $locations) = $params;
        if (!empty($locations)) {
            $tmp = [];
            foreach (self::$csv_headline as $field) {
                $tmp[] = $field;
                if ($field == 'available')
                    foreach ($locations as $loc_abbr => $loc_name)
                        $tmp[] = $loc_abbr;
            }
            self::$csv_headline = $tmp;
        }
        $user = Users::getById($user_id);
        if (!empty($user) && !$user->isRoleSupplierUser() && !in_array('supplier_id', self::$csv_headline))
            self::$csv_headline = array_merge(self::$csv_headline, ['supplier_id']);
        return self::$csv_headline;
    }

    public static function formatReportQuery($warehouse_id, array $filters): QueryBuilder
    {
        $query = new QueryBuilder();
        $whereLocations = Locations::getExcludeLocationsPartialQuery($warehouse_id);
        $forIntegrations = '';
        if (!empty($filters['product_id']) || !empty($filters['variant_id'])) {
            $forIntegrations = "JOIN SKU_channel_suppliers scs ON s.id = scs.SKU_id ";
            if (!empty($filters['product_id'])) {
                $product_id = addslashes(addcslashes($filters['product_id'], '_%'));
                $forIntegrations .= "AND (scs.external_id LIKE '%-{$product_id}' OR
                                          scs.external_id LIKE '{$product_id}\_%' OR
                                          scs.external_id = '{$product_id}') ";
            }
            if (!empty($filters['variant_id'])) {
                $variant_id = addslashes(addcslashes($filters['variant_id'], '_%'));
                $forIntegrations .= "AND (scs.external_id LIKE '{$variant_id}-%' OR
                                          scs.external_id LIKE '%\_{$variant_id}') ";
            }
            $forIntegrations .= "JOIN shop_warehouses sw ON scs.shop_id = sw.shop_id AND sw.warehouse_id = $warehouse_id";
        }
        $query->setSelectFrom("SELECT s.id as item_id, s.*,
                                             IFNULL(av.available, 0) available,
                                             IFNULL(al.allocated, 0) allocated,
                                             IFNULL(br.backorder_replenish, 0) backorder_replenish
                                      FROM SKUs s
                                          $forIntegrations
                                          JOIN suppliers sup ON s.supplier_id = sup.id
                                          JOIN users u ON sup.user_id = u.id
                                          LEFT JOIN (SELECT SKU_id, SUM(quantity) available
                                                FROM SKU_locations $whereLocations
                                                WHERE type = 1 AND reason = '2'
                                                GROUP BY SKU_id) av ON s.id = av.SKU_id
                                          LEFT JOIN (SELECT SKU_id, SUM(quantity) allocated
                                                FROM SKU_locations $whereLocations
                                                WHERE NOT (type = 1 AND reason = '2') AND type <> 4
                                                GROUP BY SKU_id) al ON s.id = al.SKU_id
                                          LEFT JOIN (SELECT st.SKU_id, SUM(st.qty) backorder_replenish
                                                FROM (SELECT os.order_id, os.SKU_id, os.quantity - SUM(IFNULL(sl.quantity, 0)) qty
                                                      FROM order_SKUs os
                                                          JOIN orders o ON os.order_id = o.id AND o.status = " . Order::BACKORDER_NUM . " AND o.warehouse_id = $warehouse_id
                                                          LEFT JOIN SKU_locations sl ON
                                                              os.SKU_id = sl.SKU_id AND
                                                              (os.order_id = sl.order_id OR (sl.order_id IS NULL AND sl.type = 1 AND POSITION('2_' IN sl.reason) = 1 AND SUBSTRING_INDEX(sl.reason, '_', -1) = os.order_id))
                                                      GROUP BY os.order_id, os.SKU_id) st
                                                GROUP BY st.SKU_id) br ON s.id = br.SKU_id");
        self::applySearchFilters($query, $filters);
        return $query;
    }

    public static function getMinStockLevels(User $user, Warehouse $warehouse, $supplier_id, $min_stock_levels = []): array
    {
        if ($user->hasAdminAccess()) {
            $warehouses = $user->isRoleSA()
                ? Warehouses::getAll($warehouse->getLCId())
                : Warehouses::getByIds(Users::getAllWarehouseAdmin($user->getId()));
            $suppliers = Suppliers::getSuppliers4SKUs($warehouse, $user);
        } elseif ($user->isRoleManager()) {
            $warehouses = UserWarehouses::getAvailableWarehouses($user->getId());
            $suppliers = Suppliers::getSuppliers4SKUs($warehouse, $user);
        } else {
            $warehouses = [['id' => $warehouse->getId(), 'name' => $warehouse->getName()]];
            if ($user->isRoleWorker())
                $suppliers = Suppliers::getByFilters($warehouse->getLCId(), ['warehouse_id' => $warehouse->getId()]);
            else
                $suppliers = [$supplier_id => null];
        }
        $min_stock_level_data = [];
        foreach ($suppliers as $s_id => $supplier) {
            $supplier_warehouses = Arr::column(Suppliers::getWarehousesById(intval($s_id)), 'id');
            array_walk($warehouses, function($w) use ($s_id, $supplier_id, $supplier_warehouses, $min_stock_levels, &$min_stock_level_data) {
                if (in_array($w['id'], $supplier_warehouses)) {
                    if ($s_id === $supplier_id)
                        $value = $min_stock_levels[$w['id']] ?? null;
                    else $value = null;
                    $min_stock_level_data[$s_id][] = [
                        'warehouse_id' => (int)$w['id'],
                        'warehouse_name' => $w['name'],
                        'value' => $value,
                    ];
                }
            });
        }
        return $min_stock_level_data;
    }

    public static function getPriceSKUs($SKUs)
    {
        $res = [];
        if (empty($SKUs) || !is_array($SKUs))
            return $res;
        $SKUs_price = (new SafeMysql())->fetchAll("
            SELECT id, market_value
            FROM SKUs
            WHERE id IN (:SKUs)", ['SKUs' => array_keys($SKUs)]);
        if (empty($SKUs_price) || count($SKUs) != count($SKUs_price))
            return $res;
        foreach ($SKUs_price as $SKU)
            $res[$SKU['id']] = ['qty' => $SKUs[$SKU['id']], 'price' => $SKU['market_value']];
        return $res;
    }

    public static function getPriceById($SKU_id): ?float
    {
        $res = (new SafeMysql())->fetchOne('SELECT id, market_value FROM SKUs WHERE id = :SKU_id', ['SKU_id' => $SKU_id]);
        if (empty($res))
            return null;
        return (float)$res['market_value'];
    }

    public static function isEditSize($size_id)
    {
        $capacities = (new SafeMysql())->fetchArrayOneField("SELECT max_capacity FROM SKUs where max_capacity like :pattern", ['pattern' => '%"' . $size_id . '":%']);
        foreach ($capacities as $capacity) {
            $check = json_decode($capacity ?: '[]', true);
            if (!empty($check[$size_id]))
                return false;
        }
        return true;
    }

    public static function getSKUsForSearch(string $term, int $warehouse_id, array $supplier_ids, int $limit, array $states = []): array
    {
        $term = $term === ' ' ? $term : trim($term);
        if (!$term)
            return [];
        $params = ['warehouse_id' => $warehouse_id, 'limit' => $limit, 'term_like' => '%' . $term. '%', 'term_in' => []];
        $where_search = "AND (Sb.barcode LIKE :term_like OR S.name LIKE :term_like OR Sb.barcode IN (:term_in) OR S.name IN (:term_in))";
        $where_states = $word = $where_supplier_id = '';

        $params['supplier_ids'] = $supplier_ids;
        $where_supplier_id = "AND S.supplier_id IN (:supplier_ids)";

        foreach (preg_split('//u', $term) as $letter)
            if ($letter)
                $params['term_in'][] = $word .= $letter;
        if ($states = array_map('intval', $states)) {
            $params['states'] = $states;
            $where_states = 'AND S.state IN (:states)';
        }
        $sql = "
        SELECT DISTINCT S.id, S.name, S.customer_ref, S.state, S.market_value price, S.market_currency, sup.name s_name, c.id currency_id, S.supplier_id, S.photo
            FROM SKUs S
        JOIN suppliers_warehouses sw ON sw.supplier_id = S.supplier_id
        JOIN SKU_barcodes Sb ON Sb.SKU_id = S.id
        JOIN suppliers sup ON sup.id = S.supplier_id
        LEFT JOIN currency c ON c.currency = S.market_currency AND S.market_currency <> ''
        WHERE sw.warehouse_id = :warehouse_id $where_supplier_id $where_states $where_search
        LIMIT :limit";
        $items = (new SafeMysql())->fetchAll($sql, $params);
        if (!$items)
            return [];
        $result = [];
        foreach ($items as $item)
            $result[(int)$item['id']] = ['id' => (int)$item['id'], 'name' => $item['name'], 'customer_ref' => $item['customer_ref'], 'state' => (int)$item['state'], 'price' => (float)$item['price'], 'market_currency' => $item['market_currency'], 'currency_id' => (int)$item['currency_id'], 's_name' => $item['s_name'], 'supplier_id' => (int)$item['supplier_id'], 'photo' => $item['photo'], 'barcodes' => []];
        foreach (SKUBarcodes::getBarcodesBySKUids(array_keys($result)) as $SKU_id => $barcodes)
            $result[$SKU_id]['barcodes'] = $barcodes;
        return $result;
    }

    public static function changeMaxCapacitySizes(?SKU $SKU, array $sizes)
    {
        if (!$SKU || !$sizes || !is_array($sizes))
            return;
        $V = $SKU->getWidth()*$SKU->getHeight()*$SKU->getDepth();
        $max_capacity = $SKU->getMaxCapacity();
        foreach ($sizes as $size_id => $size)
            $max_capacity[$size_id] = (int)($size[1]/$V);
        $SKU->setMaxCapacity($max_capacity);
    }

    public static function changeMaxCapacity(?SKU $SKU, int $warehouse_id)
    {
        self::changeMaxCapacitySizes($SKU, Sizes::getAllSizesName($warehouse_id));
    }

    public static function getSKUsIdsByFilters(int $warehouse_id, array $filters): ?array
    {
        if (empty($filters))
            return [];
        $filters_SKU_ids = $filters_SKU_ids_intersect = [];
        if (!empty($filters['available']))
            $filters_SKU_ids[] = array_merge(SKULocations::getAvailableSKUsByFilters($warehouse_id, $filters), SKULocations::getAvailableBundleSKUsByFilters($warehouse_id, $filters));
        if (!empty($filters['allocated']))
            $filters_SKU_ids[] = array_merge(SKULocations::getAllocatedSKUsByFilters($warehouse_id, $filters), SKULocations::getFreeButNotAvailableSKUsByFilters($warehouse_id, $filters));
        if (!empty($filters['backorder_replenish']))
            $filters_SKU_ids[] = SKULocations::getBackorderReplenishSKUsByFilters($warehouse_id, $filters);

        if ($filters_SKU_ids)
            $filters_SKU_ids_intersect = count($filters_SKU_ids) === 1 ? $filters_SKU_ids[0] : array_values(array_intersect(...$filters_SKU_ids));
        if ($filters_SKU_ids && !$filters_SKU_ids_intersect)
            return null;
        return $filters_SKU_ids_intersect;
    }

    public static function getDifference(SKU $original_SKU, SKU $old_SKU): array
    {
        $fields = [
            'photo' => 'Photo',
            'width' => 'Width (cm)',
            'height' => 'Height (cm)',
            'depth' => 'Depth (cm)',
            'weight' => 'Weight (kg)'
        ];
        $original_SKU_data = $original_SKU->toArray();
        $old_SKU_data = $old_SKU->toArray();
        $diff = [];
        $has_diff_old_data = false;
        $has_diff_new_data = false;

        foreach ($fields as $field => $title) {
            if ($old_SKU_data[$field] !== $original_SKU_data[$field]) {
                $diff[$field] = [
                    'title' => $title,
                    'value' => [
                        'old' => $old_SKU_data[$field],
                        'new' => $original_SKU_data[$field]
                    ]
                ];

                if ($field === 'photo') {
                    $diff[$field] += [
                        'img' => [
                            'old' => $old_SKU_data[$field] ? SKU::showPhotoSKU(urlencode($old_SKU_data[$field])) : '',
                            'new' => $original_SKU_data[$field] ? SKU::showPhotoSKU(urlencode($original_SKU_data[$field])): '',
                        ]
                    ];
                }

                if ($old_SKU_data[$field]) {
                    $has_diff_old_data = true;
                }

                if ($original_SKU_data[$field]) {
                    $has_diff_new_data = true;
                }
            }
        }

        return $has_diff_old_data && $has_diff_new_data ? $diff : [];
    }
}
