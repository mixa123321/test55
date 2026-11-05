<?php

namespace app\core\traits;

use app\core\helpers\Request;
use app\backend\models\SKUs as SKUsModel;
use app\backend\models\SKULocations;
use app\core\entities\SKU;
use app\core\exceptions\RedirectException;

trait CommonAjaxRequests
{
    public function getSKUsForSearch()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $term = (string)($_GET['term'] ?? '');
        $term = mb_ereg('^\s+$', $term) ? ' ' : trim($term);
        if ($term === '')
            return $this->response = ['data' => [], 'errors' => ['Access denied']];
        $supplier_id = Request::getPositiveId(null, 'supplier_id');
        $suppliers = $this->getSuppliers(['warehouse_id' => $this->warehouse_id]);
        if ($supplier_id && empty($suppliers[$supplier_id]))
            return $this->response = ['data' => [], 'errors' => ['Access denied']];
        $SKUs = SKUsModel::getSKUsForSearch($term, (int)$this->warehouse_id, $supplier_id ? [$supplier_id] : array_keys($suppliers), 20, [SKU::LOCKED_NUM, SKU::ACTIVE_NUM, SKU::UNEXPECTED_NUM, SKU::NOT_MAPPED_NUM]);
        if (!empty($_GET['left'])) {
            $available_SKUs = SKULocations::getAvailableSKUsQty(array_column($SKUs, 'id'), $this->warehouse_id);
            foreach ($SKUs as $key => $SKU)
                $SKUs[$key]['left'] = $available_SKUs[(int)$SKU['id']];
        }
        return $this->response = ['data' => $SKUs, 'errors' => []];
    }
}