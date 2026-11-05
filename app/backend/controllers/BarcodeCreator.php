<?php

namespace app\backend\controllers;

use app\core\exceptions\RedirectException;

class BarcodeCreator extends AuthController
{
    /** @throws  */
    public function index()
    {
        throw new RedirectException('/housekeeping/barcode_creator');
    }
}
