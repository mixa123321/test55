<?php

namespace app\api\models\actions;

use app\api\APIException;
use app\api\models\ErrorCodes;
use app\api\models\Webhooks;

class ActionWebhooksPut extends BaseWebhooks
{
    /** @throws APIException */
    public function validateFields()
    {
        if (empty($this->getData()['WebHookName']))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_REQUIRED, 'message' => 'WebHookName is required']]);
        if (!in_array($this->getData()['WebHookName'], Webhooks::WEBHOOK_LIST))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_INVALID, 'message' => "Webhook '{$this->getData()['WebHookName']}' is not found"]]);
        if (!isset($this->getData()['Subscribed']) && !isset($this->getData()['CallbackURL']))
            throw new APIException(400, [['code' => ErrorCodes::FIELD_INVALID, 'message' => "Subscribed or CallbackURL must exist"]]);
    }

    /** @throws APIException */
    public function process()
    {
        $result = Webhooks::update($this->getData()['WebHookName'], $this->getData()['Subscribed'] ?? null, $this->getData()['CallbackURL'] ?? null, $this->getUser()->getId());
        if (!$result[0])
            return ['errors' => [$result[1]]];
        return ['success' => $this->getData()['WebHookName']];
    }
}
