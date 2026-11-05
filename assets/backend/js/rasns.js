$(function () {
    $('.js-btn-cancel').click(function () {
        $('#cancelRASNModal').modal('show');
        var rasn_id = $(this).data('rasn_id');
        $('#rasn_cancel_id').val(rasn_id);
    });

    $('#date_created_from').change(function () {
        var datecreatedTo, datecreatedFrom = new Date(date_created_from.value);
        date_created_to.min = date_created_from.value;
        if (date_created_to.value.trim() != '') {
            datecreatedTo = new Date(date_created_to.value);
            if (datecreatedFrom > datecreatedTo)
                date_created_to.value = '';
        }
    });
});