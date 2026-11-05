<?php

namespace app\core\helpers;

use app\core\models\CurlLogger;

class CURL
{
    private $url;

    private static $curl;
    private $headers = [];
    private $options = [];
    private $body;

    private $response;
    private $result_code = 0;
    private $result_headers = '';
    private $result_error = '';
    private $repeat_by_timeout = 0;
    private $repeat_codes = [0];

    private $source;

    public function __construct($url = null, $source = 'undefined')
    {
        $this->url = $url;
        if (!self::$curl)
            self::$curl = curl_init();
        $this->source = $source;
    }

    public function setRepeatByTimeout(int $repeat_by_timeout)
    {
        $this->repeat_by_timeout = abs($repeat_by_timeout);
    }

    public function setRepeatCodes(array $codes)
    {
        $this->repeat_codes = array_map('intval', $codes);
    }

    public function setSource($source = 'undefined')
    {
        $this->source = $source;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function addOption($option_code, $option_value)
    {
        $this->options[$option_code] = $option_value;
    }

    public function resetHeader()
    {
        $this->headers = [];
    }

    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
    }

    public function getHeaders()
    {
        return !empty($this->options[CURLOPT_HTTPHEADER]) ? $this->options[CURLOPT_HTTPHEADER] : [];
    }

    public function setBody($data)
    {
        if (\is_array($data)) {
            $encode = true;
            foreach ($data as $key => $val)
                if ($val instanceof \CURLFile) {
                    $encode = false;
                    break;
                }
            if ($encode)
                $data = http_build_query($data);
        }
        $this->body = $data;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setMethod($method)
    {
        $this->options[CURLOPT_CUSTOMREQUEST] = $method;
    }

    public function setUrl($url)
    {
        $this->options[CURLOPT_URL] = $url;
    }

    public function setBaseAuth($login, $password)
    {
        $this->setHeader('Authorization', 'Basic ' . base64_encode("$login:$password"));
    }

    public function setBearerAuth($token)
    {
        $this->setHeader('Authorization', "Bearer $token");
    }

    public function setOAuth($token)
    {
        $this->setHeader('Authorization', "OAuth $token");
    }

    public function getResultCode()
    {
        return $this->result_code;
    }

    public function getResultHeaders()
    {
        return $this->result_headers;
    }

    public function getResultError()
    {
        return $this->result_error;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function exec($anyway = true)
    {
        $this->setOptions();
        $repeat_by_timeout = $this->repeat_by_timeout;
        $repeat_codes = $this->repeat_codes;
        do {
            $response = curl_exec(self::$curl);
            $this->result_code = (int)curl_getinfo(self::$curl, CURLINFO_HTTP_CODE);
            if (!$repeat_by_timeout || !in_array($this->result_code, $repeat_codes))
                break;
            //Делаем паузу от 1 сек. до 2 сек.
            usleep(random_int(1000000, 2000000));
        } while ($repeat_by_timeout--);
        if (isset($this->options[CURLOPT_HEADER]) && $this->options[CURLOPT_HEADER] === true) {
            $header_size = curl_getinfo(self::$curl, CURLINFO_HEADER_SIZE);
            $this->result_headers = trim(substr($response, 0, $header_size));
            $response = substr($response, $header_size);
        }
        $result = $response;
        $this->response = $response;
        if (strpos($this->result_code, '2') !== 0) {
            $this->result_error = $response;
            $result = null;
        } elseif ($error = curl_errno(self::$curl)) {
            $this->result_error = $error . ' - ' . curl_error(self::$curl);
            $result = null;
        }
        CurlLogger::log($this, $anyway);
        return $result;
    }

    private function setOptions()
    {
        //It does not change the following information kept in the handle:
        //live connections, the Session ID cache, the DNS cache, the cookies and shares.
        curl_reset(self::$curl);
        $this->result_code = 0;
        $this->result_error = '';
        $this->result_headers = '';
        $params = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ];
        if (empty($this->options[CURLOPT_URL]))
            $this->options[CURLOPT_URL] = $this->url;
        if (empty($this->body)) {
            $this->options[CURLOPT_POST] = false;
            unset($this->options[CURLOPT_POSTFIELDS]);
        } else {
            if (empty($this->options[CURLOPT_CUSTOMREQUEST]))
                $this->options[CURLOPT_POST] = true;
            $this->options[CURLOPT_POSTFIELDS] = $this->body;
        }
        $this->options[CURLOPT_HTTPHEADER] = [];
        foreach ($this->headers as $key => $val)
            $this->options[CURLOPT_HTTPHEADER][] = "$key: $val";
        $this->options += $params;
        curl_setopt_array(self::$curl, $this->options);
    }

    public function getResponseHeaders()
    {
        return array_merge(
            array_map(
                static function ($val) {
                    $pair = explode(':', $val, 2);
                    return isset($pair[1]) ? [strtolower(trim($pair[0])) => trim($pair[1])] : [trim($val)];
                },
                explode("\n", $this->result_headers)
            )
        );
    }

    public function getResponseTime()
    {
        return curl_getinfo(self::$curl, CURLINFO_TOTAL_TIME);
    }
}
