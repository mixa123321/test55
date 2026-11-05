<div class="modal" tabindex="-1" role="dialog" id="selectSKUBatchModal">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="titleModal"></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <select id="SKUBatchId" class="form-control form-control-lg custom-select">
                    </select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-dismiss="modal" id="cancelSKUBatch" type="button">Cancel</button>
                    <button class="btn btn-danger" id="selectSKUBatch" disabled type="button">Select</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $('.js-btn-scan').click(function () {
        var cont = true;
        var search = $('#search').val().trim();
        if (!$('.js-btn-scan').data('empty'))
            if (search == '') {
                $('#search').focus();
                return false;
            }
        var reason = $('.js-btn-scan').data('reason');
        if (typeof reason == 'undefined')
            reason = '';
        var need_loc = $('.js-btn-scan').data('need_loc');
        if (typeof need_loc == 'undefined')
            need_loc = false;
        $.ajax({
            url: "/SKUs/get_SKU_batch_by_barcode",
            method: "POST",
            data: {
                barcode: search,
                locations: JSON.stringify($('.js-btn-scan').data('locations')),
                suppliers: JSON.stringify($('.js-btn-scan').data('suppliers')),
                batches: JSON.stringify($('.js-btn-scan').data('batches')),
                type: $('.js-btn-scan').data('type'),
                reason: reason,
            },
            dataType: "json",
            success :function (json) {
                if (json.length > 1) {
                    $('#titleModal').text('SKU ' + search);
                    $('#SKUBatchId').children().remove().end();
                    $('#SKUBatchId').append($('<option>', { value : 0 }).text('Select company...'));
                    json.forEach(function (item) {
                        $('#SKUBatchId').append($('<option>', { value : item.id + (need_loc ? ('_' + item.location_id) : '') }).text(item.name + (need_loc ? (': ' + item.loc_name) : '')));
                    });
                    $('#selectSKUBatchModal').modal('show');
                    cont = false;
                } else if (json.length == 1 && json != 'error') {
                    var SKUBatch = json[0].id.split('_');
                    $('#SKU_id').val(SKUBatch[0]);
                    $('#batch_id').val(SKUBatch[1] == '.' ? '' : SKUBatch[1]);
                    if (need_loc)
                        $('#location_id').val(json[0].location_id);
                }
            },
            async: false
        });
        return cont;
    });

    $('#SKUBatchId').change(function () {
        $('#selectSKUBatch').prop('disabled', $('#SKUBatchId').val() == 0);
    });

    $('#cancelSKUBatch').click(function () {
        $('#selectSKUBatchModal').modal('hide');
        $('#search').val('');
        $('#search').focus();
    });

    $('#selectSKUBatch').click(function () {
        var SKUBatch = $('#SKUBatchId').val().split('_');
        $('#SKU_id').val(SKUBatch[0]);
        $('#batch_id').val(SKUBatch[1] == '.' ? '' : SKUBatch[1]);
        var need_loc = $('.js-btn-scan').data('need_loc');
        if (typeof need_loc != 'undefined' && need_loc)
            $('#location_id').val(SKUBatch[2]);
        $('#selectSKUBatchModal').modal('hide');
        $('#' + $('.js-btn-scan').data('send')).submit();
    });
</script>
