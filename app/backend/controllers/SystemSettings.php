<?php

namespace app\backend\controllers;

use app\backend\models\Carriers as CarriersModel;
use app\backend\models\LogisticCompanies;
use app\backend\models\ShippingServices as ShippingServicesModel;
use app\core\entities\ShippingService;
use app\core\exceptions\RedirectException;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\helpers\FileWrapper;

class SystemSettings extends SystemController
{
    public function index()
    {
        $data = [
            'warehouses_statistic' => LogisticCompanies::getWarehousesStatistic(),
            'locations_statistic' => LogisticCompanies::getLocationsStatistic(),
            'companies_statistic' => LogisticCompanies::getCompaniesStatistic(),
            'users_statistic' => LogisticCompanies::getUsersStatistic(),
            'SKUs_statistic' => LogisticCompanies::getSKUsStatistic(),
            'ASNs_statistic' => LogisticCompanies::getASNsStatistic(),
            'orders_statistic' => LogisticCompanies::getOrdersStatistic(),
        ];
        $this->render($data, 'system_settings/dashboard');
    }

    public function carriers()
    {
        $records = CarriersModel::getAll();
        $logos = [];
        foreach ($records as $key => $record)
            if (empty($logos[$record['logo']])) {
                $path = ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR . $record['logo'];
                $res = (new FileWrapper(FileWrapper::getContainerByPath($path)))->getStream($path);
                $img_string = $res ? stream_get_contents($res) : '';
                $logos[$record['logo']] = base64_encode($img_string);
            }
        $this->render([
            'records' => CarriersModel::getAll(),
            'logistic_companies' => LogisticCompanies::getAll(),
            'logos' => $logos,
        ], 'system_settings/carriers');
    }

    /** @throws */
    public function returnCarriers()
    {
//        $this->render([], 'system_settings/return_carriers');
        throw new RedirectException('/system_settings');
    }

    public function createCarrier()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSystem())
            return $this->response = json_encode(['result' => 'error', 'errors' => ['Access denied']]);
        if (empty($_POST))
            return $this->response = json_encode(['result' => 'error', 'errors' => ['Error data']]);
        $method = '\app\backend\models\Carriers::' . (empty($_POST['id']) ? 'add' : 'change') . 'Carrier';
        [$res, $messages] = $method($_POST, $_FILES);
        return $this->response = json_encode(['result' => $res, 'errors' => $messages]);
    }

    /** @throws */
    public function shippingServices()
    {
        $carrier = CarriersModel::getById($_GET['id']);
        if (empty($_GET['id']) || !$carrier) {
            View::addFlash('shipping_services', 'danger','Carrier is not found');
            throw new RedirectException('/system_settings/carriers');
        }
        $shipping_services = ShippingServicesModel::getByCarrier($_GET['id']);
        $path = ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR . $carrier->getLogo();
        $res = (new FileWrapper(FileWrapper::getContainerByPath($path)))->getStream($path);
        $img_string = $res ? stream_get_contents($res) : '';
        $data = [
            'shipping_services' => $shipping_services,
            'carrier' => $carrier,
            'logo' =>  base64_encode($img_string)
        ];
        $this->render($data, 'system_settings/shipping_services');
    }

    public function addingShippingService()
    {
        $shipping_service_data = $_POST;
        if (empty($shipping_service_data['id']))
            unset($shipping_service_data['id']);
        $shipping_service = new ShippingService($shipping_service_data);
        $errors = ShippingServicesModel::validate($shipping_service);
        if (empty($errors)) {
            $shipping_service->save(array_keys($shipping_service_data));
            View::addFlash('shipping_service_saved', 'success', 'Shipping service ' .
                $shipping_service->getName() . ' (' . $shipping_service->getCode() . ') ' . (!empty($shipping_service_data['id']) ? 'updated' : 'created'));
        }
        return $this->response = ['result' => empty($errors) ? 'success' : 'error', 'errors' => array_map(function ($e) {
            return $e['message'];
        }, $errors)];
    }
}
