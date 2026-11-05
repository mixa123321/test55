<?php

namespace app\backend\models;

use app\core\entities\Carrier;
use app\core\entities\Warehouse;
use app\core\helpers\File;
use app\core\helpers\Validator;
use app\core\helpers\View;
use app\core\models\WarehouseCarriers;
use app\core\helpers\FileWrapper;

class Carriers extends \app\core\models\Carriers
{
    const COUNT_ON_PAGE = 20;

    public static function validate(Carrier $carrier)
    {
        $rules = [
            'name' => [
                'val' => $carrier->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 100]]
            ],
            'logo' => [
                'val' => $carrier->getLogo(),
                'rules' => [Validator::REQUIRED => 1]
            ],
            'code' => [
                'val' => $carrier->getCode(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 50]]
            ],
        ];
        if (!$carrier->getIsInternal())
            $rules['url'] = [
                'val' => $carrier->getUrl(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [3, 50]]
            ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors)) {
            return $errors;
        }
        if (!empty($carrier->getId())) {
            $carrier_by_id = Carriers::getById($carrier->getId());
            if ($carrier_by_id === null)
                $validator->addError("carrier_not_found", "Carrier not found");
            $carrier_by_name = Carriers::getByName($carrier->getName());
            if (!empty($carrier_by_name) && $carrier_by_name->getId() != $carrier->getId())
                $validator->addError("unique_name", "Name is not unique");
            $carrier_by_code = Carriers::getByCode($carrier->getCode());
            if (!empty($carrier_by_code) && $carrier_by_code->getId() != $carrier->getId())
                $validator->addError("unique_code", "Code is not unique");
        } else {
            $carrier_by_name = Carriers::getByName($carrier->getName());
            if (!empty($carrier_by_name))
                $validator->addError("unique_name", "Name is not unique");
            $carrier_by_name = Carriers::getByCode($carrier->getCode());
            if (!empty($carrier_by_name))
                $validator->addError("unique_code", "Code is not unique");
        }
        return $validator->getErrors();
    }

    public static function getProcessing($warehouse_id)
    {
        return WarehouseCarriers::getCarriers($warehouse_id);
    }

    public static function changingData(Carrier $old_carrier, Carrier $new_carrier)
    {
        $new_data = [];
        if ($old_carrier->getName() !== $new_carrier->getName())
            $new_data[] = 'name';
        if ($old_carrier->getLogo() !== $new_carrier->getLogo())
            $new_data[] = 'logo';
        if ($old_carrier->getCode() !== $new_carrier->getCode())
            $new_data[] = 'code';
        if ($old_carrier->getUrl() !== $new_carrier->getUrl())
            $new_data[] = 'url';
        if ($old_carrier->getIsInternal() !== $new_carrier->getIsInternal())
            $new_data[] = 'is_internal';
        if ($old_carrier->getIsSpecial() !== $new_carrier->getIsSpecial())
            $new_data[] = 'is_special';
        if ($old_carrier->getMaxWeight() !== $new_carrier->getMaxWeight())
            $new_data[] = 'max_weight';
        if (!empty($new_data))
            $new_data[] = 'id';
        return $new_data;
    }

    public static function applyCarrier($carriers_ins, Warehouse $warehouse)
    {
        $carriers_del = WarehouseCarriers::getCarriers($warehouse->getId());
        foreach ($carriers_ins as $key => $carrier_value)
            if (($pos = array_search($carrier_value, $carriers_del)) !== false)
                unset($carriers_ins[$key], $carriers_del[$pos]);
        if (empty($carriers_ins) && empty($carriers_del))
            return false;
        if (!empty($carriers_ins))
            foreach ($carriers_ins as $carriers_in)
                self::addRecord($warehouse, (int)$carriers_in);
        if (!empty($carriers_del))
            foreach ($carriers_del as $carrier_remove)
                self::deleteRecord($warehouse, (int)$carrier_remove);
        return true;
    }

    private static function normalizeUrl(string $url): string
    {
        if ($url === '')
            return $url;
        return preg_match('~^https?://~i', $url) ? $url : 'http://' . $url;
    }

    private static function uploadLogo($code, $files, &$errors)
    {
        $logo_name = '';
        if (!empty($files)) {
            if (!$files['logo']['size'])
                return false;
            // the logo is written into a web-served folder: raster images only, name built from a safe code
            // and the real image type, never from the uploaded file name
            if (!File::isRasterImage($files['logo']['tmp_name'])) {
                $errors[] = ['message' => 'Logo must be an image (png, jpg, gif, bmp)'];
                return false;
            }
            if (!preg_match('/^[A-Za-z0-9_-]+$/', (string)$code)) {
                $errors[] = ['message' => 'Carrier code may contain only letters, digits, "_" and "-"'];
                return false;
            }
            $ext = File::getImageType($files['logo']['tmp_name']);
            $logo_src = "carrier_$code.$ext";
            $path = ROOT_PATH . RESOURCES_CARRIERS . DIRECTORY_SEPARATOR;
            $original_path = $path . $logo_src;
            $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($path)))->handleDir($path);
            $file_wrapper->writeFilePath($original_path, $files['logo']['tmp_name']);
            $temp = tmpfile();
            $logo_name = "carrier_$code.png";
            $converted_path = $path . $logo_name;
            if (!File::convert2PNG($files['logo']['tmp_name'], $temp, $ext))
                return false;
            $tmp_file = stream_get_meta_data($temp)['uri'];
            if (filesize($tmp_file))
                $file_wrapper->writeFileResource($converted_path, $temp);
            else
                $file_wrapper->writeFilePath($converted_path, $files['logo']['tmp_name']);
        } else
            $errors[] = ['message' => 'logo is not found'];
        return $logo_name;
    }

    public static function addCarrier($carrier_data, $files)
    {
        $errors = [];
        if (($logo_name = self::uploadLogo($carrier_data['code'], $files, $errors)) === false)
            return ['error', array_column($errors, 'message') ?: ['File is not found']];
        if ($carrier_data['max_weight'] !== '' && (string)(float)$carrier_data['max_weight'] !== $carrier_data['max_weight'])
            return ['error', ['Wrong max weight']];
        $data = [
            'name' => $carrier_data['name'],
            'logo' => $logo_name,
            'code' => $carrier_data['code'],
            'url' => self::normalizeUrl($carrier_data['url']),
            'is_internal' => $carrier_data['is_internal'],
            'is_special' => $carrier_data['is_special'],
        ];
        if (!empty($carrier_data['max_weight']))
            $data['max_weight'] = (float)$carrier_data['max_weight'];
        $carrier = new Carrier($data);
        $errors = array_merge($errors, self::validate($carrier));
        if (!empty($errors))
            return ['error', array_map(function ($e) { return $e['message']; }, $errors)];
        $carrier->save(array_keys($data));
        LogisticCompanyCarriers::addRecords(empty($_POST['lcs']) ? [] : explode(',', $_POST['lcs']), $carrier->getId(), $errors);
        View::addFlash('carrier_saved', 'success', "Carrier {$carrier->getName()} ({$carrier->getCode()}) was created");
        return ['success', []];
    }

    public static function changeCarrier($carrier_data, $files)
    {
        $old_carrier = self::getById($carrier_data['id']);
        if (empty($old_carrier))
            return ['error', ['Carrier is not found']];
        if (($carrier_data['max_weight'] ?? '') !== '' && (string)(float)$carrier_data['max_weight'] !== $carrier_data['max_weight'])
            return ['error', ['Wrong max weight']];
        $errors = [];
        if ($carrier_data['change']) {
            if (($logo_name = self::uploadLogo($carrier_data['code'], $files, $errors)) === false)
                return ['error', array_column($errors, 'message') ?: ['File is not found']];
        } else
            $logo_name = $old_carrier->getLogo();
        $data = [
            'id' => $carrier_data['id'],
            'name' => $carrier_data['name'],
            'code' => $carrier_data['code'],
            'url' => self::normalizeUrl($carrier_data['url']),
            'is_internal' => $carrier_data['is_internal'] ?? 0,
            'is_special' => $carrier_data['is_special'] ?? 0,
            'logo' => $logo_name,
        ];
        if (!empty($carrier_data['max_weight']))
            $data['max_weight'] = (float)$carrier_data['max_weight'];
        $carrier = new Carrier($data);
        $is_change = LogisticCompanyCarriers::addRecords(empty($_POST['lcs']) ? [] : explode(',', $_POST['lcs']), $carrier->getId(), $errors);;
        $errors = array_merge($errors, self::validate($carrier));
        if (!empty($errors))
            return ['error', array_map(function ($e) { return $e['message']; }, $errors)];
        $carrier_change = self::changingData($old_carrier, $carrier);
        if (!$is_change && empty($carrier_change) && !$carrier_data['change'])
            View::addFlash('carrier', 'warning', 'Nothing to change');
        else {
            $carrier->save($carrier_change);
            View::addFlash('carrier_saved', 'success', "Carrier {$carrier->getName()} ({$carrier->getCode()}) was updated");
        }
        return ['success', []];
    }
}