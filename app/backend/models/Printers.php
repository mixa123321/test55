<?php

namespace app\backend\models;

use app\core\entities\Printer;

class Printers extends \app\core\models\Printers
{
    public static function getSelectPrinters($warehouse_id)
    {
        $printers = self::getPrinters($warehouse_id);
        $printer_list = [];
        $printer_def = 0;
        foreach ($printers as $printer_id => $printer)
            if ($printer->getState() != Printer::PRINTER_BLOCKED) {
                $printer_list[$printer_id] = $printer->getName() . " (Source ID #{$printer->getSourceId()})";
                if ($printer->getState() == Printer::PRINTER_DEFAULT)
                    $printer_def = $printer_id;
            }
        return [$printer_def, $printer_list];
    }
}
