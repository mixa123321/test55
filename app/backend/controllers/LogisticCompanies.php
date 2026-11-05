<?php

namespace app\backend\controllers;

use app\backend\models\Locations;
use app\backend\models\LogisticCompanies as LCModel;
use app\backend\models\Roles;
use app\backend\models\Warehouses;
use app\backend\models\Users;
use app\core\entities\LogisticCompany;
use app\core\entities\User;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\File;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\Langs;
use app\core\models\WarehouseCarriers;
use app\core\statical\Countries;
use app\core\statical\Currencies;
use app\core\statical\Timezones;
use app\backend\models\Carriers;
use app\core\helpers\FileWrapper;

class LogisticCompanies extends SystemController
{
    public function index()
    {
        $records = LCModel::getAll();
        $carriers = $carrier_logos = [];
        foreach ($records as $key => $record) {
            $carriers[$record->getCarrierId()] = Carriers::getById($record->getCarrierId());
            if ($carriers[$record->getCarrierId()]) {
                $path = ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR . $carriers[$record->getCarrierId()]->getLogo();
                $carrier_logos[$record->getCarrierId()] = base64_encode(stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($path)))->getStream($path)));
            }
        }
        $data = [
            'logistic_companies' => $records,
            'langs' => Langs::getAll(),
            'carrier_logos' => $carrier_logos,
            'carriers' => $carriers,
        ];
        $this->render($data, 'logistic_companies/logistic_companies');
    }

    public function createLC()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST))
            return $this->response = ['error' => 'Access denied'];
        $lc = new LogisticCompany($_POST);
        $errors = LCModel::validate($lc);
        if (!empty($errors))
            return $this->response = ['error' => '<li>' . implode('</li><li>', array_map(function($item) {
                return View::toHtml("{$item['message']} ({$item['field']})");
            }, $errors)) . '</li>'];
        $save_fields = array_keys($lc->toArray());
        if (!$lc->getId())
            array_shift($save_fields);
        $lc->save($save_fields);
        return $this->response = ['success' => ''];
    }

    public function getShippers()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST))
            return $this->response = ['error' => 'Access denied'];
        $shippers = Arr::map(WarehouseCarriers::getAvailableCarriers($_POST['lc_id']), 'id', 'title');
        if (empty($shippers))
            return $this->response = ['error' => 'There are no shippers set for the logistic company'];
        return $this->response = ['success' => $shippers];
    }

    /** @throws */
    public function setShipper()
    {
        if (empty($_POST) || empty($_POST['id'])) {
            View::addFlash('logistic_companies', 'danger', 'Access denied');
            throw new RedirectException('/logistic_companies');
        }
        $data = [
            'id' => $_POST['id'],
            'carrier_id' => $_POST['carrier_id'] ?? 0,
        ];
        $lc = new LogisticCompany($data);
        $lc->save(array_keys($data));
        throw new RedirectException('/logistic_companies');
    }

    /** @throws */
    public function lockLC()
    {
        if (empty($_POST) || empty($_POST['id'])) {
            View::addFlash('logistic_companies', 'danger', 'Access denied');
            throw new RedirectException('/logistic_companies');
        }
        $data = [
            'id' => $_POST['id'],
            'status' => 1 - ($_POST['status'] ?? 1),
        ];
        $lc = new LogisticCompany($data);
        $lc->save(array_keys($data));
        throw new RedirectException('/logistic_companies');
    }

    /** @throws */
    public function warehouses()
    {
        if (empty($_GET) || empty($_GET['id'])) {
            View::addFlash('logistic_companies', 'danger', 'Access denied');
            throw new RedirectException('/logistic_companies');
        }
        $lc = LCModel::getById($_GET['id']);
        if (empty($lc)) {
            View::addFlash('logistic_companies', 'danger', 'Unknown logistic company');
            throw new RedirectException('/logistic_companies');
        }
        $data = [
            'logistic_company' => $lc,
            'countries' => Countries::getAll(),
            'langs' => Langs::getAll(),
            'default_lang' => $lc->getLangId(),
            'currencies' => Arr::map(Currencies::getAll(), 'id', 'currency'),
            'timezones' => ['UTC' => 'UTC'],
            'warehouses' => Warehouses::getAll($lc->getId()),
        ];
        $this->render($data, 'logistic_companies/warehouses');
    }

    public function getTimezones()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST) || empty($_POST['country_id']))
            return $this->response = ['error' => 'Access denied'];
        return $this->response = ['success' => ['UTC' => 'UTC'] + Timezones::getByCountryCode($_POST['country_id'])];
    }

    public function createWarehouse()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST))
            return $this->response = ['error' => 'Access denied'];
        $result = Warehouses::createWithMinimumLocations($_POST);
        if ($result !== true)
            return $this->response = ['error' => json_encode($result)];
        return $this->response = ['success' => ''];
    }

    public function uploadLocations()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST) || empty($_POST['warehouse_id']) || empty($_FILES))
            return $this->response = ['error' => 'Access denied'];
        if ($_FILES['csv']['type'] != 'text/csv')
            return $this->response = ['error' => 'File format is not CSV'];
        list($res, $name) = File::loadFile($_FILES['csv']);
        if (!$res)
            return $this->response = ['error' => $name];
        $warehouse = Warehouses::getById($_POST['warehouse_id']);
        if (empty($warehouse))
            return $this->response = ['error' => 'Warehouse is not found'];
        $res = Locations::uploadLocations($warehouse, $name);
        if ($res === true)
            return $this->response = ['success' => ''];
        return $this->response = ['error' => $res];
    }

    /** @throws */
    public function sa()
    {
        if (empty($_GET) || empty($_GET['id'])) {
            View::addFlash('logistic_companies', 'danger', 'Access denied');
            throw new RedirectException('/logistic_companies');
        }
        $lc = LCModel::getById($_GET['id']);
        if (empty($lc)) {
            View::addFlash('logistic_companies', 'danger', 'Unknown logistic company');
            throw new RedirectException('/logistic_companies');
        }
        $_SESSION[LCModel::LC_SA] = http_build_query($_GET);
        $data = [
            'logistic_company' => $lc,
            'sas' => Users::getAllRoles($lc->getId(), Roles::SA, true),
            'warehouses' => Arr::map(Warehouses::getAll($lc->getId()), 'id', 'name'),
        ];
        $this->render($data, 'logistic_companies/sa');
    }

    public function createSA()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_POST))
            return $this->response = ['error' => 'Access denied'];
        $res = Users::createSA($_POST, $this->user);
        if ($res === true)
            return $this->response = ['success' => ''];
        return $this->response = ['error' => json_encode($res)];
    }

    /** @throws */
    public function lockSA()
    {
        if (empty($_POST) || empty($_POST['id'])) {
            View::addFlash('logistic_companies', 'danger', 'Access denied');
            throw new RedirectException('/logistic_companies');
        }
        $data = [
            'id' => $_POST['id'],
            'status' => 1 - ($_POST['status'] ?? 1),
        ];
        $user = new User($data);
        $user->save(array_keys($data));
        throw new RedirectException('/logistic_companies/sa?' . $_SESSION[LCModel::LC_SA]);
    }
}
