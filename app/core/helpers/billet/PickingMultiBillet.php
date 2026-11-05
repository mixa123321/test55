<?php

namespace app\core\helpers\billet;

use app\backend\models\SKUs;
use app\core\entities\SKU;
use app\core\helpers\Html;
use app\core\helpers\scan\Scan;
use app\core\helpers\View;

class PickingMultiBillet extends StandardBillet
{
    private array $batches;
    private bool $is_addition;
    private ?BilletData $data;
    private $batch_id;
    private bool $is_multi_dropping;

    public function __construct(array $SKU, bool $src = true, bool $short = false, $is_multi_dropping = false)
    {
        $SKU['SKU_id'] = $SKU['id'];
        $SKU['quantity'] = $SKU['quantity'] ?? $SKU['summa'] ?? 0;
        $this->batches = $SKU['batches'] ?? [];
        $this->batch_id = $SKU['batch_id'] ?? null;
        $this->is_multi_dropping = $is_multi_dropping;
        unset($SKU['id'], $SKU['batches']);
        parent::__construct(
            $src ? "{$SKU['warehouse']}-{$SKU['type_id']}-{$SKU['zone']}-{$SKU['column']}-{$SKU['row']}" : '',
            '',
            new BilletData($SKU),
            SKUs::selectView($SKU, 'purple background-purple', 'nextday', 'info'),
            $short);
        $this->is_addition = !empty($SKU['despatch_date']) && strtotime($SKU['despatch_date']) < strtotime(date('Y-m-d', time()));

        $this->data = new BilletData($SKU);


        return $this;
    }

    public function setBatches(&$content)
    {
        if (!empty($this->batches))
            foreach($this->batches as $batch)
                $content[] = "$batch<hr class=\"mt-0 mb-1\"/>";
    }

    public function getAdditionStyle(): string
    {
        if ($this->is_addition)
            return "background-color: #feebef !important;";
        return '';
    }

    public function addData()
    {
        if (empty($this->data))
            return;
        $content = [];
        $checkbox = '';
        if ($this->is_multi_dropping) {
            $checkbox = '<input type="checkbox" class="checkSKU" style="float:left; margin-top:10px;" value="'. $this->data->getSKUId() .'" name="checkSKU['. $this->data->getSKUId() .'][sku]" />
                    <input type="hidden" name="checkSKU['. $this->data->getSKUId() .'][quantity]" value="'. $this->data->getQuantity() .'" />';
        }

        $content[] = $checkbox. '
                    <b>' . View::toHtml($this->data->getName()) . '</b> (#' . View::toHtml($this->data->getSKUId()) . ')';
        $content[] = '<span class="text-success text-lg">
                          <b class="scan-barcode">' . View::toHtmlConvertWithPrimaryBarcode($this->data->getBarcode()) . '</b>
                      </span>';

        if ($this->batch_id) {
            $content[] = '<input type="hidden" name="checkSKU['. $this->data->getSKUId() .'][batch_id]" value="'. $this->batch_id .'" />';
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

}