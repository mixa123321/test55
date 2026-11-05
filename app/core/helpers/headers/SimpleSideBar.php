<?php

namespace app\core\helpers\headers;

use app\core\entities\LogisticCompany;

class SimpleSideBar extends SideBar
{
    public function __construct(?LogisticCompany $logistic_company = null)
    {
        $this->setHead(is_null($logistic_company) ? 'logo_mask.png' : $logistic_company->getLogo());
    }

    public function render()
    {
    }
}