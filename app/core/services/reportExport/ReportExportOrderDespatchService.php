<?php

namespace app\core\services\reportExport;

use app\backend\models\Orders;
use app\backend\models\Shippings;
use app\core\dbal\SafeMysql;
use app\core\db\QueryBuilder;
use app\core\entities\Order;
use app\core\helpers\Arr;
use app\core\models\Warehouses;

class ReportExportOrderDespatchService extends ReportExportService
{
    private $locations;
    private $interval = [
        'day',
        'week',
        'month',
    ];

    public function setFields()
    {
        $this->fields = [
            'Order ID',
            'Reference',
            'Reference 2',
            'Date Created',
            'Date Despatched',
            'Destination Country',
            'Destination State',
            'Destination City',
            'Destination Zip',
            'Address Line 1',
            'Address Line 2',
            'Address Line 3',
            'Contact Name',
            'Contact Phone',
            'Contact Email',
            'Tracking Number',
        ];
    }

    private function changeFields()
    {
        $tmp = [];
        foreach ($this->fields as $field) {
            $tmp[] = $field;
            if ($field == 'Date Despatched')
                $tmp[] = 'Shipped from';
        }
        $this->fields = $tmp;
    }

    /** @throws \Exception */
    public function __construct($supplier_id, $warehouse_id)
    {
        parent::__construct($supplier_id, $warehouse_id);
        $this->setId($this->fields[0]);
        $this->locations = Warehouses::getLocations($warehouse_id);
        if (!empty($this->locations))
            $this->changeFields();
        $this->makeFile("{$this->getReportName()}_" . date("Y-m-d-His"));
    }

    public function getData($last_id): array
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("
            SELECT o.id,
                   o.ref,
                   o.ref2,
                   o.create_date,
                   o.despatched_date,
                   o.delivery_id
            FROM orders o");
        $query->addJoin('LEFT JOIN deliveries d ON o.delivery_id = d.id');
        $query->addWhere("o.warehouse_id = :warehouse_id");
        $query->addWhere("o.supplier_id = :supplier_id");
        $query->addWhere('o.status = :status');
        $query->addWhere("o.despatched_date >= :despatched_date");
        $query->addWhere("o.id > :last_id");
        $query->setLimit('10000');
        $query->setOrderBy('o.id ASC');
        $orders = (new SafeMysql())->fetchAll($query->build(), [
            'warehouse_id' => $this->warehouse['id'],
            'supplier_id' => $this->sftp->getSupplierId(),
            'status' => Order::DESPATCHED_NUM,
            'last_id' => $last_id,
            'despatched_date' => date('Y-m-d', strtotime('-1 ' . $this->interval[$this->getFrequency()]))
        ]);
        if (empty($orders))
            return [];
        $order_ids = Arr::column($orders, 'id');
        [$tracking_numbers, $warehouses] = Shippings::getTrackingNumbersByOrders($order_ids);
        list(, $deliveries) = Orders::getDespatchedBatchesData($order_ids);
        $data = [];
        foreach ($orders as $order_data) {
            $order_id = $order_data['id'];
            $tmp = [
                'Order ID' => $order_id,
                'Reference' => $order_data['ref'],
                'Reference 2' => $order_data['ref2'],
                'Date Created' => $order_data['create_date'],
                'Date Despatched' => $order_data['despatched_date'],
            ] + (!empty($this->locations) ? ['Shipped from' => $warehouses[$order_id] ?? ''] : []) +
            [
                'Destination Country' => $deliveries[$order_id]['country_id'],
                'Destination State' => $deliveries[$order_id]['county'],
                'Destination City' => $deliveries[$order_id]['town'],
                'Destination Zip' => $deliveries[$order_id]['postcode'],
                'Address Line 1' => $deliveries[$order_id]['address1'],
                'Address Line 2' => $deliveries[$order_id]['address2'],
                'Address Line 3' => $deliveries[$order_id]['address3'],
                'Contact Name' => $deliveries[$order_id]['contact'],
                'Contact Phone' => $deliveries[$order_id]['phone'],
                'Contact Email' => $deliveries[$order_id]['email'],
                'Tracking Number' => '',
            ];
            if (empty($tracking_numbers[$order_id])) {
                $data[] = $tmp;
                continue;
            }
            foreach ($tracking_numbers[$order_id] as $tracking_number) {
                $tmp['Tracking Number'] = $tracking_number;
                $data[] = $tmp;
            }
        }
        return $data;
    }
}