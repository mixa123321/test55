<?php

namespace app\api\models\actions;

use app\core\entities\Carrier;
use app\core\helpers\Validator;
use app\core\services\shipping\Shipping;

abstract class BaseOrderPost extends BaseAction
{
    protected $data;
    protected $original_request = '';

    public function getRequestFieldsConfig()
    {
        /** @var Carrier $carrier */
        $carrier = Shipping::getMainCarrier();
        return [
            'CarrierService' => [
                'rules' => [Validator::TYPE_INT => [0, 10000]],
                'example' => $carrier->getId() ?? 0,
                'description' => 'Carrier Service. Default value - ' . ($carrier->getId() ?? 0),
            ],
            'Reference' => [
                'rules' => [Validator::TYPE_STRING => [1, 255]],
                'example' => 'My Ref',
                'description' => 'Client reference for order',
            ],
            'DespatchDate' => [
                'rules' => [Validator::TYPE_DATE => 1],
                'example' => '2019/07/20',
                'description' => 'Despatch date',
            ],
            'Delivery' => [
                'rules' => [Validator::TYPE_ARRAY => [1, 100], Validator::TYPE_OBJECT => 1],
            ],
            'SKUs' => [
                'rules' => [Validator::TYPE_ARRAY => [1, 100], Validator::TYPE_OBJECT => 1],
            ],
        ];
    }

    public function setOriginalRequest($original_request)
    {
        if (is_array($original_request))
            $original_request = json_encode($original_request);
        $this->original_request = (string)$original_request;
    }

    public function getOriginalRequest()
    {
        return !empty($this->original_request) ? $this->original_request : json_encode($this->data ?? []);
    }
}
