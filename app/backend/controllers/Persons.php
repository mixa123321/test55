<?php

namespace app\backend\controllers;

use app\backend\models\Persons as PersonsModel;
use app\core\entities\Person;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\accounts\PersonHistories;

class Persons extends AuthController
{
    /** @throws */
    public function index()
    {
        if (!Request::isAjax()) {
            View::addFlash('persons', 'danger', 'Request fail');
            throw new RedirectException('/');
        }
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $person = PersonsModel::getById(Request::getPositiveId(null, 'person_id'));
                return $this->response = ['result' => 'success', 'person' => !empty($person) ? $person->toArray() : []];
            case 'POST':
                $person_data = $_POST + ['lc_id' => $this->logistic_company->getId()];
                $person = new Person($person_data);
                $errors = PersonsModel::validate($person);
                if (empty($errors)) {
                    $person->save(array_keys($person_data));
                    PersonHistories::create($person, $this->user->getId());
                    return $this->response = ['result' => 'success', 'person' => $person->toArray()];
                } else {
                    $_SESSION['create_person'] = $person->toArray();
                    View::addFlash('role_fail', 'danger', Arr::column($errors, 'message'));
                    return $this->response = ['result' => 'error'];
                }
            case 'PUT':
                $person_data = Request::getData() + ['lc_id' => $this->logistic_company->getId()];
                $exist_person = PersonsModel::getById($person_data['id'] ?? null);
                if (empty($exist_person))
                    return $this->response = ['result' => 'error', 'errors' => ['Person not found']];
                $person = clone $exist_person;
                $owner = $person->getUserId() == $this->user->getId();
                if (!$owner)
                    $person->setName($person_data['name']);
                $person->setEmail($person_data['email']);
                $person->setPhone($person_data['phone']);
                $errors = PersonsModel::validate($person, $owner);
                if (empty($errors)) {
                    $person->save(array_keys($person_data));
                    PersonHistories::update($person, $exist_person, $this->user->getId());
                    return $this->response = ['result' => 'success', 'person' => $person->toArray()];
                } else
                    return $this->response = ['result' => 'error', 'errors' => Arr::column($errors, 'message')];
        }
    }
}
