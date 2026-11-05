/**
 *  Need bootstrap-datetimepicker.min and jquery plugins
 *
 *  !!! You can use function generate_datepicker_view($name) or create html by yourself !!!
 *
 *  You must create html with unique id(for container, date-view-from, date-view-to, selector custom, menu)
 *  Create containers for bootstrap-datetimepicker.min (from and to)
 *  For example look file datepicker_view.php
 *
 *  In js
 *  $.fn.returnCalendar();
 *  this function init calendar with default settings
 *  you can rewrite default settings
 *  For example
 *  $.fn.returnCalendar({
 *      'date_selector_from': '#date_from',
 *      'default_date_from': default_date_from,
 *      'max_date_from': today,
 *      'date_selector_to': '#date_to',
 *      'default_date_to': default_date_to,
 *      'max_date_to': today
 *  });
 *
 *  All settings:
 *  {
 *      'date_selector_from': '#date_from',              - unique selector for container
 *                                                         bootstrap-datetimepicker.min from
 *      'default_date_from': date,                       - default date for date from
 *      'max_date_from': date,                           - max date for date from
 *      'date_view_selector_from': '#js-date-view-from', - unique selector for container view date from
 *      'date_selector_to': '#date_to',                  - unique selector for container
 *                                                         bootstrap-datetimepicker.min to
 *      'default_date_to': date,                         - default date for date to
 *      'max_date_to': date,                             - max date for date to
 *
 *      'date_from_format': 'DD-MM-YYYY',                - format for date from
 *      'date_to_format': 'DD-MM-YYYY',                  - format for date to
 *
 *      'date_view_selector_to': '#js-date-view-to',     - unique selector for container view date to
 *      'custom_selector': '#js-custom',                 - unique selector for container custom selector
 *      'toggle_button_selector': '#js-change-date',     - unique selector for button open menu
 *      'toggle_menu_selector': '#js-change-date-menu'   - unique selector for container menu with calendars
 *  }
 *
 */
//$(function () {
$.fn.returnCalendar = function (options) {
    var date = new Date();
    date.setHours(23);
    date.setMinutes(59);
    date.setSeconds(59);
var settings = $.extend({
'default_date_from': date,
'max_date_from': date,
'default_date_to': date,
'max_date_to': date,
'date_from_format': 'DD-MM-YYYY',//'DD-MM-YYYY',
'date_to_format': 'DD-MM-YYYY', //'DD-MM-YYYY',
'date_from_inline': false,
'date_to_inline': false,
}, options);
var ids = {
'date_selector_from': '#date' + (settings.name ? '_' + settings.name : '') + '_from',
'date_selector_to': '#date' + (settings.name ? '_' + settings.name : '') + '_to',
'date_view_selector_from': '#js-date-view-from' + (settings.name ? '-' + settings.name : ''),
'date_view_selector_to': '#js-date-view-to' + (settings.name ? '-' + settings.name : ''),
'custom_selector': '#js-custom' + (settings.name ? '-' + settings.name : ''),
'toggle_button_selector': '#js-change-date' + (settings.name ? '-' + settings.name : ''),
'toggle_menu_selector': '#js-change-date-menu' + (settings.name ? '-' + settings.name : ''),
'apply_menu': '#js-apply-menu' + (settings.name ? '-' + settings.name : ''),
'cancel_menu': '#js-cancel-menu' + (settings.name ? '-' + settings.name : '')
};

var formatDate = function(format, date) {
format = format.replace(/(DD|MM|YYYY|HH|mm|ss)/g, function(match){
switch(match){
    case 'DD':
    return date.getDate().toString().padStart(2, '0');
    case 'MM':
    return (date.getMonth() + 1).toString().padStart(2, '0');
    case 'YYYY':
    return date.getFullYear().toString();
    case 'HH':
    return date.getHours().toString().padStart(2, '0');
    case 'mm':
    return date.getMinutes().toString().padStart(2, '0');
    case 'ss':
    return date.getSeconds().toString().padStart(2, '0');
}
});

return format;
};

date = get_default_date(ids.date_selector_from.substr(1), settings.date_from_format);

if(date && date != 'Invalid Date'){
    settings.default_date_from = date;
}
date = get_default_date(ids.date_selector_to.substr(1), settings.date_to_format);
if(date && date != 'Invalid Date'){
    settings.default_date_to = date;
}
var old_date = {
'from': settings.default_date_from,
'to': settings.default_date_to
};

if(old_date.from !== null && old_date.from.getDate !== undefined) {
    $(ids.date_selector_from).find('input').val(formatDate(settings.date_from_format, old_date.from));
}
if(old_date.to.getDate !== undefined) {
    $(ids.date_selector_to).find('input').val(formatDate(settings.date_to_format, old_date.to));
}

$('html').click(function() {
    $('.hide-dropdown').slideUp(300);
});
$(ids.toggle_button_selector).on('click', function (e) {
    e.stopPropagation();
    var open = $(ids.toggle_menu_selector).css('display') == 'none';
    $('.hide-dropdown').slideUp(300);
if(open) {
    $(ids.toggle_menu_selector).slideDown(300);
}
});
$(ids.toggle_menu_selector).on('click', function (e) {
    e.stopPropagation();
});

$(ids.apply_menu).on('click', function () {
    old_date.from = getOldDate(ids.date_selector_from);
    old_date.to = getOldDate(ids.date_selector_to);

updateView(ids.date_selector_from, ids.date_view_selector_from);
updateView(ids.date_selector_to, ids.date_view_selector_to);

    $(ids.toggle_menu_selector).slideUp(300);
});
$(ids.cancel_menu).on('click', function () {
setOldDate(old_date.from, ids.date_selector_from);
setOldDate(old_date.to, ids.date_selector_to);

updateView(ids.date_selector_from, ids.date_view_selector_from);
updateView(ids.date_selector_to, ids.date_view_selector_to);

resetCustomSelect(ids.custom_selector);

    $(ids.toggle_menu_selector).slideUp(300);
});

$(ids.custom_selector).on('change', function () {
    var val = $(this).val();

if(val.length > 0) {
    var to = new Date(),
    from = new Date();

switch (val){
    case '0':
    to.setDate(from.getDate());
    break;
    case '1':
    var d = from.getDate() - 1;
    from.setDate(d);
    to.setDate(d);
    break;
    case '6':
    to.setDate(to.getDate() - 1);
    from.setDate(from.getDate() - 7);
    break;
    case '7':
    to.setDate(to.getDate() + (6 - to.getDay()) - 7);
    from.setDate(from.getDate() - from.getDay() - 7);
    break;
    case '30':
    from.setDate(1);
    from.setMonth(from.getMonth() - 1);
    to.setDate(0);
    break;
}

from.setHours(0);
from.setMinutes(0);
from.setSeconds(0);

to.setHours(23);
to.setMinutes(59);
to.setSeconds(59);

$(ids.date_selector_from).find('input').val(formatDate(settings.date_from_format, from));
$(ids.date_selector_to).find('input').val(formatDate(settings.date_to_format, to));

updateView(ids.date_selector_from, ids.date_view_selector_from);
updateView(ids.date_selector_to, ids.date_view_selector_to);
}
});

$(ids.date_selector_from).datetimepicker({
    format: settings.date_from_format,
    maxDate: settings.max_date_from,
    defaultDate: settings.default_date_from,
}).on('change', function (e) {
updateView(ids.date_selector_from, ids.date_view_selector_from);
resetCustomSelect(ids.custom_selector);
//TODO: resolve problem with 'data("DateTimePicker") == undefined'
//$(ids.date_selector_to).data("DateTimePicker").minDate(e.date);
});

$(ids.date_selector_to).datetimepicker({
    format: settings.date_to_format,
    maxDate: settings.max_date_to,
    defaultDate: settings.default_date_to,
}).on('change', function (e) {
updateView(ids.date_selector_to, ids.date_view_selector_to);
resetCustomSelect(ids.custom_selector);
//TODO: resolve problem with 'data("DateTimePicker") == undefined'
//$(ids.date_selector_from).data("DateTimePicker").maxDate(e.date);
});

updateView(ids.date_selector_from, ids.date_view_selector_from);
updateView(ids.date_selector_to, ids.date_view_selector_to);
};

function getValue(selector) {
    var val = selector.find('input').val();
    return val ? val : '...';
}

function getOldDate(selector) {
    var tmp = getValue($(selector));
if(tmp == '...'){
    return new Date();
} else {
      tmp = tmp.split('-');
      return new Date(tmp[2], tmp[1] - 1, tmp[0], 12, 0, 0, 0);
  }
}

function setOldDate(date, selector) {
if(date) {
//TODO: resolve problem with 'data("DateTimePicker") == undefined'
//$(selector).data("DateTimePicker").date(new Date(date));
} else {
      $(selector).find('input').val('');
  }
}

function updateView(selector, selector_view) {
    $(selector_view).html(getValue($(selector)));
}

function resetCustomSelect(selector) {
    $(selector).find('option:first').attr('selected', null).attr('selected', 'selected');
}

function formatNumb(str)
{
if (String(str).length < 2){
    str = '0' + str;
}

return str;
}

var get_params = {};
window.location.search.substr(1).split("&").forEach(function (item) {
    get_params[item.split("=")[0]] = item.split("=")[1];
});

function get_default_date(input_name, format)
{
    var default_date = false;
if (input_name in get_params) {
if(get_params[input_name]) {
//we NOT use moment.js, use only date format dd-mm-yyyy:
//default_date = moment(decodeURIComponent(get_params[input_name]), format).toDate();
    default_date = decodeURIComponent(get_params[input_name]);
    default_date = default_date.split('-');
    default_date = new Date(default_date[2], default_date[1] - 1, default_date[0], 12, 0, 0, 0);
}
}

return default_date;
}
//});
