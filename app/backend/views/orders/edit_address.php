<?php

use app\core\entities\User;

/**
 * @var User $authUser
 * @var string $country_id
 */
?>
<div class="modal" tabindex="-1" role="dialog" id="editAddress">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit address</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group row">
                    <label for="ea_country_id" class="col-md-4 col-form-label">Country <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <select class="selectpicker form-control" id="ea_country_id"></select>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_postcode" class="col-md-4 col-form-label">Postcode <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <input required id="ea_postcode" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_name" class="col-md-4 col-form-label">Company Name</label>
                    <div class="col-md-8">
                        <input id="ea_name" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_contact" class="col-md-4 col-form-label">Contact Name <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <input required id="ea_contact" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_phone" class="col-md-4 col-form-label">Contact Tel <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <input required id="ea_phone" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_email" class="col-md-4 col-form-label">Contact Email <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <input required id="ea_email" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_address1" class="col-md-4 col-form-label">Address 1 <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <input required id="ea_address1" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_address2" class="col-md-4 col-form-label">Address 2</label>
                    <div class="col-md-8">
                        <input id="ea_address2" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_address3" class="col-md-4 col-form-label">Address 3</label>
                    <div class="col-md-8">
                        <input id="ea_address3" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_town" class="col-md-4 col-form-label">Town <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <input required id="ea_town" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_county" class="col-md-4 col-form-label"><?= $country_id == 'US' ? 'State' : 'County'; ?> <span class="text-danger">*</span></label>
                    <div class="col-md-8">
                        <input id="ea_county" type="text" class="form-control"/>
                    </div>
                </div>
                <?php if ($authUser->hasManagerAccess()) { ?>
                <div class="form-group row">
                    <label for="ea_eori_number" class="col-md-4 col-form-label">EORI Number</label>
                    <div class="col-md-8">
                        <input id="ea_eori_number" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_vat_number" class="col-md-4 col-form-label">VAT Number</label>
                    <div class="col-md-8">
                        <input id="ea_vat_number" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_ioss_number" class="col-md-4 col-form-label">IOSS Number</label>
                    <div class="col-md-8">
                        <input id="ea_ioss_number" type="text" class="form-control"/>
                    </div>
                </div>
                <div class="form-group row">
                    <label for="ea_tax_id" class="col-md-4 col-form-label">TAX ID</label>
                    <div class="col-md-8">
                        <input id="ea_tax_id" type="text" class="form-control"/>
                    </div>
                </div>
                <?php } ?>
                <div id='eaError' class='col bg-danger text-white p-1 rounded text-center mt-1 mb-0 d-none'>
                    Information invalid
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal" type="button">Cancel</button>
                <button class="btn btn-success" id="updateEditAddress" type="button">Update</button>
            </div>
        </div>
    </div>
</div>

<script>
    $('.js-btn-edit').click(function () {
        var countries = $('.js-btn-edit').data('countries');
        var delivery = $('.js-btn-edit').data('delivery');
        $('#ea_country_id').children().remove().end();
        for (var item in countries)
            if (countries.hasOwnProperty(item))
                $('#ea_country_id').append($('<option>', { value : item }).text(countries[item]));
        $('#ea_country_id option[value="' + delivery.country_id + '"]').prop('selected', true);
        $('#ea_postcode').val(delivery.postcode);
        $('#ea_name').val(delivery.name);
        $('#ea_contact').val(delivery.contact);
        $('#ea_phone').val(delivery.phone);
        $('#ea_email').val(delivery.email);
        $('#ea_address1').val(delivery.address1);
        $('#ea_address2').val(delivery.address2);
        $('#ea_address3').val(delivery.address3);
        $('#ea_town').val(delivery.town);
        $('#ea_county').val(delivery.county);
        $('#ea_eori_number').val(delivery.eori_number);
        $('#ea_vat_number').val(delivery.vat_number);
        $('#ea_ioss_number').val(delivery.ioss_number);
        $('#ea_tax_id').val(delivery.tax_id);
        $('#editAddress').find('input, select').not('#ea_eori_number, #ea_vat_number, #ea_ioss_number, #ea_tax_id')
            .prop('disabled', !$('.js-btn-edit').data('can_edit_address'));
        $('#editAddress').modal('show');
    });

    $('#updateEditAddress').click(function () {
        var delivery = $('.js-btn-edit').data('delivery');
        $.ajax({
            url: "/orders/save_delivery",
            method: "POST",
            data: {
                id: delivery.id,
                name: $('#ea_name').val(),
                contact: $('#ea_contact').val(),
                phone: $('#ea_phone').val(),
                email: $('#ea_email').val(),
                address1: $('#ea_address1').val(),
                address2: $('#ea_address2').val(),
                address3: $('#ea_address3').val(),
                town: $('#ea_town').val(),
                county: $('#ea_county').val(),
                postcode: $('#ea_postcode').val(),
                country_id: $('#ea_country_id').val(),
                eori_number: $('#ea_eori_number').val(),
                vat_number: $('#ea_vat_number').val(),
                ioss_number: $('#ea_ioss_number').val(),
                tax_id: $('#ea_tax_id').val(),
                order_id: $('.js-btn-edit').data('order_id')
            },
            dataType: "json",
            success :function (json) {
                if (json == 'error') {
                    $('#eaError').prop('classList').remove('d-none');
                    setTimeout(() => {
                        $('#eaError').prop('classList').add('d-none');
                    }, 3000);
                } else if (json == 'none') {
                } else {
                    location = '/orders/view_order/?id=' + $('.js-btn-edit').data('order_id');
                }
            },
            async: false
        });
    });
</script>
