<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Address;
use app\core\entities\File;
use app\core\entities\Order;
use app\core\entities\Supplier;
use app\core\entities\SupplierUser;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\ErrorHandler;
use app\core\helpers\File as FileHelper;
use app\core\helpers\Validator;
use app\core\models\Warehouses;
use app\core\Storage;
use app\core\helpers\FileWrapper;

class Suppliers extends \app\core\models\Suppliers
{
    public static function validate(Supplier $supplier, User $authUser, string $mode = 'admin') : array
    {
        $rules = [
            'name' => [
                'val' => $supplier->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 100]]
            ],
            'contact' => [
                'val' => $supplier->getContact(),
                'rules' => [Validator::TYPE_STRING => [0, 15]]
            ],
            'email' => [
                'val' => $supplier->getEmail(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'type_account' => [
                'val' => $supplier->getTypeAccount(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, 3]]
            ],
            'day30' => [
                'val' => $supplier->getDay30(),
                'rules' => [Validator::TYPE_INT => [0, 100000]]
            ],
            'user_id' => [
                'val' => $supplier->getUserId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, 100000]]
            ],
        ];
        if ($mode === 'user')
            unset($rules['type_account'], $rules['day30'], $rules['user_id']);
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        if (!empty($supplier->getId())) {
            $supplier_by_id = Suppliers::getById($supplier->getId());
            if ($supplier_by_id === null)
                $validator->addError("company_not_found", "Company not found");
            if (!$authUser->hasUserAccess($supplier_by_id->getId(), $supplier_by_id->getUserId()) && $mode !== 'user')
                $validator->addError("access_denied", "Access to the edit this company denied");
        }
        $supplier_by_name = Suppliers::getByName(trim($supplier->getName()));
        if ($supplier_by_name && $supplier_by_name->getId() != $supplier->getId())
            $validator->addError("not_unique", "This name is already used.", 'name');
        return $validator->getErrors();
    }

    public static function getSuppliersBySupplierSide($warehouse_id, $is_admin, $user_id)
    {
        return $is_admin ? self::getSuppliersByAdmin($warehouse_id, $user_id)
                         : self::getSuppliersByUser($warehouse_id, $user_id);
    }

    public static function getWarehousesById($id)
    {
        $supplier = self::getById($id);
        if (empty($supplier) || empty($supplier->getWarehouses()))
            return [];

        return (new SafeMysql())->fetchAll("
            SELECT id, name
            FROM warehouses
            WHERE id IN (:warehouse_ids)", ['warehouse_ids' => $supplier->getWarehouses()]);
    }

    public static function getByUser(User $user)
    {
        return $user->isClientSide()
                    ? self::getSuppliersBySupplierSide($user->getWarehouseId(), $user->isRoleSupplierAdmin(), $user->getId())
                    : [];
    }

    public static function getSuppliersByWarehouse($warehouse_id, $supplier_ids = null)
    {
        $warehouse_id = (int) $warehouse_id;
        $suppliers = (new SafeMysql())->fetchAll("
            SELECT sup.id, sw.sage_id, sw.address, sup.warehouses
            FROM suppliers sup
                LEFT JOIN suppliers_warehouses sw ON sup.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id
            " . (empty($supplier_ids) ? '' : 'WHERE sup.id IN (:supplier_ids)'), [
            'warehouse_id' => $warehouse_id,
            'supplier_ids' => $supplier_ids ?: []
        ]);
        foreach ($suppliers as $key => $supplier) {
            $warehouses = json_decode($supplier['warehouses']);
            if (empty($warehouses) || !in_array($warehouse_id, $warehouses))
                unset($suppliers[$key]);
            else {
                $address = (new Address(json_decode($supplier['address'] ?? '') ?? []))->toSupplierAddressArray();
                unset($supplier['address'], $supplier['warehouses']);
                $suppliers[$key] = array_merge($supplier, $address);
            }
        }
        return $suppliers;
    }

    public static function saveLogo(File $logo)
    {
        $path = substr(md5($logo->getName()), 0, 10) . time();
        $logoPath = SUPPLIERS_STORAGE . $path;
        FileHelper::convertImageSize($logo->getTmpName(), 300, 300);
        if (FileHelper::moveUploadedFile($logo->getTmpName(), $logoPath))
            return $path;
        return false;
    }

    public static function destroyOldLogo(int $id)
    {
        if (!empty($logo = (new SafeMysql())->fetchScalar("SELECT logo FROM suppliers WHERE id = :id", ['id' => $id]))) {
            $path = Storage::getFile(SUPPLIERS_STORAGE, $logo);
            if (file_exists($path)) unlink($path);
        }
    }

    private static function saveCustomReturnDoc(File $file): array
    {
        switch ($file->getError()) {
            case 0:
                // getType() is what the browser claims; the content itself must be a PDF too
                if ($file->getType() !== 'application/pdf' || FileHelper::getMimeType($file->getTmpName()) !== 'application/pdf')
                    return [false, 'Custom return doc must be in PDF format'];
                $path = substr(md5($file->getName()), 0, 10) . time() . '.pdf';
                $returnPath = CUSTOM_RETURN_DOC_STORAGE . $path;
                FileHelper::handleDir(SUPPLIERS_STORAGE);
                FileHelper::handleDir(CUSTOM_RETURN_DOC_STORAGE);
                if (FileHelper::moveUploadedFile($file->getTmpName(), $returnPath))
                    return [true, $path];
                else
                    return [false, 'Error file'];
            case 4:
                return [true, ''];
            default:
                return [false, ErrorHandler::errorFileUpload($file->getError())];
        }
    }

    public static function destroyCustomReturnDoc($id): void
    {
        $supplier = self::getById($id);
        if (empty($supplier))
            return;
        $doc = $supplier->getCustomReturnDoc();
        if (empty($doc))
            return;
        $path = Storage::getFile(CUSTOM_RETURN_DOC_STORAGE, $doc);
        if (file_exists($path))
            unlink($path);
    }

    public static function addSupplier($supplier_data, $files, $null_logo, User $user, Warehouse $warehouse, $mode = 'admin')
    {
        $fields_to_save = array_keys($supplier_data);
        $supplier = new Supplier($supplier_data);
        $errors = self::validate($supplier, $user, $mode);
        $hasLogo = !empty($files['logo']) && !empty($files['logo']['tmp_name']) && !empty($files['logo']['name']);
        $logo_obj = new File($files['logo']);
        if ($hasLogo && !FileHelper::isRasterImage($logo_obj->getTmpName()))
            $errors[] = ['message' => 'Logo is not image'];
        else {
            if ($hasLogo && ((!empty($null_logo) && $null_logo === 'FALSE') || empty($null_logo))) {
                $logo = self::saveLogo($logo_obj);
                if ($logo) {
                    $fields_to_save[] = 'logo';
                    $supplier->setLogo($logo);
                } else
                    $errors[] = ['message' => 'Logo error'];
            } elseif (!empty($null_logo) && $null_logo === 'TRUE'){
                $fields_to_save[] = 'logo';
                $supplier->setLogo(null);
                self::destroyOldLogo($supplier->getId());
            } elseif (!$hasLogo && !empty($supplier->getLogo()))
                unset($fields_to_save['logo']);
        }
        if (empty($errors)) {
            if (!empty($files['custom-return-doc']['name'])) {
                $return_pdf_res = self::saveCustomReturnDoc(new File($files['custom-return-doc']));
                if ($return_pdf_res[0] === false)
                    $errors[] = ['message' => $return_pdf_res[1]];
                else {
                    self::destroyCustomReturnDoc($supplier->getId());
                    $supplier->setCustomReturnDoc($return_pdf_res[1]);
                    $fields_to_save[] = 'custom_return_doc';
                }
            } elseif (($supplier_data['clear_custom_return_doc'] ?? '') && !empty($supplier->getId())) {
                self::destroyCustomReturnDoc($supplier->getId());
                $supplier->setCustomReturnDoc('');
                $fields_to_save[] = 'custom_return_doc';
            } else
                $supplier->setCustomReturnDoc((new SafeMysql())->fetchScalar('SELECT custom_return_doc FROM suppliers WHERE id = :id', ['id' => $supplier->getId()]));
        }
        if ($supplier->isCustomReturn() && !$supplier->existCustomReturnDocFile())
            $errors[] = ['message' => 'Not found doc for custom customer return'];
        if (empty($errors)) {
            list($is_new, $data) = self::handleDifference($supplier);
            $supplier->save($fields_to_save);
            if (!$is_new && !empty($data)) {
                [$warehouses_all, $expired_locs] = Housekeeping::getWarehousesWithExpiredLocations($warehouse->getLCId());
                if (isset($data['has_weeks_before_expiry']) && $data['has_weeks_before_expiry']['old'] === true && $data['has_weeks_before_expiry']['new'] === false)
                    SKULocations::restoreExpired($supplier->getId(), $warehouses_all, $supplier_data, $warehouse->getId(), $user->getId());
                else {
                    if (isset($data['weeks_before_expiry']) && $data['weeks_before_expiry']['old'] > $data['weeks_before_expiry']['new'])
                        SKULocations::restoreExpired($supplier->getId(), $warehouses_all, $supplier_data, $warehouse->getId(), $user->getId());
                    $sup_data = Suppliers::getWeeksBeforeExpiryBySupplierId($supplier->getId());
                    if (!empty($sup_data) && !empty($sup_data['warehouses'])) {
                        $warehouses = json_decode($sup_data['warehouses'], true);
                        if (!empty($warehouses))
                            Housekeeping::setExpiredSKUs($supplier->getId(), $warehouses, $sup_data['weeks_before_expiry'], $warehouses_all, $expired_locs);
                    }
                }
                if (isset($data['utilise_despatch_date']) || isset($data['days_before_despatch']))
                    Orders::checkScheduled($supplier);
            }
            if ($is_new)
                SuppliersHistory::create($supplier->getId(), $data, $user->getId());
            else
                SuppliersHistory::updateInfo($supplier->getId(), $data, $user->getId());
        }
        return [$supplier, $errors];
    }

    public static function handleDifference(Supplier $supplier): array
    {
        $supplier_data = $supplier->toArray();
        unset($supplier_data['id'], $supplier_data['warehouses'], $supplier_data['del']);
        $old_supplier = self::getById($supplier->getId());
        if (empty($supplier) || empty($supplier->getId()) || empty($old_supplier))
            return [true, $supplier_data];
        $old_supplier_data = $old_supplier->toArray();
        unset($old_supplier_data['id'], $old_supplier_data['warehouses'], $old_supplier_data['del']);
        $diff_data = [];
        $main_fields = [
            'name' => 'string',
            'contact' => 'string',
            'type_account' => 'int',
            'user_id' => 'int',
            'logo' => 'string',
            'email' => 'string',
            'shopify_barcode_field' => 'string',
            'order_inserts' => 'boolean',
            'default_description_of_goods' => 'string',
            'packing_slip_additional_information' => 'string',
            'packing_slip_batch_expired' => 'boolean',
            'product_price' => 'int',
            'shopify_locations' => 'int',
            'offer_exchanges' => 'boolean',
            'offer_returns' => 'boolean',
            'has_weeks_before_expiry' => 'boolean',
            'weeks_before_expiry' => 'int',
            'utilise_despatch_date' => 'boolean',
            'days_before_despatch' => 'int',
            'custom_return' => 'boolean',
            'custom_return_doc' => 'string',
        ];
        foreach ($supplier_data as $field => $new_supplier_value) {
            if (!array_key_exists($field, $main_fields))
                continue;
            switch ($main_fields[$field]) {
                case 'string':
                    $default = ''; break;
                case 'boolean':
                    $default = false; break;
                case 'int':
                    $default = 0; break;
                default:
                    $default = '';
            }
            $old_value = $old_supplier_data[$field] ?? $default;
            $new_value = $new_supplier_value ?: $default;
            settype($old_value, $main_fields[$field]);
            settype($new_value, $main_fields[$field]);
            if ($old_value !== $new_value)
            $diff_data[$field] = [
                'old' => $old_value,
                'new' => $new_value
            ];
        }
        return [false, $diff_data];
    }

    public static function updateWarehouseAccess($id, $warehouse_id, $access, $user_id): array
    {
        $supplier = self::getById($id);
        if (empty($supplier))
            return [false, 'Access not changed. Supplier error'];
        $warehouses = $supplier->getWarehouses();
        if (empty($warehouses))
            $warehouses = [];
        if ($access) {
            if (!in_array($warehouse_id, $warehouses))
                $warehouses[] = $warehouse_id;
            else
                return [false, null];
        } else {
            if (!self::checkExistActiveOrders($id, $warehouse_id)) {
                if (($w = array_search($warehouse_id, $warehouses)) !== false)
                    unset($warehouses[$w]);
                else
                    return [false, null];
            } else
                return [false, 'Access not changed. Exist active orders'];

        }
        $supplier->setWarehouses($warehouses);
        $supplier->save(['id', 'warehouses']);
        SuppliersHistory::changeAccess($id, $warehouse_id, $access, $user_id);
        return [true, ['Access changed']];
    }

    public static function checkExistActiveOrders(int $id, int $warehouse_id): bool
    {
        return (boolean)(new SafeMysql())->fetchScalar("
            SELECT 1
            FROM orders
            WHERE supplier_id = :supplier_id AND
                  warehouse_id = :warehouse_id AND 
                  SUBSTR(status, 1, 2) <= :status_despatched AND
                  status <> :status_canceled
            LIMIT 1", [
            'supplier_id' => $id,
            'warehouse_id' => $warehouse_id,
            'status_despatched' => Order::DESPATCHED_NUM,
            'status_canceled' => Order::CANCELLED_NUM
        ]);
    }

    public static function getLogoPath($logo): string
    {
        $path = Storage::getFile(SUPPLIERS_STORAGE, $logo);
        return !empty($path) ? $path : '';
    }

    public static function showLogoSrc($logo): string
    {
        $path = self::getLogoPath($logo);
        if (!$path)
            return '';
        $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($path)));
        if (!$file_wrapper->isFile($path))
            return '';
        $image_mime_type = $file_wrapper->getMimeType($path);
        $image = stream_get_contents($file_wrapper->getStream($path));
        $image = base64_encode($image);
        return "data:$image_mime_type;base64,$image";
    }

    public static function getInstanceByUser(int $user_id): ?Supplier
    {
        $data = (new SafeMysql())->fetchOne("
            SELECT DISTINCT s.*
            FROM suppliers s
                JOIN supplier_users su ON s.id = su.supplier_id AND su.user_id = :user_id", ['user_id' => $user_id]);
        return !empty($data) ? new Supplier($data) : null;
    }

    public static function addUser($supplier_id, $user_id, $warehouse_id, $create_user_id)
    {
        $su_data = [
            'supplier_id' => (int)$supplier_id,
            'user_id' => (int)$user_id
        ];
        $supplier_user = new SupplierUser($su_data);
        $supplier_user->save($su_data);
        SuppliersHistory::addUser($supplier_id, $warehouse_id, $user_id, $create_user_id);
    }

    public static function getSuppliersBilling(int $lc_id, int $warehouse_id, array $types, Pagination $pagination)
    {
        $warehouses_lc = Warehouses::getAllId($lc_id);
        if ($warehouse_id && !in_array($warehouse_id, $warehouses_lc))
            return [];
        $data = (new SafeMysql())->fetchAll("
            SELECT SQL_CALC_FOUND_ROWS s.*, s.id, s.name, sw.id sw_id, sw.is_billing, sw.billing_address 
            FROM suppliers s 
                LEFT JOIN suppliers_warehouses sw ON s.id = sw.supplier_id AND sw.warehouse_id = :warehouse_id
            WHERE JSON_CONTAINS(s.warehouses, :warehouse_id_json)
            ORDER BY s.name
            LIMIT :limit OFFSET :offset", [
            'warehouse_id' => $warehouse_id,
            'warehouse_id_json' => $warehouse_id,
            'limit' => $pagination->getPerPage(),
            'offset' => $pagination->getOffset()
        ]);
        $pagination->setTotalCount((new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count'));
        $suppliers = [];
        foreach ($data as $supplier_data)
            $suppliers[$supplier_data['sw_id']] = [
                'id' => $supplier_data['id'],
                'name' => $supplier_data['name'],
                'is_billing' => $supplier_data['is_billing'],
                'billing_address' => $supplier_data['billing_address'],
                'charges' => [],
                'recurring' => [],
            ];
        $charges = Charges::getStandardChargesBilling(array_keys($suppliers), $types);
        $recurring = Charges::getRecurringChargesBilling(array_keys($suppliers));
        foreach ($suppliers as $sw_id => &$supplier) {
            $supplier['charges'] = $charges[$sw_id] ?? [];
            $supplier['recurring'] = $recurring[$sw_id] ?? [];
        }
        unset($supplier);
        return $suppliers;
    }
}
