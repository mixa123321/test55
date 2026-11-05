<?php

namespace app\core\entities;



class JobData extends Entity
{
    const VAR_CLASSNAME = 'class';
    const VAR_METHOD = 'method';
    const VAR_PARAMS = 'params';

    private $class;
    private $method;
    private $params;

    /**
     * @return mixed
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param mixed $class
     */
    public function setClass($class)
    {
        // Jobs queued while toArray() still applied addslashes were stored with doubled backslashes
        $this->class = is_string($class) ? str_replace('\\\\', '\\', $class) : $class;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param mixed $method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return mixed
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param mixed $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }

    public function toArray()
    {
        return [
            self::VAR_CLASSNAME => $this->getClass(),
            self::VAR_METHOD => $this->getMethod(),
            self::VAR_PARAMS => $this->getParams(),
        ];
    }

    public function toJson()
    {
        return json_encode($this->toArray());
    }
}