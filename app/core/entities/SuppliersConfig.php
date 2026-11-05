<?php

namespace app\core\entities;

use app\core\dbal\SafeMysql;

class SuppliersConfig extends Entity
{
    const TYPE_SHOPIFY_SPLIT_ORDER = 'shopifySplitOrder';
    // EU PID mapping (ABF-475): empty value = use SKU as default, non-empty = the channel attribute/field name to read the value from
    const TYPE_SHOPIFY_NS_PID_ATTRIBUTE = 'shopify_ns_pid_attribute';
    const TYPE_WOOCOMMERCE_NS_PID_ATTRIBUTE = 'woocommerce_ns_pid_attribute';
    const TYPE_MAGENTO_NS_PID_ATTRIBUTE = 'magento_ns_pid_attribute';
    const TYPE_MAGENTO_S_PID_ATTRIBUTE = 'magento_s_pid_attribute';

    const FIELD_VALUE_BOOL = 'value_bool';
    const FIELD_VALUE_INT = 'value_int';
    const FIELD_VALUE_STRING = 'value_string';

    const TYPES = [
        self::TYPE_SHOPIFY_SPLIT_ORDER => self::FIELD_VALUE_BOOL,
        self::TYPE_SHOPIFY_NS_PID_ATTRIBUTE => self::FIELD_VALUE_STRING,
        self::TYPE_WOOCOMMERCE_NS_PID_ATTRIBUTE => self::FIELD_VALUE_STRING,
        self::TYPE_MAGENTO_NS_PID_ATTRIBUTE => self::FIELD_VALUE_STRING,
        self::TYPE_MAGENTO_S_PID_ATTRIBUTE => self::FIELD_VALUE_STRING,
    ];

    private $supplier_id;
    private $type;
    private $value_bool = false;
    private $value_int = 0;
    private $value_string = '';

    public function getSupplierId(): int
    {
        return (int)$this->supplier_id;
    }

    public function setSupplierId(int $supplier_id): SuppliersConfig
    {
        $this->supplier_id = $supplier_id;
        return $this;
    }

    public function getType(): string
    {
        return (string)$this->type;
    }

    public function setType(string $type): SuppliersConfig
    {
        if (!isset(self::TYPES[$type])) {
            throw new \Exception('Wrong type');
        }
        $this->type = $type;
        return $this;
    }

    public function getValueBool(): bool
    {
        return (bool)$this->value_bool;
    }

    public function setValueBool(bool $value_bool): SuppliersConfig
    {
        $this->value_bool = $value_bool;
        return $this;
    }

    public function getValueInt(): int
    {
        return (int)$this->value_int;
    }

    public function setValueInt(int $value_int): SuppliersConfig
    {
        $this->value_int = $value_int;
        return $this;
    }

    public function getValueString(): string
    {
        return (string)$this->value_string;
    }

    public function setValueString(string $value_string): SuppliersConfig
    {
        $this->value_string = $value_string;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'supplier_id' => $this->getSupplierId(),
            'type' => $this->getType(),
            'value_bool' => $this->getValueBool(),
            'value_int' => $this->getValueInt(),
            'value_string' => $this->getValueString(),
        ];
    }

    public function getSetting()
    {
        $sql = "
        SELECT value_bool, value_int, value_string
        FROM suppliers_config
        WHERE supplier_id = :supplier_id AND type = :type;";
        $result = (new SafeMysql())->fetchOne($sql, ['supplier_id' => $this->getSupplierId(), 'type' => $this->getType()]);
        if (self::TYPES[$this->getType()] === self::FIELD_VALUE_BOOL) {
            if (!$result)
                return false;
            return (bool)($result['value_bool']);
        } elseif (self::TYPES[$this->getType()] === self::FIELD_VALUE_INT) {
            if (!$result)
                return 0;
            return (int)($result['value_int']);
        } elseif (self::TYPES[$this->getType()] === self::FIELD_VALUE_STRING) {
            if (!$result)
                return '';
            return (string)($result['value_string']);
        }
        throw new \Exception('Wrong field');
    }

    /**
     * @param SuppliersConfig[] $objects
     * @throws \app\core\db\DBException
     */
    public static function saveConfigs(array $objects): void
    {
        if (!$objects)
            return;
        $values = $bind = [];
        foreach ($objects as $key => $object) {
            $values[] = "(:supplier_id_$key, :type_$key, :value_bool_$key, :value_int_$key, :value_string_$key)";
            $bind["supplier_id_$key"] = $object->getSupplierId();
            $bind["type_$key"] = $object->getType();
            $bind["value_bool_$key"] = $object->getValueBool();
            $bind["value_int_$key"] = $object->getValueInt();
            $bind["value_string_$key"] = $object->getValueString();
        }
        $sql = "
        INSERT INTO suppliers_config (supplier_id, type, value_bool, value_int, value_string)
        VALUES " . implode(', ', $values) . "
        ON DUPLICATE KEY UPDATE value_bool = VALUES(value_bool), value_int = VALUES(value_int), value_string = VALUES(value_string)";
        (new SafeMysql())->execute($sql, $bind);
    }

    public static function readConfigsBySupplierId(int $supplier_id): array
    {
        $sql = "
        SELECT type, value_bool, value_int, value_string
        FROM suppliers_config
        WHERE supplier_id = :supplier_id";
        $data = [];
        $result = (new SafeMysql())->fetchAll($sql, ['supplier_id' => $supplier_id]);
        foreach ($result as $item) {
            if (!isset(self::TYPES[$item['type']]))
                throw new \Exception('Wrong type');
            $field = self::TYPES[$item['type']];
            // TYPES only maps to BOOL/STRING today, so Psalm proves the INT/STRING branches below can't currently
            // be reached/missed - kept anyway so a future value type (e.g. a date) fails loudly instead of silently
            /**
             * @psalm-suppress RedundantCondition
             * @psalm-suppress TypeDoesNotContainType
             */
            if ($field === self::FIELD_VALUE_BOOL)
                $data[$item['type']] = (bool)$item[$field];
            elseif ($field === self::FIELD_VALUE_INT)
                /** @psalm-suppress InvalidArrayOffset */
                $data[$item['type']] = (int)$item[$field];
            elseif ($field === self::FIELD_VALUE_STRING)
                $data[$item['type']] = (string)$item[$field];
            else
                throw new \Exception('Wrong field');
        }
        //Если настройки нет в базе, наполняем значениями по умолчанию
        foreach (self::TYPES as $type => $field) {
            if (isset($data[$type]))
                continue;
            /**
             * @psalm-suppress RedundantCondition
             * @psalm-suppress TypeDoesNotContainType
             */
            if ($field === self::FIELD_VALUE_BOOL)
                $data[$type] = false;
            elseif ($field === self::FIELD_VALUE_INT)
                $data[$type] = 0;
            elseif ($field === self::FIELD_VALUE_STRING)
                $data[$type] = '';
            else
                throw new \Exception('Wrong field');
        }
        return $data;
    }

    public static function readConfigBySupplierId(int $supplier_id, string $config_name)
    {
        if (!isset(self::TYPES[$config_name])) {
            throw new \Exception('Wrong config name');
        }
        $field = self::TYPES[$config_name];
        $sql = "
        SELECT value_bool, value_int, value_string
        FROM suppliers_config
        WHERE supplier_id = :supplier_id AND type = :type";
        $values = (new SafeMysql())->fetchAll($sql, ['supplier_id' => $supplier_id, 'type' => $config_name]);
        /**
         * @psalm-suppress RedundantCondition
         * @psalm-suppress TypeDoesNotContainType
         */
        if ($field === self::FIELD_VALUE_BOOL) {
            if (!$values)
                return false;
            return (bool)$values[0][self::FIELD_VALUE_BOOL];
        } elseif ($field === self::FIELD_VALUE_INT) {
            if (!$values)
                return 0;
            return (int)$values[0][self::FIELD_VALUE_INT];
        } elseif ($field === self::FIELD_VALUE_STRING) {
            if (!$values)
                return '';
            return (string)$values[0][self::FIELD_VALUE_STRING];
        }
        throw new \Exception('Wrong field value');
    }
}