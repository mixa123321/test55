<?php

namespace app\core\services\reportExport;

use app\backend\models\Warehouses;
use app\core\dbal\SafeMysql;
use app\core\models\SKUBarcodes;
use app\core\db\QueryBuilder;
use app\core\entities\ASN;
use app\core\statical\Timezones;

class ReportExportReceiptReportReturnASNService extends ReportExportService
{
    public function __construct($supplier_id, $warehouse_id)
    {
        parent::__construct($supplier_id, $warehouse_id);
        $this->setId($this->fields[0]);
        $this->makeFile("{$this->getReportName()}ReturnASN_" . date("Y-m-d-His"));
    }

    public function getMethod()
    {
        return 'ReportExportReceiptReportService';
    }

    public function setFields()
    {
        $this->fields = [
            'ASN ID',
            'Client',
            'Order ID',
            'Reference',
            'Reference 2',
            'Contact Name',
            'Email',
            'Despatched Date',
            'Return Create Date',
            'Status',
            'Product Name',
            'Customer Ref',
            'Barcode',
            'Quantity',
            'Condition',
            'Reason',
            'Refund or Exchange',
            'Action Required',
            'CS Actioned'
        ];
    }

    public function getData($last_id): array
    {
        $params = [
            'warehouse_id' => $this->warehouse['id'],
            'supplier_id' => $this->sftp->getSupplierId(),
            'last_id' => $last_id,
        ];
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT a.id, a.order_id, a.status, a.actioned,
                   sup.name AS supplier_name,
                   o.ref, o.ref2, o.despatched_date, o.is_hash,
                   ood.delivery,
                   (SELECT create_date FROM ASN_history AS ah WHERE ah.ASN_id = a.id AND ah.comment LIKE 'ASN created%' LIMIT 1) AS create_date,
                   s.name, s.customer_ref, s.id SKU_id,
                   ask.quantity, ask.batch_number
            FROM ASNs AS a");
        $query->addJoin("JOIN suppliers sup ON a.supplier_id = sup.id");
        $query->addJoin("JOIN orders o ON a.order_id = o.id");
        $query->addJoin("LEFT JOIN orders_original_despatched ood ON o.id = ood.order_id");
        $query->addJoin("JOIN ASN_SKUs ask ON a.id = ask.ASN_id");
        $query->addJoin("JOIN SKUs s ON ask.SKU_id = s.id");
        $query->addWhere("a.order_id > 0");
        $query->addWhere("a.warehouse_id = :warehouse_id");
        $query->addWhere("a.supplier_id = :supplier_id");
        if (!empty($this->sftp_report->getAdditional()['date_from'])) {
            $query->addWhere("a.arrival_date >= :arrival_date");
            $params['arrival_date'] = $this->sftp_report->getAdditional()['date_from'];
        }
        $query->addWhere("a.id > :last_id");
        $query->setOrderBy("a.id, s.id");
        $query->setLimit('10000');
        $ASNs = (new SafeMysql())->fetchAll($query->build(), $params);
        if (empty($ASNs))
            return [];
        SKUBarcodes::addBarcodesToArray($ASNs, 'SKU_id');
        $timezone = '';
        if ($warehouse = Warehouses::getById($this->warehouse['id']))
            $timezone = $warehouse->getTimezone();
        $data = [];
        foreach ($ASNs as $ASN_data) {
            $delivery = json_decode($ASN_data['delivery'], true) ?? [];
            $batch_number = json_decode($ASN_data['batch_number'], true) ?? [];
            $data[] = [
                'ASN ID' => $ASN_data['id'],
                'Client' => $ASN_data['supplier_name'],
                'Order ID' => $ASN_data['order_id'],
                'Reference' => $ASN_data['ref'],
                'Reference 2' => $ASN_data['ref2'],
                'Contact Name' => $ASN_data['is_hash'] ? 'Hashed' : ($delivery['contact'] ?? ''),
                'Email' =>  $ASN_data['is_hash'] ? 'Hashed' : ($delivery['email'] ?? ''),
                'Despatched Date' => $timezone ? Timezones::getDiff($warehouse->getTimezone(), $ASN_data['despatched_date'])
                                               : $ASN_data['despatched_date'],
                'Return Create Date' => $timezone ? Timezones::getDiff($warehouse->getTimezone(), $ASN_data['create_date'])
                                               : $ASN_data['create_date'],
                'Status' => ASN::getStateNameReturn($ASN_data['status']),
                'Product Name' => $ASN_data['name'],
                'Customer Ref' => $ASN_data['customer_ref'],
                'Barcode' => $ASN_data['barcode'],
                'Quantity' => $ASN_data['quantity'],
                'Condition' => ASN::CONDITIONS[$batch_number['condition'] ?? 0],
                'Reason' => ASN::REASONS[$batch_number['reason'] ?? 0],
                'Refund or Exchange' => ASN::REFUNDS[$batch_number['refund'] ?? 0],
                'Action Required' => ASN::ACTIONS[$batch_number['action'] ?? 0],
                'CS Actioned' => $ASN_data['actioned'] ? 'Y' : 'N',
            ];
        }
       return $data;
    }
}