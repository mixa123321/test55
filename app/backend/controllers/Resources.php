<?php

namespace app\backend\controllers;

use app\core\helpers\File;

class Resources extends AuthController
{
    public function downloadPrinterDriver()
    {
        return File::outputFile(ROOT_PATH . RESOURCES_ASSETS_PATH . '/eplprinter.exe', OUTPUT_ATTACHMENT);
    }
}