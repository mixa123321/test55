<?php

namespace app\backend\controllers;

use app\backend\models\Dimensioners as DimensionersModel;
use app\backend\models\Suppliers;
use app\core\entities\Dimensioner;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\DimensionerHistories;

class Dimensioners extends AuthController
{
    /** @throws RedirectException */
    public function beforeAction($action)
    {
        parent::beforeAction($action);
        $this->mobile = true;
    }

    public function index()
    {
        $data = [
            'dimensioners' => DimensionersModel::getDimensioners($this->warehouse_id),
            'suppliers' => Arr::columnAssoc(Suppliers::getSuppliers($this->logistic_company->getId(), $this->warehouse_id), 'name'),
        ];
        $this->render($data, 'warehouse/dimensioners');
    }

    /** @throws */
    public function lockDimensioner()
    {
        if (empty($_POST))
            throw new RedirectException('/');
        if (empty($_POST['dim_lock_id'])) {
            View::addFlash('dimensioners', 'danger', 'Invalid information');
            throw new RedirectException('/dimensioners');
        }
        $dimensioner = DimensionersModel::getById($_POST['dim_lock_id']);
        if (empty($dimensioner) || !in_array((int)$_POST['dim_lock'], array_keys(Dimensioner::SHOW_STATE))) {
            View::addFlash('dimensioners', 'danger', 'Invalid information');
            throw new RedirectException('/dimensioners');
        }
        DimensionersModel::setState($dimensioner, Dimensioner::changeState($_POST['dim_lock']));
        DimensionerHistories::state($dimensioner, $this->user->getId());
        View::addFlash('dimensioners', 'success', 'Dimensioner status is changed');
        throw new RedirectException('/dimensioners');
    }

    /** @throws */
    public function editDimensioner()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST))
            return $this->response = ['error' => 'Invalid information'];
        $dimensioner_data = $_POST['dimensioner'];
        $dimensioner_data['warehouse_id'] = $this->warehouse_id;
        if (empty($dimensioner_data['id'])) {
            unset($dimensioner_data['id']);
            $dimensioner_data['state'] = Dimensioner::ACTIVE;
            $dimensioner = new Dimensioner($dimensioner_data);
            $errors = DimensionersModel::validate($dimensioner);
            if (empty($errors)) {
                $dimensioner->save(array_keys($dimensioner_data));
                DimensionerHistories::create($dimensioner, $this->user->getId());
                return $this->response = ['success'];
            }
        } else {
            $dimensioner = DimensionersModel::getById($dimensioner_data['id']);
            if (empty($dimensioner) || $dimensioner->getWarehouseId() != $this->warehouse_id)
                return $this->response = ['error' => 'Invalid information'];
            $dimensioner_data['state'] = $dimensioner->getState();
            $errors = DimensionersModel::validate(new Dimensioner($dimensioner_data));
            if (empty($errors)) {
                if ($dimensioner->getName() == $dimensioner_data['name'] &&
                    $dimensioner->getUnitId() == $dimensioner_data['unit_id'] &&
                    $dimensioner->getBranchId() == $dimensioner_data['branch_id'] &&
                    $dimensioner->getSupplierId() == $dimensioner_data['supplier_id']) {
                    View::addFlash('dimensioners', 'warning', 'Data not changed');
                    return $this->response = ['success'];
                }
                $dimensioner->setName($dimensioner_data['name']);
                $dimensioner->setUnitId($dimensioner_data['unit_id']);
                $dimensioner->setBranchId($dimensioner_data['branch_id']);
                $dimensioner->setSupplierId($dimensioner_data['supplier_id']);
                $dimensioner->save(array_keys($dimensioner_data));
                DimensionerHistories::update($dimensioner, $this->user->getId());
                View::addFlash('dimensioners', 'success', 'Data was updated');
                return $this->response = ['success'];
            }
        }
        return $this->response = ['error' =>
            implode('<hr class="bg-white m-0"/>', array_map(function ($item) {
                return View::toHtml("{$item['field']}: {$item['message']} ({$item['code']})");
            }, $errors))
        ];
    }
}