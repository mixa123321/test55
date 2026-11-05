<?php

namespace app\core\helpers\actions;

use app\core\helpers\Html;

class Actions
{
    private $id;
    private $buttons = [];
    private $bg_style = 'action';
    private $style_menu;

    const ANCHOR = 'a';
    const BUTTON = 'button';
    const SUBMIT = 'submit';

    const TYPES = [
        self::ANCHOR,
        self::BUTTON,
        self::SUBMIT
    ];

    public function __construct($id, $style_menu = '#2e2e2e')
    {
        $this->id = "{$id}Actions";
        $this->style_menu = $style_menu;
        return $this;
    }

    public function addButton($type, $name, $icon, $url, $addition = [])
    {
        if (!in_array($type, self::TYPES))
            return $this;
        $class = "dropdown-item";
        if ($type === self::ANCHOR) {
            $options = ['class' => $class, 'href' => $url];
            $tag_name = self::ANCHOR;
        } else {
            $options = ['class' => "$class $url", 'type' => $type];
            $tag_name = self::BUTTON;
        }
        $this->buttons[] = Html::tag($tag_name, "<i class=\"fas fa-$icon fa-sm fa-fw mr-2\"></i> $name", $options + $addition);
        return $this;
    }

    public function addDivider()
    {
        $this->buttons[] = Html::div('', ['class' => "dropdown-divider bg-{$this->bg_style}"]);
        return $this;
    }

    public function get()
    {
        if ($this->empty())
            return '';
        $top_button = Html::tag('a', '<i class="fas fa-ellipsis-h p-0"></i>', [
            'class' => "nav-link d-flex p-2 px-3 border",
            'style' => "border: 1px solid {$this->style_menu} !important; color: {$this->style_menu} !important;",
            'href' => '#',
            'id' => $this->id,
            'data-toggle' => 'dropdown'
        ]);
        $menu = Html::div(implode($this->buttons), ['class' => "dropdown-menu dropdown-menu-right shadow bg-{$this->bg_style}", 'aria-labelledby' => $this->id]);
        return Html::div($top_button . $menu, ['class' => 'col-auto align-self-center']);
    }

    public function empty()
    {
        return !count($this->buttons);
    }

    public function count()
    {
        return count($this->buttons);
    }
}