<?php

namespace app\backend\controllers;

use app\backend\models\Carriers as CarriersModel;
use app\core\exceptions\RedirectException;
use app\core\helpers\Request;
use app\core\helpers\FileWrapper;

class Carriers extends AuthController
{
    public function index()
    {
        $records = CarriersModel::getByWarehouse($this->warehouse);
        $logos = [];
        foreach ($records as $key => $record)
            if (empty($logos[$record['logo']])) {
                $path = ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR . $record['logo'];
                $res = (new FileWrapper(FileWrapper::getContainerByPath($path)))->getStream($path);
                $img_string = $res ? stream_get_contents($res) : '';
                $logos[$record['logo']] = base64_encode($img_string);
            }
        $this->render([
            'records' => $records,
            'log_comp_id' => $this->logistic_company->getId(),
            'logos' => $logos,
        ], 'warehouse/carriers');
    }

    public function changeMode()
    {
        $carriers_ins = array_keys($_POST);
        CarriersModel::applyCarrier($carriers_ins, $this->warehouse);
        return $this->response = ['success'];
    }

    public function createCarrier()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = json_encode(['result' => 'error', 'errors' => ['Access denied']]);
        if (empty($_POST))
            return $this->response = json_encode(['result' => 'error', 'errors' => ['Error data']]);
        if (!$_POST['is_internal'] && $_POST['is_special'])
            $_POST['is_special'] = 0;
        $method = '\app\backend\models\Carriers::' . (empty($_POST['id']) ? 'add' : 'change') . 'Carrier';
        [$res, $messages] = $method($_POST, $_FILES);
        return $this->response = json_encode(['result' => $res, 'errors' => $messages]);
    }
}