<?php

namespace app\core\helpers\notifiers;

use app\core\models\Notifiers;

abstract class Notifier extends Notifiers
{
    protected $group;
    protected $message;

    public function __construct($group, $message)
    {
        $this->group = $group;
        $this->message = $message;
    }

    public function setMessage($message)
    {
        $this->message = $message;
    }

    abstract public function send();
}