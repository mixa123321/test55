<?php

namespace app\core\helpers;

class Arr
{
    public static function arrayMergeRecursiveDistinct()
    {
        $arrays = func_get_args();
        $merged = array_shift($arrays);
        foreach ($arrays as $innerArray)
            foreach ($innerArray as $key => $value)
                if (\is_array($value) && isset($merged[$key]) && \is_array($merged[$key]))
                    $merged[$key] = self::arrayMergeRecursiveDistinct($merged[$key], $value);
                else
                    $merged[$key] = $value;
        return $merged;
    }

    public static function isAssoc($array)
    {
        if ($array === [])
            return false;
        return array_keys($array) !== range(0, \count($array) - 1);
    }

    public static function map($array, $keyColumn, $valueColumn)
    {
        $result = [];
        foreach ($array as $row)
        {
            if (!isset($row[$keyColumn]) || !isset($row[$valueColumn]))
                continue;
            $result[$row[$keyColumn]] = $row[$valueColumn];
        }
        return $result;
    }

    public static function maps($array, $keyColumn, $valueColumns)
    {
        $result = [];
        if (!is_array($valueColumns))
            return [];
        foreach ($array as $row) {
            $keys = array_keys($row);
            if (!isset($row[$keyColumn]))
                continue;
            $line = [];
            foreach ($valueColumns as $col) {
                if (!in_array($col, $keys))
                    break;
                $line[$col] = $row[$col];
            }
            if (count($line) == count($valueColumns))
                $result[$row[$keyColumn]] = $line;
        }
        return $result;
    }

    public static function filterKeysRecursive($array, $keys)
    {
        if (empty($keys))
            return $array;
        foreach ($array as $key => &$v)
        {
            if (\is_array($v))
                $v = self::filterKeysRecursive($v, $keys);
            if (\in_array($key, $keys))
                unset($array[$key]);
        }
        return $array;
    }

    public static function column($array, $name, $isSort = true, $index = false)
    {
        $res = [];
        foreach ($array as $key => $value)
            if ($index)
                $res[$key] = $value[$name];
            else
                $res[] = $value[$name];
        if ($isSort)
            if ($index)
                asort($res);
            else
                sort($res);
        return $res;
    }

    public static function columnAssoc($array, $name)
    {
        $res = [];
        foreach ($array as $key => $value)
            $res[$key] = $value[$name];
        return $res;
    }

    public static function toList($array)
    {
        $list = [];
        foreach ($array as $value)
            $list[$value] = $value;
        return $list;
    }

    public static function cleanEmptyVal($arr)
    {
        foreach ($arr as $key => &$val)
        {
            if (\is_array($val))
                $val = self::cleanEmptyVal($val);
            if (empty($val))
                unset($arr[$key]);
        }
        unset($val);
        return $arr;
    }

    public static function intersectCleanEmpty()
    {
        $arrays = self::cleanEmptyVal(func_get_args());
        if (count($arrays) === 0)
            return [];
        if (count($arrays) === 1)
            return array_shift($arrays);
        return array_intersect($arrays[0], $arrays[1]);
    }

    public static function array2csvDownload($array, $filename = "export.csv", $allowedColumns=[])
    {
        if(empty($array)) throw new \Exception("Empty array in " . __METHOD__ );
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="'.$filename.'";');
        $f = fopen('php://output', 'w');
        $columns = array_keys((!empty($allowedColumns)) ? array_intersect_key($array[0], array_flip($allowedColumns)) : $array[0]);
        array_unshift($array, array_combine($columns, $columns));
        foreach ($array as $line) {
            if(!empty($allowedColumns))
                $line = array_intersect_key($line, array_flip($allowedColumns));
            fputcsv($f, $line);
        }
        fpassthru($f);
    }

    public static function csv2array($filename = "export.csv", $delimiter=";", $enclosure = '"', $escape_char = "\\")
    {
        $handle = @fopen($filename, "r");
        if ($handle) {
            $i = 0;
            while (($row = fgetcsv($handle, 4096,  $delimiter, $enclosure, $escape_char)) !== false) {
                $row = explode(',', $row[0]);
                if (empty($fields)) {
                    $fields = $row;
                    continue;
                }
                foreach ($row as $k => $value)
                    $array[$i][$fields[$k]] = $value;
                $i++;
            }
            if (!feof($handle))
                throw new \ErrorException("Error: unexpected fgets() fail");
            fclose($handle);
        }
        return $array;
    }

    public static function includeAll($arr, $inarr)
    {
        foreach ($arr as $el)
            if (!in_array($el, $inarr))
                return false;
        return true;
    }

    public static function slices($arr, $len)
    {
        if ($len < 2)
            return [];
        $res = [];
        do {
            $res[] = array_splice($arr, 0, $len);
        } while (count($arr) > 0);
        return $res;
    }

    public static function equal($arr1, $arr2)
    {
        return empty(array_diff($arr1, $arr2)) && empty(array_diff($arr2, $arr1));
    }

    public static function findOneBy($arr, $key, $value)
    {
        foreach ($arr as $el)
            if ($el[$key] == $value)
                return $el;
        return null;
    }

    public static function groupBy($arr, $key)
    {
        $res = [];
        foreach ($arr as $el)
            $res[$el[$key]][] = $el;
        return $res;
    }

    public static function mapWithKey(callable $callable, array $arr)
    {
        $res = [];
        foreach ($arr as $key => $el)
            $res[$key] = $callable($el, $key);
        return $res;
    }

    public static function getByPath($arr, $path, $default = null, $all = false)
    {
        $keys = self::getKeysInPath($path);

        foreach ($keys as $i => $key) {
            if ($key == '*') {
                $new_keys = $keys;
                array_splice($new_keys, 0, $i + 1);
                $all_res = [];
                foreach (array_keys($arr) as $k) {
                    $res = self::getByPath($arr, $k . '.' . implode('.', $new_keys), $default, $all);
                    if ($res !== $default) {
                        if ($all)
                            $all_res = array_merge($all_res, $res);
                        else
                            return $res;
                    }
                }
                if ($all)
                    return $all_res;
                return $default;
            }
            if (!isset($arr[$key]))
                return $default;
            $arr = $arr[$key];
        }
        if ($all)
            return [$path => $arr];
        return $arr;
    }

    /**
     * Returns array [path => value]
     */
    public static function getAllByPath(array $arr, string $path)
    {
        $keys = self::getKeysInPath($path);

        $res = [];
        foreach ($keys as $i => $key) {
            if ($key == '*') {
                $new_keys = $keys;
                array_splice($new_keys, 0, $i + 1);
                foreach (array_keys($arr) as $k) {
                    $res = array_merge($res, self::getAllByPath($arr, $k . '.' . implode('.', $new_keys)));
                }
                return $res;
            }
            if (!isset($arr[$key]))
                return [];
            $arr = $arr[$key];
        }
        return [$path => $arr];
    }

    public static function hasByPath(array $arr, string $path)
    {
        $keys = self::getKeysInPath($path);

        foreach ($keys as $i => $key) {
            if ($key == '*') {
                $new_keys = $keys;
                array_splice($new_keys, 0, $i + 1);
                foreach (array_keys($arr) as $k) {
                    if (self::hasByPath($arr, $k . '.' . implode('.', $new_keys)))
                        return true;
                }
                return false;
            }
            if (!isset($arr[$key]))
                return false;
            $arr = $arr[$key];
        }
        return true;
    }

    public static function setByPath(array &$arr, string $path, $value)
    {
        $keys = self::getKeysInPath($path);

        $last = array_pop($keys);
        foreach ($keys as $i => $key) {
            if ($key == '*') {
                $new_keys = $keys;
                array_splice($new_keys, 0, $i + 1);
                array_unshift($new_keys, $last);
                foreach (array_keys($arr) as $k) {
                    self::setByPath($arr, $k . '.' . implode('.', $new_keys), $value);
                }
                return;
            }
            if (!is_array($arr))
                continue;
            if (!isset($arr[$key]))
                $arr[$key] = [];
            $arr = &$arr[$key];
        }
        if (is_array($arr))
            $arr[$last] = $value;
    }

    public static function unsetByPath(array &$arr, string $path)
    {
        $keys = self::getKeysInPath($path);

        $last = array_pop($keys);
        foreach ($keys as $i => $key) {
            if ($key == '*' && is_array($arr)) {
                $new_keys = $keys;
                array_splice($new_keys, 0, $i + 1);
                array_unshift($new_keys, $last);
                foreach (array_keys($arr) as $k) {
                    self::unsetByPath($arr, $k . '.' . implode('.', $new_keys));
                }
            }
            if (!isset($arr[$key]))
                return;
            $arr = &$arr[$key];
        }
        if (isset($arr[$last]))
            unset($arr[$last]);
    }

    private static function getKeysInPath(string $path)
    {
        return array_map(function ($key) {
            if (strpos($key, '[') !== false)
                return str_replace(['[', ']'], '', $key);
            return $key;
        }, explode('.', $path));
    }

    public static function params2Bind(array $params): array
    {
        if (!$params)
            return [];
        $result = [];
        foreach ($params as $key => $value)
            $result[":$key"] = $value;
        return $result;
    }
}
