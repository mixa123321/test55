<?php

namespace app\api\models;

use app\backend\models\SKUs;
use app\backend\models\Dimensioners as DimensionersModel;
use app\core\helpers\Arr;
use app\core\helpers\File;
use app\core\models\DimensionerHistories;
use app\core\models\SKUHistories;
use app\core\helpers\FileWrapper;

class Dimensioners
{
    public static function measure($data, int $warehouse_id, int $user_id)
    {
        $dimensioner = DimensionersModel::getByUnitId($warehouse_id, (int)$data['UnitID']);
        if (empty($dimensioner) || !$dimensioner->getSupplierId())
            return [false, 'wrong dimensioner'];
        $barcode = addslashes($data['Barcode']);
        $SKU = SKUs::getByBarcodeAndSupplier($barcode, $dimensioner->getSupplierId());
        if (empty($SKU) || !$SKU->getState())
            return [false, 'barcode not found'];
        $SKU->setWidth((float)$data['Width']);
        $SKU->setHeight((float)$data['Height']);
        $SKU->setDepth((float)$data['Length']);
        $SKU->setWeight((float)$data['Weight']);
        $errors = SKUs::validate($SKU);
        self::clearErrors($errors);
        SKUs::changeMaxCapacity($SKU, $warehouse_id);
        $fields = ['id', 'max_capacity', 'width', 'height', 'depth', 'weight'];
        if (!empty($data['ImageBase64'])) {
            try {
                $file_string = base64_decode(addslashes($data['ImageBase64']));
                $temp = tmpfile();
                fwrite($temp, $file_string);
                fseek($temp, 0);
                $tmp_file = stream_get_meta_data($temp)['uri'];
                if (!File::getSKUFromPhoto($tmp_file, $data['ObjectRGBCoordinates']))
                    throw new \Exception();
                $photo = SKUs::savePhotoFromDisk($tmp_file, "{$SKU->getId()}_{$dimensioner->getSupplierId()}");
                $SKU->setPhoto($photo);
                $fields[] = 'photo';
                $SKU->save($fields);
            } catch (\Exception $e) {
                $errors[] = ['message' => 'photo error'];
            }
        }
        if (!empty($errors))
            return [false, ['code' => ErrorCodes::DATA_ERROR, 'message' => json_encode(Arr::column($errors, 'message'))]];
        SKUHistories::updateDimensioner($SKU->getId(), $user_id, [
            'width' => $data['Width'],
            'height' => $data['Height'],
            'depth' => $data['Length'],
            'weight' => $data['Weight'],
            'photo' => $SKU->getPhoto(),
        ], $warehouse_id);
        DimensionerHistories::measure($dimensioner, $data, $user_id);
        return [true];
    }

    private static function clearErrors(array &$errors)
    {
        foreach ($errors as $key => $error)
            if ($error['field'] === 'hs_code') // поле hs_code нужно для интеграций
                unset($errors[$key]);
    }
}
