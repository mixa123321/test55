<?php

namespace app\api\models\actions;

use app\core\helpers\Str;
use app\core\helpers\Validator;

abstract class BaseAction
{
    protected $request_fields;

    abstract public function process();
    abstract public function getRequestFieldsConfig();
    abstract public function getResponseFieldsConfig();

    public function getRequestFields()
    {
        return $this->request_fields;
    }

    public function validateFields()
    {
        $validator = new Validator($this->getRequestFields());
        return $validator->validate();
    }

    public function setRequestFieldsByObject($object)
    {
        if (empty($this->request_fields))
            $this->request_fields = $this->getRequestFieldsConfig();
        foreach ($this->request_fields as $field_name => &$field_data) {
            $field_name_expl = explode(".", $field_name);
            $getter = $object;
            foreach ($field_name_expl as $one_field) {
                if (\is_array($getter)) {
                    foreach ($getter as &$getter_item) {
                        $getter_function = Str::toCamelCase("get_{$one_field}");
                        if (!method_exists($getter_item, $getter_function)) {
                            $getter = false;
                            break;
                        }
                        $getter_item = $getter_item->$getter_function();
                    }
                    unset($getter_item);
                    continue;
                }
                if (\is_object($getter)) {
                    $getter_function = Str::toCamelCase("get_{$one_field}");
                    if (!method_exists($getter, $getter_function)) {
                        $getter = false;
                        break;
                    }
                    $getter = $getter->$getter_function();
                }
            }
            if (!$getter)
                continue;
            $field_data['val'] = $getter;
        }
        unset($field_data);
    }

    public function setRequestFieldsByArray($array)
    {
        if (empty($this->request_fields))
            $this->request_fields = static::getRequestFieldsConfig();
        foreach ($this->request_fields as $field_name => &$field_data) {
            $field_name_expl = explode(".", $field_name);
            $val = $array;
            foreach ($field_name_expl as $one_field) {
                $val = isset($val[$one_field]) ? $val[$one_field] : null;
                if ($val === null)
                    break;
            }
            if ($val === null)
                continue;
            $field_data['val'] = $val;
        }
        unset($field_data);
    }
}
