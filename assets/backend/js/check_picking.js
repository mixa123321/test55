$('form').submit(function(e) {
    const form = this;
    e.preventDefault();
    let data = {};
    $(this).serializeArray().forEach(item => data[item.name] = item.value);
    $.ajax({
        url: '/pick/check_pick',
        method: 'POST',
        dataType: 'json',
        data,
        success: function (response) {
            if (response.result === undefined)
                location.reload();
            if (response.result === 'error') {
                showError('err_msg', response.message, 3000);
                $('#search').val('');
                return;
            }
            if (response.result === 'success')
                form.submit();
        },
        error: () => location.reload()
    });
});