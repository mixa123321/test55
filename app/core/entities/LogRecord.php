<?php

namespace app\core\entities;

class LogRecord extends Entity
{
    private $id = 0;
    private $user_id;
    private $date_added;
    private $memory_usage = 0;

    private $request_id = '';
    private $request_exec_time = 0;
    private $request_uri = '';
    private $request_host = '';
    private $request_method = '';
    private $request_get = [];
    private $request_post;
    private $request_raw;

    private $request_headers = [];
    private $response_body;
    private $response_headers = [];
    private $response_http_code = '';
    private $ip = '';

    public function getMemoryUsage()
    {
        return $this->memory_usage;
    }

    public function setMemoryUsage($memory_usage)
    {
        $this->memory_usage = (string)$memory_usage;
    }


    public function getRequestGet()
    {
        if (!is_array($this->request_get)) {
            $this->request_get = json_decode($this->request_get, true);
        }
        return $this->request_get;
    }

    public function setRequestGet($request_get)
    {
        $this->request_get = $request_get;
    }

    public function getRequestPost()
    {
        if (!is_array($this->request_post)) {
            $this->request_post = json_decode($this->request_post, true);
        }
        return $this->request_post;
    }

    public function setRequestPost($request_post)
    {
        $this->request_post = $request_post;
    }

    public function getRequestRaw()
    {
        return $this->request_raw;
    }

    public function setRequestRaw($request_raw)
    {
        $this->request_raw = (string)$request_raw;
    }

    public function getRequestHost()
    {
        return $this->request_host;
    }

    public function setRequestHost($request_host)
    {
        $this->request_host = (string)$request_host;
    }

    public function getRequestMethod()
    {
        return $this->request_method;
    }

    public function setRequestMethod($request_method)
    {
        $this->request_method = (string)$request_method;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = (int)$user_id;
    }

    public function getDateAdded()
    {
        return $this->date_added;
    }

    public function setDateAdded($date_added)
    {
        $this->date_added = $date_added;
    }

    public function getRequestId()
    {
        if (empty($this->request_id)) {
            $this->request_id = uniqid('', true) . '_' . time();
        }
        return $this->request_id;
    }

    public function setRequestId($request_id)
    {
        $this->request_id = (string)$request_id;
    }

    public function getRequestExecTime()
    {
        return $this->request_exec_time;
    }

    public function setRequestExecTime($request_exec_time)
    {
        $this->request_exec_time = (int)$request_exec_time;
    }

    public function getRequestUri()
    {
        return $this->request_uri;
    }

    public function setRequestUri($request_uri)
    {
        $this->request_uri = (string)$request_uri;
    }

    public function getRequestHeaders()
    {
        if (!is_array($this->request_headers)) {
            $this->request_headers = json_decode($this->request_headers, true);
        }
        return $this->request_headers;
    }

    public function setRequestHeaders($request_headers)
    {
        $this->request_headers = $request_headers;
    }

    public function getResponseBody()
    {
        return $this->response_body;
    }

    public function setResponseBody($response_body)
    {
        $this->response_body = (string)$response_body;
    }

    public function getResponseHeaders()
    {
        return $this->response_headers;
    }

    public function setResponseHeaders($response_headers)
    {
        $this->response_headers = $response_headers;
    }

    public function getResponseHttpCode()
    {
        return $this->response_http_code;
    }

    public function setResponseHttpCode($response_http_code)
    {
        $this->response_http_code = (int)$response_http_code;
    }

    public function getIp()
    {
        return $this->ip;
    }

    public function setIp($ip)
    {
        $this->ip = (string)$ip;
    }


    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'user_id' => $this->getUserId(),
            'date_added' => $this->getDateAdded(),
            'memory_usage' => $this->getMemoryUsage(),
            'request_id' => $this->getRequestId(),
            'request_exec_time' => $this->getRequestExecTime(),
            'request_uri' => $this->getRequestUri(),
            'request_host' => $this->getRequestHost(),
            'request_method' => $this->getRequestMethod(),
            'request_get' => $this->getRequestGet(),
            'request_post' => $this->getRequestPost(),
            'request_raw' => $this->getRequestRaw(),
            'request_headers' => $this->getRequestHeaders(),
            'response_body' => $this->getResponseBody(),
            'response_headers' => $this->getResponseHeaders(),
            'response_http_code' => $this->getResponseHttpCode(),
            'ip' => $this->getIp(),
        ];
    }

    public function getDataToSave()
    {
        $raw_data = $this->toArray();
        $raw_data['request_get'] = json_encode($raw_data['request_get']);
        $raw_data['request_post'] = json_encode($raw_data['request_post']);
        $raw_data['request_headers'] = json_encode($raw_data['request_headers']);
        $raw_data['response_headers'] = json_encode($raw_data['response_headers']);
        return $raw_data;
    }
}