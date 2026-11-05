function showErrorMessage(id, id_message, msg, timeout)
{
    $('#' + id).prop('classList').remove('d-none');
    $('#' + id_message).html(msg);
    setTimeout(() => {
        $('#' + id).prop('classList').add('d-none');
    }, timeout);
}