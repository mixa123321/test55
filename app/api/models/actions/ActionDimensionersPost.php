<?php

namespace app\api\models\actions;

use app\api\APIException;
use app\api\models\Dimensioners;
use app\api\models\ErrorCodes;

class ActionDimensionersPost extends BaseWebhooks
{
    public function validateFields() { }

    public function process() { }

    /** @throws APIException */
    public function measureValidateFields()
    {
        if ($this->getData()['StatusCode'] < 0)
            throw new APIException(400, [['code' => ErrorCodes::DATA_ERROR, 'message' => 'Status code failure']]);
        if (!empty($this->getData()['StatusMessage']))
            throw new APIException(400, [['code' => ErrorCodes::DATA_ERROR, 'message' => $this->getData()['StatusMessage']]]);
        if (empty($this->getData()['UnitID']))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_REQUIRED, 'message' => 'Wrong or empty UnitID']]);
        if (empty($this->getData()['Length']) || empty($this->getData()['Width']) || empty($this->getData()['Height']))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_REQUIRED, 'message' => 'Wrong or empty dimensions']]);
        if (empty($this->getData()['Weight']))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_REQUIRED, 'message' => 'Wrong or empty weight']]);
        if (empty($this->getData()['Barcode']))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_REQUIRED, 'message' => 'Wrong or empty barcode']]);
        if (empty($this->getData()['ImageBase64']))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_REQUIRED, 'message' => 'Wrong or empty image']]);
    }

    /** @throws APIException */
    public function measure()
    {
        $result = Dimensioners::measure($this->getData(), $this->getUser()->getWarehouseId(), $this->getUser()->getId());
        if (!$result[0])
            return ['ResponseCode' => -3, 'ResponseMessage' => $result[1]];
        return ['ResponseCode' => 0, 'ResponseMessage' => ''];
    }
}
