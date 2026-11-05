<?php

namespace app\api\models\actions;

use app\api\API;
use app\backend\models\SKUs;
use app\core\models\SKUBarcodes;
use app\core\entities\SKU;

class ActionSKUGet extends BaseAction
{
    private $user_id;
    private $SKUs_id = [];
    private $warehouse_id;
    private $stock_level = false;

    public function __construct($data)
    {
        $this->user_id = API::getUser()->getId();
        $this->warehouse_id = API::getUser()->getWarehouseId();
        $data = json_decode($data['q'] ?? '', 1);
        if (empty($data))
            return;
        foreach ($data as $item) {
            if (!empty($item['SKU'])) {
                $this->SKUs_id[] = (int)$item['SKU'];
                continue;
            }
            if (!empty($item['StockLevel']))
                $this->stock_level = true;
        }
    }

    public function validateFields() { }

    public function process()
    {
        if (count($this->SKUs_id) < 1)
            return ['critical_error' => [
                'error_id' => 1,
                'error_msg' => 'Missing SKUs']
            ];
        $errors_SKU = [];
        $SKUs = [];
        foreach ($this->SKUs_id as $key => $SKU_id) {
            /** @var SKU $SKU */
            $SKU = SKUs::getByIdAndUser($SKU_id, $this->user_id);
            if (is_null($SKU)) {
                $errors_SKU[] = ["Rec #$key" => $SKU_id, 'error' => 'SKU not found'];
                continue;
            }
            if (!$this->stock_level)
                $SKU_data = $SKU->toArrayView();
            else
                $SKU_data = array_merge([
                    'id' => $SKU->getId(),
                    'name' => $SKU->getName(),
                    'barcode' => SKUBarcodes::getBarcodesBySKUIdString($SKU->getId()),
                ], array_map('intval', SKUs::getStockLevel($this->stock_level, $SKU->getId())));

            $SKUs[] = ["Rec #$key" => $SKU_data];
        }
        return ['info_SKUs' => $SKUs, 'error_SKUs' => $errors_SKU];
    }

    public function getRequestFieldsConfig()
    {
        return [];
    }

    public function getResponseFieldsConfig()
    {
        return [];
    }
}
