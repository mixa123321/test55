$(function () {
    $('.js-btn-arrive').click(function () {
        $('#arrivalASNModalSwitch').modal('show');
        var asn_id = $(this).data('asn_id');
        var container = $(this).data('asn_container');
        $('#asn_arrive_id').val(asn_id);
        $('.js-select-container').val(container);
    });

    $('.js-btn-cancel').click(function () {
        $('#asn_cancel_id').val($(this).data('asn_id'));
        $('#cancelASNModalConfirm').modal('show');
    });

    $('.js-btn-revert').click(function () {
        $('#asn_revert_id').val($(this).data('asn_id'));
        $('#revertASNModalConfirm').modal('show');
    });

    $('#date_expected_from').change(function () {
        var dateexpectedTo, dateexpectedFrom = new Date(date_expected_from.value);
        date_expected_to.min = date_expected_from.value;
        if (date_expected_to.value.trim() != '') {
            dateexpectedTo = new Date(date_expected_to.value);
            if (dateexpectedFrom > dateexpectedTo)
                date_expected_to.value = '';
        }
    });

    $('#date_arrived_from').change(function () {
        var datearrivedTo, datearrivedFrom = new Date(date_arrived_from.value);
        date_arrived_to.min = date_arrived_from.value;
        if (date_arrived_to.value.trim() != '') {
            datearrivedTo = new Date(date_arrived_to.value);
            if (datearrivedFrom > datearrivedTo)
                date_arrived_to.value = '';
        }
    });
});