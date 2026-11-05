$(function () {
    $('.fa-exclamation-circle').tooltip();
    $('.js-swap-connected-sku').click(function () {
        $(this).closest('tr').find('form').submit();
    });
    $('.js-delete-connected-sku').click(function (e) {
        $('#deleteSKUChannelModalConfirm').modal('show');
        $('#SKU_channel_id').val(e.currentTarget.id.split('_')[1]);
        return false;
    });
});
