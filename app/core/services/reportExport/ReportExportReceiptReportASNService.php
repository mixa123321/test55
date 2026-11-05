<?php

namespace app\core\services\reportExport;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\entities\ASN;
use app\core\entities\Warehouse;
use app\core\models\Warehouses;
use app\core\models\SKUBarcodes;
use app\core\statical\Timezones;

class ReportExportReceiptReportASNService extends ReportExportService
{
    public function __construct($supplier_id, $warehouse_id)
    {
        parent::__construct($supplier_id, $warehouse_id);
        $this->setId($this->fields[0]);
        $this->makeFile("{$this->getReportName()}ASN_" . date("Y-m-d-His"));
    }

    public function getMethod()
    {
        return 'ReportExportReceiptReportService';
    }

    public function setFields()
    {
        $this->fields = [
            'ASN ID',
            'ASN Reference',
            'Expected Date',
            'Arrived Date',
            'Container',
            'SKU Code',
            'Customer Ref',
            'Barcode',
            'Batches',
            'Expected SKUs',
            'Accepted SKUs',
            'Status',
            'Client',
            'Charge',
            'Charge Currency',
        ];
    }

    public function getData($last_id): array
    {
        $params = [
            'warehouse_id' => $this->warehouse['id'],
            'supplier_id' => $this->sftp->getSupplierId(),
            'last_id' => $last_id
        ];
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT a.id, a.ref, a.arrival_date, a.arrived_date, a.container, a.status, sup.name as supplier_name,
                   SUM(ask.quantity) as SKU_total,
                   SUM(ask.accepted_quantity) as SKU_accepted_quantity,
                   GROUP_CONCAT(DISTINCT ask.batch_number ORDER BY ask.batch_number SEPARATOR ',') as SKU_batch_list,
                   a.billing_charge_pallet + a.billing_charge_rework charge, 
                   a.billing_charge_currency,
                   s.id SKU_id, s.customer_ref
            FROM ASNs a");
        $query->addJoin("JOIN suppliers sup ON a.supplier_id = sup.id");
        $query->addJoin("JOIN ASN_SKUs ask ON ask.ASN_id = a.id");
        $query->addJoin("JOIN SKUs s ON ask.SKU_id = s.id");
        $query->addWhere("(a.order_id IS NULL OR a.order_id = 0)");
        $query->addWhere("a.warehouse_id = :warehouse_id");
        $query->addWhere("a.supplier_id = :supplier_id");
        if (!empty($this->sftp_report->getAdditional()['date_from'])) {
            $query->addWhere("a.arrival_date >= :arrival_date");
            $params['arrival_date'] = $this->sftp_report->getAdditional()['date_from'];
        }
        $query->addWhere("a.id > :last_id");
        $query->setGroupBy('a.id, s.id');
        $query->setOrderBy('a.id, s.id');
        $query->setLimit('10000');
        $ASNs = (new SafeMysql())->fetchAll($query->build(), $params);
        if (empty($ASNs))
            return [];
        SKUBarcodes::addBarcodesToArray($ASNs, 'SKU_id');
        $warehouse = Warehouses::getById($this->warehouse['id']);
        $time_diff = $warehouse instanceof Warehouse ? Timezones::getDiff($warehouse->getTimezone()) : 0;
        $data = [];
        foreach ($ASNs as $ASN_data) {
            $data[] = [
                'ASN ID' => $ASN_data['id'],
                'ASN Reference' => $ASN_data['ref'],
                'Expected Date' => $ASN_data['arrival_date'],
                'Arrived Date' => !empty($row['arrived_date'])
                    ? date('Y-m-d', strtotime(empty($time_diff)
                                                        ? $ASN_data['arrived_date']
                                                        : Timezones::getDiff($warehouse->getTimezone(), $ASN_data['arrived_date'])))
                    : '',
                'Container' => $ASN_data['container'],
                'SKU Code' => $ASN_data['SKU_id'],
                'Customer Ref' => $ASN_data['customer_ref'],
                'Barcode' => $ASN_data['barcode'],
                'Batches' => $ASN_data['SKU_batch_list'],
                'Expected SKUs' => $ASN_data['SKU_total'],
                'Accepted SKUs' => $ASN_data['SKU_accepted_quantity'] ?: '',
                'Status' => ASN::getStateName($ASN_data['status']),
                'Client' => $ASN_data['supplier_name'],
                'Charge' => $ASN_data['charge'],
                'Charge Currency' => $ASN_data['billing_charge_currency'],
            ];
        }
        return $data;
    }
}