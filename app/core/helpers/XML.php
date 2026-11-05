<?php

namespace app\core\helpers;

class XML
{
    public static function clean($str)
    {
        return View::toHtml(preg_replace('/[^\x09\x0A\x0D\x20-\xFF]+/', '', $str));
    }

    public static function arr2xml($data, $is_clean = true, $last_node = '')
    {
        if (!is_array($data)) {
            if ($is_clean)
                return htmlspecialchars(preg_replace('/[^\x09\x0A\x0D\x20-\xFF]+/', '', $data), ENT_QUOTES);
            return $data;
        }
        $result = '';
        foreach ($data as $key => $val)
            if (is_numeric($key) && !empty($last_node)) {
                $result .= '<' . $last_node . '>' . self::arr2xml($val, $is_clean, '') . '</' . $last_node . ">\n";
            } else {
                if (is_array($val) && ($t = array_keys($val)) && is_numeric($t[0]))
                    $result .= self::arr2xml($val, $is_clean, $key);
                else
                    $result .= '<' . $key . '>' . self::arr2xml($val, $is_clean, $key) . '</' . $key . ">\n";
            }
        return $result;
    }

    public static function xml2arr($xml)
    {
        $enc = 'UTF-8';
        $tags = [];
        $error = '';
        if ($p = @xml_parser_create($enc)) {
            @xml_parser_set_option($p, XML_OPTION_SKIP_WHITE, 0);
            @xml_parse_into_struct($p, $xml, $tags);
            if ($tmp = @xml_get_error_code($p))
                $error = xml_error_string($tmp);
            elseif (!$tags)
                $error = 'Empty result';
            @xml_parser_free($p);
        } else
            $error = "Can not create XML-parser with '$enc' encoding";
        if ($error)
            return $error;
        $res = $Pointer = [];
        $Pointer[0] = &$res;
        foreach ($tags as $tag) {
            $type = $tag['type'];
            $isOpen = ($type === 'open');
            $isClose = ($type === 'close');
            $isComp = ($type === 'complete');
            $tagName = $tag['tag'];
            $tagName = strtolower($tagName);
            $l = (int)$tag['level'];
            $attrs = isset($tag['attributes']) ? @$tag['attributes'] : [];
            $val = isset($tag['value']) ? @trim($tag['value']) : '';
            $vl = \strlen($val);
            if ($isOpen || $isComp) {
                $elem = $val;
                if ($isOpen || $attrs) {
                    $elem = [];
                    if ($attrs) {
                        $tmp = [];
                        foreach ($attrs as $k => $v) {
                            $k = strtolower($k);
                            $tmp[$k] = $v;
                        }
                        $elem['@ATTRS'] = $tmp;
                    }
                    if ($vl)
                        $elem['@VALUE'] = $val;
                }
                $par = &$Pointer[$l - 1];
                $tmp = &$par[$tagName];
                if (isset($tmp)) {
                    if (!\is_array($tmp) || !isset($tmp[0]))
                        $tmp = [$tmp];
                    $tmp[] = &$elem;
                } else
                    $par[$tagName] = &$elem;
                unset($tmp);
                $Pointer[$l] = &$elem;
            } elseif ($vl) {
                $elem = &$Pointer[$l];
                if (\is_array($elem))
                    $elem['@VALUE'] .= (isset($elem['@VALUE']) ? ' ' : '') . $val;
                else
                    $elem .= ' ' . $val;
            }
            if (($isClose || $isComp) && \is_array($elem = &$Pointer[$l]) && isset($elem['@ATTRS']))
                foreach ((array)@$elem['@ATTRS'] as $k => $v)
                    if (!isset($elem[$k]))
                        $elem[$k] = $v;
            unset($elem);
        }
        return $res;
    }

    public static function insertPropertyToXMLString($xml, $tag, $property)
    {
        return str_replace("<{$tag}>", "<{$tag} {$property}>", $xml);
    }
}
