$(function () {
    $(document).ready(function() {
        if (date_from.value === date_to.value) {
            if (new Date(date_from.value + ' ' + time_from.value) > new Date(date_to.value + ' ' + time_to.value))
                time_to.value = '';
            else
                time_to.min = time_from.value;
        }

        if ($('#date_type').val() === 'despatch_date')
            $('.time_group').attr('disabled', 'disabled');
    });

    $('#date_from, #time_from').on('change', function () {
        var dateTo, dateFrom = new Date(date_from.value + ' ' + time_from.value);
        date_to.min = date_from.value;
        if (date_to.value.trim() !== '') {
            dateTo = new Date(date_to.value + ' ' + time_to.value);
            if (dateFrom > dateTo) {
                date_to.value = '';
                time_to.value = '';
            }
        }
        if (date_to.value === date_from.value) {
            time_to.min = time_from.value
        }
    });

    $('#date_to').on('change', function () {
        if (date_to.value === date_from.value)
            time_to.min = time_from.value;
        if (date_to.value > date_from.value)
            time_to.min = '';
    });

    $('#global_search').change(() => {
        $('#search_focus').val('global_search');
    });

    $('#id').change(() => {
        $('#search_focus').val('id');
    });

    $('#ref').change(() => {
        $('#search_focus').val('ref');
    });

    $('#date_from').on('change', () => {
        $('#search_focus').val('');
    });

    $('#date_to').on('change', () => {
        $('#search_focus').val('');
    });

    $('#date_type').change(function () {
        const $time_group = $('.time_group');
        if ($(this).val() === 'despatch_date') {
            $time_group.val('');
            $time_group.attr('disabled', 'disabled');
        } else
            $time_group.removeAttr('disabled');

        $('#search_focus').val('');
    });

    $('#status').change(() => {
        $('#search_focus').val('');
    });

    $('#supplier_id').change(() => {
        $('#search_focus').val('');
    });

    $('#type_create').change(() => {
        $('#search_focus').val('');
    });

    $('#country_id').change(() => {
        $('#search_focus').val('');
    });

    $('#order_ids').keyup(() => {
        $('#bulk_confirm').prop('disabled', $('#order_ids').val().trim() === '');
    });
});
