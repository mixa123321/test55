<?php

use app\core\entities\ChannelSupplier;
use app\core\helpers\Html;
use app\core\helpers\View;

/**
 * @var string $title
 * @var array $mappings
 * @var array $shipping_services
 * @var ChannelSupplier $channel_supplier
 * @var int $shop_id
 * @var int $selected_warehouse_id
 * @var array $terms
 */

?>
<div class="container-fluid p-sm-4 p-2">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Channel shipping mapping</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary float-left">
                    <span>
                        <i class="fas fa-fw fa-sign-in-alt"></i>
                        Shipping mapping
                    </span>
                    </h6>
                </div>
                <div class="col-auto">
                    <h6 class="m-0 font-weight-bold text-primary float-left"><span><?= $title; ?></span></h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($mappings)) { ?>
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>Channel service code</th>
                        <th>System service</th>
                        <th>Terms</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mappings as $mapping) { ?>
                        <form method="POST" action="/channels/confirm_shipping_mapping">
                            <input type="hidden" name="id" value="<?= (int) $mapping['id']?>">
                            <input type="hidden" name="shop_id" value="<?= $shop_id; ?>">
                            <input type="hidden" name="warehouse_id" value="<?= $selected_warehouse_id; ?>">
                            <tr>
                                <td>
                                    <?= View::toHtml($mapping['channel_shipping_code']) ?>
                                </td>
                                <td>
                                    <div class="form-group">
                                        <div class="form-group-element">
                                            <?php
                                            $options = [
                                                'class' => 'form-control form-control-sm custom-select',
                                                'id' => 'shipping_services_id_' . $mapping['id'],
                                            ];
                                            echo Html::dropDownList(
                                                    'shipping_services_id',
                                                    $shipping_services,
                                                    $mapping['shipping_services_id'],
                                                    $options
                                                );
                                            ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="form-group">
                                        <div class="form-group-element">
                                        <?php
                                        $options = [
                                            'class' => 'form-control form-control-sm custom-select',
                                            'id' => 'Terms_' . $mapping['id'],
                                        ];
                                        echo Html::dropDownList(
                                            'terms',
                                            $terms,
                                            $mapping['DDP'],
                                            $options
                                        );
                                        ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-primary" type="submit">Confirm</button>
                                </td>
                            </tr>
                        </form>
                    <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <h4>No services found to map</h4>
            <?php } ?>
        </div>
        <div class="card-footer">
            <div class="row">
                <div class="col">
                    <a class="btn btn-secondary btn-icon-split" href="/channels/channel_details/?id=<?= $channel_supplier->getChannelId(); ?>&supplier_id=<?= $channel_supplier->getSupplierId(); ?>">
                        <span class="icon text-white-50">
                            <i class="fas fa-fw fa-chevron-left"></i>
                        </span>
                        <span class="text">Back</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
