<?php

namespace app\backend\models;

use app\core\db\Mysql;
use app\core\dbal\SafeMysql;
use app\core\entities\User;
use app\core\entities\UserGroup;
use app\core\helpers\Validator;
use app\core\models\accounts\UserHistories;

class UserGroups extends \app\core\models\perms\UserGroups
{
    public static function validate(UserGroup $user_group): array
    {
        $rules = [
            'name' => [
                'val' => $user_group->getName(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [4, 60]]
            ]
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (!empty($errors))
            return $errors;

        $exist_user_group = self::getByName($user_group->getName());
        if (!empty($exist_user_group) && $exist_user_group->getId() != $user_group->getId())
            $validator->addError("no_uniq_name", "Name must be unique", 'name');
        return $validator->getErrors();
    }

    public static function getList($only_active = false, $role_id = null): array
    {
        $sql = 'SELECT ug.* FROM user_groups ug '
            . (!empty($role_id) ? ('JOIN user_group_roles ugr ON ugr.user_group_id = ug.id AND ugr.role_id = :role_id') : '')
            . ($only_active ? ' WHERE ug.is_active = 1' : '') . ' GROUP BY ug.id';
        $params = [];
        if (!empty($role_id))
            $params['role_id'] = $role_id;
        $groups = (new SafeMysql())->fetchAll($sql, $params);
        return array_map(function($group) {
            return new UserGroup($group);
        }, $groups);
    }

    public static function getUsersByGroups($group_id): array
    {
        $users = (new SafeMysql())->fetchAll('SELECT u.* FROM users u
                                                    JOIN user_groups_map ugm ON u.id = ugm.user_id
                                                    JOIN user_group_roles ugr ON ugr.role_id = u.role_id AND ugr.user_group_id = ugm.group_id 
                                                    JOIN roles r ON r.id = u.role_id AND r.is_active = 1
                                                WHERE ugm.group_id = :group_id GROUP BY u.id', ['group_id' => $group_id]);
        return array_map(function($user) {
            return new User($user);
        }, $users);
    }

    public static function getActiveByUsers(User $user): array
    {
        return (new SafeMysql())->fetchAll('SELECT DISTINCT ug.id, ug.name FROM user_groups_map ugm 
                                            JOIN user_groups ug ON ugm.group_id = ug.id AND ug.is_active = 1
                                              JOIN user_group_roles ugr ON ug.id = ugr.user_group_id AND ugr.role_id = :role_id 
                                                WHERE ugm.user_id = :user_id', [
            'role_id' => $user->getRoleId(),
            'user_id' => $user->getId()
        ]);
    }

    public static function getGroupNamesByUsers($users): array
    {
        $user_groups = [];
        /** @var User $user */
        foreach ($users as $user) {
            if ($user instanceof User)
                $user_groups[$user->getId()] = array_map(function($group) {
                    return $group['name'];
                }, UserGroups::getActiveByUsers($user));
        }
        return $user_groups;
    }

    public static function addUserToGroup($user_id, $group_id, $create_user_id)
    {
        Mysql::saveRow('user_groups_map', [
           'user_id' => (int)$user_id,
           'group_id' => (int)$group_id,
        ]);
        UserHistories::addToGroup($user_id, $group_id, $create_user_id);
    }

    public static function removeUserFromGroup(User $user, $group_id, $create_user_id)
    {
        $user_id = (int)$user->getId();
        $group_id = (int)$group_id;
        (new SafeMysql())->execute("DELETE FROM user_groups_map WHERE user_id = :user_id AND group_id = :group_id", [
            'user_id' => $user_id,
            'group_id' => $group_id,
        ]);
//        if ($user->isRoleWorker())
//            self::handleWorkerAccess($user);
        UserHistories::removeFromGroup($user->getId(), $group_id, $create_user_id);
    }

    public static function getByName($group_name): ?UserGroup
    {
        $res = (new SafeMysql())->fetchOne("SELECT * from user_groups WHERE name = :name", ['name' => trim($group_name)]);

        return !empty($res) ? new UserGroup($res) : null;
    }

    public static function handleGroups($user_group_ids): array
    {
        if (empty($user_group_ids))
            return [];
        return (new SafeMysql())->fetchArrayOneField('SELECT id FROM user_groups WHERE is_active = 1 AND id IN (:user_group_ids)', ['user_group_ids' => $user_group_ids]);
    }

    public static function getWorkersByGroupAccess($warehouse_id, $access): array
    {
        $worker_role_id = Roles::WORKER;
        $sql = "SELECT DISTINCT u.id, u.login, p.name FROM users u
                     JOIN user_groups_map ugm ON u.id = ugm.user_id
                     JOIN user_groups ug ON ug.id = ugm.group_id AND ug.access_scope LIKE CONCAT('%', :access_pattern, '%')
                     LEFT JOIN persons p on u.id = p.user_id                   
                WHERE u.role_id = :worker_role_id AND u.warehouse_id = :warehouse_id";
        return (new SafeMysql())->fetchAssocWithKey($sql, [
            'access_pattern' => addcslashes($access, '_%'),
            'worker_role_id' => $worker_role_id,
            'warehouse_id' => $warehouse_id
        ], 'id');
    }

//    /**
//     * @deprecated after user groups in rules
//     */
//    public static function handleWorkerAccess(User $user)
//    {
//        $user->setRoleId(Roles::WORKER);
//        $user_groups = Arr::column(self::getActiveByUsers($user), 'id');
//        $access_scope = [];
//
//        $all_groups = [];
//        foreach (self::getList(true, Roles::WORKER) as $item) {
//            /** @var $item UserGroup */
//            $all_groups[$item->getId()] = $item;
//        }
//
//        foreach ($user_groups as $user_group_id) {
//            if (empty($all_groups[$user_group_id]))
//                continue;
//            /** @var $user_group UserGroup */
//            $user_group = $all_groups[$user_group_id];
//            if (!empty($user_group->getAccessScope())) {
//                foreach ($user_group->getAccessScope() as $access_item) {
//                    if (in_array($access_item, AccessScope::$worker_gr) && !in_array($access_item, $access_scope))
//                        $access_scope[] = $access_item;
//                }
//            }
//        }
//        $user->setAccessScope($access_scope);
//        $user->save(['id', 'access_scope']);
//    }
}
