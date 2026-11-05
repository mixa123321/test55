<?php

namespace app\core\helpers\billet;

use app\backend\models\Movements;
use app\core\entities\Movement;
use app\core\helpers\Html;

class MovementBillet extends StandardBillet
{
    private bool $is_cart;
    private string $type;
    private array $resume = [];

    public function __construct(array $movement, int $cart_id, bool $src = true, bool $dest = true, bool $short = false)
    {
        parent::__construct(
            $src ? $movement['lf_name'] : '',
            $dest ? $movement['lt_name'] : '',
            new BilletData($movement),
            '',
            $short);
        $this->is_cart = $movement['loc_from_id'] == $cart_id;
        $this->type = $movement['type'];
        if (Movement::isInProgress($movement['status']))
            $this->resume = [
                'id' => $movement['id'],
                'SKU_id' => $movement['SKU_id'],
                'loc_from_id' => $movement['loc_from_id'],
                'loc_to_id' => $movement['loc_to_id'],
            ];
        return $this;
    }

    public function addSource($content = '')
    {
        $content .= sprintf('<span class="badge badge-%s">%s</span><br/>',
            $this->is_cart ? 'primary' : 'secondary',
            $this->is_cart ? 'My trolley' : 'Origin');
        parent::addSource($content);
    }

    public function addDestination($content = '')
    {
        $content .= '<span class="badge badge-secondary">Destination</span><br/>';
        parent::addDestination($content);
    }

    public function addHeader()
    {
        $this->addContent(Html::div(
            Html::tag('span', $this->type ? Movement::getTypeName($this->type) : 'Processing', ['class' => 'badge badge-dark px-4']),
            ['class' => 'text-right mb-1']));
    }

    public function addFooter()
    {
        if (empty($this->resume))
            return;
        $content = [];
        $content[] = '<b>Movement in progress</b>';
        $content[] = Html::tag('button', 'Continue', [
            'class' => 'btn btn-success mb-1 js-btn-fixed',
            'id' => "fix{$this->resume['id']}",
            'data-SKU_id' => $this->resume['SKU_id'],
            'data-loc_id' => $this->resume['loc_from_id'],
            'data-loc_to_id' => $this->resume['loc_to_id'],
        ]);
        $this->addContent(implode('<br/>', $content));
    }

    public function collect()
    {
        $this->addHeader();
        parent::collect();
        $this->addFooter();
    }
}