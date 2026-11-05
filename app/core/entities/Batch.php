<?php


namespace app\core\entities;


use app\core\db\Mysql;
use app\core\helpers\View;

class Batch extends Entity
{
    private $id;
    private $number;
    private $SKU_id;
    private $expired;

    const MIN_EXPIRED = 2;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int) $id;
    }

    public function getNumber()
    {
        return $this->number;
    }

    public function setNumber($number)
    {
        $this->number = (string) $number;
    }

    public function getSKUId()
    {
        return $this->SKU_id;
    }

    public function setSKUId($id)
    {
        $this->SKU_id = (int) $id;
    }

    public function getExpired()
    {
        return $this->expired;
    }

    public function setExpired($expired)
    {
        $this->expired = $expired;
    }

    public function getInfo()
    {
        return self::toBatchInfo($this->getNumber(), $this->getExpired());
    }

    public static function toBatchInfo($number, $expired, $html = true, $br = false)
    {
        $number = $html ? View::toHtml($number) : addslashes($number);
        return ($html ? ("<b>Batch#:</b> $number;" . ($br ? '<br/>' : '') . ' <b>Exp#:</b> ') : "Batch#: $number; Exp#: ") . date('jS M Y', strtotime($expired));
    }

    public function save($fields_to_save)
    {
        if (empty($fields_to_save))
            return;
        $data = $this->toArray();
        foreach ($data as $data_field => $data_value)
            if (!\in_array($data_field, $fields_to_save, true))
                unset($data[$data_field]);
        if (!$this->getId())
            $this->setId(Mysql::saveRow('batches', $data));
        else
            Mysql::saveRow('batches', $data);
    }

    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'number' => $this->getNumber(),
            'SKU_id' => $this->getSKUId(),
            'expired' => $this->getExpired(),
        ];
    }

}