<?php

namespace app\backend\models;

use app\core\entities\FlatIntegrationHistory;

class FlatIntegrationHistories extends \app\core\models\FlatIntegrationHistories
{
    public static function showMode($mode)
    {
        if (!in_array($mode, FlatIntegrationHistory::ALL_MODES))
            return '';
        return "<span class='badge badge-" . FlatIntegrationHistory::MODE_COLOR[$mode] . "'>" . str_replace("_", " ", $mode) . "</span>";
    }
}