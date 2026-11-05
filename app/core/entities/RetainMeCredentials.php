<?php

namespace app\core\entities;



class RetainMeCredentials extends Entity
{
    const VAR_ORDERS_LOGIN = 'orders_login';
    const VAR_ORDERS_PWD = 'orders_pwd';
    const VAR_PRINT_LOGIN = 'print_login';
    const VAR_PRINT_PWD = 'print_pwd';

    private $orders_login;
    private $orders_pwd;
    private $print_login;
    private $print_pwd;

    /**
     * @return mixed
     */
    public function getOrdersLogin()
    {
        return $this->orders_login;
    }

    /**
     * @param mixed $orders_login
     */
    public function setOrdersLogin($orders_login)
    {
        $this->orders_login = $orders_login;
    }

    /**
     * @return mixed
     */
    public function getOrdersPwd()
    {
        return $this->orders_pwd;
    }

    /**
     * @param mixed $orders_pwd
     */
    public function setOrdersPwd($orders_pwd)
    {
        $this->orders_pwd = $orders_pwd;
    }

    /**
     * @return mixed
     */
    public function getPrintLogin()
    {
        return $this->print_login;
    }

    /**
     * @param mixed $print_login
     */
    public function setPrintLogin($print_login)
    {
        $this->print_login = $print_login;
    }

    /**
     * @return mixed
     */
    public function getPrintPwd()
    {
        return $this->print_pwd;
    }

    /**
     * @param mixed $print_pwd
     */
    public function setPrintPwd($print_pwd)
    {
        $this->print_pwd = $print_pwd;
    }

    public function toArray(): array
    {
        return [
            self::VAR_ORDERS_LOGIN => $this->getOrdersLogin(),
            self::VAR_ORDERS_PWD => $this->getOrdersPwd(),
            self::VAR_PRINT_LOGIN => $this->getPrintLogin(),
            self::VAR_PRINT_PWD => $this->getPrintPwd(),
        ];
    }

    public function toJson()
    {
        return json_encode($this->toArray());
    }
}