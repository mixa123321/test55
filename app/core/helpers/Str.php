<?php

namespace app\core\helpers;

class Str
{
    public static function toOneLine()
    {
        return implode(', ', array_filter(func_get_args()));
    }

    public static function toMaxLength($str, $max_length)
    {
        if (is_null($str))
            return '';
        if (!is_string($str))
            $str = (string)$str;
        return mb_strcut($str, 0, $max_length);
    }

    public static function processHsCode($hs_code)
    {
        $hs_code = preg_replace('/[^a-z0-9]/i', '', $hs_code);
        if (strlen($hs_code) < 10)
            $hs_code = str_pad($hs_code, 10, '0', STR_PAD_RIGHT);
        return $hs_code;
    }

    public static function clearWhitespace($str)
    {
        $replace = [
            "\xc2\xa0",
            json_decode('"\u200B"'),
        ];
        return preg_replace('/[\t\s]+/', ' ', trim(str_replace($replace, ' ', $str)));
    }

    public static function rusToTranslit($data)
    {
        $converter = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'e',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'h',
            'ц' => 'c',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'sch',
            'ь' => '\'',
            'ы' => 'y',
            'ъ' => '\'',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',

            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'E',
            'Ж' => 'Zh',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'Y',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'H',
            'Ц' => 'C',
            'Ч' => 'Ch',
            'Ш' => 'Sh',
            'Щ' => 'Sch',
            'Ь' => '\'',
            'Ы' => 'Y',
            'Ъ' => '\'',
            'Э' => 'E',
            'Ю' => 'Yu',
            'Я' => 'Ya',
        ];
        if (is_array($data)) {
            foreach ($data as &$item)
                $item = self::rusToTranslit($item);
            unset($item);
        } else
            if (preg_match('/[А-Яа-яЁё]/u', $data))
                $data = strtr($data, $converter);
        return $data;
    }

    public static function greekToTranslit($data)
    {
        $converter = [
            // upper case
            'Α' => 'A',
            'Ά' => 'A',
            'Β' => 'V',
            'Γ' => 'G',
            'Δ' => 'D',
            'Ε' => 'E',
            'Έ' => 'E',
            'Ζ' => 'Z',
            'Η' => 'I',
            'Ή' => 'I',
            'Θ' => 'Th',
            'Ι' => 'I',
            'Ί' => 'I',
            'Ϊ' => 'I',
            'ΐ' => 'I',
            'Κ' => 'K',
            'Λ' => 'L',
            'Μ' => 'M',
            'Ν' => 'N',
            'Ξ' => 'X',
            'Ο' => 'O',
            'Ό' => 'O',
            'Π' => 'P',
            'Ρ' => 'R',
            'Σ' => 'S',
            'Τ' => 'T',
            'Υ' => 'Y',
            'Ύ' => 'Y',
            'Ϋ' => 'Y',
            'ΰ' => 'Y',
            'Φ' => 'F',
            'Χ' => 'Ch',
            'Ψ' => 'Ps',
            'Ω' => 'O',
            'Ώ' => 'O',
            // lower case
            'α' => 'a',
            'ά' => 'a',
            'β' => 'v',
            'γ' => 'g',
            'δ' => 'd',
            'ε' => 'e',
            'έ' => 'e',
            'ζ' => 'z',
            'η' => 'i',
            'ή' => 'i',
            'θ' => 'th',
            'ι' => 'i',
            'ί' => 'i',
            'ϊ' => 'i',
            'κ' => 'k',
            'λ' => 'l',
            'μ' => 'm',
            'ν' => 'n',
            'ξ' => 'x',
            'ο' => 'o',
            'ό' => 'o',
            'π' => 'p',
            'ρ' => 'r',
            'σ' => 's',
            'τ' => 't',
            'υ' => 'y',
            'ύ' => 'y',
            'ϋ' => 'y',
            'φ' => 'f',
            'χ' => 'ch',
            'ψ' => 'ps',
            'ω' => 'o',
            'ώ' => 'o',
        ];

        if (is_array($data)) {
            foreach ($data as &$item)
                $item = self::greekToTranslit($item);
            unset($item);
        } else
            foreach ($converter as $greek => $lat)
                if (strpos($data, $greek) !== false) {
                    $data = strtr($data, $converter);
                    break;
                }
        return $data;
    }

    public static function getFirstLastName($full_name)
    {
        $arr = explode(' ', $full_name, 2);
        return [
            $arr[0],
            isset($arr[1]) ? $arr[1] : $arr[0],
        ];
    }

    public static function getFirstLastNamePatronymic($full_name)
    {
        $arr = explode(' ', $full_name, 3);
        return [
            'lastName'   => $arr[0] ?: '',
            'firstName'  => $arr[1] ?: '',
            'patronymic' => $arr[2] ?: '',
        ];
    }

    public static function toUtf8($data)
    {
        if (\is_array($data))
            foreach ($data as $key => &$value)
                $value = self::toUtf8($value);
        else
            return mb_convert_encoding($data, 'utf-8');
        return $data;
    }

    public static function utf8Encode($data)
    {
        if (\is_array($data))
            foreach ($data as $key => &$value)
                $value = self::utf8Encode($value);
        else
            return utf8_encode($data);
        return $data;
    }

    public static function toCamelCase($str, $controller = false)
    {
        if ($controller)
            $str = str_replace(['_sku', '_skus', '_rasn', '_asn', '_asns'], [' SKU', ' SKUs', ' RASN', ' ASN', ' ASNs', ], $str);
        $str = str_replace("_", " ", $str);
        $str = trim($str);
        $str = ucwords($str);
        $str = str_replace(" ", "", $str);
        return empty($str) ? "" : strtolower($str[0]) . substr($str, 1);
    }

    public static function camelCaseToSnakeCase($str): string
    {
        $input = str_replace(['SKU', 'SKUs', 'RASN', 'ASN', 'ASNs', ], ['_sku','_skus', '_rasn', '_asn', '_asns', ], $str);
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    public static function getMimeType($str)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_buffer($finfo, $str);
        finfo_close($finfo);
        return $mime_type;
    }

    public static function removeNonASCII($str)
    {
        return preg_replace("/[^\x20-\x7E]/", "", $str);
    }

    public static function hasVal($haystack, $needles) {
        foreach($needles as $needle) {
            if (stripos($haystack, $needle) !== false){
                return true;
            }
        }
        return false;
    }

    public static function removeEmoji($string)
    {
        // Match Enclosed Alphanumeric Supplement
        $regex_alphanumeric = '/[\x{1F100}-\x{1F1FF}]/u';
        $clear_string = preg_replace($regex_alphanumeric, '', $string);

        // Match Miscellaneous Symbols and Pictographs
        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clear_string = preg_replace($regex_symbols, '', $clear_string);

        // Match Emoticons
        $regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clear_string = preg_replace($regex_emoticons, '', $clear_string);

        // Match Transport And Map Symbols
        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clear_string = preg_replace($regex_transport, '', $clear_string);

        // Match Supplemental Symbols and Pictographs
        $regex_supplemental = '/[\x{1F900}-\x{1F9FF}]/u';
        $clear_string = preg_replace($regex_supplemental, '', $clear_string);

        // Match Miscellaneous Symbols
        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
        $clear_string = preg_replace($regex_misc, '', $clear_string);

        // Match Dingbats
        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
        $clear_string = preg_replace($regex_dingbats, '', $clear_string);

        return $clear_string;
    }
}
