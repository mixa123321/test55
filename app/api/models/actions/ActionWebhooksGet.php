<?php

namespace app\api\models\actions;

use app\api\APIException;
use app\api\models\ErrorCodes;
use app\api\models\Webhooks;

class ActionWebhooksGet extends BaseWebhooks
{
    /** @throws APIException */
    public function validateFields()
    {
        if (!empty($this->getData()))
            throw new APIException(400, [['code' => ErrorCodes::FIELDS_FOUND, 'message' => 'Data must be empty']]);
    }

    public function process()
    {
        return Webhooks::list($this->getUser()->getId());
    }

    /** @throws APIException */
    public function subscriptiontypesValidateFields()
    {
        if (!empty($this->getData()))
            throw new APIException(400, [['code' => ErrorCodes::FIELDS_FOUND, 'message' => 'Data must be empty']]);
    }

    public function subscriptiontypes()
    {
        return Webhooks::WEBHOOK_LIST;
    }
}
