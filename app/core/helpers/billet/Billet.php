<?php

namespace app\core\helpers\billet;

use app\core\helpers\Html;
use app\core\interfaces\HtmlComponent;

abstract class Billet implements HtmlComponent
{
    private int $radius = 10;
    private array $content = [];
    private ?string $color;

    public function getColor(): string
    {
        return $this->color;
    }

    abstract public function getAdditionStyle(): string;

    public function setColor(string $color): Billet
    {
        $this->color = $color;
        return $this;
    }

    private function getDivider(): string
    {
        return '<hr class="m-0 bg-' . $this->getColor() . '"/>';
    }

    protected function addContent(string $content)
    {
        $this->content[] = $content;
    }

    abstract public function collect();

    public function put(): string
    {
        $this->collect();
        return Html::div(implode($this->getDivider(), $this->content), [
            'class' => 'col-12 col-md-2 m-1 text-center border border-' . $this->getColor(),
            'style' => "border-radius: {$this->radius}px; " . $this->getAdditionStyle(),
        ]);
    }
}