<?php

use app\backend\models\ShippingServices;
use app\backend\models\Sizes;
use app\core\entities\Address;
use app\core\entities\Size;
use app\core\entities\User;
use app\core\entities\Warehouse;
use app\core\helpers\Html;
use app\core\helpers\modal\SwitchModal;
use app\core\helpers\View;
use app\core\models\config\WarehousesConfig;

/**
 * @var $authUser User
 * @var $warehouse Warehouse
 * @var $size Size
 * @var $address Address|null
 * @var $country string
 * @var $currency string
 * @var $timezones array
 * @var $countries array
 * @var $currencies array
 * @var $sizes array
 * @var $types array
 * @var $location_type_zones array
 * @var $max_length_zone int
 * @var $release_date int
 * @var $regexp_zone string
 * @var $warehouse_config array
 */

$shipping_service = ShippingServices::getById($warehouse->getDefaultShippingServiceId());
$CA = $warehouse->getCA();
$size_CA = Sizes::getById($CA['size_id'] ?? 0);
$TP = $warehouse->getTP();
$size_TP = Sizes::getById($TP['size_id'] ?? 0);
$OB = $warehouse->getOB();
$size_OB = Sizes::getById($OB['size_id'] ?? 0);
$address_data = $warehouse->getAddress();
$virtual_info = $warehouse->getPickingHistory();

?>
<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Warehouse settings</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <div class="row">
                <div class="col align-self-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <span>
                            <i class="fas fa-fw fa-warehouse"></i>
                        </span>
                        <?= View::toHtml($warehouse->getName()) ?>
                    </h6>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col align-self-center">
                    <h5 class="font-weight-bolder mb-3">Information</h5>
                </div>
                <div class="col-auto">
                    <button class="btn btn-success btn-icon-split" id="editWarehouseModalButton">
                    <span class="icon text-white-50">
                        <i class="fas fa-fw fa-edit"></i>
                    </span>
                        <span class="text">Edit</span>
                    </button>
                </div>
            </div>
            <div class="row justify-content-center">
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <h5>General information</h5>
                        <hr class="my-2">
                        <label>Name:</label> <b class="text-success"><?= View::toHtml($warehouse->getName()); ?></b><br>
                        <label>Label:</label> <b class="text-success"><?= !empty($warehouse->getLabel()) ? View::toHtml($warehouse->getLabel()) : '-'; ?></b><br>
                        <label>Start date:</label> <b class="text-success"><?= View::toHtml(date('jS M Y', strtotime($warehouse->getStartWarehouse()))) ?></b><br>
                        <label>Timezone:</label> <b class="text-success"><?= View::toHtml($warehouse->getTimezone()) ?></b><br>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-0">
                        <h5>Addition information</h5>
                        <hr class="my-2">
                        <label>Is Virtual:</label> <?= !empty($warehouse->getIsVirtual()) ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br>
                        <label>Country:</label> <b class="text-success"><?= View::toHtml($country) ?></b><br>
                        <label>Currency:</label> <b class="text-success"><?= View::toHtml($currency) ?></b><br>
                        <label>Billing:</label> <b class="text-success"><?= View::toHtml($warehouse->getBilling()) ?></b><br>
                        <label>Is Mezzanine:</label> <?= !empty($warehouse->getMezzanine()) ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br>
                    </div>
                </div>
                <?php if (!$warehouse->isVirtual()) { ?>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <h5>Address</h5>
                            <hr class="my-2">
                            <?php if (!empty($address_data['company'])) { ?>
                                <b class="text-success"><?=  View::toHtml($address_data['company']) ?></b></br>
                            <?php } ?>
                            <b class="text-success"><?= !$address->empty() ? $address->toString() : '-'; ?></b>
                        </div>
                    </div>
                <?php } else {?>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <h5>Connection information</h5>
                            <hr class="my-2">
                            <label>Host:</label> <b class="text-success"><?=  View::toHtml($virtual_info['host']) ?></b></br>
                            <label>Locations:</label><br>
                            <div class="col-12" id="locs">
                                <?php if (!empty($virtual_info['locations'])) { ?>
                                    <?php foreach ($virtual_info['locations'] as $key => $location) { ?>
                                        <label><?= $key; ?>:</label> <b class="text-success"><?=  View::toHtml($location) ?></b></br>
                                    <?php } ?>
                                <?php } else echo '-';?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <?php if (!$warehouse->isVirtual()) { ?>
                <div class="row pt-4">
                    <div class="col align-self-center">
                        <h5 class="font-weight-bolder mb-3">Configuration</h5>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success btn-icon-split" id="configureWarehouseModalButton">
                            <span class="icon text-white-50">
                                <i class="fas fa-fw fa-edit"></i>
                            </span>
                            <span class="text">Edit</span>
                        </button>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <h5>Trolleys</h5>
                            <hr class="my-2">
                            <label>Quantity:</label> <b class="text-success"><?= !empty($CA['nums']) ? View::toHtml($CA['nums']) : '-' ?></b><br>
                            <?php if (!empty($warehouse->getMezzanine())) { ?>
                                <label>Quantity Mezzanine:</label> <b class="text-success"><?= !empty($CA['nums_mez']) ? View::toHtml($CA['nums_mez']) : '-' ?></b><br>
                            <?php } ?>
                            <label>Parameters of trolley:</label><br>
                            <div class="col-12">
                                <label>Size:</label> <b class="text-success">
                                    <?php
                                        if (!empty($size_CA)) {
                                            $is_sizes = !empty($size_CA->getWidth()) && !empty($size_CA->getDepth()) && !empty($size_CA->getHeight());
                                            if ($is_sizes)
                                                echo View::toHtml($size_CA->getDepth() . 'x' . $size_CA->getWidth() . 'x' . $size_CA->getHeight());
                                            if (!empty($size_CA->getName())) {
                                                if ($is_sizes)
                                                    echo View::toHtml(' (' . $size_CA->getName() . ')');
                                                else
                                                    echo View::toHtml(' ' . $size_CA->getName());
                                            }
                                        } else
                                            echo "-";
                                    ?></b><br>
                                <label>Shelves:</label> <b class="text-success"><?= !empty($CA['shelves']) ? View::toHtml($CA['shelves']) : '-' ?></b><br>
                                <label>Max capacity:</label> <b class="text-success"><?= !empty($CA['weight']) ? View::toHtml($CA['weight'] . ' kg') : '-' ?></b><br>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <h5>Order bins</h5>
                            <hr class="my-2">
                            <label>Quantity:</label> <b class="text-success"><?= !empty($OB['nums']) ? View::toHtml(($OB['nums'] * $OB['cols'] * $OB['rows']) .' ('. $OB['nums'] .'x'.$OB['cols'].'x'.$OB['rows'].')') : '-' ?></b><br>
                            <?php if (!empty($warehouse->getMezzanine())) {?>
                                <label>Quantity Mezzanine:</label> <b class="text-success"><?= !empty($OB['nums_mez']) ? View::toHtml(($OB['nums_mez'] * $OB['cols'] * $OB['rows']) .' ('. $OB['nums_mez'] .'x'.$OB['cols'].'x'.$OB['rows'].')') : '-' ?></b><br>
                            <?php }?>
                            <label>Size type:</label> <b class="text-success"><?= View::toHtml($size_OB ? $size_OB->getName() : '-'); ?></b><br>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <h5>Packing tables</h5>
                            <hr class="my-2">
                            <label>Table pack:</label> <b class="text-success"><?= !empty($TP['nums']) ? View::toHtml($TP['nums']) : '-' ?></b><br>
                            <label>Large pack:</label> <b class="text-success"><?= !empty($TP['LP']) ? View::toHtml($TP['LP']) : '-'?></b><br>
                            <label>Size type:</label> <b class="text-success"><?= View::toHtml($size_TP ? $size_TP->getName() : '-'); ?></b><br>
                        </div>
                    </div>
                </div>
                <div class="row pt-4">
                    <div class="col-6">
                        <div class="row">
                            <div class="col align-self-center">
                                <h5 class="font-weight-bolder mb-3">Zones</h5>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-success btn-icon-split" id="editZones">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </span>
                                    <span class="text">Edit</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="row">
                            <div class="col align-self-center">
                                <h5 class="font-weight-bolder mb-3">Global</h5>
                            </div>
                            <div class="col-auto">
                                <button class="btn btn-success btn-icon-split" id="editAssginment">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-edit"></i>
                                    </span>
                                    <span class="text">Edit</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <h5>Special allowed zones</h5>
                            <hr class="my-2">
                            <?php foreach ($types as $type => $name) { ?>
                                <?php if (isset($location_type_zones[$type])) { ?>
                                    <label><?= $name . " ($type)" ?>:</label><b class="text-success"> <?= View::toHtml(implode(', ', $location_type_zones[$type])) ?></b>
                                <?php } else { ?>
                                    <label><?= $name . " ($type)" ?>:</label><b class="text-success"> -</b>
                                <?php } ?>
                                <br>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-0">
                            <h5>Processing Settings</h5>
                            <hr class="my-2">
                            <label>Collection by customer:</label> <?= !empty($warehouse->getCollectionByCustomer()) ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br>
                            <label>Is Multi Dropping:</label> <?= !empty($warehouse->getMultiDropping()) ? '<i class="fas fa-fw fa-check-circle"></i>' : '' ?><br>
                            <label>Default shipping service:</label> <b class="text-success"><?= View::toHtml($shipping_service ? $shipping_service->getName() : '-'); ?></b><br>
                            <label>Assigned Location Release:</label><b class="text-success"> <?= $release_date ?> d</b><br>
                            <label>Timer of print pdf:</label> <b class="text-success"><?= View::toHtml($warehouse_config[WarehousesConfig::TYPE_TIMER_STATUS] ? $warehouse_config[WarehousesConfig::TYPE_TIMER_PDF] . ' s' : 'disable'); ?></b><br>
                        </div>
                    </div>
                    <div class="col-md-4">
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php
    $modal_edit_warehouse = new SwitchModal('editWarehouseModal','Edit' ,SwitchModal::SIZE_LG);
    $company_info = '';
    if (!$warehouse->isVirtual())
        $company_info = '
            <h5 class="mb-3">Company</h5>
            <div class="row">
                <div class="form-group col-8">
                    <div class="form-group-element">
                        <input type="text" class="form-control form-control-lg" placeholder=" " id="company" name="company"/>
                        <label for="company">Company<span class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="form-group col-6 pr-2">
                    <div class="form-group-element">
                        <input type="text" class="form-control form-control-lg" placeholder=" " id="address1" name="address1"/>
                        <label for="address1">Address 1<span class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="form-group col-6 pl-2">
                    <div class="form-group-element">
                        <input type="text" class="form-control form-control-lg" placeholder=" " id="address2" name="address2"/>
                        <label for="address2">Address 2</label>
                    </div>
                </div>
                <div class="form-group col-6 pr-2">
                    <div class="form-group-element">
                        <input type="text" class="form-control form-control-lg" placeholder=" " id="town" name="town"/>
                        <label for="town">Town<span class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="form-group col-6 pl-2">
                    <div class="form-group-element">
                        <input type="text" class="form-control form-control-lg" placeholder=" " id="county" name="county"/>
                        <label for="county">County</label>
                    </div>
                </div>
                <div class="form-group col-6 pr-2">
                    <div class="form-group-element">
                        <input type="text" class="form-control form-control-lg" placeholder=" " id="postcode" name="postcode"/>
                        <label for="postcode">Postcode<span class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="col-md-6 my-0 mb-0 pl-2">
                    <div class="form-group-element">' .
                        Html::dropDownList("company_country", [-1 => "As in warehouse"] + $countries,null, [
                            "class" => "form-control form-control-lg custom-select",
                            "id" => "company_country",
                        ]) . '
                        <label for="company_country">Country</label>
                    </div>
                </div>
            </div>';
        else $company_info = '
             <h5 class="mb-3">Connection information</h5>
             <div class="row">
                <div class="form-group col-6 pl-2">
                    <div class="form-group-element">
                        <input type="text" class="form-control form-control-lg" placeholder=" " id="host" name="host"/>
                        <label for="host">Host<span class="text-danger">*</span></label>
                    </div>
                </div>
                <div class="form-group col-6 pr-2">
                    <div class="form-group-element">
                        <input type="password" autocomplete="new-password" class="form-control form-control-lg' . (empty($virtual_info['api_key']) ? '" placeholder=" "' : ' filled" placeholder="Set - leave empty to keep"') . ' id="api_key" name="api_key"/>
                        <label for="api_key">' . View::toHtml($virtual_info['api_key_name'] ?? '') . (empty($virtual_info['api_key']) ? '<span class="text-danger">*</span>' : '') . '</label>
                    </div>
                </div>
                <div class="form-group col-6 pr-2">
                    <div class="form-group-element">
                        <input type="hidden" class="form-control form-control-lg" placeholder=" " id="api_name" name="api_name"/>
                    </div>
                </div>            
                <div class="form-group col-6 pr-2">
                    <div class="form-group-element">
                        <input type="hidden" class="form-control form-control-lg" placeholder=" " id="class" name="class_host"/>
                    </div>
                </div>            
        </div>';
echo $modal_edit_warehouse ->addContent('
            <div class="alert alert-danger d-none" id="save_info_err"></div>' .
            $company_info . '
            <h5 class="mb-3 mt-2">Location</h5>
            <div class="row">
                <div class="form-group col-6 pr-2">
                    <div class="form-group-element">' .
                        Html::dropDownList("timezone", $timezones,null, [
                            "class" => "form-control form-control-lg custom-select",
                            "id" => "timezone",
                        ]) . '
                        <label for="timezone">Timezone</label>
                    </div>
                </div>
                <div class="form-group col-6 pl-2">
                    <div class="form-group-element"> ' .
                        Html::dropDownList("country_id", $countries, null, [
                            "class" => "form-control form-control-lg custom-select",
                            "id" => "country_id",
                        ]) . '
                        <label for="country_id">Country</label>
                    </div>
                </div>
            </div>
            <h5 class="mb-3 mt-2">Additional</h5>
            <div class="row">
                <div class="form-group col-3 pr-2">
                    <div class="form-group-element">
                        <input type="text" class="form-control form-control-lg" placeholder=" " id="label" name="label"</input>
                        <label for="label">Label</label>
                    </div>
                </div>
                <div class="form-group col-3 pr-2">
                    <div class="form-group-element">' .
                        Html::dropDownList("currency_id", $currencies, null, [
                            "class" => "form-control form-control-lg custom-select",
                            "id" => "currency_id",
                        ]) . '
                        <label for="currency_id">Currency</label>
                    </div>
                </div>
                <div class="form-group col-3 pl-2">
                    <div class="form-group-element">
                        <input type="text" class="form-control form-control-lg" placeholder=" " id="billing" name="billing"></input>
                        <label for="billing">Billing</label>
                    </div>
                </div>
                <div class="col-3 pl-2">
                    <div class="form-group">
                        <label for="mezzanine">Mezzanine</label>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input position-static" id="mezzanine" name="mezzanine"/>
                        </div>
                    </div>
                </div>
            </div>')
        ->addButton('Save', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['id' => 'btn_save'])
        ->put();
?>

<?php
$modal_conf_warehouse = new SwitchModal('editConfigureModal','Configure' ,SwitchModal::SIZE_LG);
$mezzanine_CA_info = '';
$mezzanine_OB_info = '';
$mezzanine_info = function ($quantity) {
    return "
        <div class=\"form-group-element\">
            <input type=\"text\" class=\"form-control form-control-lg\" placeholder=\" \" id=\"$quantity\" name=\"$quantity\"</input>
            <label for=\"$quantity\">Mezzanine</label>
        </div>";
};
if (!empty($warehouse->getMezzanine())) {
    $mezzanine_CA_info = $mezzanine_info('quantity_mez');
    $mezzanine_OB_info = $mezzanine_info('quantity_ob_mez');
}
echo $modal_conf_warehouse->addContent('
        <div class="alert alert-danger d-none" id="save_conf_err"></div>
        <h5 class="mb-3">CA</h5>
        <div class="row">
            <div class="form-group col-2 px-2">
                <div class="form-group-element">
                    <input type="text" class="form-control form-control-lg" placeholder=" " id="quantity_ca" name="quantity_ca"</input>
                    <label for="quantity_ca">Quantity<span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="form-group col-2 px-2"> ' . $mezzanine_CA_info .' </div>
            <div class="form-group col-2 px-2">
                <div class="form-group-element">
                    <input type="text" class="form-control form-control-lg" placeholder=" " id="shelves" name="shelves"</input>
                    <label for="shelves">Shelves<span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="form-group col-2 px-2">
                <div class="form-group-element">
                    <input type="text" class="form-control form-control-lg" placeholder=" " id="capacity" name="capacity"</input>
                    <label for="capacity">Capacity<span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="form-group col-4 px-2">
                <div class="form-group-element">' .
                    Html::dropDownList("size_id_ca", $sizes, null, [
                        "class" => "form-control form-control-lg custom-select",
                        "id" => "size_id_ca",
                    ]) . '
                    <label for="size_id_ca">Size name<span class="text-danger">*</span></label>
                </div>
            </div>
        </div>
        <h5 class="mb-3">OB</h5>
        <div class="row">
            <div class="form-group col-2 px-2">
                <div class="form-group-element">
                    <input type="text" class="form-control form-control-lg" placeholder=" " id="quantity_ob" name="quantity_ob"</input>
                    <label for="quantity_ob">Nums<span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="form-group col-2 px-2">'. $mezzanine_OB_info .'</div>
            <div class="form-group col-2 px-2">
                <div class="form-group-element">
                    <input type="text" class="form-control form-control-lg" placeholder=" " id="cols" name="cols"</input>
                    <label for="cols">Cols<span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="form-group col-2 px-2">
                <div class="form-group-element">
                    <input type="text" class="form-control form-control-lg" placeholder=" " id="rows" name="rows"</input>
                    <label for="rows">Rows<span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="form-group col-4 px-2">
                <div class="form-group-element">' .
                    Html::dropDownList("size_ob", $sizes, null, [
                        "class" => "form-control form-control-lg custom-select",
                        "id" => "size_ob",
                    ]) . '
                    <label for="size_ob">Size name<span class="text-danger">*</span></label>
                </div>
            </div>
        </div>
        <h5 class="mb-3">TP</h5>
        <div class="row">
            <div class="form-group col-4 px-2 mb-0">
                <div class="form-group-element">
                    <input type="text" class="form-control form-control-lg" placeholder=" " id="table_pack" name="table_pack"</input>
                    <label for="table_pack">Table pack<span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="form-group col-4 px-2 mb-0">
                <div class="form-group-element">
                    <input type="text" class="form-control form-control-lg" placeholder=" " id="large_pack" name="large_pack"</input>
                    <label for="large_pack">Large pack<span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="form-group col-4 px-2 mb-0">
                <div class="form-group-element">' .
                    Html::dropDownList("size_pa", $sizes, null, [
                        "class" => "form-control form-control-lg custom-select",
                        "id" => "size_pa",
                    ]) . '
                    <label for="size_pa">Size name<span class="text-danger">*</span></label>
                </div>
            </div>
        </div>')
    ->addButton('Save', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['id' => 'btn_conf_save'])
    ->put();
?>
<?php
$options = '';
foreach ($types as $id => $type) {
    $options .= '<option value="' . View::toHtml($id) . '">' . View::toHtml($type) . '</option>';
}
$content = '<div class="alert alert-danger d-none" id="error-zone"></div>
            <div class="form-row">
                <div class="col-6">
                    <div class="form-group mb-0">
                        <div class="form-group-element">
                            <select id="type-id" class="form-control form-control-lg custom-select">
                                ' . $options . '
                            </select>
                            <label for="type-id">Location type</label>
                        </div>
                    </div>
                </div>
                <div class="form-group col-6 mb-0">
                    <div class="form-group-element">
                        <input type="text" id="add-zone" class="form-control form-control-lg" placeholder=" " value="" maxlength="' . $max_length_zone . '">
                        <label for="add-zone">Add zone</label>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="col-12">
                    <div class="form-group mb-0">
                        <div class="form-group-element" id="active-zones"></div>
                    </div>
                </div>
            </div>';
$modal_edit_zones = new SwitchModal('editZones', 'Edit zones ' . View::toHtml($warehouse->getName()));
echo $modal_edit_zones->addContent($content)
    ->addButton('Save', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['disabled' => "disabled", 'id' => 'zonesSave'])
    ->addCustomButtons($modal_edit_zones->getButtons())
    ->put();
$modal_edit_release = new SwitchModal('editAssignment', 'Edit Global Settings');
echo $modal_edit_release->addContent('        
        <div class="alert alert-danger d-none" id="save_release_err"></div>
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label for="collection">Collection by customer</label>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input position-static" id="collection" name="collection"/>
                    </div>
                </div>
            </div>
             <div class="col-6 pl-2">
                <div class="form-group">
                    <label for="is_multi_dropping">Multi dropping</label>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input position-static" id="is_multi_dropping" name="is_multi_dropping"/>
                    </div>
                </div>
            </div>
        </div>
        <h5>Assigned Location Release</h5>
        <div class="row">
            <div class="form-group col-12">
                <div class="form-group-element">
                    <input type="number" min="0" class="form-control form-control-lg" placeholder=" " id="quantity_time" name="quantity_time" value='. $release_date .' </input>
                    <label for="quantity_time">Amount of days</label>
                </div>
            </div>
        </div>
        <h5>Timer of print pdf</h5>
        <div class="row">
            <div class="col-3">
                <div class="form-group mb-0">
                    <label for="timer_status">Timer status</label>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input position-static" id="timer_status" name="timer_status"/>
                    </div>
                </div>
            </div>
            <div class="form-group col-9 mb-0">
                <div class="form-group-element">
                    <input type="text" class="form-control form-control-lg" placeholder=" " id="timer_print_pdf" name="timer_print_pdf"></input>
                    <label for="timer_print_pdf">Seconds</label>
                </div>
            </div>
        </div>')
    ->addButton('Save', SwitchModal::TYPE_BUTTON, SwitchModal::BUTTON_DONE, ['id' => 'releaseSave'])
    ->put();
?>
<script>
    $(document).ready(function () {

        function send(method, data, showError) {
            $.ajax({
                url: "/warehouses/" + method,
                method: "POST",
                dataType: 'json',
                data: data,
                success: function (json) {
                    if ('error' in json)
                        showError(json.error);
                    else
                        location = '';
                },
                error: function (json) {
                    showError('Connection is not found!');
                }
            });
        }

        let information_object = {
            show_error: function (msg) {
                let save_info_err = $('#save_info_err');
                save_info_err.html(msg);
                save_info_err.prop('classList').remove('d-none');
                setTimeout(() => {
                    save_info_err.prop('classList').add('d-none');
                }, 3000);
            },
            addEvents: function () {
                let self = this;
                $('#editWarehouseModalButton').click(function () {
                    <?php if (!$warehouse->isVirtual()) { ?>
                        $('#company').val(<?= View::toJs((string)($address_data['company'] ?? '')); ?>);
                        $('#address1').val(<?= View::toJs((string)($address_data['address1'] ?? '')); ?>);
                        $('#address2').val(<?= View::toJs((string)($address_data['address2'] ?? '')); ?>);
                        $('#town').val(<?= View::toJs((string)($address_data['town'] ?? '')); ?>);
                        $('#county').val(<?= View::toJs((string)($address_data['county'] ?? '')); ?>);
                        $('#postcode').val(<?= View::toJs((string)($address_data['postcode'] ?? '')); ?>);
                        $('#company_country').val(<?= View::toJs((string)($address_data['country_id'] ?? '')); ?>);
                    <?php } else { ?>
                        $('#host').val(<?= View::toJs((string)($virtual_info['host'] ?? '')); ?>);
                        $('#api_key').val('');
                        $('#api_name').val(<?= View::toJs((string)($virtual_info['api_key_name'] ?? '')); ?>);
                        $('#class').val(<?= View::toJs((string)($virtual_info['class'] ?? '')); ?>);
                    <?php }?>
                    $('#timezone').val(<?= View::toJs((string)($warehouse->getTimezone() ?: '')); ?>);
                    $('#country_id').val(<?= View::toJs((string)($warehouse->getCountryId() ?: 'GB')); ?>);
                    $('#label').val(<?= View::toJs((string)($warehouse->getLabel() ?: '')); ?>);
                    $('#currency_id').val(<?= View::toJs((string)($warehouse->getCurrencyId() ?: '')); ?>);
                    $('#mezzanine').prop('checked', <?= $warehouse->getMezzanine() ? 'true' : 'false'; ?>);
                    $('#billing').val(<?= View::toJs((string)($warehouse->getBilling() ?: '')); ?>);
                    <?= $modal_edit_warehouse->show();?>
                });
                $('#btn_save').click(function () {
                    let data = {
                        warehouse_id: <?= $warehouse->getId(); ?>,
                        company: $('#company').val(),
                        address1: $('#address1').val(),
                        address2: $('#address2').val(),
                        town: $('#town').val(),
                        county: $('#county').val(),
                        postcode: $('#postcode').val(),
                        company_country: $('#company_country').val(),
                        timezone: $('#timezone').val(),
                        country_id: $('#country_id').val(),
                        label: $('#label').val(),
                        mezzanine: $('#mezzanine').is(':checked') ? 1 : 0,
                        currency_id: $('#currency_id').val(),
                        billing: $('#billing').val(),
                        host: $('#host').val(),
                        class: $('#class').val(),
                        api_key: $('#api_key').val(),
                        api_key_name: $('#api_name').val(),
                    };
                    send('store', data, self.show_error.bind(self));
                });
            }
        };

        let configuration_object = {
            show_error: function (msg) {
                let save_conf_err = $('#save_conf_err');
                save_conf_err.html(msg);
                save_conf_err.prop('classList').remove('d-none');
                setTimeout(() => {
                    save_conf_err.prop('classList').add('d-none');
                }, 3000);
            },
            addEvents: function () {
                let self = this;
                $('#configureWarehouseModalButton').click(function () {
                    $('#quantity_ca').val('<?= $CA['nums'] ?? ''; ?>');
                    <?php if ($warehouse->getMezzanine()) { ?>
                        $('#quantity_mez').val(<?= $CA['nums_mez'] ?? 0; ?>);
                        $('#quantity_ob_mez').val(<?= $OB['nums_mez'] ?? 0; ?>);
                    <?php } ?>
                    $('#shelves').val('<?= $CA['shelves'] ?? ''; ?>');
                    $('#capacity').val('<?= $CA['weight'] ?? ''; ?>');
                    $('#size_id_ca').val('<?= $size_CA ? $size_CA->getId() : 0; ?>');
                    $('#quantity_ob').val('<?= $OB['nums'] ?? ''; ?>');
                    $('#cols').val('<?= $OB['cols'] ?? ''; ?>');
                    $('#rows').val('<?= $OB['rows'] ?? ''; ?>');
                    $('#size_ob').val('<?= $size_OB ? $size_OB->getId() : 0; ?>');
                    $('#table_pack').val('<?= $TP['nums'] ?? ''; ?>');
                    $('#large_pack').val('<?= $TP['LP'] ?? ''; ?>');
                    $('#size_pa').val('<?= $size_TP ? $size_TP->getId() : 0; ?>');
                    <?= $modal_conf_warehouse->show(); ?>;
                });
                $('#btn_conf_save').click(function () {
                    let data = {
                        warehouse_id: <?= $warehouse->getId(); ?>,
                        quantity_ca: $('#quantity_ca').val(),
                        quantity_mez: $('#quantity_mez').val(),
                        shelves: $('#shelves').val(),
                        weight: $('#capacity').val(),
                        quantity_ob: $('#quantity_ob').val(),
                        quantity_ob_mez: $('#quantity_ob_mez').val(),
                        cols: $('#cols').val(),
                        rows: $('#rows').val(),
                        table_pack: $('#table_pack').val(),
                        large_pack: $('#large_pack').val(),
                        size_CA: $('#size_id_ca').val(),
                        size_OB: $('#size_ob').val(),
                        size_PA: $('#size_pa').val(),
                    };
                    send('store_configuration', data, self.show_error.bind(self));
                });
            }
        };

        let zones_object = {
            zones: {},
            reg_exp: new RegExp('<?= $regexp_zone; ?>'),
            save_info_err: $('#error-zone'),
            add_zone: $("#add-zone"),
            active_zone: '.active-zone',
            zones_save: $('#zonesSave'),
            type_id: $('#type-id'),
            active_zones: $('#active-zones'),
            addMergeForZones: function () {
                let add_class = 'mt-2';
                if ($(this.active_zone).length === 0) {
                    this.active_zones.removeClass(add_class);
                } else {
                    this.active_zones.addClass(add_class);
                }
            },
            enableButtonSave: function () {
                let original_zones = <?= View::toJs($location_type_zones ?: new stdClass()) ?>;
                let is_equal = true;

                let keys_original = Object.keys(original_zones);
                let keys = Object.keys(this.zones);
                if (keys_original.length !== keys.length) {
                    is_equal = false;
                } else {
                    break_end:
                        for (let location in this.zones) {
                            if (original_zones[location] === undefined || original_zones[location].length !== this.zones[location].length) {
                                is_equal = false;
                                break;
                            }
                            for (let zone_key in this.zones[location]) {
                                if (!original_zones[location].includes(this.zones[location][zone_key])) {
                                    is_equal = false;
                                    break break_end;
                                }
                            }
                        }
                }
                if (is_equal) {
                    this.zones_save.prop('disabled', true);
                } else {
                    this.zones_save.prop('disabled', false);
                }
            },
            addZoneHtml: function (zone) {
                let html =
                    '<button class="btn btn-success btn-icon-split active-zone mr-1 mt-2" type="button" value="' + zone + '">' +
                    '<span class="text text-white">' + zone + '</span>' +
                    '<span class="icon text-white"><i class="fas fa-fw fa-times"></i></span>' +
                    '</button>';
                this.active_zones.append(html);
                this.addMergeForZones();
            },
            addZone: function (type_id, zone) {
                if (this.zones[type_id] === undefined) {
                    this.zones[type_id] = [];
                }
                if (this.zones[type_id].includes(zone)) {
                    return;
                }
                this.zones[type_id].push(zone);
                this.addZoneHtml(zone);
            },
            fillZones: function () {
                $(this.active_zone).remove();
                let type_id = this.type_id.val();
                if (this.zones[type_id] === undefined) {
                    return;
                }
                for (let zone of this.zones[type_id]) {
                    this.addZoneHtml(zone);
                }
            },
            showError: function (msg) {
                this.save_info_err.html(msg);
                this.save_info_err.prop('classList').remove('d-none');
                this.save_info_err.add('mb-2');
            },
            hideError: function () {
                this.save_info_err.prop('classList').add('d-none');
                this.save_info_err.remove('mb-2');
            },
            focus: function () {
                this.add_zone.val('');
                this.add_zone.focus();
            },
            addEvents: function () {
                let self = this;
                $('#editZones').click(function () {
                    self.zones = <?= View::toJs($location_type_zones ?: new stdClass()) ?>;
                    self.fillZones();
                    <?= $modal_edit_zones->show(); ?>
                });
                this.add_zone.keyup(function () {
                    this.value && !self.reg_exp.test(this.value) ? self.showError('Only printable characters are allowed in zone') : self.hideError();
                });
                this.add_zone.change(function () {
                    let zone = this.value;
                    if (!self.reg_exp.test(zone)) {
                        return;
                    }
                    zone = zone.toUpperCase();
                    let type_id = self.type_id.val();
                    self.addZone(type_id, zone);
                    self.focus();
                    self.enableButtonSave();
                });
                this.type_id.change(function () {
                    self.fillZones();
                });
                $(document).on("click", this.active_zone, function () {
                    let zone = this.value;
                    let type_id = self.type_id.val();
                    if (!self.zones[type_id].includes(zone)) {
                        return;
                    }
                    self.zones[type_id].splice(self.zones[type_id].indexOf(zone), 1);
                    $(this).remove();
                    self.enableButtonSave();
                    self.hideError()
                    self.addMergeForZones();
                });
                this.zones_save.click(function () {
                    send('store_zones', {'zones': self.zones}, self.showError.bind(self));
                });
            }
        };

        let release_object = {
            release_save: $('#releaseSave'),
            show_error: function (msg) {
                let save_release_err = $('#save_release_err');
                save_release_err.html(msg);
                save_release_err.prop('classList').remove('d-none');
                setTimeout(() => {
                    save_release_err.prop('classList').add('d-none');
                }, 3000);
            },
            disableEnableTimerPdf() {
                $('#timer_print_pdf').prop('disabled', !$('#timer_status').is(':checked'));
            },
            addEvents: function () {
                let self = this;
                $('#editAssginment').click(function () {
                    $('#timer_status').prop('checked', <?= $warehouse_config[WarehousesConfig::TYPE_TIMER_STATUS] ? 'true' : 'false'; ?>);
                    $('#timer_print_pdf').val(<?= View::toJs((string)$warehouse_config[WarehousesConfig::TYPE_TIMER_PDF]); ?>);
                    $('#collection').prop('checked', <?= $warehouse->getCollectionByCustomer() ? 'true' : 'false'; ?>);
                    $('#is_multi_dropping').prop('checked', <?= $warehouse->getMultiDropping() ? 'true' : 'false'; ?>);
                    self.disableEnableTimerPdf();
                    <?= $modal_edit_release->show(); ?>
                });
                self.release_save.click(function () {
                    let data = {
                        warehouse_id: <?= $warehouse->getId(); ?>,
                        amount: $('#quantity_time').val(),
                        timer_print_pdf: $('#timer_print_pdf').val(),
                        collection: $('#collection').is(':checked') ? 1 : 0,
                        is_multi_dropping: $('#is_multi_dropping').is(':checked') ? 1 : 0,
                        timer_status: $('#timer_status').is(':checked') ? 1 : 0
                    };
                    send('store_release', data, self.show_error.bind(self));
                });
                $('#timer_status').change(function () {
                    self.disableEnableTimerPdf();
                });
            }
        }

        information_object.addEvents();
        configuration_object.addEvents();
        zones_object.addEvents();
        release_object.addEvents();
    });
</script>
