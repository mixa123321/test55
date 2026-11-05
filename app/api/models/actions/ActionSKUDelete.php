<?php

namespace app\api\models\actions;

use app\api\API;
use app\backend\models\SKUs;
use app\core\entities\SKU;
use app\core\models\SKUHistories;

class ActionSKUDelete extends BaseAction
{
    private $user_id;
    private $SKUs_id;

    public function __construct($data)
    {
        $this->user_id = API::getUser()->getId();
        $this->SKUs_id = [];
        if (count($data) < 1)
            return;
        foreach ($data as $id)
            $this->SKUs_id[] = (int)$id['SKU'];
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
            /**
             * @var $SKU SKU
             */
            $SKU = SKUs::getByIdAndUser($SKU_id, $this->user_id);
            if (is_null($SKU)) {
                $errors_SKU[] = ["Rec #$key" => $SKU_id, 'error' => 'SKU not found'];
                continue;
            }
            if ($SKU->locked())
                $errors_SKU[] = ["Rec #$key" => $SKU_id, 'error' => 'SKU is already cancelled'];
            else {
                $SKU->setState(SKU::LOCKED_NUM);
                $SKU->save(["id", "state"]);
                SKUHistories::setState($SKU->getId(), $this->user_id, SKU::LOCKED_NUM, API::getUser()->getWarehouseId());
                $SKUs[] = ["Rec #$key" => $SKU_id];
            }
        }
        return ['cancelled_SKUs' => $SKUs, 'error_SKUs' => $errors_SKU];
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
