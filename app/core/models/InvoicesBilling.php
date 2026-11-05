<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;

class InvoicesBilling
{
    public static function add(int $year, int $month): int
    {
        $id = (new SafeMysql())->fetchScalar("SELECT id FROM invoices_billing WHERE year = :year AND month = :month", ['year' => $year, 'month' => $month]);
        if ($id)
            return $id;
        return (new SafeMysql())->insert("INSERT INTO invoices_billing (year, month) VALUES (:year, :month)", [
            'year' => $year,
            'month' => $month,
        ]);
    }

    public static function getById(int $id): ?array
    {
        return (new SafeMysql())->fetchOne("SELECT * FROM invoices_billing WHERE id = :id", ['id' => $id]);
    }
}