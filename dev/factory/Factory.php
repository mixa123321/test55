<?php

namespace dev\factory;

abstract class Factory {


    const DIR = __DIR__;
    const TEMPLATE = __DIR__;

    public $name = '';
    public $template;

    public function __construct()
    {
        $this->setTemplate();
    }

    public function setName($name)
    {
        $this->name = mb_ereg_replace("([~,;.?!\/ ])", '', $name);
    }

    abstract public function make();

    public function setTemplate()
    {
        $template = file_get_contents(static::TEMPLATE);
        if (empty($template))
            die("View template is missing");
        $this->template = $template;
    }

}