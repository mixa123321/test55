<?php

namespace app\core\helpers\modal;

class InfoModal extends Modal
{
    /** @throws \Exception */
    public function __construct($id, $title = null, $type = self::SIZE_NORMAL, $secondary = false)
    {
        parent::__construct($id, $title, $type, $secondary);
        $this->id .= 'Info';
        $this->addButton('Ok', self::TYPE_BUTTON, self::BUTTON_DONE | self::BUTTON_CONFIRM, $options = ['aria-label' => 'Close']);
        return $this;
    }
}