<?php

namespace app\backend\controllers;

use app\backend\models\Packages as PackagesModel;
use app\backend\models\Roles;
use app\backend\models\Suppliers;
use app\core\dbal\SafeMysql;
use app\core\models\PackageConsumables;
use app\core\models\PackageWarehouses;
use app\core\entities\Package;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\backend\models\Consumables;

class Packages extends AuthController
{
    /** @throws RedirectException */
    public function index()
    {
        $id = Request::getPositiveId();
        if (empty($id))
            throw new RedirectException('/forbidden');
        $suppliers = $this->getSuppliers();
        if (empty($suppliers[$id])) {
            View::addFlash('packages', 'danger', 'Access denied');
            throw new RedirectException('/forbidden');
        }
        $_SESSION[PackagesModel::FILTER_PARAM] = http_build_query($_GET);
        $data = [
            'packages' => PackagesModel::getPackages($id, $this->warehouse_id),
            'back' => '/suppliers/settings/?' . (!empty($_SESSION[PackagesModel::FILTER_PARAM]) ? $_SESSION[PackagesModel::FILTER_PARAM] : ''),
            'supplier_id' => $id,
            'supplier_name' => $suppliers[$id],
        ];
        $this->render($data, 'consumables/packages');
    }

    /** @throws RedirectException */
    public function addPackage()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        if (empty($id))
            throw new RedirectException('/forbidden');
        $roles = Roles::getById($this->user->getRoleId());
        [$packagings, $consumables] = Consumables::getConsumables($this->logistic_company->getId(), $id, $roles->isClientSide());
        if (empty($packagings)) {
            View::addFlash('packages', 'danger', 'Packaging is not found');
            throw new RedirectException('/packages/?' . (!empty($_SESSION[PackagesModel::FILTER_PARAM]) ? $_SESSION[PackagesModel::FILTER_PARAM] : ''));
        }
        $package = new Package();
        $package->setState(Package::STATE_ACTIVE);
        $package->setSupplierId($id);
        $package->setPackagingId(array_keys($packagings)[0]);
        $data = [
            'package' => $package,
            'package_warehouses' => PackageWarehouses::getWarehousesByPackageId($package->getId()),
            'package_consumables' => PackageConsumables::getByPackageId($package->getId()),
            'in_package' => false,
            'packagings' => $packagings,
            'consumables' => $consumables,
            'supplier' => Suppliers::getById($id),
            'back' => "/packages/?id=$id",
        ];
        $this->render($data, 'consumables/add_package');
    }

    /** @throws RedirectException */
    public function addingPackage()
    {
        if (empty($_POST) || empty($_POST['package'])) {
            View::addFlash('packages', 'danger', 'Access denied');
            throw new RedirectException('/forbidden');
        }
        $package_data = $_POST['package'];
        $warehouses_data = $_POST['warehouses'] ?? [];
        $consumables_data = $_POST['consumables'] ?? [];
        $in_package = !empty($_POST['in_package']);
        $packagings_data = $in_package ? ($_POST['in_packages'] ?? []) :  [];
        if (!empty($packagings_data))
            unset($packagings_data[$package_data['packaging_id']]);
        $package = new Package($package_data);
        $roles = Roles::getById($this->user->getRoleId());
        $errors = PackagesModel::validate($package, $this->logistic_company->getId(), $warehouses_data, $consumables_data, $roles->isClientSide());
        if (empty($errors)) {
            if (empty($package_data['id']))
                unset($package_data['id']);
            $fields_to_save = array_keys($package_data);
            $mysql = new SafeMysql();
            $mysql->beginTransaction();
            $package->save($fields_to_save);
            $supplier = Suppliers::getById($package->getSupplierId());
            foreach ($supplier->getWarehouses() as $warehouse_id)
                if (!empty($warehouses_data[$warehouse_id]))
                    PackageWarehouses::addPackageWarehouse($package->getId(), $warehouse_id);
                else
                    PackageWarehouses::delPackageWarehouse($package->getId(), $warehouse_id);
            $consumables = PackageConsumables::getByPackageId($package->getId());
            $add_consum = $upd_consum = $del_consum = [];
            foreach ($consumables_data + $packagings_data as $consumable_id => $quantity) {
                if (empty($quantity)) {
                    if (!empty($consumables[$consumable_id])) {
                        $del_consum[$consumable_id] = 1;
                        unset($consumables[$consumable_id]);
                    }
                } elseif (empty($consumables[$consumable_id]))
                    $add_consum[$consumable_id] = $quantity;
                else {
                    if ($quantity != $consumables[$consumable_id])
                        $upd_consum[$consumable_id] = $quantity;
                    unset($consumables[$consumable_id]);
                }
            }
            $del_consum += $consumables;
            PackageConsumables::addPackageConsumables($package->getId(), $add_consum);
            PackageConsumables::updPackageConsumables($package->getId(), $upd_consum);
            PackageConsumables::delPackageConsumables($package->getId(), $del_consum);
            $mysql->commit();
            throw new RedirectException("/packages/?id={$package->getSupplierId()}");
        }
        View::addFlash('packages', 'danger', Arr::column($errors, 'message'));
        $this->addPackageAgain($package, $warehouses_data, $consumables_data, $in_package, $packagings_data);
        return;
    }

    private function addPackageAgain(Package $package, $warehouses_data, $consumables_data, $in_package, $packagings_data)
    {
        $this->disableWarehouseChange();
        $roles = Roles::getById($this->user->getRoleId());
        [$packagings, $consumables] = Consumables::getConsumables($this->logistic_company->getId(), $package->getSupplierId(), $roles->isClientSide());
        $data = [
            'package' => $package,
            'package_warehouses' => $warehouses_data,
            'package_consumables' => $consumables_data + $packagings_data,
            'in_package' => $in_package,
            'packagings' => $packagings,
            'consumables' => $consumables,
            'supplier' => Suppliers::getById($package->getSupplierId()),
            'back' => "/packages/?id={$package->getSupplierId()}",
        ];
        $this->render($data, 'consumables/add_package');
    }

    /** @throws RedirectException */
    public function editPackage()
    {
        $this->disableWarehouseChange();
        $id = Request::getPositiveId();
        $supplier_id = Request::getPositiveId($_GET, 'supplier_id');
        if (empty($id) || empty($supplier_id))
            throw new RedirectException('/forbidden');
        $package = PackagesModel::getById($id);
        if (empty($package) || $package->getSupplierId() != $supplier_id)
            throw new RedirectException('/forbidden');
        $roles = Roles::getById($this->user->getRoleId());
        [$packagings, $consumables] = Consumables::getConsumables($this->logistic_company->getId(), $supplier_id, $roles->isClientSide());
        $package_consumables = PackageConsumables::getByPackageId($package->getId());
        $in_package = false;
        foreach ($packagings as $packaging_id => $value)
            if (!empty($package_consumables[$packaging_id])) {
                $in_package = true;
                break;
            }
        $data = [
            'package' => $package,
            'package_warehouses' => PackageWarehouses::getWarehousesByPackageId($package->getId()),
            'package_consumables' => $package_consumables,
            'in_package' => $in_package,
            'packagings' => $packagings,
            'consumables' => $consumables,
            'supplier' => Suppliers::getById($supplier_id),
            'back' => "/packages/?id=$supplier_id",
        ];
        $this->render($data, 'consumables/add_package');
    }

    /** @throws RedirectException */
    public function lockPackage()
    {
        $id = Request::getPositiveId();
        $lock = isset($_GET['lock']) ? $_GET['lock'] : 0;
        $back = '/packages/?' . (!empty($_SESSION[PackagesModel::FILTER_PARAM]) ? $_SESSION[PackagesModel::FILTER_PARAM] : '');
        if (empty($id)) {
            View::addFlash('packages', 'danger', 'Package is not found');
        } else {
            $package = PackagesModel::getById($id);
            if (empty($package)) {
                View::addFlash('packages', 'danger', 'Package is not found');
                throw new RedirectException($back);
            }
            $package->setState($lock);
            $package->save(['id', 'state']);
        }
        throw new RedirectException($back);
    }

    /** @throws RedirectException */
    public function defaultPackage()
    {
        $id = Request::getPositiveId();
        $supplier_id = Request::getPositiveId($_GET, 'supplier_id');
        $back = '/packages/?' . (!empty($_SESSION[PackagesModel::FILTER_PARAM]) ? $_SESSION[PackagesModel::FILTER_PARAM] : '');
        if (empty($id) || empty($supplier_id))
            View::addFlash('packages', 'danger', 'Package or supplier is not found');
        else {
            $package = PackagesModel::getById($id);
            $supplier = Suppliers::getById($supplier_id);
            if (empty($package) || empty($supplier)) {
                View::addFlash('packages', 'danger', 'Package or supplier is not found');
                throw new RedirectException($back);
            }
            if (!in_array($this->warehouse_id, $supplier->getWarehouses())) {
                View::addFlash('packages', 'danger', 'Supplier not available for this warehouse');
                throw new RedirectException($back);
            }
            PackageWarehouses::setDefaultPackage($id, $supplier_id, $this->warehouse_id);
        }
        throw new RedirectException($back);
    }
}
