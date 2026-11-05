<?php

namespace app\core\helpers;

class Filter
{
    const TYPE__EQUALS = 0;
    const TYPE__STARTS_WITH = 1;

    const SESSION_KEY = 'global_filters';
    const NOT_SELECTED = -1;

    public static function getFilterOperator(int $filter): string
    {
        switch ($filter) {
            case self::TYPE__EQUALS:
                return '=';
            case self::TYPE__STARTS_WITH:
                return 'LIKE';
            default:
                return '=';
        }
    }

    public static function getFilterValue(string $value, $type = self::TYPE__EQUALS): string
    {
        switch ($type) {
            case self::TYPE__EQUALS:
                return $value;
            case self::TYPE__STARTS_WITH:
                return $value . '%';
            default:
                return $value;
        }
    }

    public static function getFilterTypeFromRequest(array $request, ?string $key = null): int
    {
        $key = self::getFilterTypeKey($key);
        return $request[$key] ?? self::TYPE__EQUALS;
    }

    public static function getFilterTypeKey(?string $key = null): string
    {
        return $key ? $key . '_filter_type' : 'filter_type';
    }

    public static function processFilters(
        int $warehouse_id,
        array $request,
        array $default_filters = [],
        bool $clear = false,
        array $availableValues = []
    ): array {
        if (!isset($_SESSION[self::SESSION_KEY]))
            $_SESSION[self::SESSION_KEY] = [];

        $filters = array_merge($default_filters, (self::getFilters($warehouse_id) ?? []));
        if ($clear) // clear only needed filters
            $filters = $default_filters + $filters;

        $filter_params = $request['filter'] ?? [];

        if (!empty($filter_params)) {
            foreach ($filter_params as $key => $value) {
                if (is_array($value))
                    $value = array_filter($value, function($val) {
                        return $val != self::NOT_SELECTED;
                    });
                if ($value === self::NOT_SELECTED || empty($value))
                    $filters[$key] = self::NOT_SELECTED;
                else
                    $filters[$key] = $value;
            }
        }

        foreach ($availableValues as $key => $values) {
            if (isset($filters[$key])) {
                if (is_array($filters[$key])) {
                    $valid_values = [];
                    foreach ($filters[$key] as $val)
                        if (in_array($val, $values))
                            $valid_values[] = $val;

                    if (empty($valid_values))
                        $filters[$key] = isset($default_filters[$key]) ? $default_filters[$key] : self::NOT_SELECTED;
                    else
                        $filters[$key] = $valid_values;
                } elseif (!is_array($filters[$key]) && !in_array($filters[$key], $values))
                    $filters[$key] = isset($default_filters[$key]) ? $default_filters[$key] : self::NOT_SELECTED;
            }
        }

        self::setFilters($warehouse_id, $filters);

        return $filters;
    }

    public static function getFilters(int $warehouse_id, array $default_filters = []): ?array
    {
        $filters = $_SESSION[self::SESSION_KEY][$warehouse_id] ?? null;

        if ($filters !== null && !empty($default_filters))
            foreach ($default_filters as $key => $value)
                if (!isset($filters[$key]))
                    $filters[$key] = $value;

        return $filters;
    }

    public static function setFilters(int $warehouse_id, array $filters): void
    {
        $_SESSION[self::SESSION_KEY][$warehouse_id] = $filters;
    }

    public static function getFilterByKey(int $warehouse_id, string $key, $default = null)
    {
        $filters = self::getFilters($warehouse_id);
        return $filters[$key] ?? $default;
    }

    public static function processSelectedServices(int $warehouse_id, $selected_services)
    {
        if (!is_null($selected_services)) {
            $filters = self::getFilters($warehouse_id) ?? [];
            $filters['services'] = $selected_services;
            self::setFilters($warehouse_id, $filters);
        } else
            $selected_services = self::getFilterByKey($warehouse_id, 'services', self::NOT_SELECTED);

        return $selected_services;
    }
    
    /**
     * Process zone value to ensure it's either an array or Filter::NOT_SELECTED
     * 
     * @param mixed $zone Zone value to process
     * @return array|int Array of zone values or Filter::NOT_SELECTED
     */
    public static function clearZone($zone)
    {
        if (is_array($zone)) {
            if (empty($zone))
                return self::NOT_SELECTED;
                
            $result = [];
            foreach ($zone as $z) {
                if (!is_string($z) || $z === '')
                    continue;
                    
                $z = strpos($z, '|') !== false ? explode('|', $z)[1] : $z;
                $z = strpos($z, '%7C') !== false ? explode('%7C', $z)[1] : $z;
                
                if ($z !== '')
                    $result[] = $z;
            }
            
            return empty($result) ? self::NOT_SELECTED : $result;
        }
        
        // If zone is not a string or is empty, return NOT_SELECTED
        if (!is_string($zone) || $zone === '' || $zone == self::NOT_SELECTED)
            return self::NOT_SELECTED;
        
        // Process and wrap single value in array
        $zone = strpos($zone, '|') !== false ? explode('|', $zone)[1] : $zone;
        $zone = strpos($zone, '%7C') !== false ? explode('%7C', $zone)[1] : $zone;
        
        return [$zone];
    }
}