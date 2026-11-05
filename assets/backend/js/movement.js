$(function () {
    $('#date_created_from').change(function () {
        var dateCreatedTo, dateCreatedFrom = new Date(date_created_from.value);
        date_created_to.min = date_created_from.value;
        if (date_created_to.value.trim() != '') {
            dateCreatedTo = new Date(date_created_to.value);
            if (dateCreatedFrom > dateCreatedTo)
                date_created_to.value = '';
        }
    });
    $('#date_despatch_from').change(function () {
        var dateDespatchTo, dateDespatchFrom = new Date(date_despatch_from.value);
        date_despatch_to.min = date_despatch_from.value;
        if (date_despatch_to.value.trim() != '') {
            dateDespatchTo = new Date(date_despatch_to.value);
            if (dateDespatchFrom > dateDespatchTo)
                date_despatch_to.value = '';
        }
    });
    $('.js-btn-delete').click(function () {
        $('#movement_id').val($(this).data('id'));
        $('#deleteMovementModalConfirm').modal('show');
    });

    $('.movement_type').change(function () {
        var id = $(this).data('id');
        var type = $(this).val();

        $.ajax({
            url: "/movement/change_type",
            method: "POST",
            data: {
                id: id,
                type: type
            },
            success: function (json) {
                
            },
            error: function (json) {

            }
        });
    });
});