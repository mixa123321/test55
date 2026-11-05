<?php

namespace app\core\entities;

class CurlLogRecord extends Entity
{
    private $id;
    private $url;
    private $http_code;
    private $response_time;
    private $request;
    private $response;
    private $created_at;
    private $request_id;
    private $get_params;
    private $source;
    private $response_headers;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = (int)$id;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getFullUrl()
    {
        $url = $this->url;
        $get_params = $this->getGetParams();

        if (!is_null($get_params))
            $url .= '?' . $get_params;

        return $url;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function getHttpCode()
    {
        return $this->http_code;
    }

    public function setHttpCode($http_code)
    {
        $this->http_code = (int)$http_code;
    }

    public function getResponseTime()
    {
        return $this->response_time;
    }

    public function setResponseTime($response_time)
    {
        $this->response_time = $response_time;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function setRequest($request)
    {
        $this->request = $request;
    }

    public function getResponse()
    {
        return $this->response;
    }


    public function setResponse($response)
    {
        $this->response = $response;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function setCreatedAt($created_at)
    {
        $this->created_at = date("Y-m-d H:i", strtotime($created_at));
    }

    public function getRequestId()
    {
        return $this->request_id;
    }

    public function setRequestId($request_id)
    {
        $this->request_id = $request_id;
    }

    public function getGetParams()
    {
        return $this->get_params;
    }

    public function setGetParams($get_params)
    {
        $this->get_params = $get_params;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function setSource($source)
    {
        $this->source = $source;
    }

    public function getResponseHeaders()
    {
        return $this->response_headers;
    }

    public function setResponseHeaders($response_headers)
    {
        $this->response_headers = $response_headers;
    }


    public function toArray()
    {
        return [
            'id' => $this->getId(),
            'request_id' => $this->getRequestId(),
            'url' => $this->getUrl(),
            'http_code' => $this->getHttpCode(),
            'request' => $this->getRequest(),
            'response' => $this->getResponse(),
            'response_headers' => $this->getResponseHeaders(),
            'created_at' => $this->getCreatedAt(),
            'get_params' => $this->getGetParams(),
        ];
    }
}