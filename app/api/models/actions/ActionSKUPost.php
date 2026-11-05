<?php

namespace app\api\models\actions;

use app\api\API;
use app\api\APIException;
use app\api\models\ErrorCodes;
use app\backend\models\SKUs;
use app\core\entities\Supplier;
use app\core\models\Suppliers;
use app\core\interfaces\RequestValidationInterface;
use app\core\traits\RequestValidation;

class ActionSKUPost extends BaseSKUPost implements RequestValidationInterface
{
    use RequestValidation;

    private $SKUs;
    private $user_id;
    private $warehouse_id;
    private $supplier_id;

    public function init(): BaseAction {
        $data = $this->getValidRequestData();
        $this->SKUs = [];
        if (count($data) < 1)
            throw new APIException(422, [['code' => ErrorCodes::DATA_ERROR, 'message' => 'SKUs are not found']]);
        $this->user_id = API::getUser()->getId();
        $this->warehouse_id = API::getUser()->getWarehouseId();
        $suppliers = API::getUser()->isRoleSupplierAdmin() ?
                        Suppliers::getSuppliersByAdmin($this->warehouse_id, $this->user_id, Supplier::ACTIVE) :
                        Suppliers::getSuppliersByUser($this->warehouse_id, $this->user_id, Supplier::ACTIVE);
        $this->supplier_id = empty($suppliers) ? 0 : $suppliers[array_keys($suppliers)[0]]['id'];
        foreach ($data as $key => $SKU) {
            $this->SKUs[$key] = [
                'name' => empty($SKU['Name']) ? '' : $SKU['Name'],
                'barcode' => empty($SKU['Barcode']) ? '' : $SKU['Barcode'],
                'gs1' => empty($SKU['GS1']) ? '' : $SKU['GS1'],
                'cost_value' => empty($SKU['CostValue']) ? 0 : $SKU['CostValue'],
                'cost_currency' => empty($SKU['CostCurrency']) ? '' : $SKU['CostCurrency'],
                'market_value' => empty($SKU['MarketValue']) ? 0 : $SKU['MarketValue'],
                'market_currency' => empty($SKU['MarketCurrency']) ? '' : $SKU['MarketCurrency'],
                'width' => empty($SKU['Width']) ? 0 : $SKU['Width'],
                'height' => empty($SKU['Height']) ? 0 : $SKU['Height'],
                'depth' => isset($SKU['Length']) ? ($SKU['Length'] ?: 0) : ($SKU['Depth'] ?? 0),
//                'depth' => empty($SKU['Depth']) ? 0 : $SKU['Depth'],
                'weight' => empty($SKU['Weight']) ? 0 : $SKU['Weight'],
                'hs_code' => empty($SKU['HSCode']) ? '' : $SKU['HSCode'],
                'season' => empty($SKU['Season']) ? '' : $SKU['Season'],
                'customer_ref' => empty($SKU['CustomerReference']) ? '' : $SKU['CustomerReference'],
                'customer_ref2' => empty($SKU['CustomerReference2']) ? '' : $SKU['CustomerReference2'],
                'supplier_id' => $this->supplier_id,
                'state' => 1,
                'photo_src' => empty($SKU['Photo']) ? '' : $SKU['Photo'],
            ];
            foreach ($this->SKUs[$key] as $k => $val)
                if (empty($val))
                    unset($this->SKUs[$key][$k]);
        }
        return $this;
    }

    public function validateFields() {
        if (count($this->SKUs) < 1)
            return [['code' => ErrorCodes::DATA_ERROR, 'message' => 'SKUs are not found']];
        return [];
    }

    public function process()
    {
        if (count($this->SKUs) < 1)
            return ['critical_error' => [
                'error_id' => 1,
                'error_msg' => 'Missing SKUs']
            ];
        $errors_SKU = [];
        $SKUs = [];
        foreach ($this->SKUs as $key => $SKU_data) {
            if (empty($SKU_data['customer_ref'])) {
                $errors_SKU["Rec #$key"]['SKU'][] = 'Customer Reference is empty';
                continue;
            }
            $barcodes = [$SKU_data['barcode']];
            unset($SKU_data['barcode']);
            $res = SKUs::addSKU($SKU_data, $barcodes, API::getUser()->getWarehouseId(), $this->user_id,true);
            if (!$res[0]) {
                $errors_SKU["Rec #$key"]['SKU'] = [];
                foreach ($res[1] as $error)
                    $errors_SKU["Rec #$key"]['SKU'][] = $error['message'];
                continue;
            }
            $SKUs["Rec #$key"] = $res[1];
        }
        return ['created_SKUs' => $SKUs, 'error_SKUs' => array_merge($errors_SKU, $this->getValidationRequestErrors())];
    }

    public function getResponseFieldsConfig()
    {
        return [];
    }

    public function validationRequest(): RequestValidationInterface
    {
        if (empty($this->request_data)) {
            $this->errors[self::ERROR_KEY_PREFIX . "0"]['SKU'][] = 'SKU is required';
            return $this;
        }
        foreach ($this->request_data as $key => $values) {
            if ($error = $this->mandatoryMaxLen($values, self::MAX_LEN_100, 'Name'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->mandatoryMaxLen($values, self::MAX_LEN_25, 'Barcode'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryRegexp($values, self::REGEXP_INT_AS_BOOL, 'GS1'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->nomandatoryRegexp($values, self::REGEXP_FLOAT_1_TO_5_POINT_1_TO_2, 'CostValue'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->mandatoryMaxLen($values, self::MAX_LEN_3, 'CostCurrency'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->nomandatoryRegexp($values, self::REGEXP_FLOAT_1_TO_5_POINT_1_TO_2, 'MarketValue'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->mandatoryMaxLen($values, self::MAX_LEN_3, 'MarketCurrency'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryRegexp($values, self::REGEXP_FLOAT_1_TO_4_POINT_1_TO_2, 'Width'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryRegexp($values, self::REGEXP_FLOAT_1_TO_4_POINT_1_TO_2, 'Height'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryRegexp($values, self::REGEXP_FLOAT_1_TO_4_POINT_1_TO_2, 'Length'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryRegexp($values, self::REGEXP_FLOAT_1_TO_4_POINT_1_TO_2, 'Depth'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryRegexp($values, self::REGEXP_FLOAT_1_TO_4_POINT_1_TO_2, 'Weight'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryMaxLen($values, self::MAX_LEN_20, 'HSCode'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryMaxLen($values, self::MAX_LEN_100, 'Season'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryMaxLen($values, self::MAX_LEN_255, 'CustomerReference'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryMaxLen($values, self::MAX_LEN_255, 'CustomerReference2'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if ($error = $this->noMandatoryMaxLen($values, self::MAX_LEN_255, 'Photo'))
                $this->errors[self::ERROR_KEY_PREFIX . $key]['SKU'][] = $error;
            if (empty($this->errors[self::ERROR_KEY_PREFIX . $key]))
                $this->valid_request_data[$key] = $values;
        }
        return $this;
    }
}
