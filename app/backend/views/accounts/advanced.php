<?php
/**
 * @var $authUser User
 * @var $dictionary array
 * @var $total_count int
 * @var $filters array
 * @var $pagination_path string
 */

use app\backend\models\Routes;
use app\core\entities\User;
use app\core\helpers\Html;
use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Accounts advanced</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                    <span>
                        <i class="fas fa-fw fa-list"></i>
                    </span>
                    Access dictionary
                        <?php if (isset($dictionary)) { ?>
                            (<?= $dictionary['total']; ?> item<?= $dictionary['total'] > 1 ? 's' : '' ?>)
                        <?php } ?>
                    </h6>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/accounts/users">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-user"></i>
                        </span>
                        <span class="text">Users</span>
                    </a>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/accounts/roles">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-address-card"></i>
                        </span>
                        <span class="text">Roles</span>
                    </a>
                </div>
                <div class="col-auto">
                    <a class="btn btn-primary btn-icon-split" href="/accounts/user_groups">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-address-card"></i>
                        </span>
                        <span class="text">User groups</span>
                    </a>
                </div>
            </div>
        </div>
            <div class="card-body">
                <?php if (isset($dictionary)) { ?>
                    <form>
                        <input type="hidden" name="b" value="1">
                        <div class="form-row">
                            <div class="form-group col-3 mb-0">
                                <div class="form-group-element">
                                    <input type="text" name="search" id="search" class="form-control form-control-lg" placeholder=" " value="<?= !empty($filters['search']) ? View::toHtml($filters['search']) : '' ?>">
                                    <label for="search">Search</label>
                                </div>
                            </div>
                            <div class="form-group col-3">
                                <div class="form-group-element">
                                    <?php
                                    $options = [
                                        'class' => 'form-control form-control-lg custom-select',
                                        'id' => 'status',
                                    ];
                                    echo Html::dropDownList('status', ['all' => 'All', 0 => 'Locked', 1 => 'Active'],
                                        (isset($filters['status']) && $filters['status'] !== 'all') ? $filters['status'] : 'all', $options);
                                    ?>
                                    <label for="status">Status</label>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-auto mx-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="editable" name="editable" onclick="submit()" <?= !empty($filters['editable']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="editable">Only editable</label>
                                </div>
                            </div>
                            <div class="form-group col-auto mr-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="virtual" name="virtual" onclick="submit()" <?= !empty($filters['virtual']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="virtual">Only virtual</label>
                                </div>
                            </div>
                        </div>
                        <div class="row justify-content-end mr-3 mb-3">
                            <a href="/accounts/advanced?b=1" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                            <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                        </div>
                    </form>
                    <?php
                    if (!empty($dictionary['records'])) {
                        View::pagination($pagination_path,1, ceil($dictionary['total'] / Routes::LIMIT_PER_PAGE), $dictionary['current_page'], Routes::LIMIT_PER_PAGE);
                     ?>
                            <div class="row mt-3">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                    <colgroup>
                                        <col width="30%">
                                        <col width="45%">
                                        <col width="15%">
                                        <col width="10%">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Alias</th>
                                            <th>Route</th>
                                            <th>State</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dictionary['records'] as $route) { ?>
                                            <tr>
                                                <td class="px-md-2">
                                                    <?= View::toHtml($route['alias']) ?>
                                                </td>
                                                <td class="px-md-2">
                                                    <?= View::toHtml($route['name']) ?>
                                                </td>
                                                <td>
                                                    <b>Status:</b><div class="float-right"><?php if (empty($route['is_active'])) { ?>
                                                            <span class="badge badge-danger">Locked</span>
                                                        <?php } else { ?>
                                                            <span class="badge badge-success">Active</span>
                                                        <?php } ?></div><br>
                                                    <b>Editable:</b><div class="float-right"><?php if (!empty($route['editable'])) { ?>
                                                        <span>
                                                        <i class="fas fa-fw fa-check-circle"></i>
                                                    </span>
                                                    <?php } ?></div><br>
                                                    <b>Virtual:</b><div class="float-right"><?php if (!empty($route['virtual_access'])) { ?>
                                                        <span>
                                                        <i class="fas fa-fw fa-check-circle"></i>
                                                    </span>
                                                    <?php } ?></div>
                                                </td>
                                                <td>
                                                    <a href="/route_accesses/route_detail?route_id=<?= $route['id'] ?>" class="btn btn-primary btn-block mb-1">Details</a>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                                </div>
                            </div>
                    <?php View::pagination($pagination_path,1, ceil($dictionary['total'] / Routes::LIMIT_PER_PAGE), $dictionary['current_page'], Routes::LIMIT_PER_PAGE);
                    } else echo Html::warning('No records found');
                } ?>
            </div>
    </div>
</div>
