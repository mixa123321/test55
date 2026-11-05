<?php
/**
 * @var $authUser User
 * @var $supplier Supplier
 * @var $packing_slip_types array
 * @var $warehouses_list array
 * @var $countries array
 * @var $return_carriers array
 * @var $warehouses array
 */

use app\core\entities\Address;
use app\core\entities\RetainMeCredentials;
use app\core\entities\Supplier;
use app\core\entities\User;
use app\core\helpers\View;
use app\core\helpers\Html;
use app\core\models\PackingSlips;

?>
    <div class="container-fluid p-sm-4 p-2">
        <!-- Page Heading -->
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Warehouse setting</h1>
        </div>
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-fw fa-user-circle"></i> <?= View::toHtml($supplier->getName()) ?>
                </h6>
            </div>
                <div class="card-body">
                    <div class="row justify-content-center">
                        <div class="col-md-12">
                            <?php if (!empty($warehouses_list)) { ?>
                            <?php foreach ($warehouses_list as $warehouse_id => $warehouse) { ?>
                                    <div id="warehouse_<?= $warehouse_id; ?>" class="border border-2 p-3 warehouses">
                                        <div id="response_<?= $warehouse_id; ?>" class="responses"></div>
                                        <div class="row my-0 justify-content-between">
                                            <div class="col-auto">
                                                <div class="row ml-0">
                                                    <div class="col-auto form-check align-self-center">
                                                        <input type="checkbox"
                                                               class="form-check-input warehouse_access"
                                                               id="access_<?= $warehouse_id; ?>"
                                                               name="access"
                                                            <?= !empty($warehouse['access']) ? 'checked' : '' ?>
                                                        >
                                                        <label class="mb-0" for="access_<?= $warehouse_id; ?>">Access</label>
                                                        <input type="hidden" id="is_virtual_<?= $warehouse_id; ?>" name="is_virtual" value="<?= (int)$warehouse['is_virtual']; ?>"/>
                                                    </div>
                                                    <div class="col-auto">
                                                        <button class="border-0 p-2 toggle_warehouse" style="outline: none">
                                                            <span><?= View::toHtml($warehouse['name']); ?></span>
                                                            <i class="fas fa-fw fa-chevron-down ml-2"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <button class="btn btn-success pr-3 float-lg-right update_warehouse"
                                                        id="update_warehouse<?= $warehouse_id; ?>" >
                                                    <span class="mr-1">Update</span>
                                                    <i class="fas fa-fw fa-chevron-right ml-2"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="warehouse_control">
                                            <div class="row my-3">
                                                <div class="col-md-4">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input id="VAT_<?= $warehouse_id; ?>" type="text"
                                                                   class="form-control form-control-lg" placeholder=" "
                                                                   value="<?= empty($warehouse['VAT']) ? '' : View::toHtml($warehouse['VAT']); ?>">
                                                            <label for="VAT_<?= $warehouse_id; ?>">VAT Number</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input id="EORI_<?= $warehouse_id; ?>" type="text"
                                                                   class="form-control form-control-lg" placeholder=" "
                                                                   value="<?= empty($warehouse['EORI']) ? '' : View::toHtml($warehouse['EORI']); ?>">
                                                            <label for="EORI_<?= $warehouse_id; ?>">EORI Number</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input id="sage_id_<?= $warehouse_id; ?>" type="text"
                                                                   class="form-control form-control-lg" placeholder=" "
                                                                   value="<?= empty($warehouse['sage_id']) ? '' : View::toHtml($warehouse['sage_id']); ?>">
                                                            <label for="name">Sage ID<span
                                                                        class="text-danger">*</span></label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input id="reg_number_<?= $warehouse_id; ?>" type="text"
                                                                   class="form-control form-control-lg" placeholder=" "
                                                                   value="<?= empty($warehouse['reg_number']) ? '' : View::toHtml($warehouse['reg_number']); ?>">
                                                            <label for="reg_number_<?= $warehouse_id; ?>">IOSS Registration Number</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input type="password" autocomplete="new-password"
                                                                   id="shipping_api_key_<?= $warehouse_id; ?>"
                                                                   class="form-control form-control-lg js-secret" data-secret="shipping_api_key">
                                                            <label for="name">Shipping API key</label>
                                                        </div>
                                                        <div class="form-check ml-1 mt-1" id="shipping_api_key_clear_box_<?= $warehouse_id; ?>">
                                                            <input type="checkbox" class="form-check-input" id="shipping_api_key_clear_<?= $warehouse_id; ?>">
                                                            <label class="form-check-label" for="shipping_api_key_clear_<?= $warehouse_id; ?>">Clear shipping API key</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input type="text"
                                                                   id="shipping_account_number_<?= $warehouse_id; ?>"
                                                                   class="form-control form-control-lg" placeholder=" "
                                                                   value="<?= empty($warehouse['shipping_account_number']) ? '' : View::toHtml($warehouse['shipping_account_number']); ?>">
                                                            <label for="name">Shipping account number</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row my-3">
                                                <div class="col-md-1">
                                                    <div class="form-group">
                                                        <div class="form-check ml-1">
                                                            <input type="checkbox"
                                                                   class="form-check-input"
                                                                   id="return_label_<?= $warehouse_id; ?>"
                                                                <?= $warehouse['return_label'] ? ' checked' : ''; ?>>
                                                            <label class="form-check-label" for="return_label_<?= $warehouse_id; ?>">Return label</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <?= Html::dropDownList(
                                                                'return_carrier_id',
                                                                $return_carriers,
                                                                !empty($warehouse['return_carrier_id']) ? $warehouse['return_carrier_id'] : '',
                                                                [
                                                                    'class' => 'selectpicker form-control form-control-lg custom-select return_carrier-select',
                                                                    'id' => 'return_carrier_'.$warehouse_id,
                                                                ]
                                                            ) ?>
                                                            <label for="return_carrier_<?= $warehouse_id ?>">Return Carrier</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php if ($warehouse['is_virtual']) { ?>
                                                    <div class="col-md-4">
                                                        <div class="form-group mb-0">
                                                            <div class="form-group-element">
                                                                <input type="text"
                                                                       id="virtual_credentials_user_name_<?= $warehouse_id; ?>"
                                                                       class="form-control form-control-lg" placeholder=" "
                                                                       value="<?= empty($warehouse['virtual_credentials']['user_name']) ? '' : View::toHtml($warehouse['virtual_credentials']['user_name']); ?>">
                                                                <label for="virtual_credentials_user_name_<?= $warehouse_id; ?>">Virtual API login</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="form-group mb-0">
                                                            <div class="form-group-element">
                                                                <input type="password" autocomplete="new-password"
                                                                       id="virtual_credentials_pass_<?= $warehouse_id; ?>"
                                                                       class="form-control form-control-lg js-secret" data-secret="virtual_pass">
                                                                <label for="virtual_credentials_pass_<?= $warehouse_id; ?>">Virtual API key</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-4">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <?= Html::dropDownList(
                                                                'packing_slip_type',
                                                                $packing_slip_types,
                                                                $warehouse['packing_slip_type'] ?? null,
                                                                [
                                                                    'class' => 'selectpicker form-control form-control-lg custom-select packing_slip_type-select',
                                                                    'id' => 'packing_slip_type_' . $warehouse_id,
                                                                ]
                                                            ) ?>
                                                            <label for="packing_slip_type_<?= $warehouse_id ?>">Packing slip type</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mt-3 mb-0" id="retain-me_<?= $warehouse_id; ?>" style="display: none">
                                                <?php $retainme_credentials = new RetainMeCredentials($warehouse['retainme_credentials']); ?>
                                                <div class="col-md-3">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input type="text"
                                                                   id="retainme_credentials_orders_login_<?= $warehouse_id; ?>"
                                                                   class="form-control form-control-lg" placeholder=" "
                                                                   value="<?= View::toHtml(!empty($retainme_credentials->getOrdersLogin()) ? $retainme_credentials->getOrdersLogin() : ''); ?>">
                                                            <label for="retainme_credentials_orders_login_<?= $warehouse_id; ?>">Retain.me Orders API
                                                                login</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input type="password" autocomplete="new-password"
                                                                   id="retainme_credentials_orders_pwd_<?= $warehouse_id; ?>"
                                                                   class="form-control form-control-lg js-secret" data-secret="orders_pwd">
                                                            <label for="retainme_credentials_orders_pwd">Retain.me Orders API
                                                                password</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input type="text"
                                                                   id="retainme_credentials_print_login_<?= $warehouse_id; ?>"
                                                                   class="form-control form-control-lg" placeholder=" "
                                                                   value="<?= View::toHtml(!empty($retainme_credentials->getPrintLogin()) ? $retainme_credentials->getPrintLogin() : ''); ?>">
                                                            <label for="retainme_credentials_orders_pwd_<?= $warehouse_id; ?>">Retain.me Print API
                                                                login</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group mb-0">
                                                        <div class="form-group-element">
                                                            <input type="password" autocomplete="new-password"
                                                                   id="retainme_credentials_print_pwd_<?= $warehouse_id; ?>"
                                                                   class="form-control form-control-lg js-secret" data-secret="print_pwd">
                                                            <label for="retainme_credentials_print_pwd_<?= $warehouse_id; ?>">Retain.me Print API
                                                                password</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-12 p-0 m-0">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <h5 class="mt-4">Company Address</h5>
                                                        <?php $address = new Address($warehouse['address'] ?? []); ?>
                                                        <div class="form-row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="address1_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($address->getAddress1()) ?>">
                                                                        <label for="address1_<?= $warehouse_id; ?>">Address Line 1 <span
                                                                                    class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="address2_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($address->getAddress2()) ?>">
                                                                        <label for="address2_<?= $warehouse_id; ?>">Address Line 2</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="address3_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($address->getAddress3()) ?>">
                                                                        <label for="address3_<?= $warehouse_id; ?>">Address Line 3</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="town_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($address->getTown()) ?>">
                                                                        <label for="town_<?= $warehouse_id; ?>">Town <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="county_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($address->getCounty()) ?>">
                                                                        <label for="county_<?= $warehouse_id; ?>"><?= $warehouse['country_id'] == 'US' ? 'State' : 'County'; ?> <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="postcode_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($address->getPostcode()) ?>">
                                                                        <label for="postcode_<?= $warehouse_id; ?>">Postcode <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <?= Html::dropDownList(
                                                                            'supplier_data[country_id]',
                                                                            $countries,
                                                                            $address->getCountryId(),
                                                                            [
                                                                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                                                                'id' => 'country_id_' . $warehouse_id,
                                                                            ]
                                                                        ) ?>
                                                                        <label for="country_id_<?= $warehouse_id; ?>">Country <span
                                                                                    class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <h5 class="mt-4">Billing Address</h5>
                                                        <?php $billing_address = new Address($warehouse['billing_address'] ?? []); ?>
                                                        <div class="form-row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="billing_name_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($billing_address->getName()); ?>">
                                                                        <label for="billing_name_<?= $warehouse_id; ?>">Company name <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="billing_contact_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($billing_address->getContact()); ?>">
                                                                        <label for="billing_contact_<?= $warehouse_id; ?>">Name <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="billing_email_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($billing_address->getEmail()); ?>">
                                                                        <label for="billing_email_<?= $warehouse_id; ?>">Email <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="form-row">
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="billing_address1_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($billing_address->getAddress1()) ?>">
                                                                        <label for="billing_address1_<?= $warehouse_id; ?>">Address Line 1 <span
                                                                                    class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="billing_address2_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($billing_address->getAddress2()) ?>">
                                                                        <label for="billing_address2_<?= $warehouse_id; ?>">Address Line 2</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="billing_address3_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($billing_address->getAddress3()) ?>">
                                                                        <label for="billing_address3_<?= $warehouse_id; ?>">Address Line 3</label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="billing_town_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($billing_address->getTown()) ?>">
                                                                        <label for="billing_town_<?= $warehouse_id; ?>">Town <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="billing_county_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($billing_address->getCounty()) ?>">
                                                                        <label for="billing_county_<?= $warehouse_id; ?>"><?= $warehouse['country_id'] == 'US' ? 'State' : 'County'; ?> <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <input id="billing_postcode_<?= $warehouse_id; ?>"
                                                                               type="text"
                                                                               class="form-control form-control-lg" placeholder=" "
                                                                               value="<?= View::toHtml($billing_address->getPostcode()) ?>">
                                                                        <label for="billing_postcode_<?= $warehouse_id; ?>">Postcode <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <div class="form-group">
                                                                    <div class="form-group-element">
                                                                        <?= Html::dropDownList(
                                                                            'supplier_data[billing_country_id]',
                                                                            $countries,
                                                                            $billing_address->getCountryId(),
                                                                            [
                                                                                'class' => 'selectpicker form-control form-control-lg custom-select',
                                                                                'id' => 'billing_country_id_' . $warehouse_id,
                                                                            ]
                                                                        ) ?>
                                                                        <label for="billing_country_id_<?= $warehouse_id; ?>">Country <span class="text-danger">*</span></label>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div  class="form-row">
                                                <div class="col-md-9">
                                                    <h5 class="mt-2">Processing Settings</h5>
                                                    <div class="form-check ml-1">
                                                        <input type="checkbox"
                                                               class="form-check-input"
                                                               id="automatical_assignment_<?= $warehouse_id; ?>"
                                                            <?= $warehouse['automatical_assignment'] ? ' checked' : ''; ?>>
                                                        <label class="form-check-label" for="automatical_assignment_<?= $warehouse_id; ?>">Automatical locations assignment</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                            <?php } } else { ?>
                                <hr class="mt-3">
                                <?= Html::warning("No warehouses found"); ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col">
                            <a class="btn btn-outline-orange pl-3"
                               href="/<?= 'suppliers/settings/?id=' . $supplier->getId(); ?>">
                                <i class="fas fa-fw fa-chevron-left mr-2"></i>
                                <span class="ml-1">Back</span>
                            </a>
                        </div>
                    </div>
                </div>
        </div>
    </div>
<script>
    const RETAIN_ME = '<?= PackingSlips::TYPE_RETAIN_ME ?>';

    const warehouses_list = <?= View::toJs($warehouses_list) ?>;

    const getWarehouseId = target => +$(target).parents('.warehouses').attr('id').replace('warehouse_', '');

    // Secrets are not sent to the page: their fields stay empty, an empty field keeps the stored value
    function showSecrets(warehouse_id) {
        const secrets = warehouses_list[warehouse_id].secrets;
        $('#warehouse_' + warehouse_id + ' .js-secret').each(function () {
            const is_set = !!secrets[$(this).data('secret')];
            $(this).val('').attr('placeholder', is_set ? 'Set - leave empty to keep' : ' ').toggleClass('filled', is_set);
        });
        $('#shipping_api_key_clear_' + warehouse_id).prop('checked', false);
        $('#shipping_api_key_clear_box_' + warehouse_id).toggle(secrets.shipping_api_key);
    }

    function getSupplierWarehouse(warehouse_id) {
        let supplier_warehouse = {};
        supplier_warehouse.warehouse_id = +warehouse_id;
        supplier_warehouse._supplier_id = parseInt('<?= $supplier->getId(); ?>');
        supplier_warehouse.access = $('#access_' + warehouse_id).is(':checked');
        supplier_warehouse.VAT = $('#VAT_' + warehouse_id).val().trim();
        supplier_warehouse.EORI = $('#EORI_' + warehouse_id).val().trim();
        supplier_warehouse.sage_id = $('#sage_id_' + warehouse_id).val().trim();
        supplier_warehouse.shipping_api_key = $('#shipping_api_key_' + warehouse_id).val().trim();
        supplier_warehouse.shipping_api_key_clear = $('#shipping_api_key_clear_' + warehouse_id).is(':checked');
        supplier_warehouse.shipping_account_number = $('#shipping_account_number_' + warehouse_id).val().trim();
        supplier_warehouse.return_label = $('#return_label_' + warehouse_id).is(':checked');
        supplier_warehouse.return_carrier_id = $('#return_carrier_' + warehouse_id).val();
        supplier_warehouse.packing_slip_type = $('#packing_slip_type_' + warehouse_id).val();
        supplier_warehouse.reg_number = $('#reg_number_' + warehouse_id).val();
        supplier_warehouse.is_virtual = +$('#is_virtual_' + warehouse_id).val();
        supplier_warehouse.automatical_assignment = $('#automatical_assignment_' + warehouse_id).is(':checked');
        if (supplier_warehouse.packing_slip_type === RETAIN_ME)
            supplier_warehouse.retainme_credentials = {
                orders_login: $('#retainme_credentials_orders_login_' + warehouse_id).val().trim(),
                orders_pwd: $('#retainme_credentials_orders_pwd_' + warehouse_id).val().trim(),
                print_login: $('#retainme_credentials_print_login_' + warehouse_id).val().trim(),
                print_pwd: $('#retainme_credentials_print_pwd_' + warehouse_id).val().trim(),
            };
        supplier_warehouse.address = {
            address1: $('#address1_' + warehouse_id).val().trim(),
            address2: $('#address2_' + warehouse_id).val().trim(),
            address3: $('#address3_' + warehouse_id).val().trim(),
            town: $('#town_' + warehouse_id).val().trim(),
            county: $('#county_' + warehouse_id).val().trim(),
            postcode: $('#postcode_' + warehouse_id).val().trim(),
            country_id: $('#country_id_' + warehouse_id).val()
        };
        supplier_warehouse.billing_address = {
            name: $('#billing_name_' + warehouse_id).val().trim(),
            contact: $('#billing_contact_' + warehouse_id).val().trim(),
            email: $('#billing_email_' + warehouse_id).val().trim(),
            address1: $('#billing_address1_' + warehouse_id).val().trim(),
            address2: $('#billing_address2_' + warehouse_id).val().trim(),
            address3: $('#billing_address3_' + warehouse_id).val().trim(),
            town: $('#billing_town_' + warehouse_id).val().trim(),
            county: $('#billing_county_' + warehouse_id).val().trim(),
            postcode: $('#billing_postcode_' + warehouse_id).val().trim(),
            country_id: $('#billing_country_id_' + warehouse_id).val()
        };
        if (supplier_warehouse.is_virtual)
            supplier_warehouse.virtual_credentials = {
                user_name: $('#virtual_credentials_user_name_' + warehouse_id).val().trim(),
                pass: $('#virtual_credentials_pass_' + warehouse_id).val().trim(),
            }
        return supplier_warehouse;
    }

    function toggleRetainMeFields(warehouse_id, is_retain_me) {
        if (warehouse_id)
            if (is_retain_me) {
                $('#retain-me_' + warehouse_id).show();
            } else {
                $('#retain-me_' + warehouse_id).hide();
            }
    }

    function isUpdate(supplierWarehouse) {
        let updated = false;
        const exist_supplierWarehouse = warehouses_list[supplierWarehouse.warehouse_id];
        if (!exist_supplierWarehouse)
            updated = true;
        if (exist_supplierWarehouse.access !== supplierWarehouse.access
            || exist_supplierWarehouse.VAT !== supplierWarehouse.VAT
            || exist_supplierWarehouse.EORI !== supplierWarehouse.EORI
            || exist_supplierWarehouse.sage_id !== supplierWarehouse.sage_id
            || exist_supplierWarehouse.shipping_account_number !== supplierWarehouse.shipping_account_number
            || supplierWarehouse.shipping_api_key !== ''
            || supplierWarehouse.shipping_api_key_clear
            || exist_supplierWarehouse.return_label !== supplierWarehouse.return_label
            || exist_supplierWarehouse.return_carrier_id !== supplierWarehouse.return_carrier_id
            || exist_supplierWarehouse.packing_slip_type !== supplierWarehouse.packing_slip_type
            || exist_supplierWarehouse.reg_number !== supplierWarehouse.reg_number
            )
            updated = true;
        if (supplierWarehouse.packing_slip_type === RETAIN_ME) {
            if (!exist_supplierWarehouse.retainme_credentials)
                return true;
            if (exist_supplierWarehouse.retainme_credentials.orders_login !== supplierWarehouse.retainme_credentials.orders_login
                || supplierWarehouse.retainme_credentials.orders_pwd !== ''
                || exist_supplierWarehouse.retainme_credentials.print_login !== supplierWarehouse.retainme_credentials.print_login
                || supplierWarehouse.retainme_credentials.print_pwd !== ''
            )
                updated = true;
        }
        if (
                exist_supplierWarehouse.address.address1 !== supplierWarehouse.address.address1
                || exist_supplierWarehouse.address.address2 !== supplierWarehouse.address.address2
                || exist_supplierWarehouse.address.address3 !== supplierWarehouse.address.address3
                || exist_supplierWarehouse.address.town !== supplierWarehouse.address.town
                || exist_supplierWarehouse.address.county !== supplierWarehouse.address.county
                || exist_supplierWarehouse.address.postcode !== supplierWarehouse.address.postcode
                || exist_supplierWarehouse.address.country_id !== supplierWarehouse.address.country_id
            )
            updated = true;
        if (supplierWarehouse.is_virtual) {
            if (exist_supplierWarehouse.virtual_credentials.user_name !== supplierWarehouse.virtual_credentials.user_name
                || supplierWarehouse.virtual_credentials.pass !== ''
            )
                updated = true;
        }
        return updated;
    }

    $(document).ready(function() {
        $('.packing_slip_type-select').each(function() {
            const is_retain_me = $(this).val() === RETAIN_ME;
            const warehouse_id = getWarehouseId(this);
            toggleRetainMeFields(warehouse_id, is_retain_me);
        })

        $('.toggle_warehouse').each(function() {
            if (!warehouses_list[getWarehouseId(this)].access)
                $(this).trigger('click');
        })

        Object.keys(warehouses_list).forEach(showSecrets);
    })

    $('.packing_slip_type-select').change(function ({target}) {
        const is_retain_me = $(target).val() === RETAIN_ME;
        const warehouse_id = getWarehouseId(target);
        toggleRetainMeFields(warehouse_id, is_retain_me);
    });

    let timeOut = null;

    const renderResponse = (messages, success = true) => {
        if (messages && messages.length > 0)
            return messages.reduce((temp, m) => {
                temp += `<div class="alert alert-${success ? 'success' : 'danger'}">${m}</div>`
                return temp;
            },'')
        return '';
    }

    $('.update_warehouse').click(function ({target}) {
        $('.responses').html(null);
        clearTimeout(timeOut);
        const warehouse_id = getWarehouseId(target);
        const responseBlock = $('#response_' + warehouse_id);
        const clearResponseBlock = () => responseBlock.html(null);
        if (warehouse_id) {
            let supplierWarehouse = getSupplierWarehouse(warehouse_id);
            if (!isUpdate(supplierWarehouse)) {
                responseBlock.html('<div class="alert alert-primary">Not changes</div>');
                timeOut = setTimeout(clearResponseBlock, 5000);
                return;
            }
            const btn = this;
            btn.disabled = true;
            responseBlock.html('<div class="alert alert-primary">Loading...</div>');
            $.ajax({
                url: "/suppliers/update_warehouses",
                method: "POST",
                data: supplierWarehouse,
                dataType: "json",
                success: function (response) {
                    responseBlock.html(null);
                    if (response.result) {
                        const old_access = warehouses_list[warehouse_id].access;
                        warehouses_list[warehouse_id] = response.result;
                        if (response.result.address)
                            warehouses_list[warehouse_id].address = JSON.parse(response.result.address)
                        if (response.result.access === null)
                            warehouses_list[warehouse_id].access = old_access;
                        showSecrets(warehouse_id);
                        responseBlock.html(renderResponse(response.errors, false) + renderResponse(response.messages))
                    } else {
                        responseBlock.html(renderResponse(response.errors, false))
                    }
                    $('#access_' + warehouse_id).prop('checked', warehouses_list[warehouse_id].access);
                },
                error: function () {
                    responseBlock.html('<div class="alert alert-danger">Error</div>');
                },
                complete: function() {
                    timeOut = setTimeout(clearResponseBlock, 5000);
                    btn.disabled = false;
                }
            });
        }
    });

    let toggle = {};
    $('.toggle_warehouse').click(function() {
        const warehouse_id = getWarehouseId(this);
        const control = $(this).parents('.warehouses').find('.warehouse_control')
        control.toggle('fast');
        toggle[warehouse_id] = !toggle[warehouse_id];
        const classDirection = name => `fas fa-fw fa-chevron-${name} ml-2`;
        $(this).find('i').attr('class', toggle[warehouse_id] ? classDirection('right') : classDirection('down'));
    })
</script>
