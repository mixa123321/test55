<?php

namespace app\core\exceptions;

use app\core\helpers\HTTP;

class RedirectException extends \Exception
{
    private $http_code;
    /**
     * @param $code int
     * <ul>
     *  <li>301 - Moved Permanently</li>
     *  <li>302 - Found</li>
     * </ul>
     */
    public function __construct($url, $code = 302, $replace = true)
    {
        $this->http_code = $code;
        $url = HTTP::safeRedirectUrl($url);
        header("Location: $url", $replace, $code);
        parent::__construct("", $code);
    }

    public function getHttpCode()
    {
        return $this->http_code;
    }
}
