$(function () {
    var worker_template = $('#worker_template').html();
    var section;
    var workers;

    function setTemplate(worker_tmp, workers_binding, workers)
    {
        var tmp = worker_tmp;
        tmp = tmp.replace(/\{\{name\}\}/g, () => html_escape(workers_binding[key]['login'] + ' (' + workers_binding[key]['name'] + ')'));
        tmp = tmp.replace(/\{\{id\}\}/g, () => html_escape(workers_binding[key]['id']));
        tmp = tmp.replace(/\{\{checked\}\}/g, (typeof workers !== 'undefined' && typeof workers[workers_binding[key]['id']] !== 'undefined') ? ' checked' : '');
        return tmp;
    }

    function setData(type_id, e)
    {
        $('#title').text('Assignment ' + (type_id === 'W' ? 'Order Bin' : (type_id === 'T' ? 'Pack Station' : 'Large Pack')));
        $('#type_id').text(type_id === 'W' ? 'Pick&Drop' : 'Pack');
        section = $('#' + e.currentTarget.id);
        $('#section_id').val(section.data('id'));
        $('#section_num').val(section.data('num'));
        $('#section_type').val(type_id);
        workers = section.data(type_id.toLowerCase());
        $('#workers').empty();
    }
    //
    // $('.js-workers').click((e) => {
    //     setData('W', e);
    //     for(key in workers_OB)
    //         $('#workers').append(setTemplate(worker_template, workers_OB, workers));
    //     $('#editWorkers').modal('show');
    //     return false;
    // });

    $('.js-tables').click((e) => {
        setData('T', e);
        for(key in workers_TP)
            $('#workers').append(setTemplate(worker_template, workers_TP, workers));
        $('#editWorkers').modal('show');
        return false;
    });

    $('.js-larges').click((e) => {
        setData('L', e);
        for(key in workers_TP)
            $('#workers').append(setTemplate(worker_template, workers_TP, workers));
        $('#editWorkers').modal('show');
        return false;
    });

    function checkOld(new_arr, old_arr)
    {
        var checked = true;
        $.each(new_arr, function (key, val) {
            var id = val.id.substring(7).slice(0, -1)*1;
            if (!(val.checked && (typeof old_arr !== 'undefined' && typeof old_arr[id] !== 'undefined')||
                !val.checked && (typeof old_arr === 'undefined' || typeof old_arr[id] === 'undefined'))) {
                checked = false;
                return;
            }
        });
        return checked;
    }

    $('.js-btn-conf').click(function() {
        var checked_worker = $('#workers').children('div.row').children('div.col-auto').children('input.form-check-input');
        if (checkOld(checked_worker, workers) === true)
            return false;
        $('#editWorkers').modal('hide');
    });
});