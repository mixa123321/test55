<?php

namespace app\core\helpers;

use setasign\Fpdi\Fpdi;

class FPDIFacade
{

    private static $loaded = false;

    private static function init()
    {
        require_once(ROOT_PATH . VENDOR_PATH . '/FPDF/fpdf.php');
        require_once(ROOT_PATH . VENDOR_PATH . '/FPDF/tfdpf/tfpdf.php');
        require_once(ROOT_PATH . VENDOR_PATH  . '/FPDI/autoload.php');

        self::$loaded = true;
    }

    public static function new($orientation = 'P', $unit = 'pt', $size = 'A4'): Fpdi
    {
        if (!self::$loaded)
            self::init();
        return new Fpdi($orientation, $unit, $size);
    }
}