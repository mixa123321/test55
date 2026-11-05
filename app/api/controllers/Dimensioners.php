<?php

namespace app\api\controllers;

class Dimensioners extends AuthApiController
{
    public function postMeasure()
    {
        self::callAction('POST dimensioners/measure', 'ActionDimensionersPost', 'measure');
    }
}
