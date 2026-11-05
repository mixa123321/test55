<?php

namespace app\backend\models;

use app\core\entities\Location;
use app\core\helpers\Html;

class PickingHistory
{
    public static function showPickingHistory(array $picking_history)
    {
        $attr = [];
        $msg = '';
        foreach (Location::STORAGE_TYPES as $zone)
            $msg .= Html::div(
                Html::div("$zone locations",['class' => 'row justify-content-center h5'] + $attr ) .
                (empty($picking_history[$zone]) ? Html::warning('No records found!') : self::showZones($picking_history[$zone])), ['class' => 'col-6'] + $attr
            );
        return Html::div($msg, ['class' => 'row'] +$attr );
    }

    public static function showZones(array $zones)
    {
        $table = '';
        foreach ($zones as $zone => $time)
            $table .= "<tr>
                <td>$zone</td>
                <td>" . $time . "</td>
            </tr>";
        $location_info = '
            <div class="card-body py-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Zone</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . $table . ' 
                    </tbody>
                </table>
            </div>';
        return Html::div($location_info, ['class' => 'row']);
    }
}
