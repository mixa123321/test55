<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ChargeBilling;

class ChargesBilling
{
    public static function getRanges(int $id)
    {
        return (new SafeMysql())->fetchAll("
            SELECT id, charge_complex_id, value, valid_from, IFNULL(valid_to, '') valid_to
            FROM charges_billing
            WHERE charge_complex_id = :id
            ORDER BY valid_from", ['id' => $id]);
    }

    public static function change(int $id, float $value)
    {
        (new SafeMysql())->execute("UPDATE charges_billing SET value = :value WHERE id = :id", [
            'value' => $value,
            'id' => $id,
        ]);
    }

    public static function add(array $data)
    {
        (new SafeMysql())->execute("
            INSERT INTO charges_billing (charge_complex_id, value, valid_from, valid_to)
            VALUES (:charge_complex_id, :value, :valid_from, :valid_to)", [
            'charge_complex_id' => $data['charge_complex_id'],
            'value' => $data['value'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?: null,
        ]);
    }

    public static function delete(int $id)
    {
        (new SafeMysql())->execute("DELETE FROM charges_billing WHERE id = :id", ['id' => $id]);
    }

    public static function update(array $data)
    {
        (new SafeMysql())->execute("
            UPDATE charges_billing
            SET value = :value, valid_from = :valid_from, valid_to = :valid_to
            WHERE id = :id", [
            'value' => $data['value'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?: null,
            'id' => $data['id'],
        ]);
    }

    public static function getById(int $id): ?ChargeBilling
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM charges_billing WHERE id = :id", ['id' => $id]);
        if (!$data)
            return null;
        return new ChargeBilling($data);
    }
}
