<?php

namespace app\core\services\upload;

use app\backend\models\ASNs;
use app\backend\models\Suppliers;
use app\core\dbal\SafeMysql;
use app\core\entities\ASN;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\exceptions\BatchException;
use app\core\interfaces\SFTPUpload;
use app\core\models\Batches;
use app\core\models\SKUBarcodes;

class ASNsUploadService implements SFTPUpload
{
    public static $fields = [
        'arrival_date',
        'asn_reference',
        'carrier',
        'tracking_number',
        'container',
        'comment',
        'service_counting',
        'service_labelling',
        'service_poly',
        'service_qc',
        'service_other',
        'sku',
        'qty',
        'batch',
        'expiry',
    ];

    private $messages = [];

    public function execute($head, $data, $form, Warehouse $warehouse, User $user): array
    {
        $ASNs = [];
        $head = array_map(function($value) { return str_replace(' ', '_', $value); }, $head);

        if ($this->validateHead($head)) {
            foreach ($data as $line => $row) {
                $assoc_row = $this->prepareRow($head, $row);
                $line_name = 'Line#'. ($line+1).' ('.$assoc_row['sku'].')';

                $ASN = $this->getASN($line_name, $assoc_row, $warehouse, $user);
                $SKUs = $this->getSKUs($line_name, $assoc_row);

                if (count($SKUs) && count($ASN)) {
                    $ASNs[$this->getKeyASN($assoc_row)]['skus'][] = $SKUs;
                    $ASNs[$this->getKeyASN($assoc_row)]['asn'] = $ASN;
                }
            }
        }
        $mysql = new SafeMysql();
        foreach ($ASNs as $asn) {
            $mysql->beginTransaction();
            list($done, ) = ASNs::addASN($asn['asn'], $this->prepareSKUs($asn['skus']), [], false, $user->getId());
            if ($done) {
                $mysql->commit();
            } else {
                $mysql->rollback();
            }
        }

        return [[true, ['danger' => $this->messages, 'success' => count($ASNs) ? 'Successfully' : 'Error']]];
    }

    private function prepareRow($head, $row): array
    {
        $assoc_row = array_combine($head, $row);
        $assoc_row = array_filter($assoc_row, static function ($column) {
            return in_array($column, self::$fields, true);
        }, ARRAY_FILTER_USE_KEY);

        return $assoc_row;
    }

    private function validateHead(array $head): bool
    {
        foreach (self::$fields as $field) {
            if (!in_array($field, $head, true)) {
                $this->messages[] = 'Column '.$field.' is required';
                return false;
            }
        }

        return true;
    }

    private function getSKUs($line_name, array $data): array
    {
        if (isset($data['sku']) && $data['sku']) {
            $skuId = SKUBarcodes::getSKUIdsByBarcode($data['sku']);
            if (isset($skuId[0]))
                return $this->validateSKU($line_name, $skuId[0], $data['sku'], $data['batch'], $data['expiry'], $data['qty']);
            else
                $this->messages[$line_name][] = 'SKU '.$data['sku'].': not found';
        }

        return [];
    }

    private function validateSKU($line_name, $skuId, $barcode, $batch, $expired, $qty): array
    {
        $expired =  str_replace('/', '.', $expired);

        $SKU = [
            'name' => '',
            'quantity' => $qty,
            'batch_number' => $batch,
            'batch_expired' => $expired,
            'sku_id' => $skuId
        ];

        if (empty($qty) || $qty < 0) {
            $this->messages[$line_name][] = "SKU $barcode: quantity must be greater than 0";
            return [];
        }
        if (empty($batch) && !empty($expired)) {
            $this->messages[$line_name][] = "SKU $barcode: batch number cannot be empty when expiry filled up";
            return [];
        }
        if (!empty($batch) && (empty($expired) || (strtotime($expired) <= time()))) {
            $this->messages[$line_name][] = "SKU $barcode: batch expiry cannot be empty when number filled up";
            return [];
        }
        if (empty($batch) && empty($expired))
            return $SKU;

        $batch_info = Batches::getBatchBySKUAndNumber($skuId, $batch);
        $expired_date_format = (new \DateTime($expired))->format('Y-m-d');
        if (empty($batch_info))
            try {
                Batches::addBatch($batch, $skuId, $expired_date_format);
            } catch (BatchException $e) {
                $this->messages[$line_name][] = "SKU $barcode: {$e->getMessage()}";
                return [];
            }
        elseif ((new \DateTime($batch_info['expired']))->format('Y-m-d') != $expired_date_format) {
            $this->messages[$line_name][] = "SKU $barcode: batch '$batch' already exists with a different expiry date";
            return [];
        }

        return $SKU;
    }

    private function prepareSKUs(array $SKUs): array
    {
        $data = [];
        foreach ($SKUs as $sku) {
            if (isset($data[$sku['sku_id']]))
                $data[$sku['sku_id']]['quantity'] += $sku['quantity'];
            else
                $data[$sku['sku_id']] = $sku;
        }

        return $data;
    }

    private function getASN($line_name, array $data, Warehouse $warehouse, User $user): array
    {
        $supplier = Suppliers::getSupplierByUser($warehouse->getId(), $user->getId());
        $arrival_date = strtotime(str_replace('/', '.', $data['arrival_date']));
        $services = $this->getServices($data);

        if ($arrival_date < strtotime(date('Y-m-d'))) {
            $this->messages[$line_name][] = "Arrival date {$data['arrival_date']} for SKU {$data['sku']} must be more than current date";
            return [];
        }
        $result = [
            'user_id' => $user->getId(),
            'supplier_id' => $supplier['id'],
            'arrival_date' => $arrival_date,
            'is_return' => 0,
            'unscheduled' => 0,
            'comment' => $data['comment'],
            'ref' => $data['asn_reference'],
            'carrier' => $data['carrier'],
            'tracking_number' => $data['tracking_number'],
            'container' => $this->getContainer(trim($data['container'])),
            'status' => ASN::NEW,
            'warehouse_id' => $warehouse->getId(),
        ];

        if (count($services)) {
            $result = array_merge($result, ['services' => $services]);
        }

        return $result;
    }

    private function getServices(array $data): array
    {
        $services = [];
        if ($data['service_other']) {
            $services[0] = $data['service_other'];
        }
        if ((bool) $data['service_counting']) {
            $services[1] = 1;
        }
        if ((bool) $data['service_labelling']) {
            $services[2] = 1;
        }
        if ((bool) $data['service_poly']) {
            $services[3] = 1;
        }
        if ((bool) $data['service_qc']) {
            $services[4] = 1;
        }

        return $services;
    }

    private function getKeyASN(array $data): string
    {
        $asnData = [
            str_replace('/', '_', $data['arrival_date']),
            $data['asn_reference'],
            $data['carrier'],
            $data['tracking_number']
        ];

        return str_replace(' ', '_', implode('_', $asnData));
    }

    private function getContainer(string $containerName): string
    {
        switch ($containerName) {
            case '20 Foot Container':
                return '20ft_container';
            case '40 Foot Container':
                return '40ft_container';
            default:
                return ''; //Loose
        }
    }
}