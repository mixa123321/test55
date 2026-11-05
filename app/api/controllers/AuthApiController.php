<?php

namespace app\api\controllers;

use app\api\API;
use app\api\APIException;
use app\api\models\ErrorCodes;
use app\backend\models\Warehouses;
use app\core\entities\LogisticCompany;
use app\core\Environment;
use app\core\models\Logger;
use app\core\models\LogisticCompanies;
use app\core\models\Notifiers;
use app\core\services\shipping\Shipping;

abstract class AuthApiController extends BaseApiController
{
    /** @var $shipping array */
    protected $shipping;

    public function beforeAction($action)
    {
        parent::beforeAction($action);
        API::authorization();
        if (!API::getUser()->hasSupplierAccess())
            throw new APIException(401, [['code' => ErrorCodes::ACCESS_DENIED, 'message' => 'Access denied']]);
        $warehouse = Warehouses::getById(API::getUser()->getWarehouseId());
        if (empty($warehouse))
            throw new APIException(401, [['code' => ErrorCodes::ACCESS_DENIED, 'message' => 'Access denied']]);
        $logistic_company = LogisticCompanies::getById($warehouse->getLCId(), LogisticCompany::ACTIVE);
        if (empty($logistic_company))
            throw new APIException(401, [['code' => ErrorCodes::ACCESS_DENIED, 'message' => 'Access denied']]);
        $this->shipping = Shipping::createShippingMethods($logistic_company);
        Notifiers::init($logistic_company);
    }

    public function afterAction()
    {
        parent::afterAction();
        if (Environment::isLoggable())
            Logger::addRecord(API::getUser() ? API::getUser()->getId() : 0, $this->response, $this->getHttpCode());
    }
}
