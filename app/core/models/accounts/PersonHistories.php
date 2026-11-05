<?php

namespace app\core\models\accounts;

use app\core\entities\Person;
use app\core\interfaces\AccessObjectEntity;

class PersonHistories extends AccountHistories
{
    public static function create(Person $person, $user_id)
    {
        self::storeCreate($person->getId(), AccessObjectEntity::OBJECT_PERSON, ['person' => $person->toArray()], $user_id);
    }

    public static function update(Person $person, Person $old_person, $user_id)
    {
        $diff = self::handleDifference($person, $old_person);
        if (empty($diff))
            return;
        self::storeUpdate($person->getId(), AccessObjectEntity::OBJECT_PERSON, $diff, $user_id);
    }
}