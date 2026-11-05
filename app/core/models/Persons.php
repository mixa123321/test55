<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Person;
use app\core\entities\User;

class Persons
{
    public static function getByEmail($email)
    {
        $person_info = (new SafeMysql())->fetchOne("SELECT * FROM persons WHERE email = :email LIMIT 1", ['email' => $email]);
        if (!empty($person_info))
            return new Person($person_info);
        return null;
    }

    public static function getById($id)
    {
        static $persons = [];
        if (!array_key_exists($id, $persons)) {
            $data = (new SafeMysql())->fetchOne('SELECT * FROM persons WHERE id = :id', ['id' => (int)$id]);
            if ($data)
                $persons[$id] = new Person($data);
            else
                $persons[$id] = null;
        }
        return $persons[$id];
    }

    public static function getByUserId($user_id)
    {
        $person_info = (new SafeMysql())->fetchOne("SELECT * FROM persons WHERE user_id = :user_id LIMIT 1", ['user_id' => (int)$user_id]);
        if (!empty($person_info))
            return new Person($person_info);
        return null;
    }

    public static function getByUsers($users): array
    {
        $user_ids = array_filter(array_map(function($user) {
            if ($user instanceof User)
                return $user->getId();
            return null;
        }, $users));
        if (empty($user_ids))
            return [];
        $person_data = self::getByUserIds($user_ids);
        $persons = [];
        /** @var $person Person */
        foreach ($person_data as $person)
            $persons[$person->getUserId()] = $person;
        return $persons;
    }

    public static function getByUserIds($user_ids): array
    {
        if (empty($user_ids))
            return [];
        $data = (new SafeMysql())->fetchAll("SELECT * FROM persons WHERE user_id IN (:user_ids)", ['user_ids' => array_map('intval', $user_ids)]);
        return array_map(function ($person) {
            return new Person($person);
        }, $data);
    }

    public static function getEmptyUser(int $lc_id)
    {
        $person_info = (new SafeMysql())->fetchAll("
            SELECT *
            FROM persons
            WHERE (user_id IS NULL OR user_id = '') AND lc_id = :lc_id", ['lc_id' => $lc_id]);
        if (!empty($person_info)) {
            $persons = [];
            foreach ($person_info as $person_id => $person)
                $persons[$person_id] = new Person($person);
            return $persons;
        }
        return [];
    }
}
