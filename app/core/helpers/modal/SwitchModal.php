<?php

namespace app\core\helpers\modal;

class SwitchModal extends Modal
{
    /** @throws \Exception */
    public function __construct($id, $title = null, $type = self::SIZE_NORMAL, $secondary = false)
    {
        parent::__construct($id, $title, $type, $secondary);
        $this->id .= 'Switch';
        $this->addButton('Cancel', self::TYPE_BUTTON, self::BUTTON_CANCEL | self::BUTTON_CONFIRM, $options = ['aria-label' => 'Close']);
        return $this;
    }

    public function addButton($text, $type = self::TYPE_BUTTON, $class = self::BUTTON_CANCEL, $options = [])
    {
        parent::addButton($text, $type, $class, $options);
        return $this;
    }
}