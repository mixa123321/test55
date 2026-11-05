<?php

namespace app\core\helpers\billet;

use app\core\entities\SKU;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

class StandardBillet extends Billet
{
    private ?string $location_from;
    private ?string $location_to;
    private ?BilletData $data;
    private bool $short;

    public function __construct(?string $location_from, ?string $location_to, ?BilletData $data, ?string $color, bool $short = false)
    {
        $this->location_from = $location_from;
        $this->location_to = $location_to;
        $this->data = $data;
        if (!empty($color))
            $this->setColor($color);
        $this->short = $short;
        return $this;
    }

    public function addSource(string $content = '')
    {
        if (empty($this->location_from))
            return;
        $this->addContent($content . Html::tag(
            'span',
            '<b>' . View::toHtml($this->location_from) . '</b>',
            ['class' => "text-{$this->getColor()} text-lg"]));
    }

    public function setBatches(&$content)
    {
        if (!empty($this->data->getNumbers()))
            $content[] = 'Batch No. <b>' . View::toHtml($this->data->getNumbers()) . '</b>';
    }

    public function addData()
    {
        if (empty($this->data))
            return;
        $content = [];
        if (!$this->short) {
            $content[] = '<b>' . View::toHtml($this->data->getName()) . '</b> (#' . View::toHtml($this->data->getSKUId()) . ')';
            $content[] = '<span class="text-success text-lg">
                              <b class="scan-barcode">' . View::toHtmlConvertWithPrimaryBarcode($this->data->getBarcode()) . '</b>
                          </span>';
        }
        $this->setBatches($content);
        if (!empty($this->data->getPhoto()))
            if ($link = SKU::showPhotoSKU(addslashes($this->data->getPhoto())))
                $content[] = '<img class="img-thumbnail mt-1" style="max-height: 70px;" src="' . $link . '"/>';
        $content[] = 'Quantity <b>' . View::toHtml($this->data->getQuantity()) . '</b>';
        $this->addContent(Html::div(implode('<br/>', $content),  [
            'data-scan-barcode' => $this->data->getBarcode(),
            'data-scan-type' => Scan::TYPE__SKU
        ]));
    }

    public function addDestination(string $content = '')
    {
        if (empty($this->location_to))
            return;
        $this->addContent($content . Html::tag(
            'span',
            '<b>' . View::toHtml($this->location_to) . '</b>',
            ['class' => "text-{$this->getColor()} text-lg"]));
    }

    public function collect()
    {
        $this->addSource();
        $this->addData();
        $this->addDestination();
    }

    public function getAdditionStyle(): string
    {
        return '';
    }
}