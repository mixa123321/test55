<?php

namespace app\api\models\actions;

use app\api\APIException;
use app\api\models\ErrorCodes;
use app\api\models\Webhooks;

class ActionWebhooksPost extends BaseWebhooks
{
    public function validateFields() { }

    public function process() { }

    /** @throws APIException */
    public function subscriptionValidateFields()
    {
        if (empty($this->getData()['WebHookName']))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_REQUIRED, 'message' => 'WebHookName is required']]);
        if (!in_array($this->getData()['WebHookName'], Webhooks::WEBHOOK_LIST))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_INVALID, 'message' => "Webhook '{$this->getData()['WebHookName']}' is not found"]]);
        if (empty($this->getData()['CallbackURL']))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_INVALID, 'message' => "CallbackURL must exist or shouldn't be empty"]]);
    }

    /** @throws APIException */
    public function subscription()
    {
        $result = Webhooks::subscribe($this->getData()['WebHookName'], $this->getData()['CallbackURL'], $this->getUser()->getId());
        if (!$result[0])
            return ['errors' => [$result[1]]];
        return ['success' => $this->getData()['WebHookName']];
    }
}
