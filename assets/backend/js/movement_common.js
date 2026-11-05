function showItem(item)
{
    if (item.length > 0 && item.prop('classList').contains('d-none'))
        item.prop('classList').remove('d-none');
}

function hideItem(item)
{
    if (item.length > 0 && !item.prop('classList').contains('d-none'))
        item.prop('classList').add('d-none');
}

function addButton(location, loc_id, loc_name, loc_icon, loc_for_id, loc_for_name)
{
    let sel_loc_content = $('#sel_location').html();
    sel_loc_content = sel_loc_content.replace(/\{\{sel_loc_id\}\}/g, loc_id);
    sel_loc_content = sel_loc_content.replace(/\{\{sel_loc_name\}\}/g, loc_name);
    sel_loc_content = sel_loc_content.replace(/\{\{sel_loc_icon\}\}/g, loc_icon);
    sel_loc_content = sel_loc_content.replace(/\{\{sel_loc_for_id\}\}/g, loc_for_id);
    sel_loc_content = sel_loc_content.replace(/\{\{sel_loc_for_name\}\}/g, loc_for_name);
    $('#' + location).append(sel_loc_content);
}

function addSelect(available, id, name, items, locs)
{
    let avail_content = $('#sel_available').html();
    avail_content = avail_content.replace(/\{\{available_id\}\}/g, id);
    avail_content = avail_content.replace(/\{\{available_name\}\}/g, name);
    avail_content = avail_content.replace(/\{\{available_items\}\}/g, items);
    $('#' + available).append(avail_content);
    $.each(locs, (key, value) => {
        let $option = $('<option>').val(value[0]).text(key + ' (' + value[1] + ')');
        $('#' + items).append($option);
    });
}

function addSelectProcessing(available, id, name, items, locs)
{
    let avail_content = $('#sel_available').html();
    avail_content = avail_content.replace(/\{\{available_id\}\}/g, id);
    avail_content = avail_content.replace(/\{\{available_name\}\}/g, name);
    avail_content = avail_content.replace(/\{\{available_items\}\}/g, items);
    $('#' + available).append(avail_content);
    $.each(locs, function(key, value) {
        let name_user = value.type_id !== undefined && value.type_id === 'US' ? value.user_name : value.name;
        let $option = $('<option>').val(value.id).text(name_user);
        $('#' + items).append($option);
    });
}

function toggleLocs(sels, items)
{
    let item;
    let nums = 0;
    for (let i = 0; i < items.length; i++) {
        item = $('#' + items[i]);
        if (!item.length)
            continue;
        if (sels.includes(i)) {
            nums++;
            showItem(item);
        } else
            hideItem(item);
    }
    return nums;
}

function isObject(object)
{
    return typeof object === 'object'
}

function getInt(object)
{
    return parseInt(/.+ \((\d+)\)$/.exec(object.text())[1]);
}

function changeSelected(locs, loc)
{
    if (!locs.includes(loc))
        return;
    for (var i = 0; i < locs.length; i++)
        if (loc != locs[i])
            $('#' + locs[i]).children('option:selected').prop('selected', false);
}
