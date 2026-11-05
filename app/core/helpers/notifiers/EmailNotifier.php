<?php

namespace app\core\helpers\notifiers;

use app\core\Environment;

class EmailNotifier extends Notifier
{

    private $header;

    public function __construct($header, $message)
    {
        $this->header = $header;
        parent::__construct(null, $message);
    }

    public function send()
    {
        mail(
            Environment::getDebugMails(),
            $this->header,
            $this->message,
            "From: notifier@" . API_DOMAIN
        );
    }
}