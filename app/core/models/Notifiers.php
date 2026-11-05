<?php

namespace app\core\models;

use app\core\entities\LogisticCompany;
use app\core\entities\Notifier;
use app\core\dbal\SafeMysql;
use app\core\services\crypto\StoredSecret;

class Notifiers
{
    public const GROUP_WAREHOUSE    = 'warehouse';
    public const GROUP_PROTECTION   = 'protection';
    public const GROUP_VIRTUAL      = 'virtual';
    public const GROUP_INTEGRATION  = 'integration';
    public const GROUP_STAGE        = 'stage';
    public const GROUP_ROBOT        = 'robot';

    public const GROUP_WAREHOUSE_TEST = 13;

    public const ALL_GROUPS         = [
        self::GROUP_WAREHOUSE,
        self::GROUP_PROTECTION,
        self::GROUP_VIRTUAL,
        self::GROUP_INTEGRATION,
        self::GROUP_STAGE,
        self::GROUP_ROBOT
    ];

    private static $groups = [];

    public static function init(LogisticCompany $lc)
    {
        self::$groups = self::getGroupsData($lc);
    }

    public static function getGroupsData(LogisticCompany $lc): array
    {
        $groups_data = (new SafeMysql())->fetchAll("
            SELECT *
            FROM notifiers
            WHERE lc_id = :lc_id AND status", ['lc_id' => (int)$lc->getId()]);
        $groups = [];
        foreach (self::ALL_GROUPS as $group_name)
            $groups[$group_name] = null;
        foreach ($groups_data as $group) {
            if (!in_array($group['group_name'], self::ALL_GROUPS))
                continue;
            $groups[$group['group_name']] = new Notifier([
                'chat_id' => $group['chat_id'],
                'token' => StoredSecret::decrypt($group['token']),
                'endpoint' => $group['endpoint'],
            ]);
        }
        return $groups;
    }

    public static function getGroups(): array
    {
        return self::$groups;
    }
}
