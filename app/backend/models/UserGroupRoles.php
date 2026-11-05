<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\UserGroupRole;
use app\core\helpers\Validator;

class UserGroupRoles extends \app\core\models\UserGroupRoles
{
    public static function validate(UserGroupRole $userGroupRole)
    {
        $rules = [
            'user_group_id' => [
                'val' => $userGroupRole->getUserGroupId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'role_id' => [
                'val' => $userGroupRole->getRoleId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ]
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;

        if (!empty($userGroupRole->getId()))
            $validator->addError('exist', 'Item already exist', 'id');
        if (self::checkUnique($userGroupRole))
            $validator->addError("not_unique", "This group with role is already used.", 'all');

        return $validator->getErrors();
    }

    public static function getRolesIdsByGroup($user_group_id): array
    {
        return array_map('intval', (new SafeMysql())->fetchArrayOneField('SELECT role_id FROM user_group_roles WHERE user_group_id = :user_group_id', ['user_group_id' => $user_group_id]));
    }
}
