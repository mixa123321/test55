<?php

namespace app\backend\models;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\helpers\Request;

class Routes extends \app\core\models\Routes
{
    const LIMIT_PER_PAGE = 20;

    public static function getAll($filters, $developer = false)
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT SQL_CALC_FOUND_ROWS r.* FROM routes r");
        self::applySearchFilters($query, $filters);
        if (!$developer)
            $query->addWhere("alias IS NOT NULL AND alias <> ''");
        $query->addWhere("parent_id IS NULL OR parent_id = ''");
        $current_page = Request::getPage($filters);
        $offset = self::LIMIT_PER_PAGE * ($current_page - 1);
        $query->setOrderBy("r.name");
        $query->setLimit("{$offset}, " . self::LIMIT_PER_PAGE);
        $records_raw = (new SafeMysql())->fetchAssocWithKey($query->build(), $filters, 'id');
        $total = (new SafeMysql())->fetchScalar('SELECT FOUND_ROWS() count');
        return ['records' => $records_raw, 'total' => (int)$total, 'current_page' => $current_page];
    }

    private static function applySearchFilters(QueryBuilder $query, array &$filters)
    {
        if (!empty($filters['search'])) {
            $filters['search'] = '%' . $filters['search'] . '%';
            $query->addWhere("name like :search OR LOWER(alias) like :search");
        }
        if (!empty($filters['editable']))
            $query->addWhere("editable = 1");
        if (!empty($filters['virtual']))
            $query->addWhere("virtual_access = 1");
        if (isset($filters['status']) && !in_array($filters['status'], ['', 'all']))
            $query->addWhere("is_active = :status");
    }

    public static function findChildRoute($id, $query): array
    {
        $sql = "SELECT id, name, alias FROM routes                                            
                WHERE id != :id AND (name LIKE :query OR alias LIKE :query)
                AND (parent_id IS NULL OR parent_id = '') LIMIT 30";
        return (new SafeMysql())->fetchAll($sql, [
            'id' => (int)$id,
            'query' => '%' . $query . '%'
        ]);
    }
}