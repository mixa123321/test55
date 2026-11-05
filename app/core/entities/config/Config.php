<?php

namespace app\core\entities\config;

use app\core\db\DBException;
use app\core\dbal\SafeMysql;
use app\core\entities\Entity;
use app\core\statical\WithDate;

abstract class Config extends Entity
{
    const FIELD_VALUE_BOOL = 'value_bool';
    const FIELD_VALUE_INT = 'value_int';
    const FIELD_VALUE_STRING = 'value_string';
    const FIELD_VALUE_DATE = 'value_date';

    protected const TYPES = [];
    protected const DEFAULTS = [];
    protected const TABLE_NAME = '';
    protected const ID = '';

    private $type;
    private $value_bool = false;
    private $value_int = 0;
    private $value_string = '';
    private $value_date = '';

    public function getType(): string
    {
        return (string)$this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getValueBool(): bool
    {
        return (bool)$this->value_bool;
    }

    public function setValueBool(bool $value_bool): self
    {
        $this->value_bool = $value_bool;
        return $this;
    }

    public function getValueInt(): int
    {
        return (int)$this->value_int;
    }

    public function setValueInt(int $value_int): self
    {
        $this->value_int = $value_int;
        return $this;
    }

    public function getValueString(): string
    {
        return (string)$this->value_string;
    }

    public function setValueString(string $value_string): self
    {
        $this->value_string = $value_string;
        return $this;
    }

    public function getValueDate()
    {
        return $this->value_date ? date(WithDate::DATE_SQL_FORMAT, $this->value_date) : WithDate::EMPTY_DATE;
    }

    public function setValueDate(int $value_date): Config
    {
        $this->value_date = WithDate::dateToInt($value_date);
        return $this;
    }

    abstract function toArray(): array;

    /** @throws */
    protected static function getValueByType(string $config_param, array $values)
    {
        if (!isset(static::TYPES[$config_param]))
            throw new \Exception("Wrong config name ($config_param)");
        switch (static::TYPES[$config_param]) {
            case self::FIELD_VALUE_BOOL:
                return (bool)($values[self::FIELD_VALUE_BOOL] ?? static::DEFAULTS[$config_param] ?? false);
            case self::FIELD_VALUE_INT:
                return (int)($values[self::FIELD_VALUE_INT] ?? static::DEFAULTS[$config_param] ??0);
            case self::FIELD_VALUE_STRING:
                return (string)($values[self::FIELD_VALUE_STRING] ?? static::DEFAULTS[$config_param] ?? '');
            case self::FIELD_VALUE_DATE:
                return $values[self::FIELD_VALUE_DATE] ?? static::DEFAULTS[$config_param] ?? '';
        }
        throw new \Exception('Wrong field value');
    }

    public static function getDefaultConfigs(): array
    {
        $data = [];
        foreach (array_keys(static::TYPES) as $type)
            $data[$type] = self::getValueByType($type, []);
        return $data;
    }

    /** @throws */
    public static function readConfigById(int $id, string $config_param)
    {
        $fields = array_unique(array_values(static::TYPES));
        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM ' . static::TABLE_NAME . ' WHERE ' . static::ID . ' = :id AND type = :config_param LIMIT 1';
        $values = (new SafeMysql())->fetchOne($sql, ['id' => $id, 'config_param' => $config_param]);
        return self::getValueByType($config_param, $values ?? []);
    }

    /** @throws */
    public static function readConfigsById(int $id): array
    {
        $fields = array_merge(['type'], array_unique(array_values(static::TYPES)));
        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM ' . static::TABLE_NAME . ' WHERE ' . static::ID . ' = :id';
        $data = [];
        $result = (new SafeMysql())->fetchAll($sql, ['id' => $id]);
        foreach ($result as $item)
            $data[$item['type']] = self::getValueByType($item['type'], $item);
        foreach (static::TYPES as $type => $field)
            if (!isset($data[$type]))
                $data[$type] = self::getValueByType($type, []);
        return $data;
    }

    /** @throws */
    public static function readConfigsByIdsAndParams(array $ids, array $config_param)
    {
        if (!$ids || !$config_param || array_diff($config_param, array_keys( static::TYPES)))
            return [];
        $fields = array_merge(['type'], array_unique(array_values(static::TYPES)));
        $sql = 'SELECT ' . static::ID . ', ' . implode(', ', $fields) . ' FROM ' . static::TABLE_NAME . ' WHERE ' . static::ID . ' IN (:ids) AND type IN (:types)';
        $data = [];
        $result = (new SafeMysql())->fetchAll($sql, ['ids' => $ids, 'types' => $config_param]);
        foreach ($result as $item)
            $data[$item[static::ID]][$item['type']] = self::getValueByType($item['type'], $item);
        foreach ($ids as $id)
            foreach ($config_param as $type)
                if (!isset($data[$id][$type]))
                    $data[$id][$type] = self::getValueByType($type, []);
        return $data;
    }

    /**
     * @param int $id
     * @param Config[] $new_objects
     * @return array
     */
    public static function getChangesObjects(int $id, array $new_objects): array
    {
        $objects = $changes = [];
        foreach ($new_objects as $new_object)
            $objects[$new_object->getType()] = $new_object;
        $old_values = $id ? self::readConfigsByIdsAndParams([$id], array_keys($objects))[$id] : null;
        foreach ($new_objects as $new_object) {
            $old_value = $old_values === null ? self::getValueByType($new_object->getType(), []) : $old_values[$new_object->getType()];
            $new_value = self::getValueByType($new_object->getType(), $new_object->toArray());
            if ($old_value !== $new_value)
                $changes[$new_object->getType()] = $new_object;
        }
        return $changes;
    }

    /**
     * @param Config[] $objects
     * @return array
     */
    public static function getValuesFromObjects(array $objects): array
    {
        if (!$objects)
            return [];
        $result = [];
        foreach ($objects as $object)
            $result[$object->getType()] = self::getValueByType($object->getType(), $object->toArray());
        return $result;
    }

    /** @throws */
    protected static function removeConfigById(int $id, string $config_param)
    {
        if (!isset(static::TYPES[$config_param]))
            throw new \Exception('Wrong config name');
        $sql = 'DELETE FROM ' . static::TABLE_NAME . ' WHERE ' . static::ID . ' = :id AND type = :config_param';
        (new SafeMysql())->execute($sql, ['id' => $id, 'config_param' => $config_param]);
    }

    public static function removeAll(int $id): void {
        $sql = 'DELETE FROM ' . static::TABLE_NAME . ' WHERE ' . static::ID . ' = :id';
        (new SafeMysql())->execute($sql, ['id' => $id]);
    }

    public static function getData(int $id, string $config_param, $value): array
    {
        return [static::ID => $id, 'type' => $config_param, static::TYPES[$config_param] => $value];
    }

    /**
     * @param self[] $objects
     * @throws DBException
     */
    public static function saveConfigs(array $objects): void
    {
        if (!$objects)
            return;
        $fields_type = array_map(function ($item) {
            return "$item = VALUES($item)";
        }, array_unique(array_values(static::TYPES)));
        $values = [];
        $params = [];
        $fields = null;
        foreach ($objects as $key => $object) {
            $object_data = $object->toArray();
            if (is_null($fields))
                $fields = array_keys($object_data);
            $placeholders = [];
            foreach ($object_data as $field => $value) {
                $placeholders[] = ":{$field}_{$key}";
                $params["{$field}_{$key}"] = $value;
            }
            $values[] = '(' . implode(', ', $placeholders) . ')';
        }
        (new SafeMysql())->execute('
            INSERT INTO ' . static::TABLE_NAME . ' (' . implode(', ', $fields) . ')
            VALUES ' . implode(', ', $values) . '
            ON DUPLICATE KEY UPDATE ' . implode(', ', $fields_type), $params);
    }
}