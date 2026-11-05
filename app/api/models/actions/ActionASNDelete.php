<?php

namespace app\api\models\actions;

use app\api\API;
use app\backend\models\ASNs;
use app\core\entities\ASN;
use app\core\models\Warehouses;

class ActionASNDelete extends BaseAction
{
    private $user;
    private $ASNs_id;

    public function __construct($data)
    {
        $this->user = API::getUser();
        $this->ASNs_id = [];
        if (count($data) < 1)
            return;
        foreach ($data as $id)
            $this->ASNs_id[] = (int)$id['ASN'];
    }

    public function validateFields() { }

    public function process()
    {
        if (count($this->ASNs_id) < 1)
            return ['critical_error' => [
                'error_id' => 1,
                'error_msg' => 'Missing ASNs']
            ];
        $errors_ASN = [];
        $ASN = [];
        foreach ($this->ASNs_id as $key => $ASN_id) {
            /**
             * @var $_ASN ASN
             */
            $_ASN = ASNs::getByIdAndUser($ASN_id, $this->user);
            if (is_null($_ASN)) {
                $errors_ASN[] = ["Rec #$key" => $ASN_id, 'error' => 'ASN not found'];
                continue;
            }
            if ($_ASN->cancelled())
                $errors_ASN[] = ["Rec #$key" => $ASN_id, 'error' => 'ASN is already cancelled'];
            elseif($_ASN->new()) {
                $errors = ASNs::cancel($_ASN, Warehouses::isVirtual(API::getUser()->getWarehouseId()), API::getUser()->getId());
                if ($errors === true)
                    $ASN[] = ["Rec #$key" => $ASN_id];
                else
                    $errors_ASN[] = ["Rec #$key" => $ASN_id, 'error' => implode(';', $errors)];
            } else
                $errors_ASN[] = ["Rec #$key" => $ASN_id, 'error' => 'ASN cannot be cancelled'];
        }
        return ['cancelled_ASNs' => $ASN, 'error_ASNs' => $errors_ASN];
    }

    public function getRequestFieldsConfig()
    {
        return [];
    }

    public function getResponseFieldsConfig()
    {
        return [];
    }
}
