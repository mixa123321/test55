<?php

namespace app\backend\controllers;

use app\backend\models\Sizes as SizesModel;
use app\core\entities\Size;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\View;
use app\backend\models\SKUs;
use app\core\models\SizesHistory;

class Sizes extends AuthController
{
    public function index()
    {
        $vars = [
            'sizes' => SizesModel::getAllSizes($this->warehouse_id),
        ];
        $this->render($vars, 'warehouse/sizes');
    }

    public function addSize()
    {
        $this->disableWarehouseChange();
        $check_size = true;
        if (isset($_GET['id'])) {
            $size = SizesModel::getById($_GET['id']);
            $check_size = SKUs::isEditSize($_GET['id']);
        } else
            $size = new Size();
        $data = [
            "size" => $size,
            "check_size" => $check_size,
        ];
        $this->render($data, 'warehouse/add_size');
    }

    public function editSize()
    {
        throw new RedirectException('/sizes/add_size/?id=' . (int)($_GET['id'] ?? 0));
    }

    /** @throws */
    public function setSize()
    {
        $redirect_url = "/sizes/add_size";
        if ($_POST) {
            $sizes = $_POST['size'];
            $sizes['name'] = strtoupper($sizes['name']);
            $sizes['warehouse_id'] = $this->warehouse_id;
            $old_size = SizesModel::getById($sizes['id']);
            $fields_to_save = array_keys($sizes);
            $size = new Size([
                'name' => $sizes['name'],
                'width' => $sizes['width'],
                'height' => $sizes['height'],
                'depth' => $sizes['depth'],
                'warehouse_id' => $sizes['warehouse_id'],
            ]);
            if ($sizes['id']) {
                $size->setId($sizes['id']);
                $diff = [];
                foreach ($size->toArray() as $field => $value)
                    if ($value != $old_size->toArray()[$field])
                        $diff[$field] = [
                            'old' => $old_size->toArray()[$field],
                            'new' => $value
                        ];
                $diff_size = SizesModel::checkDiff($size, $old_size);
            }
            $errors = SizesModel::validate($size, $this->user);
            if (!empty($errors)) {
                View::addFlash('add_size', 'danger', Arr::column($errors, 'message'));
                throw new RedirectException($sizes['id'] != 0 ? ($redirect_url . '?id=' . $sizes['id'] . '&check_size=' . $_GET['check_size']): $redirect_url);
            }
            if (isset($diff_size) && empty($diff_size))
                View::addFlash('change', 'warning', 'Nothing to change');
            else {
                $size->save($fields_to_save);
                if (!empty($diff_size) || !isset($diff_size))
                    SizesHistory::addRecord($size->getId(), $this->user->getId(), $sizes['id'] ? 'edit' : 'create', !$sizes['id'] ? $size->toArray() : $diff);
                View::addFlash('save', 'success', $sizes['id'] ? 'Size was updated' : 'Size was created');
            }
            throw new RedirectException("/sizes");
        }
    }
}
