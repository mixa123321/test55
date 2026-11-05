<?php

namespace app\core\helpers\billet;

use app\backend\models\SKUs;

class PickingBillet extends StandardBillet
{
    private array $batches;
    private bool $is_addition;

    public function __construct(array $SKU, bool $src = true, bool $short = false)
    {
        $SKU['SKU_id'] = $SKU['id'];
        $SKU['quantity'] = $SKU['quantity'] ?? $SKU['summa'] ?? 0;
        $this->batches = $SKU['batches'] ?? [];
        unset($SKU['id'], $SKU['batches']);
        parent::__construct(
            $src ? "{$SKU['warehouse']}-{$SKU['type_id']}-{$SKU['zone']}-{$SKU['column']}-{$SKU['row']}" : '',
            '',
            new BilletData($SKU),
            SKUs::selectView($SKU, 'purple background-purple', 'nextday', 'info'),
            $short);
        $this->is_addition = !empty($SKU['despatch_date']) && strtotime($SKU['despatch_date']) < strtotime(date('Y-m-d', time()));
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
}