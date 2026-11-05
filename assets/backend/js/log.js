$(function () {
    $('#date_from').change(function () {
        var dateTo, dateFrom = new Date(date_from.value);
        date_to.min = date_from.value;
        if (date_to.value.trim() != '') {
            dateTo = new Date(date_to.value);
            if (dateFrom > dateTo)
                date_to.value = '';
        }
    });

    var $modal = $('#detailsModal');
    var $modal_body = $modal.find('.modal-body');
    $('.js-details-btn').click(function () {
        $modal.modal('show');
        $modal_body.html('<h1>Loading...</h1>');

        $.ajax({
            url: "/log/details",
            method: "GET",
            data: {id: $(this).data('record-id')},
            dataType: "json",
            success: function (json) {
                $modal_body.empty().append($('<pre>').text(JSON.stringify(json, null, 2)));
            },
            error: function (json) {
                $modal_body.html('<h1>Error. Please try again</h1>');
            }
        });
    });
});