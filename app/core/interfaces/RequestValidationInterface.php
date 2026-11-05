<?php

namespace app\core\interfaces;

interface RequestValidationInterface
{
    const ERROR_KEY_PREFIX = 'Rec #';

    const MIN_LEN_2 = 2;
    const MIN_LEN_3 = 3;

    const MAX_LEN_2 = 2;
    const MAX_LEN_3 = 3;
    const MAX_LEN_4 = 4;
    const MAX_LEN_15 = 15;
    const MAX_LEN_20 = 20;
    const MAX_LEN_25 = 25;
    const MAX_LEN_50 = 50;
    const MAX_LEN_100 = 100;
    const MAX_LEN_255 = 255;

    const REGEXP_INT_1_TO_11 = '^\d{1,11}$';
    const REGEXP_FLOAT_1_TO_4_POINT_1_TO_2 = '^\d{1,4}(\.\d{1,2})?$';
    const REGEXP_FLOAT_1_TO_5_POINT_1_TO_2 = '^\d{1,5}(\.\d{1,2})?$';
    const REGEXP_INT_AS_BOOL = '^[01]{1}$';

    const DATE_WITHOUT_TIME = 'Y-m-d';

    public function getValidationRequestErrors(): array;

    public function setRequestData(array $request_data): RequestValidationInterface;

    public function getValidRequestData(): array;

    public function validationRequest(): RequestValidationInterface;
}