<?php

namespace app\core\helpers;

class HTTP
{
    const API = 'api';
    const ADMIN = 'admin';
    const WWW = 'www';

    const APP = [
        self::API => self::API,
        self::ADMIN => 'backend',
        self::WWW => 'frontend',
    ];

    const COMMON_URLS = [
        '/main/print_label',
    ];

    public static function getCodes()
    {
        return [
            200 => 200,
            301 => 301, 302 => 302, 307 => 307,
            400 => 400, 401 => 401, 403 => 403, 404 => 404,
            500 => 500,
        ];
    }

    /**
     * Prevents open redirects: allows a local path ("/..." but not "//..." or "/\...")
     * or an absolute http(s) URL on BASE_DOMAIN or its subdomains; anything else becomes "/".
     */
    public static function safeRedirectUrl($url): string
    {
        $url = trim((string)$url);
        if (preg_match('~[\x00-\x1F\x7F]~', $url))
            return '/';
        if (preg_match('~^/(?![/\\\\])~', $url))
            return $url;
        // browsers treat "\" as "/" while parse_url() does not, e.g. https://evil.com\@our.host/
        if (strpos($url, '\\') !== false || !defined('BASE_DOMAIN'))
            return '/';
        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true))
            return '/';
        $host = strtolower($parts['host']);
        $base = strtolower(BASE_DOMAIN);
        if ($host === $base || substr($host, -strlen(".$base")) === ".$base")
            return $url;
        return '/';
    }

    /**
     * For untrusted URLs used as a link or JS navigation target (e.g. "back"): like safeRedirectUrl(),
     * but also keeps scheme-less relative URLs ("?a=1", "page?a=1"), so no javascript:/data: URL gets through.
     * The result is not HTML-escaped.
     */
    public static function safeLinkUrl($url): string
    {
        $url = trim((string)$url);
        if (preg_match('~[\x00-\x1F\x7F]~', $url) || preg_match('~^[/\\\\]{2}|^/\\\\~', $url))
            return '/';
        if (preg_match('~^[a-z][a-z0-9+.\-]*:~i', $url))
            return self::safeRedirectUrl($url);
        return $url;
    }

    public static function redirect($location, $code = 302, $is_exit = true)
    {
        $location = self::safeRedirectUrl($location);
        header("Location: $location", 1, $code);
        if ($is_exit)
            exit;
    }

    public static function setResponseCode($http_code)
    {
        http_response_code($http_code);
    }

    public static function clearParams($url, $param_name)
    {
        return self::setParams($url, $param_name, '');
    }

    public static function setParams($url, $param_name, $param_value)
    {
        if (strpos($url, '?') === false)
            $url .= '?';
        list($path, $params) = explode('?', $url);
        $params_arr = explode('&', $params);
        foreach ($params_arr as $key => $param)
            if (strpos($param, "$param_name=") !== false)
                $params_arr[$key] = "$param_name=$param_value";
        return $path . '?' . implode('&', $params_arr);
    }

    public static function securitySessionStart()
    {
        ini_set('session.gc_maxlifetime', 3600*6);
        session_start();
//        $session_security_key = md5(Request::getIp() . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));
        $session_security_key = md5(BASE_DOMAIN . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''));
        if (empty($_SESSION['session_security_key'])) {
            session_unset();
            $_SESSION['session_security_key'] = $session_security_key;
            return;
        }
        if ($_SESSION['session_security_key'] === $session_security_key)
            return;
        session_regenerate_id(true);
//        session_unset();
        $_SESSION['session_security_key'] = $session_security_key;
    }

    public static function getHost()
    {
        if (array_key_exists('HTTP_X_ORIGINAL_HOST', $_SERVER))
            return $_SERVER['HTTP_X_ORIGINAL_HOST'];
        return isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : BASE_DOMAIN;
    }

    public static function getRequestUri()
    {
        return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "/";
    }

    public static function getCurrentUrl($host = '')
    {
        return BASE_PROTOCOL . "://" . ($host ?: self::getHost()) . self::getRequestUri();
    }

    public static function separateHost()
    {
        $host = strtolower(self::getHost());
        $suffix = '';
        $domain_parts = explode('.', $host, 2);
        if (in_array($domain_parts[0], array_keys(self::APP))) {
            $suffix = $domain_parts[0];
            $host = $domain_parts[1];
        }
        return [$suffix, $host];
    }

    public static function getAPIUrl()
    {
        return BASE_PROTOCOL . "://api." . BASE_DOMAIN;
    }

    /**
     * ZPRN is a protocol used to download/print ZPL labels
     * @return string
     */
    public static function getZprnUrl($action)
    {
        return "zprn://url%7Cadmin." . BASE_DOMAIN . "%7C{$action}";
    }

    public static function getDomain()
    {
        return BASE_PROTOCOL . '://admin.' . BASE_DOMAIN;
    }

    public static function httpQueryToArray($query)
    {
        if (strpos($query, '?') === false)
            $query = '?' . $query;
        list(, $params) = explode('?', $query);
        $params_arr = explode('&', $params);
        $res = [];
        foreach ($params_arr as $param) {
            list($name, $value) = explode('=', $param);
            $res[$name] = $value;
        }
        return $res;
    }
}
