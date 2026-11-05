<?php

namespace app\backend\controllers;

use app\backend\models\CollectionTimes as CollectionTimesModel;
use app\core\entities\CollectionTime;
use app\core\helpers\Arr;
use app\core\helpers\View;
use app\core\models\CollectionTimeHistories;

class CollectionTimes extends AuthController
{
    /** @throws */
    public function index()
    {
        $services = CollectionTimesModel::getCollectionTimes($this->warehouse_id);
        $data = [
            'services' => $services,
        ];
        $this->render($data, 'warehouse/collection_times');
    }

    public function updateCollectionTime()
    {
        if (empty($_POST))
            return $this->response = ['error' => 'Access denied'];
        /** @var CollectionTime $service */
        $service = CollectionTimesModel::getCollectionTime($this->warehouse_id, $_POST['service_id']);
        $_POST['collection'] = $_POST['collection'] ?: null;
        $_POST['is_priority'] = empty($_POST['collection']) ? false : (!empty($_POST['is_priority']));
        $_POST['priority_level'] = (empty($_POST['collection']) || !$_POST['is_priority']) ? 0 : (int)($_POST['priority_level'] ?? 0);
        $update = true;
        if (!$service) {
            $service = new CollectionTime([
                'shipping_service_id' => $_POST['service_id'],
                'warehouse_id' => $this->warehouse_id,
            ]);
            $update = false;
        }
        $service->setCollection($_POST['collection']);
        $service->setIsPriority($_POST['is_priority']);
        $service->setPriorityLevel($_POST['priority_level']);
        $errors = CollectionTimesModel::validate($service);
        if ($_POST['priority_level']) {
            $services = Arr::map(CollectionTimesModel::getCollectionTimes($this->warehouse_id), 'shipping_service_id', 'priority_level');
            $services = array_filter($services, function ($item) {
                return $item;
            });
            $services[$_POST['service_id']] = $_POST['priority_level'];
            if (count($services) != count(array_unique($services)))
                $errors[] = ['field' => 'priority_level', 'message' => 'priority is not unique'];
        }
        if (!empty($errors))
            return $this->response = ['error' => implode('<br/>', array_map(function($item) {
                return View::toHtml("{$item['field']}: {$item['message']}");
            }, $errors))];
        $service->save(['id', 'shipping_service_id', 'warehouse_id', 'collection', 'is_priority', 'priority_level']);
        CollectionTimeHistories::addRecord($service, $update ? CollectionTimeHistories::EVENT_UPDATE : CollectionTimeHistories::EVENT_CREATE, $this->user->getId());
        return $this->response = ['success'];
    }
}