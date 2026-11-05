<?php

namespace app\backend\controllers;

use app\backend\models\Carriers;
use app\backend\models\ShippingServiceWarehouses;
use app\backend\models\ShippingServices as ShippingServicesModel;
use app\core\helpers\Arr;
use app\core\exceptions\RedirectException;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\WarehouseCarriers;
use app\core\models\Warehouses;
use app\core\helpers\FileWrapper;

class ShippingServices extends AuthController
{
    /** @throws */
    public function index()
    {
        $carriers = WarehouseCarriers::getCarriers($this->warehouse_id);
        if (empty($carriers)) {
            View::addFlash('ShippingServices', 'danger', 'Carriers for this warehouse are not found');
            throw new RedirectException("/");
        }
        $shipping_services = ShippingServicesModel::getListForWarehouse($this->warehouse_id, $carriers);
        $default_shipping_service = Warehouses::getDefaultShippingService($this->warehouse_id);
        $shipping_services_lst = [0 => "none"];
        array_map(function($elem) use(&$shipping_services_lst) {
            $shipping_services_lst[$elem['id']] = "{$elem['ccode']} - {$elem['code']}";
        }, $shipping_services);
        $logos = $carrier_logos = [];
        foreach ($shipping_services as $shipping_service)
            if (empty($logos[$shipping_service['logo']])) {
                $img_string = '';
                $path = ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR . $shipping_service['carrier_logo'];
                if ($shipping_service['carrier_logo'] && $res = (new FileWrapper(FileWrapper::getContainerByPath($path)))->getStream($path))
                    $img_string =  stream_get_contents($res);
                $carrier_logos[$shipping_service['carrier_logo']] = base64_encode($img_string);

                $img_string = '';
                $path = ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR . $shipping_service['logo'];
                if ($shipping_service['logo'] && $res = (new FileWrapper(FileWrapper::getContainerByPath($path)))->getStream($path))
                    $img_string =  stream_get_contents($res);
                $logos[$shipping_service['logo']] = base64_encode($img_string);
            }
        $data = [
            'logos' => $logos,
            'carrier_logos' => $carrier_logos,
            'shipping_services' => $shipping_services,
            'carriers' => Arr::maps(Carriers::getByIds($carriers), 'id', ['title', 'max_weight', 'is_special']),
            'shipping_services_lst' => $shipping_services_lst,
            'default_shipping_service_id' => empty($default_shipping_service) ? 0 : $default_shipping_service['shipping_service_id']
        ];
        $this->render($data, 'warehouse/shipping_services');
    }

    public function changeServices()
    {
        $carriers = WarehouseCarriers::getCarriers($this->warehouse_id);
        $shipping_services = [];
        if (!empty($carriers))
            $shipping_services = ShippingServicesModel::getListForWarehouse($this->warehouse_id, $carriers);
        $to_add = [];
        $to_remove = [];
        $new_shipping_services = !empty($_POST['shipping_service']) ? array_keys($_POST['shipping_service']) : [];
        foreach ($shipping_services as $shipping_service)
            if (empty($shipping_service['warehouse_id'])) {
                if (in_array($shipping_service['id'], $new_shipping_services))
                    $to_add[] = $shipping_service['id'];
            } else {
                if (!in_array($shipping_service['id'], $new_shipping_services))
                    $to_remove[] = $shipping_service['id'];
            }
        ShippingServicesModel::unsetListForWarehouse($this->warehouse_id, $to_remove, $this->user->getId());
        ShippingServicesModel::setListForWarehouse($this->warehouse_id, $to_add, $this->user->getId());
        return $this->response = ['success'];
    }

    public function addingShippingService()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (!$this->user->isRoleSA())
            return $this->response = json_encode(['result' => 'error', 'errors' => ['Access denied']]);
        if (empty($_POST))
            return $this->response = json_encode(['result' => 'error', 'errors' => ['Error data']]);
        $method = '\app\backend\models\ShippingServices::' . (empty($_POST['id']) ? 'add' : 'change') . 'ShippingService';
        [$res, $messages] = $method($_POST, $_FILES);
        return $this->response = json_encode(['result' => $res, 'errors' => $messages]);
    }

    /** @throws */
    public function setDefault()
    {
        if (empty($_POST) || !isset($_POST['default_shipping_service_id'])) {
            View::addFlash('ShippingServices', 'danger', 'Access denied');
            throw new RedirectException("/");
        }
        $shipping_service_id = (int)$_POST['default_shipping_service_id'];
        if (!empty($shipping_service_id) && ShippingServiceWarehouses::checkShippingServiceServiceAndWarehouseId($shipping_service_id, $this->warehouse_id)) {
            View::addFlash('ShippingServices', 'danger', 'Shipping service is not active');
            throw new RedirectException('/shipping_services');
        }
        ShippingServicesModel::setDefaultForWarehouse($this->warehouse_id, $shipping_service_id, $this->user->getId());
        View::addFlash('ShippingServices', 'success', 'Default shipping service is set');
        throw new RedirectException('/shipping_services');
    }
}