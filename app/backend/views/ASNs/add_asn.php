<?php
/**
 * @var $authUser User
 * @var $id int
 * @var $suppliers array
 * @var $in_date DateTime
 * @var $supplier_id int
 * @var $order_id int
 * @var $unscheduled int
 * @var $is_return boolean
 */

use app\core\entities\User;
use app\core\helpers\View;

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Create <?= $is_return ? 'Return' : ''?> ASN</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-fw fa-sign-in-alt"></i> New <?= $is_return ? 'Return' : ''?> ASN
            </h6>
        </div>
        <form method="post" action="/goods_in/add_asn">
            <input type="hidden" name="ASN[user_id]" value="<?= $id; ?>">
            <input type="hidden" name="ASN[is_return]" value="<?= (int)$is_return; ?>">
            <div class="card-body">
                <div class="row justify-content-center">
                    <div class="col-md-10">
                        <div class="form-row">
                            <div class="col-md-4">
                                <div class="form-group mb-0">
                                    <div class="form-group-element">
                                        <?php if (count($suppliers) > 1) { ?>
                                            <select id="supplier_id" name="ASN[supplier_id]" class="form-control form-control-lg custom-select">
                                                <?php foreach ($suppliers as $key => $supplier) { ?>
                                                    <option value="<?= $key ?>"<?= $key == $supplier_id ? ' selected' : '' ?>><?= View::toHtml($supplier['name']) ?></option>
                                                <?php } ?>
                                            </select>
                                        <?php } else {
                                            $id = array_keys($suppliers)[0];
                                            ?>
                                            <input type="hidden" name="ASN[supplier_id]" value="<?= $suppliers[$id]['id']; ?>">
                                            <p class="form-control-static form-control form-control-lg text-success mb-0"><b><?= View::toHtml($suppliers[$id]['name']) ?></b></p>
                                        <?php } ?>
                                        <label for="supplier_id">Supplier account</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group mb-0">
                                    <div class="form-group-element">
                                        <?php if ((int)$is_return) { ?>
                                            <input type="hidden" name="ASN[arrival_date]" value="<?= date('Y-m-d',$in_date) ?>">
                                            <input type="date" id="arrival_date" class="form-control form-control-lg" value="<?= date('Y-m-d',$in_date) ?>" disabled>
                                            <label for="arrival_date">Arrival date</label>
                                        <?php } else { ?>
                                            <input type="date" name="ASN[arrival_date]" id="arrival_date" class="form-control form-control-lg" value="<?= date('Y-m-d',$in_date) ?>" min="<?= date('Y-m-d',$in_date) ?>">
                                            <label for="arrival_date">Arrival date <span class="text-danger">*</span></label>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($is_return) { ?>
                                <div class="col-md-3">
                                    <div class="form-group mb-0">
                                        <div class="form-group-element">
                                            <input type="text" name="ASN[order_id]" id="order_id" required value="<?= !empty($order_id) ? View::toHtml($order_id) : ''; ?>" class="form-control form-control-lg" placeholder=" ">
                                            <label for="order_id">Order reference/ID <span class="text-danger">*</span></label>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                            <?php if (!$authUser->isRoleSupplierUser()) { ?>
                                <div class="col-md-2">
                                    <div class="form-group mb-0">
                                        <label for="unscheduled"><?= $is_return ? 'Unassigned Return' : 'Unscheduled ASN'; ?>:</label>
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="unscheduled" name="ASN[unscheduled]"<?= (!$is_return && !empty($unscheduled)) ? ' checked' : ''; ?>>
                                        </div>
                                    </div>
                                </div>
                            <?php } else { ?>
                                <input type="hidden" name="ASN[unscheduled]" value="0">
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <div class="row">
                    <div class="col">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary btn-icon-split" type="submit">
                            <span class="text text-white">Next</span>
                            <span class="icon text-white">
                                <i class="fas fa-fw fa-chevron-right"></i>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
