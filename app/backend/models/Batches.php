<?php

namespace app\backend\models;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\Batch;
use app\core\entities\FileJob;
use app\core\entities\Location;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\entities\UserJob;
use app\core\exceptions\BatchException;
use app\core\helpers\Filter;
use app\core\helpers\formatters\FormatterCSV;
use app\core\interfaces\ReportCSV;
use app\core\models\BarCode128;
use app\core\traits\ReportCSV as TraitReportCSV;
use app\core\helpers\FPDFExtended;

require_once (ROOT_PATH . VENDOR_PATH . '/FPDF/fpdf.php');

class Batches extends \app\core\models\Batches implements ReportCSV
{
    use TraitReportCSV;

    private const REPORT_FOLDER_NAME = 'expired_batches_reports';
    private const REPORT_NAME = 'Fulfilment_Expired_batches';

    static $csv_headline = [];

    public static function getAllAsCSV($warehouse_id, array $filters, User $user): void
    {
        $csv_headline = self::handleHeadlineCSV($user->getId());
        $loc_exp = Locations::getByExpiredLocation($warehouse_id);
        if (empty($loc_exp))
            return;
        $query = self::formatReportQuery($warehouse_id, $filters, $loc_exp[0])
            ->setLimit('10000')
            ->setOrderBy("s.id, b.id, sl.location_id");
        $csv = new FormatterCSV;
        $csv->addHeader($csv_headline);
        $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        SKUBarcodes::addBarcodesToArray($raw_result);
        while (!empty($raw_result)) {
            self::handleDataToCSV($raw_result, $csv, $warehouse_id);
            $last_id = (end($raw_result))['item_id'];
            $query->addWhere("CONCAT(LPAD(s.id, 11, '0'), LPAD(b.id, 11, '0'), LPAD(sl.location_id, 11, '0')) > '$last_id'");
            $raw_result = (new SafeMysql())->fetchAll($query->build(), $filters);
        }
    }

    public static function getSKUExpired($warehouse_id, $filters, $current): array
    {
        $warehouse_id = (int)$warehouse_id;
        $loc_exp = Locations::getByExpiredLocation($warehouse_id);
        if (empty($loc_exp))
            return [false, 0];
        $query = self::formatReportQuery($warehouse_id, $filters, $loc_exp[0]);
        $query->setOrderBy("s.id, b.id, sl.location_id");
        $query->setLimit(RECORD_PAGE . " OFFSET " . (RECORD_PAGE * ($current - 1)));
        $res = (new SafeMysql())->fetchAll($query->build(), $filters);
        $resNum = (new SafeMysql())->fetchOne('SELECT FOUND_ROWS() count');
        SKUBarcodes::addBarcodesToArray($res);
        return [$res, $resNum['count']];
    }

    public static function formatReportQuery($warehouse_id, $filters, Location $loc_exp): QueryBuilder
    {
        $where_barcode = '';
        if (!empty($filters['barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode(trim($filters['barcode']), Filter::getFilterTypeFromRequest($filters, 'barcode'));
            $where_barcode = $SKU_ids ? "AND s.id IN (" . implode(', ', $SKU_ids) . ")" : "AND FALSE";
        }
        $whereSId = '';
        if (!empty($filters['supplier_id']))
            $whereSId = 'AND sup.id ' . (is_array($filters['supplier_id']) ? ('IN (' . implode(', ', array_map('intval', $filters['supplier_id'])) . ')')
                                                                           : ('= ' . (int)$filters['supplier_id']));
        $num_days = isset($filters['days']) ? (int)$filters['days'] : Batch::MIN_EXPIRED;
        $query = new QueryBuilder();
        $now_date = date('Y-m-d', time());
        $query->setSelectFrom("
            SELECT SQL_CALC_FOUND_ROWS s.id,
                   s.name, s.state,
                   sup.id sid, sup.name sname,
                   b.id batch_id, b.id item_id, b.number, b.expired,
                   DATE_ADD(b.expired, INTERVAL (IF(sup.has_weeks_before_expiry = 1, -sup.weeks_before_expiry, 0)) WEEK) <= '$now_date' danger,
                   CONCAT(IF(w.label = '' OR w.label IS NULL, l.warehouse, w.label),'-',l.type_id,'-',l.zone,'-',l.`column`,'-',l.row) loc_name,
                   SUM(sl.quantity) quantity,
                   TO_DAYS(expired) - TO_DAYS(NOW()) days,
                   CONCAT(LPAD(s.id, 11, '0'), LPAD(b.id, 11, '0'), LPAD(sl.location_id, 11, '0')) item_id
            FROM batches b");
        $query->addJoin("JOIN SKU_locations sl ON b.id = sl.batch_id");
        $query->addJoin("JOIN locations l ON sl.location_id = l.id AND
                                                    l.warehouse = $warehouse_id AND
                                                    l.type_id <> '" . Location::DH . "' AND l.id <> {$loc_exp->getId()}");
        $query->addJoin("JOIN warehouses w ON l.warehouse = w.id");
        $query->addJoin("JOIN SKUs s ON sl.SKU_id = s.id $where_barcode");
        $query->addJoin("JOIN suppliers sup ON s.supplier_id = sup.id $whereSId");
        $query->addWhere("TO_DAYS(expired) - TO_DAYS(NOW()) <= $num_days");
        if (!empty($filters['max_item_id'])) {
            $max_item_id = preg_replace('/\D/', '', (string)$filters['max_item_id']);
            $query->addWhere("CONCAT(LPAD(s.id, 11, '0'), LPAD(b.id, 11, '0'), LPAD(sl.location_id, 11, '0')) > '$max_item_id'");
        }
        $query->setGroupBy("s.id, b.id, sl.location_id");
        return $query;
    }

    private static function getHeadlineCSV(): array
    {
        return ['id', 'name', 'barcode', 'batch', 'expired', 'location', 'quantity', 'status'];
    }

    public static function handleHeadlineCSV($user_id): array
    {
        self::$csv_headline = self::getHeadlineCSV();
        $user = Users::getById($user_id);
        if (!empty($user) && !$user->isRoleSupplierUser() && !in_array('account', self::$csv_headline))
            array_splice(self::$csv_headline, 3, 0, 'account');
        return self::$csv_headline;
    }

    public static function getTotalCountReportCSV(array $filters): int
    {
        $warehouse_id = $filters['warehouse_id'];
        unset($filters['warehouse_id']);
        return (int)self::getSKUExpired($warehouse_id, $filters, 1)[1] ?? 0;
    }

    /** @throws \Exception */
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
        $loc_exp = Locations::getByExpiredLocation($addition['addition']);
        if (empty($loc_exp))
            throw new \Exception('Location expired for report CSV for user job #' . $user_job_id);
        $query = self::formatReportQuery($addition['addition'], (!empty($max_item_id) ? ['max_item_id' => $max_item_id] : []) + ($addition['filters'] ?? []), $loc_exp[0])
                    ->setOrderBy("s.id, b.id, sl.location_id")
                    ->setLimit(self::LIMIT_GENERATE_REPORT + 1);
        $raw_result = (new SafeMysql())->fetchAll($query->build());
        $next = false;
        if (!empty($raw_result))
            $next = self::processStep($raw_result, $csv, $userJob);
        if (!$next || empty($raw_result)) {
            self::transferToCloud($fileJob);
            self::doneUserJob($userJob, $fileJob);
        }
        return ['success' => 'Handled part of Expired batches report CSV for user job #' . $user_job_id];
    }

    public static function handleDataToCSV(array $raw_result, FormatterCSV $csv, $addition = null)
    {
        foreach ($raw_result as $row) {
            $res = [];
            foreach (self::$csv_headline as $title) {
                $value = $row[$title] ?? '';
                if ($title === 'account')
                    $value = $row['sname'] . ' (#' . $row['sid'] . ')';
                elseif ($title === 'batch')
                    $value = $row['number'];
                elseif ($title === 'expired')
                    $value = date('jS M Y', strtotime($row['expired']));
                elseif ($title === 'location')
                    $value = $row['loc_name'];
                elseif ($title === 'status')
                    $value = SKU::getStateName($row['state']);
                $res[$title] = $value;
            }
            $csv->addRow($res);
        }
    }

    public static function getExpiredGoodsOut($warehouse_id, $filters, $current): array
    {
        $warehouse_id = (int)$warehouse_id;
        /** @var array $loc_exp */
        $loc_exp = Locations::getByExpiredLocation($warehouse_id);
        if (empty($loc_exp))
            return [false, 0];
        $loc_exp = $loc_exp[0];
        $where_barcode = $where_batch = '';
        $filter_params = [
            'location_id' => $loc_exp->getId(),
            'SKU_id' => $filters['id']
        ];

        if (!empty($filters['barcode'])) {
            $SKU_ids = SKUBarcodes::getSKUIdsByBarcode(trim($filters['barcode']), Filter::getFilterTypeFromRequest($filters, 'barcode'));
            $where_barcode = $SKU_ids ? "AND s.id IN (:SKU_ids)" : "AND FALSE";
            $filter_params['SKU_ids'] = $SKU_ids;
        }
        $where_sid = '';
        if (!empty($filters['supplier_id'])) {
            $where_sid = 'AND sup.id ' . (is_array($filters['supplier_id']) ? ('IN (:supplier_id)') : ('= :supplier_id'));
            $filter_params['supplier_id'] = $filters['supplier_id'];
        }

        if (!empty($filters['batch'])) {
            $where_batch = "AND b.number LIKE :batch";
            $filter_params['batch'] = '%'. $filters['batch'] . '%';
        }

        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT SQL_CALC_FOUND_ROWS sl.SKU_id id,
                   s.name,
                   sup.id supplier_id,
                   b.id batch_id, b.number, b.expired, sl.quantity
            FROM SKU_locations sl");
        $query->addJoin("JOIN SKUs s ON sl.SKU_id = s.id $where_barcode");
        $query->addJoin("JOIN batches b ON sl.batch_id = b.id AND sl.SKU_id = b.SKU_id $where_batch");
        $query->addJoin("JOIN suppliers sup ON s.supplier_id = sup.id $where_sid");
        $query->addWhere("sl.location_id = :location_id");
        $query->addWhere('sl.type = 4');
        $query->addWhere("sl.reason = ''");
        $query->addWhere('sl.order_id IS NULL');
        if (!empty($filters['id']))
            $query->addWhere('sl.SKU_id = :SKU_id');
        $query->setOrderBy("sl.SKU_id, b.expired");
        $query->setLimit(RECORD_PAGE . " OFFSET " . (RECORD_PAGE * ($current - 1)));
        $res = (new SafeMysql())->fetchAll($query->build(), $filter_params);
        $resNum = (new SafeMysql())->fetchOne('SELECT FOUND_ROWS() count');
        SKUBarcodes::addBarcodesToArray($res);
        return [$res, $resNum['count']];
    }

    public static function createPdfManifest($SKU_id, $batch_id, $loc_exp_id)
    {
        $SKU = SKULocations::getExpiredSKUInfoInLocation($loc_exp_id, $SKU_id, $batch_id);
        if (empty($SKU))
            throw new BatchException('SKU is not found in EX-PI-RED location');
        $pdf = new FPDFExtended('P', 'pt', 'A4');
        $pdf->SetTitle("SKU #$SKU_id");
        $W = $pdf->GetPageWidth() - 55;
        $h = 12;
        $pdf->AddPage();
        $pdf->SetFont('Courier', 'B', 12);
        $pdf->Cell($W*0.60,$h,'SKU',1);
        $pdf->Cell($W*0.28,$h,'Barcode',1);
        $pdf->Cell($W*0.12,$h,'Quantity',1);
        $pdf->Ln();
        $pdf->SetFont('Courier', '', 12);
        $pdf->SetTopMargin(25);
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->SetXY($x, $y);
        $barcode = new BarCode128($SKU['barcode'],100, 0, ROOT_PATH . COMMON_FONT . '/consola.ttf', 9);
        $bW = min($barcode->Width(),$W*0.28);
        $title = "{$SKU['name']} (#$SKU_id)\nBatch No. {$SKU['number']}\nExpired " . (new \DateTime($SKU['expired']))->format('Y-m-d');
        $maxY = max([
            $pdf->GetMultiCellHeight($W*0.60,$h,$title),
            ($bW * $barcode->Height()) / $barcode->Width(),
            $pdf->GetMultiCellHeight($W*0.12,$h,$SKU['quantity'])
        ]);
        $pdf->MultiCell($W*0.60,$h,$title,0, 'L');
        $pdf->SetXY($x + $W*0.60, $y);
        $pdf->Image($barcode->getImage(), $x + $W*0.60, $y, $bW, 0, 'PNG');
        $pdf->SetXY($x + $W*0.88, $y);
        $pdf->MultiCell($W*0.12,$h,$SKU['quantity'],0, 'C');
        $pdf->SetXY($x, $y);
        $pdf->Cell($W*0.60,$maxY,'',1);
        $pdf->Cell($W*0.28,$maxY,'',1);
        $pdf->Cell($W*0.12,$maxY,'',1);
        $pdf->Output();
    }
}