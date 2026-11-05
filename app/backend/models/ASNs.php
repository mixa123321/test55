<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\db\QueryBuilder;
use app\core\entities\ASN;
use app\core\entities\FileJob;
use app\core\entities\ASNSKU;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\entities\UserJob;
use app\core\entities\Warehouse;
use app\core\helpers\Arr;
use app\core\helpers\Filter;
use app\core\helpers\formatters\FormatterCSV;
use app\core\helpers\Request;
use app\core\helpers\Validator;
use app\core\models\Warehouses;
use app\core\interfaces\ReportCSV as InterfaceReportCSV;
use app\core\statical\Timezones;
use app\core\traits\ReportCSV as TraitReportCSV;

class ASNs extends \app\core\models\ASNs implements InterfaceReportCSV
{
    use TraitReportCSV;

    private const REPORT_FOLDER_NAME = 'ASN_reports';
    private const REPORT_NAME = 'Fulfilment_ASN_list';

    /**
     * @param $ASN ASN
     * @return array
     */
    public static function validate(ASN $ASN)
    {
        $rules = [
            'supplier_id' => [
                'val' => $ASN->getSupplierId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'unscheduled' => [
                'val' => $ASN->getUnscheduled(),
                'rules' => [Validator::TYPE_INT => [0, 2]]
            ],
            'arrival_date' => [
                'val' => $ASN->getArrivalDate(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_DATE => 1]
            ],
            'user_id' => [
                'val' => $ASN->getUserId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [0, PHP_INT_MAX]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        if (!empty($ASN->getId())) {
            $ASN_by_id = ASNs::getById($ASN->getId());
            if ($ASN_by_id === null) {
                $validator->addError('ASN_not_found', 'ASN not found');
            }
        }
        return $validator->getErrors();
    }

    public static function getNonComplete($warehouse_id, $supplier_id)
    {
        $sql = "SELECT a.*, ah.create_date
                FROM ASNs a
                    LEFT JOIN ASN_history ah ON a.id = ah.ASN_id AND ah.`comment` LIKE 'ASN created%'
                WHERE a.warehouse_id = :warehouse_id AND a.supplier_id = :supplier_id AND a.status IN (:statuses)";
        return (new SafeMysql())->fetchAll($sql, [
            'warehouse_id' => $warehouse_id,
            'supplier_id' => $supplier_id,
            'statuses' => [ASN::NEW, ASN::PUTAWAY]
        ]);
    }

    public static function getAll($filters)
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT SQL_CALC_FOUND_ROWS 
                   a.id, a.ref, a.arrival_date, a.arrived_date, a.container, a.status, a.user_id, u.login, s.name as supplier_name, sk.id SKU_id, s.id supplier_id,
                   COUNT(sk.id) as sku_count, 
                   SUM(ask.quantity) as sku_total,
                   SUM(IF(ask.accepted_quantity IS NULL OR ask.accepted_quantity = 0, 0, 1)) as sku_accepted_count,
                   SUM(ask.accepted_quantity) as sku_accepted_quantity,
                   GROUP_CONCAT(DISTINCT ask.batch_number ORDER BY ask.batch_number SEPARATOR ', ') as sku_batch_list,
                   GROUP_CONCAT(sk.id) as sku_id_list,
                   a.billing_charge_pallet + a.billing_charge_rework charge,
                   a.billing_charge_currency, a.ship_to_location
            FROM ASNs a");
        $query->addJoin("JOIN suppliers s ON a.supplier_id = s.id");
        $query->addJoin("JOIN users u ON a.user_id = u.id");
        $query->addJoin("JOIN ASN_SKUs ask ON ask.ASN_id = a.id");
        $query->addJoin("JOIN SKUs sk ON ask.SKU_id = sk.id");
        self::applySearchFilters($query, $filters);
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setGroupBy("a.id");
        $query->setOrderBy("a.id DESC");
        $query->setLimit("{$offset}, " . self::LIMIT_PER_PAGE);
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $filters);
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        SKUBarcodes::addBarcodesToArray($records_raw, 'SKU_id');
        return ['records' => $records_raw, 'total' => $total, 'current_page' => $current_page];
    }

    public static function getAllNCR($filters)
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT a.id, a.ref, a.arrival_date, a.arrived_date, a.container, a.status, a.user_id, u.login, s.name as supplier_name, s.id supplier_id,
                   COUNT(sk.id) as sku_count, SUM(ask.quantity) as sku_total, GROUP_CONCAT(sk.id) as sku_id_list,
                   a.billing_charge_pallet + a.billing_charge_rework charge, a.billing_charge_currency, a.ship_to_location
            FROM ASNs a");
        $query->addJoin("JOIN suppliers s ON a.supplier_id = s.id");
        $query->addJoin("JOIN users u ON a.user_id = u.id");
        $query->addJoin("JOIN ASN_SKUs ask ON ask.ASN_id = a.id");
        $query->addJoin("JOIN SKUs sk ON ask.SKU_id = sk.id");
        self::applySearchFilters($query, $filters);
        $query->setGroupBy("a.id");
        $query->setOrderBy("a.id DESC");
        $records_raw = (new SafeMysql())->fetchAll($query->build(), $filters);
        return ['records' => $records_raw];
    }

    public static function getData4Billing($date_complete, $supplier_id, $warehouse_id)
    {
        $date_complete = date('Y-m-d', $date_complete);
        $supplier_id = (int) $supplier_id;
        $sql = "SELECT a.id,
                       a.ref,
                       a.completed_date,
                       a.container,
                       a.services,
                       a.billing_description_pallet, a.billing_charge_pallet, a.billing_description_rework, a.billing_charge_rework, a.billing_charge_currency,
                       a.status,
                       a.order_id,
                       COUNT(ask.accepted_quantity) accepted_num
                FROM ASNs a
                    JOIN ASN_SKUs ask ON a.id = ask.ASN_id AND ask.accepted_quantity > 0
                WHERE a.status > " . ASN::PUTAWAY . " AND
                      a.supplier_id = :supplier_id AND 
                      a.warehouse_id = :warehouse_id AND
                      DATE_FORMAT(a.completed_date, '%Y-%m-%d') = :date_complete
                GROUP BY a.id
                HAVING accepted_num > 0
                ORDER BY a.completed_date, a.id";
        return (new SafeMysql())->fetchAll($sql, [
            'supplier_id' => $supplier_id,
            'warehouse_id' => $warehouse_id,
            'date_complete' => $date_complete,
        ]);
    }

    public static function getAllAsCSV(array $filters)
    {
        $query = self::formatReportQuery($filters);
        $query->setGroupBy("a.id");
        $query->setOrderBy("a.id DESC");
        $query->setLimit('10000');
        $csv = new FormatterCSV;
        $csv->addHeader(self::handleHeadlineCSV(null));
        $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        while (!empty($raw_result)) {
            self::handleDataToCSV($raw_result, $csv, $filters['warehouse_id'] ?? null);
            $last_asn_id = intval(end($raw_result)['id']);
            $query->addWhere('a.id < ' . $last_asn_id);
            $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        }
    }

    /**
     * @throws \Exception
     */
    private static function applySearchFilters(QueryBuilder $query, array $filters): void
    {
        $query->addWhere("(a.order_id IS NULL OR a.order_id = 0)");
        $warehouse = null;
        if (!empty($filters['warehouse_id'])) {
            $warehouse = Warehouses::getById($filters['warehouse_id']);
            $query->addWhere("a.warehouse_id = :warehouse_id");
        }
        if (!empty($filters['supplier_id'])) {
            if (is_array($filters['supplier_id']))
                $query->addWhere('s.id IN (:supplier_id)');
            else
                $query->addWhere("s.id = :supplier_id ");
        }
        if (!empty($filters['date_expected_from']))
            $query->addWhere("a.arrival_date >= :date_expected_from");
        if (!empty($filters['date_expected_to']))
            $query->addWhere("a.arrival_date <= :date_expected_to");
        $time_diff = $warehouse instanceof Warehouse ? Timezones::getDiff($warehouse->getTimezone()) : 0;
        $date_format = '%Y-%m-%d %H:%i:%s';
        if (!empty($filters['date_arrived_from'])) {
            $date_from = $filters['date_arrived_from'] . ' 00:00:00';
            $date_from = empty($time_diff) ? $date_from : Timezones::getDiff($warehouse->getTimezone(), $date_from, TRUE);
            $query->addWhere("DATE_FORMAT(a.arrived_date, '{$date_format}') >= '" . addslashes($date_from) . "'");
        }
        if (!empty($filters['date_arrived_to'])) {
            $date_to = $filters['date_arrived_to'] . ' 23:59:59';
            $date_to = empty($time_diff) ? $date_to : Timezones::getDiff($warehouse->getTimezone(), $date_to, TRUE);
            $query->addWhere("DATE_FORMAT(a.arrived_date, '{$date_format}') <= '" . addslashes($date_to) . "'");
        }
        if (isset($filters['status']))
            if ($filters['status'] !== 'all')
                $query->addWhere("a.status = :status");
        //schedule can be "on_time" or "late"
        if (!empty($filters['schedule'])) {
            $late_arrival = "CONCAT(DATE(a.arrival_date), '" . ASN::ARRIVAL_END_DAY . "')";
            switch ($filters['schedule']) {
                case 'late':
                    $query->addWhere("(DATE(a.arrived_date) > $late_arrival 
                                            OR (a.arrived_date IS NULL AND $late_arrival <= date(NOW())))");
                    break;
                case 'on_time':
                    $query->addWhere("(DATE(a.arrived_date) <= $late_arrival 
                                            OR (a.arrived_date IS NULL AND $late_arrival > date(NOW())))");
            }
        }
        if (!empty($filters['ref']))
            $query->addWhere("a.ref = :ref");
        if (!empty($filters['id']))
            $query->addWhere("a.id = :id");
        if (!empty($filters['sku_barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode(trim($filters['sku_barcode']), Filter::getFilterTypeFromRequest($filters, 'sku_barcode'));
            $query->addWhere($SKU_ids ? "sk.id IN (" . implode(', ', $SKU_ids) . ")" : "FALSE");
        }
        if (!empty($filters['batch_number']))
            $query->addWhere("ask.batch_number = :batch_number");
        if (!empty($filters['number_of_units'])) {
            $query->addHaving("SUM(ask.accepted_quantity) >= :number_of_units");
        }
        if (!empty($filters['number_of_skus'])) {
            $query->addHaving("sku_accepted_count >= :number_of_skus");
            $query->addWhere('ask.accepted_quantity IS NOT NULL');
        }
        if (!empty($filters['max_item_id']))
            $query->addWhere("a.id < :max_item_id");
    }

    public static function updateAdditional(ASN $ASN, $ref, $carrier, $tracking_number, $warehouse_virtual, $user_id)
    {
        if (empty($ASN))
            return 'ASN is not found';
        $ASN_data = [];
        $save_fields = ['id'];
        if ($ASN->getRef() != $ref) {
            $ASN_data[] = "reference from '{$ASN->getRef()}' to '$ref'";
            $ASN->setRef(addslashes($ref));
            $save_fields[] = 'ref';
        }
        if ($ASN->getCarrier() != $carrier) {
            $ASN_data[] = "carrier from '{$ASN->getCarrier()}' to '$carrier'";
            $ASN->setCarrier(addslashes($carrier));
            $save_fields[] = 'carrier';
        }
        if ($ASN->getTrackingNumber() != $tracking_number) {
            $ASN_data[] = "tracking number from '{$ASN->getTrackingNumber()}' to '$tracking_number'";
            $ASN->setTrackingNumber(addslashes($tracking_number));
            $save_fields[] = 'tracking_number';
        }
        if (count($save_fields) == 1)
            return 'Information is not changed';
        $ASN->save($save_fields);
        return ASNHistories::updateASN($ASN->getId(), 'Update: ' . implode('; ', $ASN_data), $user_id);
    }

    public static function updateContainer(ASN $ASN, $container, $user_id)
    {
        if (empty($ASN))
            return false;
        $ASN->setContainer(addslashes($container));
        $ASN->save(['id', 'container']);
        if (empty($container))
            $container = 'Loose';
        return ASNHistories::updateASN($ASN->getId(), "Update container to '$container'", $user_id);
    }

    public static function updateLocation(ASN $RASN, $location, $user_id)
    {
        if (empty($RASN))
            return false;
        $RASN->setShipToLocation(addslashes($location));
        $RASN->save(['id', 'ship_to_location']);
        return ASNHistories::updateASN($RASN->getId(), "Update location to '$location' (Return ASN)", $user_id);
    }

    public static function updateArrival(ASN $ASN, $arrivalDate, $warehouse_virtual, $user_id)
    {
        if (empty($ASN))
            return false;
        $ASN->setArrivalDate($arrivalDate);
        $ASN->save(['id', 'arrival_date']);
        if ($warehouse_virtual)
            try {
                $processor = Warehouses::getVirtualProcessor($ASN->getWarehouseId(), $ASN->getSupplierId());
                if (empty($processor))
                    return 'Supplier is not found in warehouse';
                $processor->updateASN($ASN->getRef(), $ASN->getArrivalDate());
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        return ASNHistories::updateASN($ASN->getId(), "Update arrival date to '{$ASN->getArrivalDate()}'", $user_id);
    }

    public static function isAccess(ASN $ASN, User $user)
    {
        if (empty($ASN))
            return false;
        if ($user->isRoleSA())
            return true;
        if ($user->hasWorkerAccess())
            return $ASN->getWarehouseId() == $user->getWarehouseId();
        $suppliers = array_keys(Suppliers::getSuppliersByUsers([$user->getId()]));
        if (empty($suppliers))
            return false;
        return $ASN->getWarehouseId() == $user->getWarehouseId() && in_array($ASN->getSupplierId(), $suppliers);
    }

    public static function createUnexpected($warehouse_id, $warehouse_virtual, SKU $SKU, $qty, $batch_number, User $user)
    {
        $now = time();
        list($done, $ASN_id) = self::addASN([
            'user_id' => $user->getId(),
            'supplier_id' => $SKU->getSupplierId(),
            'arrival_date' => $now,
            'arrived_date' => $now,
            'is_return' => 0,
            'unscheduled' => 2,
            'comment' => 'Created automatically',
            'status' => ASN::PUTAWAY,
            'warehouse_id' => $warehouse_id,
            'ref' => 'unexpected_asn_' . $now,
            'container' => '',
        ], [$SKU->getId() => ['quantity' => $qty, 'batch_number' => $batch_number]], [], $warehouse_virtual, $user->getId());
        if ($done)
            return $ASN_id;
        return 0;
    }

    public static function resolveNonCompliant(ASN $ASN, $user_id)
    {
        $ASN->setStatus(ASN::COMPLETED);
        $ASN->save(['id', 'status']);
        return ASNHistories::commentASN($ASN->getId(), $user_id, 'Non-compliant status resolved');
    }

    public static function arrived(ASN $ASN, $user_id, $container = null)
    {
        $save_fields = ['id', 'arrived_date', 'status'];
        $ASN->setStatus(ASN::ARRIVED);
        $ASN->setArrivedDate(time());
        if (!is_null($container)) {
            $ASN->setContainer($container);
            $save_fields[] = 'container';
        }
        $ASN->save($save_fields);
        return ASNHistories::arrivedASN($ASN, $user_id);
    }

    public static function cancel(ASN $ASN, $warehouse_virtual, $user_id, $to_virtual = true)
    {
        $ASN->setStatus(ASN::CANCELLED);
        $ASN->save(['id', 'status']);
        if ($warehouse_virtual && $to_virtual) {
            $processor = Warehouses::getVirtualProcessor($ASN->getWarehouseId(), $ASN->getSupplierId());
            if (empty($processor))
                return 'Supplier is not found for warehouse';
            try {
                $processor->cancelASN($ASN->getRef());
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }
        return ASNHistories::cancelASN($ASN->getId(), $user_id);
    }

    public static function cancelReturn(ASN $RASN, $user_id)
    {
        $RASN->setStatus(ASN::CANCELLED);
        $RASN->save(['id', 'status']);
        return ASNHistories::cancelRASN($RASN->getId(), $user_id);
    }

    public static function putaway(ASN $ASN, $user_id)
    {
        $ASN->setStatus(ASN::PUTAWAY);
        $ASN->save(['id', 'status']);
        return ASNHistories::putawayASN($ASN->getId(), $user_id);
    }

    public static function completed(ASN $ASN, $type, $user_id)
    {
        $completed = ASNHistories::completedASN($ASN, ASN::getStateName($type), $user_id);
        if (is_array($completed))
            return $completed;
        $ASN->setStatus($type);
        $ASN->setCompletedDate($completed);
        $ASN->save(['id', 'status', 'completed_date']);
        return true;
    }

    public static function revert(ASN $ASN, $user_id)
    {
        $mysql = new SafeMysql();
        $mysql->beginTransaction();
        $mysql->execute('UPDATE ASNs SET arrived_date = NULL, status = :status WHERE id = :id', [
            'status' => ASN::NEW,
            'id' => $ASN->getId(),
        ]);
        $errors = ASNHistories::revertASN($ASN, $user_id);
        if (is_array($errors) && !empty($errors)) {
            $mysql->rollback();
            return $errors;
        }
        $mysql->commit();
        return true;
    }

    public static function updated(ASN $ASN, $SKUs, $warehouse_virtual, $user_id)
    {
        $mysql = new SafeMysql();
        foreach ($SKUs as $SKU_id => $SKU_data) {
            $mysql->execute("UPDATE ASN_SKUs SET quantity = :quantity WHERE ASN_id = :ASN_id AND SKU_id = :SKU_id", [
                'quantity' => $SKU_data['new_quantity'],
                'ASN_id' => $ASN->getId(),
                'SKU_id' => $SKU_id,
            ]);
            $errors = ASNHistories::updateASN(
                $ASN->getId(),
                sprintf("Update quantity (%d => %d) for SKU '%s' (#%d)", $SKU_data['quantity'], $SKU_data['new_quantity'], $SKU_data['name'], $SKU_id),
                $user_id);
            if ($errors !== true)
                return Arr::column($errors, 'message');
        }
        if ($warehouse_virtual) {
            $processor = Warehouses::getVirtualProcessor($ASN->getWarehouseId(), $ASN->getSupplierId());
            if (empty($processor))
                return 'Supplier is not found for warehouse';
            try {
                return $processor->updateASNSKUs($ASN->getRef(), Arr::columnAssoc($SKUs, 'new_quantity'));
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }
        return true;
    }

    public static function receive($ASN_id, $SKU_id, $quantity, $user_id)
    {
        $ASN_id = (int)$ASN_id;
        $SKU_id = (int)$SKU_id;
        $quantity = (int)$quantity;
        (new SafeMysql())->execute("
            UPDATE ASN_SKUs
            SET accepted_quantity = IFNULL(accepted_quantity, 0) + :quantity
            WHERE ASN_id = :ASN_id AND SKU_id = :SKU_id", [
            'quantity' => $quantity,
            'ASN_id' => $ASN_id,
            'SKU_id' => $SKU_id,
        ]);
        return ASNHistories::putsASN($SKU_id, $ASN_id, $quantity, 1, $user_id);
    }

    public static function toComplete($ASN_id, $SKU_id, $new_quantity, $quantity, $user_id)
    {
        $ASN_id = (int)$ASN_id;
        $SKU_id = (int)$SKU_id;
        $new_quantity = (int)$new_quantity;
        $quantity = (int)$quantity;
        (new SafeMysql())->execute("
            UPDATE ASN_SKUs
            SET accepted_quantity = :new_quantity
            WHERE ASN_id = :ASN_id AND SKU_id = :SKU_id AND quantity = :quantity", [
            'new_quantity' => $new_quantity,
            'ASN_id' => $ASN_id,
            'SKU_id' => $SKU_id,
            'quantity' => $quantity,
        ]);
        return ASNHistories::toCompleteASN($ASN_id, $user_id, $SKU_id, $quantity, $new_quantity);
    }

    public static function addASN($ASN_data, $SKUs, $errors, $warehouse_virtual, $user_id)
    {
        $ASN_data['ref'] = trim($ASN_data['ref'] ?? '');
        if ($warehouse_virtual) {
            if (empty($ASN_data['ref']))
                return [false, [['message' => 'Reference is not found']]];
            if (ASNs::getByRef($ASN_data['ref'], $ASN_data['supplier_id'], $ASN_data['warehouse_id']) !== false)
                return [false, [['message' => 'Reference is not unique']]];
            if (strlen($ASN_data['ref']) > 50)
                $ASN_data['ref'] = substr($ASN_data['ref'], 0, 50);
            $locations = Warehouses::getLocations($ASN_data['warehouse_id']);
            if (empty($ASN_data['ship_to_location']) || empty($locations[$ASN_data['ship_to_location']]))
                return [false, [['message' => 'Location is empty or is not found']]];
        }
        $fields_to_save = array_keys($ASN_data);
        $ASN = new ASN($ASN_data);
        $errors = array_merge($errors, ASNs::validate($ASN));
        if (!empty($errors))
            return [false, $errors];
        try {
            $processor = null;
            if ($warehouse_virtual) {
                $processor = Warehouses::getVirtualProcessor($ASN->getWarehouseId(), $ASN->getSupplierId());
                if (empty($processor))
                    return [false, 'Supplier is not found for warehouse'];
            }
            $ASN->save($fields_to_save);
            foreach ($SKUs as $SKU_id => $SKU) {
                $ASNSKU = new ASNSKU();
                $ASNSKU->setASNId($ASN->getId());
                $ASNSKU->setSKUId($SKU_id);
                $ASNSKU->setQuantity($SKU['quantity']);
                if (!empty($ASN_data['is_return'])) {
                    $batch = Batches::getBatchBySKUAndNumber($SKU_id, $SKU['batch_number']);
                    if (!empty($batch))
                        $ASNSKU->setBatchId($batch['id']);
                } elseif (!empty($SKU['batch_number']))
                    $ASNSKU->setBatchNumber($SKU['batch_number']);
                $ASNSKU->save(['ASN_id', 'SKU_id', 'quantity', 'batch_number']);
                if ($processor)
                    if (!$processor->getSKU($SKU_id)) {
                        $SKU = SKUs::getById($SKU_id);
                        $processor->addSKU($SKU->toArray());
                    }
            }
            $create_date = ASNHistories::createASN($ASN->getId(), $user_id, ($ASN->getUserId() != $user_id ? $ASN->getUserId() : 0), !empty($ASN_data['is_return']) ? $ASN_data['supplier_id'] : 0);
            if (is_array($create_date))
                return [false, $create_date];
            if ($processor)
                $processor->createASN($ASN->toArray() + ['create_date' => $create_date], $SKUs);
        } catch (\Exception $e) {
            return [false, $e->getMessage()];
        }
        return [true, $ASN->getId()];
    }

    static function handleHeadlineCSV($user_id): array
    {
        unset($user_id);
        return [
            'ASN ID',
            'ASN Reference',
            'Expected Date',
            'Arrived Date',
            'Container',
            'Batches',
            'Expected SKUs',
            'Accepted SKUs',
            'Status',
            'Client',
            'Charge',
            'Charge Currency'
        ];
    }

    static function getTotalCountReportCSV(array $filters)
    {
        return (int)self::getAll($filters)['total'];
    }

    static function handleReportCSV($user_job_id, $max_item_id = 0): array
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
        $filters = (!empty($max_item_id) ? ['max_item_id' => $max_item_id] : []) + ($addition['filters'] ?? []);
        $query = self::formatReportQuery($filters);
        $query->setGroupBy("a.id");
        $query->setOrderBy("a.id DESC");
        $query->setLimit(self::LIMIT_GENERATE_REPORT + 1);
        $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        $next = false;
        if (!empty($raw_result))
            $next = self::processStep($raw_result, $csv, $userJob);
        if (!$next || empty($raw_result)) {
            self::transferToCloud($fileJob);
            self::doneUserJob($userJob, $fileJob);
        }
        return ['success' => 'Handled part of ASN report CSV for user job #' . $user_job_id];
    }

    static function handleDataToCSV(array $raw_result, FormatterCSV $csv, $addition = null)
    {
        $warehouse = Warehouses::getById($addition);
        $time_diff = $warehouse instanceof Warehouse ? Timezones::getDiff($warehouse->getTimezone()) : 0;
        foreach ($raw_result as $row) {
            $data = [
                'ASN ID' => $row['id'],
                'ASN Reference' => $row['ref'],
                'Arrival Date' => $row['arrival_date'],
                'Arrived Date' => !empty($row['arrived_date'])
                    ? date('Y-m-d', strtotime(empty($time_diff) ? $row['arrived_date'] : Timezones::getDiff($warehouse->getTimezone(), $row['arrived_date'])))
                    : '',
                'Container' => $row['container'],
                'Batches' => $row['sku_batch_list'],
                'Expected SKUs' => sprintf('%d / %d', $row['sku_count'], $row['sku_total']),
                'Accepted SKUs' => empty($row['sku_accepted_quantity']) ? '' : sprintf('%d / %d', $row['sku_accepted_count'], $row['sku_accepted_quantity']),
                'Status' => ASN::getStateName($row['status']),
                'Client' => $row['supplier_name'],
                'Charge' => $row['charge'],
                'Charge Currency' => $row['billing_charge_currency'],
            ];
            $csv->addRow($data);
        }
    }

    public static function formatReportQuery(array $filters): QueryBuilder
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT a.id as item_id, a.id, a.ref, a.arrival_date, a.arrived_date, a.container, a.status, s.name as supplier_name,
                   COUNT(sk.id) as sku_count,
                   SUM(ask.quantity) as sku_total,
                   COUNT(ask.SKU_id) as sku_accepted_count,
                   SUM(ask.accepted_quantity) as sku_accepted_quantity,
                   GROUP_CONCAT(DISTINCT ask.batch_number ORDER BY ask.batch_number SEPARATOR ',') as sku_batch_list,
                   a.billing_charge_pallet + a.billing_charge_rework charge, 
                   a.billing_charge_currency
            FROM ASNs a");
        $query->addJoin("JOIN suppliers s ON a.supplier_id = s.id");
        $query->addJoin("JOIN ASN_SKUs ask ON ask.ASN_id = a.id");
        $query->addJoin("JOIN SKUs sk ON ask.SKU_id = sk.id");
        self::applySearchFilters($query, $filters);
        return $query;
    }
}
