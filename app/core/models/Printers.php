<?php

namespace app\core\models;

use app\core\dbal\SafeMysql;
use app\core\entities\Printer;

class Printers
{
    public static function getPrinters($warehouse_id, $type_printers = Printer::RETAIN_ME)
    {
        $params = ['warehouse_id' => (int)$warehouse_id];
        $whereType = '';
        if (!empty($type_printers)) {
            $whereType = " AND type = :type";
            $params['type'] = $type_printers;
        }
        $res = (new SafeMysql())->fetchAll("SELECT * FROM printers WHERE warehouse_id = :warehouse_id $whereType", $params);
        $printers = [];
        foreach ($res as $printer_data)
            $printers[$printer_data['id']] = new Printer($printer_data);
        return $printers;
    }

    public static function getById($id)
    {
        $printer_data = (new SafeMysql())->fetchOne("SELECT * FROM printers WHERE id = :id LIMIT 1", ['id' => (int)$id]);
        if (empty($printer_data))
            return null;
        return new Printer($printer_data);
    }

    public static function setStatus(Printer $printer, $status)
    {
        $printer->setState($status);
        $printer->save(['id', 'state']);
    }

    public static function setDefault(Printer $printer)
    {
        (new SafeMysql())->execute("UPDATE printers
                SET state = :active_state
                WHERE type = :type AND warehouse_id = :warehouse_id AND state = :default_state", [
            'active_state' => Printer::PRINTER_ACTIVE,
            'type' => $printer->getType(),
            'warehouse_id' => $printer->getWarehouseId(),
            'default_state' => Printer::PRINTER_DEFAULT,
        ]);
        $printer->setState(Printer::PRINTER_DEFAULT);
        $printer->save(['id', 'state']);
    }

    public static function getDefault($warehouse_id, $type_printers = Printer::RETAIN_ME)
    {
        $params = ['type' => $type_printers, 'warehouse_id' => (int)$warehouse_id];
        $source_id = (int)(new SafeMysql())->fetchScalar(
            "SELECT source_id FROM printers WHERE type = :type AND warehouse_id = :warehouse_id AND state = :state LIMIT 1",
            $params + ['state' => Printer::PRINTER_DEFAULT]
        );
        if (empty($source_id))
            $source_id = (int)(new SafeMysql())->fetchScalar(
                "SELECT source_id FROM printers WHERE type = :type AND warehouse_id = :warehouse_id AND state = :state LIMIT 1",
                $params + ['state' => Printer::PRINTER_ACTIVE]
            );
        return $source_id;
    }
}
