<?php

namespace app\core\services\upload;

use app\core\entities\User;
use app\core\entities\Warehouse;
use app\backend\models\Suppliers;
use app\backend\models\SKUs;
use app\backend\models\Locations;
use app\backend\models\SKULocations;
use app\backend\models\SKUPreffLocs;

class BulkMovementsUploadService
{
    public function execute($head, $skus, $form, Warehouse $warehouse, User $user): array
    {
        $supplier = Suppliers::getById($form['supplier']);

        if (!$supplier) {
            return [[true, ['danger' => ['Supplier not found']]], -1];
        }

        $errors = [];
        $barcode = $this->checkField('SkuBarcode', $head);
        $quantity = $this->checkField('Quantity', $head);
        $locationFrom = $this->checkField('LocationFrom', $head);
        $locationTo = $this->checkField('LocationTo', $head);

        $num = 0;
        foreach ($skus as $data) {
            $SKU = SKUs::getByBarcodeAndSupplier($data[$barcode], $supplier->getId());

            if (!$SKU) {
                $errors[] = "{$data[$barcode]}: is not found";
                continue;
            }
            if ($data[$quantity] <= 0) {
                $errors[] = "{$data[$barcode]}: wrong quantity";
                continue;
            }
            $loc_from = Locations::getByScan($warehouse->getId(), $data[$locationFrom]);
            if (!$loc_from) {
                $errors[] = "{$data[$barcode]}: {$data[$locationFrom]} is not found";
                continue;
            }
            $loc_to = Locations::getByScan($warehouse->getId(), $data[$locationTo]);
            if (!$loc_to) {
                $errors[] = "{$data[$barcode]}: {$data[$locationTo]} is not found";
                continue;
            }
            if ($loc_to->storage()) {
                $checkEmptyMaxCapacity = SKULocations::hasSkuWithEmptyMaxCapacity($warehouse->getId(), $loc_to);
                if ($checkEmptyMaxCapacity) {
                    $errors[] = "{$data[$barcode]}: Movement is impossible. There are items without specified dimensions in the destination location";
                    continue;
                }
                $qty0 = SKULocations::getCountFreeInLocation($warehouse->getId(), $SKU, $loc_to);
                if (empty($qty0)) {
                    $errors[] = "{$data[$barcode]}: Movement is impossible. Not enough space";
                    continue;
                }
                if ($qty0 < $data[$quantity]) {
                    $errors[] = "{$data[$barcode]}: Movement is impossible. Take $qty0 items maximum";
                    continue;
                }
            }
            $exit = false;
            SKULocations::handleBatchMovement($SKU, $loc_from, $loc_to, function () use($data, $barcode, &$exit) {
                $errors[] = "{$data[$barcode]}: SKU batches in this location cannot be mixed";
                $exit = true;
            });
            if ($exit)
                continue;
            if (SKUPreffLocs::checkExistPreffLoc($warehouse->getId(), $SKU->getId(), $loc_to->getId(), $loc_to->getTypeId()) === false)
                SKUPreffLocs::add($SKU->getId(), $loc_to);
            $res = SKULocations::createMovement(
                $loc_from, $loc_to, $SKU, -1, $data[$quantity],
                'Manually', $user->getId(), true, Locations::isExpiredLocation($loc_to)
            );
            if (!$res[0]) {
                $errors[] = "{$data[$barcode]}: {$res[1]}";
                continue;
            }

            $num++;
        }

        return [[true, ['danger' => $errors, 'success' => "Moved $num SKUs"]], -1];
    }

    private function checkField($field, $data)
    {
        $pos = array_search($field, $data);
        if ($pos === false)
            die("Field '$field' is absent\n");
        return $pos;
    }
}