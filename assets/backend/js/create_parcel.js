class Create_parcel {
    static init() {
        let item_count = 0;
        let item_tpl = $('#item_template').html();
        let create_parcel_form = $('#create_parcel_form');

        $('#result_accordion').hide();

        $('#add_item_btn').click(function () {
            item_count++;
            Create_parcel.add_item(item_count, item_tpl);
        });
        $('#create_parcel_btn').click(function () {
            Create_parcel.add_parcel(create_parcel_form.serialize(), $(this));
        });
        $('#copy_request_btn').click(function () {
            Create_parcel.copy_text('request_div');
        });
        $('#copy_response_btn').click(function () {
            Create_parcel.copy_text('response_div');
        });
        $('#courier_pickup_date').datetimepicker({
            'format': 'DD-MM-YYYY',
            'minDate': moment().startOf('d'),
            'maxDate': moment().add(6, 'days').endOf('d'),
        });
        $('#courier_pickup_time_from').datetimepicker({
            'format': 'HH:mm',
        });
        $('#courier_pickup_time_to').datetimepicker({
            'format': 'HH:mm',
        });
        $('#service').change(function () {
            Create_parcel.service_change($(this).find('option:selected').data('type'));
        }).trigger('change');
    }

    static add_item(item_count, item_tpl) {
        let item_html = item_tpl.replace(/%i%/g, item_count);
        $('#items').append(item_html);
        $('.js-remove-item-btn').click(function () {
            $(this).closest('.js-item-wrapper').remove();
        });
    }

    static add_parcel(form_data, $create_btn) {
        let response_div = $('#response_div');
        let request_div = $('#request_div');
        let result_div = $('#result_div');
        let result_h3 = $('#create_parcel_result');
        $('#result_accordion').show();
        request_div.html("");
        response_div.html("");
        result_div.html("");
        $create_btn.prop("disabled", true);
        result_h3.html("Sending API request. Please wait up to 30 seconds...").css("color", "black");
        Create_parcel.begin_progress_bar();
        $.ajax({
            url: "/parcels/createNew",
            type: "POST",
            data: form_data
        }).done(function (data) {
            try {
                data = JSON.parse(data);
                if(!("response" in data) || !("body" in data["response"])) {
                    throw "error";
                }
                request_div.html("<pre>" + JSON.stringify(data["request"]["body"], null, 2) + "</pre>");
                response_div.html("<pre>" + JSON.stringify(data["response"]["body"], null, 2) + "</pre>");

                if ("errors" in data["response"]["body"]) {
                    result_h3.html("Request failed").css("color", "red");
                    result_div.append("<h4>Error list:</h4>");
                    result_div.append("<ul>");
                    for(let error_key in data["response"]["body"]["errors"]) {
                        let error_val = data["response"]["body"]["errors"][error_key];
                        if("message" in error_val) {
                            let err_msg = error_val["message"]
                                .replace(/\_|\./g, " ")
                                .replace(/addr(\d)/gi, "address line $1")
                                .replace(/^./, str => str.toUpperCase());
                            result_div.append("<li>" + err_msg + "</li>");
                        }
                    }
                    result_div.append("</ul>");
                } else if ("label" in data["response"]["body"] && "tracking_number" in data["response"]["body"]) {
                    result_h3.html("Parcel created").css("color", "green");
                    result_div.append("<p>Tracking number: <b>"
                        + data["response"]["body"]["tracking_number"] + "</b></p>");
                    let is_test = $('#mode').val();
                    result_div.append(
                        "<a href=\"/parcels/download_label?tracking_number=" + data["response"]["body"]["tracking_number"]
                        +"&is_test=" + is_test + "&disposition=inline\" target=\"_blank\"" +
                        "class=\"btn btn-primary\">" +
                        "<span class=\"glyphicon glyphicon-download\"></span> View shipping label" +
                        "</a>");
                }
            } catch(e) {
                result_h3.html("Internal system error").css("color", "red");
                response_div.html("<pre>Internal system error</pre>");
            }
        }).fail(function () {
            result_h3.html("Internal system error").css("color", "red");
        }).always(function () {
            $create_btn.prop("disabled", false);
            Create_parcel.stop_progress_bar();
            window.scrollBy(0, 400);
        });

    }

    static service_change(service_type) {
        switch (service_type) {
            case 'pudo':
                $('#courier_pickup_schedule_div').hide().find('.form-control').prop('disabled', true);
                $('#pudo_id_div').show().find('.form-control').prop('disabled', false);
                break;
            case 'courier':
                $('#courier_pickup_schedule_div').show().find('.form-control').prop('disabled', false);
                $('#pudo_id_div').hide().find('.form-control').prop('disabled', true);
                break;
        }
    }

    static copy_text(containerid) {
        if (document.selection) {
            document.selection.empty();
            let range = document.body.createTextRange();
            range.moveToElementText(document.getElementById(containerid));
            range.select().createTextRange();
            document.execCommand("copy");
        } else if (window.getSelection) {
            window.getSelection().removeAllRanges();
            let range = document.createRange();
            range.selectNode(document.getElementById(containerid));
            window.getSelection().addRange(range);
            document.execCommand("copy");
        } else {
            alert("Cannot copy this text. Please copy it manually");
        }
    }

    //TODO create a separate progress bar builder
    static begin_progress_bar() {
        let $progress_bar_current = $('#progress_bar_current');
        $progress_bar_current.width(0);
        let $progress_bar = $('#progress_bar');
        $progress_bar.show();
        let max_width = $progress_bar.width();
        Create_parcel.progress_timer = setInterval(function () {
            $progress_bar_current.width(function (i, cur_width) {
                return cur_width + max_width / 100;
            });
            if($progress_bar_current.width() >= max_width) {
                clearInterval(Create_parcel.progress_timer);
            }
        }, 300);
    }

    static stop_progress_bar() {
        clearInterval(Create_parcel.progress_timer);
        $('#progress_bar_current').width('100%');
        $('#progress_bar').fadeOut(2000);
    }
}
