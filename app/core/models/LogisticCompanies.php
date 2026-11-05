<?php

namespace app\core\models;

use app\core\entities\LogisticCompany;
use app\core\dbal\SafeMysql;

class LogisticCompanies
{
    public static function getById($id, $status = null): ?LogisticCompany
    {
        $id = (int)$id;
        $where_status = '';
        if (!is_null($status))
            $where_status = 'AND status = ' . (int)$status;
        $data = (new SafeMysql())->fetchOne("SELECT * FROM logistic_companies WHERE id = :id $where_status", ['id' => $id]);
        if (!empty($data))
            return new LogisticCompany($data);
        return null;
    }

    public static function getByName($name): ?LogisticCompany
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM logistic_companies WHERE name = :name", ['name' => $name]);
        if (!empty($data))
            return new LogisticCompany($data);
        return null;
    }

    public static function getAllIds($status = null): array
    {
        $where_status = '';
        if (!is_null($status))
            $where_status = 'WHERE status = ' . (int)$status;
        return (new SafeMysql())->fetchArrayOneField("SELECT id FROM logistic_companies $where_status");
    }

    public static function getAll($status = null): array
    {
        $where_status = '';
        if (!is_null($status))
            $where_status = 'WHERE status = ' . (int)$status;
        $rows = (new SafeMysql())->fetchAll("SELECT * FROM logistic_companies $where_status");
        $result = [];
        foreach ($rows as $row)
            $result[] = new LogisticCompany($row);
        return $result;
    }
}