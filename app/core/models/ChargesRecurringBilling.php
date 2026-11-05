<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\ChargeRecurringBilling;

class ChargesRecurringBilling
{
    public static function getRanges(int $id)
    {
        return (new SafeMysql())->fetchAll("
            SELECT id, charge_recurring_id, quantity, value, valid_from, IFNULL(valid_to, '') valid_to
            FROM charges_recurring_billing
            WHERE charge_recurring_id = :id
            ORDER BY valid_from", ['id' => $id]);
    }

    public static function change(int $id, int $quantity, float $value)
    {
        (new SafeMysql())->execute("UPDATE charges_recurring_billing SET quantity = :quantity, value = :value WHERE id = :id", [
            'quantity' => $quantity,
            'value' => $value,
            'id' => $id,
        ]);
    }

    public static function add(array $data)
    {
        (new SafeMysql())->execute("
            INSERT INTO charges_recurring_billing (charge_recurring_id, quantity, value, valid_from, valid_to)
            VALUES (:charge_recurring_id, :quantity, :value, :valid_from, :valid_to)", [
            'charge_recurring_id' => $data['charge_recurring_id'],
            'quantity' => $data['quantity'],
            'value' => $data['value'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?: null,
        ]);
    }

    public static function delete(int $id)
    {
        (new SafeMysql())->execute("DELETE FROM charges_recurring_billing WHERE id = :id", ['id' => $id]);
    }

    public static function update(array $data)
    {
        (new SafeMysql())->execute("
            UPDATE charges_recurring_billing
            SET quantity = :quantity, value = :value, valid_from = :valid_from, valid_to = :valid_to
            WHERE id = :id", [
            'quantity' => $data['quantity'],
            'value' => $data['value'],
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'] ?: null,
            'id' => $data['id'],
        ]);
    }

    public static function getById(int $id): ?ChargeRecurringBilling
    {
        $data = (new SafeMysql())->fetchOne("SELECT * FROM charges_recurring_billing WHERE id = :id", ['id' => $id]);
        if (!$data)
            return null;
        return new ChargeRecurringBilling($data);
    }
}
