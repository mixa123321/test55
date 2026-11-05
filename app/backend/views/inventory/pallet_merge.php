<?php

use app\core\helpers\scan\Scan;

/**
 * @var string $search
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Pallet Merge</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-object-group"></i>
                            Location merge
                        </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <form method="post" action="/housekeeping/merge_SKUs">
                        <?php (new Scan([
                                'id' => 'search',
                                'name' => 'search',
                                'placeholder' => 'Source location',
                                'value' => empty($search) ? '' : htmlentities($search),
                                'type' => Scan::TYPE__LOCATION,
                        ], ['title' => 'Search']))->render(); ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
