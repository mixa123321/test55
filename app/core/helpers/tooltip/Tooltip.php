<?php

namespace app\core\helpers\tooltip;

class Tooltip
{
    private $id;
    private $text;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getText()
    {
        return $this->text;
    }

    public function setText($txt)
    {
        $this->text = $txt;
    }

    public function __construct($text, $id = 0)
    {
        $this->setId($id);
        $this->setText(mb_strtoupper($text));
    }

    public function get()
    {
        return "
            <script>
                $('#{$this->getId()}').mouseover(function () {
                    $(this).tooltip({
                        \"html\": true,
                        \"title\": \"{$this->getText()}\",
                        \"animation\": false
                    });
                });
                $('#{$this->getId()}').click(function (e) {
                    $(this).tooltip('hide');
                });
            </script>";
    }
}