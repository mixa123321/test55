<?php

namespace app\core\helpers\modal;

class ConfirmModal extends Modal
{
    /** @throws \Exception */
    public function __construct($id, $title = null, $type = self::SIZE_NORMAL, $secondary = false)
    {
        parent::__construct($id, $title, $type, $secondary);
        $this->id .= 'Confirm';
        $this->addButton('No', self::TYPE_BUTTON, self::BUTTON_CANCEL | self::BUTTON_CONFIRM, $options = ['aria-label' => 'Close']);
        $this->addButton('Yes', self::TYPE_SUBMIT, self::BUTTON_DONE | self::BUTTON_CONFIRM, $options = []);
        return $this;
    }

    public function changeYes($type = self::TYPE_SUBMIT, $class = self::BUTTON_DONE | self::BUTTON_CONFIRM, $options = [], $text = 'Yes')
    {
        unset($this->buttons[1]);
        $this->addButton($text, $type, $class, $options);
        return $this;
    }

    public function changeNo($type = self::TYPE_SUBMIT, $class = self::BUTTON_DONE | self::BUTTON_CONFIRM, $options = [], $text = 'No') {
        unset($this->buttons[0]);
        $this->addButton($text, $type, $class, $options);
        return $this;
    }

    public static function areYouSure($id, $title = null, $text = 'Are you sure?', $size = self::SIZE_NORMAL, $secondary = false)
    {
        $modal = new self($id, $title, $size, $secondary);
        $modal->addContent($text)
            ->changeYes(ConfirmModal::TYPE_BUTTON, ConfirmModal::BUTTON_DONE | ConfirmModal::BUTTON_CONFIRM, ['class' => 'js-btn-conf'])
            ->addContent(<<<HTML
<script>
$('{$modal->getSelector()}').on("click", ".js-btn-conf", function() {
    var e = $.Event("confirm");
    $('{$modal->getSelector()}').trigger(e);
    if (e.isDefaultPrevented()) return;
    $('{$modal->getSelector()}').modal("hide");
});
</script>
HTML);
        return $modal;
    }
}