<?php

namespace app\backend\models;

use app\core\helpers\Validator;
use app\core\models\ReturnCarriers as ReturnCarriersModel;

/**
 * @property int $current_page
 * @property int $id
 * @property int $service_id
 * @property string $name
 * @property string $credentials_key
 * @property int $is_test
 * @property string $countries
 */
class ReturnCarriers extends ReturnCarriersModel
{
    const COUNT_ON_PAGE = 20;
    const EDIT_URL = '/return_carriers/edit';
    const CREATE_URL = '/return_carriers/create';

    private $current_page = 0;
    private $id = 0;
    private $service_id = 0;
    private $name = '';
    private $credentials_key = '';
    private $is_test = 0;
    private $countries = '';

    public function __get($name)
    {
        if (!in_array($name, ['current_page', 'id', 'service_id', 'name', 'credentials_key', 'is_test', 'countries'])) {
            $trace = debug_backtrace();
            trigger_error('Undefined property via __get(): ' . $name . ' in ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'], E_USER_NOTICE);
            return null;
        }
        return $this->$name;
    }

    public function __set($name, $value)
    {
        if (!in_array($name, ['current_page', 'id', 'service_id', 'name', 'credentials_key', 'is_test', 'countries'])) {
            $trace = debug_backtrace();
            trigger_error('Undefined property via __set(): ' . $name . ' in ' . $trace[0]['file'] . ' on line ' . $trace[0]['line'], E_USER_NOTICE);
            return null;
        }
        if (gettype($value) !== gettype($this->$name)) {
            throw new \Exception("Field $name has wrong type");
        }
        $this->$name = $value;
    }

    public function validationCurrentPage(): array
    {
        return (new Validator([
            'CurrentPage' => [
                'val' => $this->current_page,
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ]
        ]))->validate();
    }

    public function validationId(bool $zero = false): array
    {
        return (new Validator([
            'id' => [
                'val' => $this->id,
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [$zero ? 0 : 1, PHP_INT_MAX]]
            ]
        ]))->validate();
    }

    public function validationNameIsUnique(): array
    {
        if (self::isNameDuplicated($this->name, $this->id)) {
            return [
                [
                    'field' => 'Name',
                    'code' => 'field_name_is_duplicate',
                    'message' => 'Field name is duplicate'
                ]
            ];
        }
        return [];
    }

    public function validationBeforeSave(): array
    {
        $validator = (new Validator([
            'Service' => [
                'val' => $this->service_id,
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [1, PHP_INT_MAX]]
            ],
            'Name' => [
                'val' => $this->name,
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'Credentials key' => [
                'val' => $this->credentials_key,
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_STRING => [1, 255]]
            ],
            'Test' => [
                'val' => $this->is_test,
                'rules' => [Validator::REQUIRED => 1, Validator::TYPE_INT => [0, 1]]
            ]
        ]));
        if (!json_decode($this->countries)) {
            $validator->addError('not_correct', "Country is required", 'Countries');
        }
        return $validator->validate();
    }

    public function getEditUri(): string
    {
        if ($this->id) {
            return self::EDIT_URL . '?' . http_build_query([
                    'c' => $this->current_page,
                    'id' => $this->id,
                ]);
        }
        return self::CREATE_URL . '?' . http_build_query([
                'c' => $this->current_page,
                'id' => $this->id,
                'service_id' => $this->service_id,
                'name' => $this->name,
                'credentials_key' => $this->credentials_key,
                'is_test' => $this->is_test,
                'countries' => $this->countries
            ]);
    }
}