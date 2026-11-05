<?php

namespace app\backend\controllers;

use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\View;
use app\backend\models\ReturnCarriers as ReturnCarriersModel;
use app\core\helpers\Request;
use app\core\entities\ReturnCarrier;
use app\backend\models\Countries;
use app\core\models\Warehouses;
use app\core\models\SuppliersWarehouses;

class ReturnCarriers extends AuthController
{
    private $model;

    public function __construct()
    {
        $this->model = new ReturnCarriersModel();
        $this->model->current_page = (int)(Request::get('c') ?? 1);
        $this->model->id = (int)(Request::get('id') ?? 0);
        $this->model->service_id = (int)(Request::get('service_id') ?? 0);
        $this->model->name = Request::get('name') ?? '';
        $this->model->credentials_key = Request::get('credentials_key') ?? '';
        $this->model->is_test = (int)(bool)Request::get('is_test');
        $this->model->countries = Countries::sanitizeJsonCodes(Request::get('countries') ?? "[]");
    }

    /** @throws */
    public function index()
    {
        if ($errors = $this->model->validationCurrentPage()) {
            View::addFlash('return_carriers_index', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException('/return_carriers');
        }
        $records = ReturnCarriersModel::getWithLimit($this->logistic_company->getId(), ($this->model->current_page - 1) * ReturnCarriersModel::COUNT_ON_PAGE, ReturnCarriersModel::COUNT_ON_PAGE);
        $ids = ReturnCarriersModel::getIdsFromReturnCarriers($records);
        $used_ids = SuppliersWarehouses::getUsedReturnCarriers($ids);
        $this->render([
            'records' => $records,
            'used_ids' => $used_ids,
            'total_records' => ReturnCarriersModel::getCount($this->logistic_company->getId()),
            'count_on_page' => ReturnCarriersModel::COUNT_ON_PAGE,
            'current_page' => $this->model->current_page
        ], 'warehouse/return_carriers');
    }

    /** @throws */
    public function del()
    {
        if ($errors = array_merge($this->model->validationCurrentPage(), $this->model->validationId())) {
            View::addFlash('return_carriers_del', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
        }
        $location = ReturnCarriersModel::getById($this->model->id);
        if (!$location) {
            View::addFlash('return_carriers_del', 'danger', 'Invalid information');
            throw new RedirectException('/return_carriers');
        }
        if (SuppliersWarehouses::checkIsUsedReturnCarrier($this->model->id)) {
            View::addFlash('return_carriers_del', 'danger', 'Return carrier is attach to supplier');
            throw new RedirectException('/return_carriers');
        }
        $location->setState((int)(bool)Request::get('lock'));
        $location->save(['id', 'state']);
        throw new RedirectException('/return_carriers?c=' . $this->model->current_page);
    }

    /** @throws */
    public function edit()
    {
        if ($errors = array_merge($this->model->validationCurrentPage(), $this->model->validationId())) {
            View::addFlash('return_carriers_add', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException('/return_carriers');
        }
        $location = ReturnCarriersModel::getById($this->model->id);
        $this->render([
            'all_countries' => Countries::getAll(),
            'current_page' => $this->model->current_page,
            'id' => $location->getId(),
            'service_id' => $location->getServiceId(),
            'name' => $location->getName(),
            'credentials_key' => $location->getCredentialsKey(),
            'is_test' => $location->getIsTest(),
            'countries' => $location->getCountries(),
        ], 'warehouse/create_edit_return_carriers');
    }

    /** @throws */
    public function create()
    {
        if ($errors = $this->model->validationCurrentPage()) {
            View::addFlash('return_carriers_create', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException('/return_carriers/create');
        }
        $countries = json_decode($this->model->countries);
        $this->render([
            'all_countries' => Countries::getAll(),
            'current_page' => $this->model->current_page,
            'id' => 0,
            'service_id' => $this->model->service_id > 0 ? $this->model->service_id : 1,
            'name' => $this->model->name,
            'credentials_key' => $this->model->credentials_key,
            'is_test' => $this->model->is_test,
            'countries' => empty($countries) ? [Warehouses::getCountryId($this->warehouse_id)] : $countries
        ], 'warehouse/create_edit_return_carriers');
    }

    /** @throws */
    public function save()
    {
        if ($errors = array_merge($this->model->validationCurrentPage(), $this->model->validationId(true), $this->model->validationBeforeSave())) {
            View::addFlash('return_carriers_save', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException($this->model->getEditUri());
        }
        if ($errors = $this->model->validationNameIsUnique()) {
            View::addFlash('return_carriers_save', 'danger', Arr::column($errors, 'message'));
            throw new RedirectException($this->model->getEditUri());
        }
        if ($this->model->id) {
            $location = ReturnCarriersModel::getById($this->model->id);
            $old_location = clone $location;
        } else {
            $location = new ReturnCarrier();
        }
        if (!$location) {
            View::addFlash('return_carriers_save', 'danger', 'Invalid information');
            throw new RedirectException('/return_carriers');
        }
        $location->setServiceId($this->model->service_id);
        $location->setName($this->model->name);
        $location->setCredentialsKey($this->model->credentials_key);
        $location->setIsTest($this->model->is_test);
        $location->setCountries($this->model->countries);
        if (isset($old_location) && $location == $old_location) {
            View::addFlash('return_carriers_save', 'warning', 'Nothing to change');
            throw new RedirectException($this->model->getEditUri());
        }
        $location->save(['id', 'service_id', 'name', 'credentials_key', 'is_test', 'countries']);
        throw new RedirectException('/return_carriers?c=' . $this->model->current_page);
    }
}