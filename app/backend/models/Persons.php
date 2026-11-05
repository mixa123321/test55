<?php

namespace app\backend\models;

use app\core\entities\Person;
use app\core\helpers\Validator;

class Persons extends \app\core\models\Persons
{
    public static function validate(Person $person, $owner = false)
    {
        $rules = [
            'name' => [
                'val' => $person->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [4, 255]]
            ],
            'email' => [
                'val' => $person->getEmail(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [4, 255]]
            ],
            'phone' => [
                'val' => $person->getPhone(),
                'rules' => [Validator::TYPE_STRING => [0, 15]],
            ],
        ];
        if ($owner)
            unset($rules['name']);
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;
        $person_by_email = Persons::getByEmail($person->getEmail());
        if ($person_by_email && $person_by_email->getId() != $person->getId())
            $validator->addError("not_unique", "This email is already used.", 'email');
        if (!filter_var($person->getEmail(), FILTER_VALIDATE_EMAIL))
            $validator->addError("email_fail", "Email not correct.", 'email');
        return $validator->getErrors();
    }
}
