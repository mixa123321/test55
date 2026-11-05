<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\LogisticCompany;
use app\core\helpers\Validator;

class LogisticCompanies extends \app\core\models\LogisticCompanies
{
    const LC_SA = '/logistic_companies/sa';
    public static function validate(LogisticCompany $lc)
    {
        $rules = [
            'name' => [
                'val' => $lc->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 255]]
            ],
            'logo' => [
                'val' => $lc->getLogo(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [5, 255]]
            ],
            'css' => [
                'val' => $lc->getCss(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [5, 255]]
            ],
            'shipping_class' => [
                'val' => $lc->getShippingClass(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
        ];
        $validator = new Validator($rules);
        $validator->validate();
        if (!empty($lc->getLogo()))
            if (!is_file(ROOT_PATH . DIRECTORY_SEPARATOR . COMMON_IMG . DIRECTORY_SEPARATOR . $lc->getLogo()))
                $validator->addError('invalid_file','Logo file is not found', 'logo');
        if (!empty($lc->getCss()))
            if (!is_file(ROOT_PATH . DIRECTORY_SEPARATOR . BACKEND_CSS . DIRECTORY_SEPARATOR . $lc->getCss()))
                $validator->addError('invalid_file','CSS file is not found', 'css');
        if (!empty($lc->getShippingClass())) {
            $class_name = '\\app\\core\\services\\shipping\\' . $lc->getShippingClass();
            if (class_exists($class_name))
                $parent_classes = class_parents($class_name);
            if (!class_exists($class_name) || empty($parent_classes) || !in_array('app\\core\\services\\shipping\\Shipping', $parent_classes))
                $validator->addError('invalid_class','Shipping class is not found', 'shipping_class');
        }
        if (!empty($lc->getBillingClass())) {
            $class_name = '\\app\\core\\services\\billing\\' . $lc->getBillingClass();
            if (class_exists($class_name))
                $parent_classes = class_parents($class_name);
            if (!class_exists($class_name) || empty($parent_classes) || !in_array('app\\core\\services\\billing\\Billing', $parent_classes))
                $validator->addError('invalid_class','Billing class is not found', 'billing_class');
        }
        if ($lc->getCarrierId() && $lc->getId()) {
            $id = LogisticCompanyCarriers::getId($lc->getId(), $lc->getCarrierId());
            if (!$id)
                $validator->addError('invalid_carrier','Carrier is not found', 'carrier_id');
        }
        return $validator->getErrors();
    }

    public static function getWarehousesStatistic()
    {
        return (new SafeMysql())->fetchAll("
            SELECT lc.id, lc.name, SUM(IF(w.id IS NULL, 0, 1)) qty
            FROM logistic_companies lc
                LEFT JOIN warehouses w ON lc.id = w.lc_id
            GROUP BY lc.id");
    }

    public static function getLocationsStatistic()
    {
        return (new SafeMysql())->fetchAll("
            SELECT lc.id, lc.name, SUM(IF(l.id IS NULL, 0, 1)) qty
            FROM logistic_companies lc
                LEFT JOIN warehouses w ON lc.id = w.lc_id
                LEFT JOIN locations l ON w.id = l.warehouse
            GROUP BY lc.id");
    }

    public static function getCompaniesStatistic()
    {
        return (new SafeMysql())->fetchAll("
            SELECT lc.id, lc.name, SUM(IF(sup.id IS NULL, 0, 1)) qty
            FROM logistic_companies lc
                LEFT JOIN persons p ON lc.id = p.lc_id
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN suppliers sup ON u.id = sup.user_id
            GROUP BY lc.id");
    }

    public static function getUsersStatistic()
    {
        return (new SafeMysql())->fetchAll("
            SELECT lc.id, lc.name, SUM(IF(u.id IS NULL, 0, 1)) qty
            FROM logistic_companies lc
                LEFT JOIN persons p ON lc.id = p.lc_id
                LEFT JOIN users u ON p.user_id = u.id
            GROUP BY lc.id");
    }

    public static function getSKUsStatistic()
    {
        return (new SafeMysql())->fetchAll("
            SELECT lc.id, lc.name, SUM(IF(s.id IS NULL, 0, 1)) qty
            FROM logistic_companies lc
                LEFT JOIN persons p ON lc.id = p.lc_id
                LEFT JOIN users u ON p.user_id = u.id
                LEFT JOIN suppliers sup ON u.id = sup.user_id
                LEFT JOIN SKUs s ON sup.id = s.supplier_id
            GROUP BY lc.id");
    }

    public static function getASNsStatistic()
    {
        return (new SafeMysql())->fetchAll("
            SELECT lc.id, lc.name, SUM(IF(a.id IS NULL, 0, 1)) qty
            FROM logistic_companies lc
                LEFT JOIN warehouses w ON lc.id = w.lc_id
                LEFT JOIN ASNs a ON w.id = a.warehouse_id
            GROUP BY lc.id");
    }

    public static function getOrdersStatistic()
    {
        return (new SafeMysql())->fetchAll("
            SELECT lc.id, lc.name, SUM(IF(o.id IS NULL, 0, 1)) qty
            FROM logistic_companies lc
                LEFT JOIN warehouses w ON lc.id = w.lc_id
                LEFT JOIN orders o ON w.id = o.warehouse_id
            GROUP BY lc.id");
    }
}
