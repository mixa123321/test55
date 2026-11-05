<?php

namespace app\api\models\actions;

use app\api\API;
use app\backend\models\ASNs;
use app\core\entities\ASN;

class ActionASNGet extends BaseAction
{
    private $user;
    private $ASNs_id;

    public function __construct($data)
    {
        $this->user = API::getUser();
        $data = json_decode($data['q'] ?? '', 1);
        $this->ASNs_id = [];
        if (empty($data) || count($data) < 1)
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
            $ASN[] = ["Rec #$key" => $_ASN->toArrayView()];
        }
        return ['info_ASNs' => $ASN, 'error_ASNs' => $errors_ASN];
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
