<?php

use app\core\helpers\scan\Scan;

/**
 * @var string $search
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Reconciliation</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        Location counting
                    </span>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">
                <div class="col-md-10">
                    <form method="post" action="/housekeeping/SKUs">
                        <div class="form-group row last-div">
                            <div class="col">
                                <?php (new Scan([
                                    'id' => 'search',
                                    'name' => 'search',
                                    'placeholder' => 'Location',
                                    'value' => empty($search) ? '' : htmlentities($search),
                                    'type' => Scan::TYPE__LOCATION,
                                ], ['title' => 'Search']))->render(); ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
