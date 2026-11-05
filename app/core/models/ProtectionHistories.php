<?php

namespace app\core\models;

use app\core\entities\ProtectionHistory;

class ProtectionHistories
{
    public static function addAttack($host, $params, $ip, $continent, $country_code, $country_name)
    {
        $protection = new ProtectionHistory();
        $protection->setAttackDate(time());
        $protection->setHost($host);
        $protection->setParams($params);
        $protection->setIp($ip);
        $protection->setContinent($continent);
        $protection->setCountryCode($country_code);
        $protection->setCountryName($country_name);
        $fields_to_save = array_keys($protection->toArray());
        array_shift($fields_to_save);
        $protection->save($fields_to_save);
    }
}