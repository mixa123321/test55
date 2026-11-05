class Edit_user {
    static init() {
        let $services = $("#services");

        $('select.js_carrier_filter').change(function(){
            Edit_user.carrier_change(this, '.tab-pane#services tbody tr')
        });
    }

    static carrier_change(carrier_select_oblect, service_row_selector) {
        //event.preventDefault();
        if(carrier_select_oblect.value === '') {
            $(service_row_selector).css('display','table-row');
        } else {
            let carrier_id = carrier_select_oblect.value;
            $(service_row_selector).css('display', 'none');
            $(service_row_selector +'[data-carrier_id="' + carrier_id + '"]').css('display', 'table-row');
        }
    }
}
