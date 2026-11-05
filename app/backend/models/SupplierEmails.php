<?php

namespace app\backend\models;

use app\core\dbal\SafeMysql;
use app\core\entities\SupplierEmail;
use app\core\helpers\Validator;

class SupplierEmails extends \app\core\models\SupplierEmails
{
    /**
     * @param $supplier_email SupplierEmail
     * @return array
     */
    public static function validate(SupplierEmail $supplier_email)
    {
        $rules = [
//            'name' => [
//                'val' => $supplier_email->getName(),
//                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 1000]]
//            ],
            'supplier_id' => [
                'val' => $supplier_email->getSupplierId(),
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
        ];
        $validator = new Validator($rules);
        $errors = $validator->validate();
        if (empty($supplier_email->getName()))
            $validator->addError('invalid_email',"E-mail is required");
        elseif (!filter_var($supplier_email->getName(), FILTER_VALIDATE_EMAIL))
            $validator->addError('invalid_email',"E-mail invalid");
        if (!empty($errors)) {
            return $errors;
        }
        $old_value = self::getByNameAndSupplierId($supplier_email->getSupplierId(), $supplier_email->getName());
        if (!empty($old_value)) {
            if ($supplier_email->getId() !== $old_value->getId()) {
                $validator->addError('not_uniq', 'Not unique name');
            }
        }
        return $validator->getErrors();
    }

    public static function getById($id)
    {
        $res = (new SafeMysql())->fetchOne('SELECT * FROM supplier_emails WHERE id = :id', ['id' => $id]);
        if (empty($res)) {
            return null;
        }
        return new SupplierEmail($res);
    }

    public static function getBySupplierId($supplier_id)
    {
        return (new SafeMysql())->fetchAll('SELECT * FROM supplier_emails WHERE supplier_id = :supplier_id', ['supplier_id' => $supplier_id]);
    }

    public static function getListBySupplierId($supplier_id)
    {
        return (new SafeMysql())->fetchAll("SELECT se.*,
                                           IF(se.is_active AND (SELECT count(*) FROM supplier_notifications sn 
                                                                    WHERE sn.supplier_email_id = se.id) > 0,
                                              true, false) as used
                                    FROM supplier_emails se WHERE se.supplier_id = :supplier_id", ['supplier_id' => $supplier_id]);
    }

    public static function getBySupplierIdAndType($supplier_id, $type_id)
    {
        $supplier_id = (int) $supplier_id;
        $type_id = (int) $type_id;
        return (new SafeMysql())->fetchAll("SELECT sm.id, sm.name, IFNULL(sn.type_id, 0) as is_active 
                                            FROM supplier_emails sm
                                                 LEFT JOIN supplier_notifications sn ON sm.id = sn.supplier_email_id
                                                    AND sn.type_id = :type_id
                                                        WHERE sm.supplier_id = :supplier_id", [
            'supplier_id' => $supplier_id,
            'type_id' => $type_id
        ]);
    }

    public static function getByNameAndSupplierId($supplier_id, $name)
    {
        $supplier_id = (int) $supplier_id;
        $res = (new SafeMysql())->fetchOne('SELECT * FROM supplier_emails WHERE supplier_id = :supplier_id and name = :name LIMIT 1', [
            'supplier_id' => $supplier_id,
            'name' => $name
        ]);
        if (!empty($res)) {
            return new SupplierEmail($res);
        }
        return null;
    }

    public static function changeActiveList(array $supplier_email_ids, bool $active, $user_id)
    {
        if (!empty($supplier_email_ids)) {
            array_walk($supplier_email_ids, function (&$m) {
                $m = (int) $m;
            });
            $emails = (new SafeMysql())->fetchAll('SELECT * FROM supplier_emails WHERE id IN (:ids)', ['ids' => $supplier_email_ids]);
            foreach ($emails as $mail) {
                $emailObj = new SupplierEmail($mail);
                $emailObj->setIsActive($active);
                $emailObj->save(['id', 'is_active']);
                SupplierNotificationHistories::changeSupplierEmail($emailObj->getSupplierId(), $user_id, [
                    'supplier_email_id' => $emailObj->getId(),
                    'active' => $active
                ]);
            }
        }
    }

    public static function remove(SupplierEmail $supplier_email, $user_id)
    {

        SupplierNotifications::removeForSupplierEmail($supplier_email->getId());
        (new SafeMysql())->execute('DELETE FROM supplier_emails WHERE id = :id', ['id' => $supplier_email->getId()]);
        SupplierNotificationHistories::removeSupplierEmail($supplier_email, $user_id);
    }


}
