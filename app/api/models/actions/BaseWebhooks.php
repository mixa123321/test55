<?php

namespace app\api\models\actions;

use app\api\API;
use app\api\APIException;
use app\api\models\ErrorCodes;
use app\core\entities\User;
use app\core\models\Warehouses;

abstract class BaseWebhooks extends BaseAction
{
    /** @var User $user */
    private $user;
    private $data;

    /** @throws APIException */
    public function __construct($data)
    {
        $this->setData($data);
        $this->setUser(API::getUser());
        $warehouse_virtual = Warehouses::isVirtual($this->getUser()->getWarehouseId());
        if ($warehouse_virtual)
            throw new APIException(401, [
                [
                    'code' => ErrorCodes::ACCESS_DENIED,
                    'message' => 'Virtual warehouse is not accessible for webhooks'
                ]
            ]);
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
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
