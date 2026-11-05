class Api_console {
    static init() {
        let $api_url_input = $('#api_url');
        let request_params_count = 0;
        let request_param_tpl = $('#request_param_template').html();
        let send_request_form = $('#send_request_form');
        $('.js_link_api_url').click(function(e){
            e.preventDefault();
            $api_url_input.val($(this).data('url'));
        });
        $('#method').change(function(){
            Api_console.change_request_method($(this).val());
        }).trigger('change');
        $('#add_request_param_btn').click(function () {
            request_params_count++;
            Api_console.add_request_param(request_params_count, request_param_tpl);
        });
        $('#send_api_request_btn').click(function(){
            Api_console.send_api_request(send_request_form.serialize(), $(this));
        });
    }

    static send_api_request(form_data, $send_btn)
    {
        let $response_body_div = $('#response_body_div');
        let $response_code_div = $('#response_code_div');

        $send_btn.prop("disabled", true);
        Api_console.begin_progress_bar();
        $.ajax({
            url: "/api_console/process_request",
            type: "POST",
            data: form_data
        }).done(function (data) {
            try {
                data = JSON.parse(data);
                if(!("response" in data) || !("body" in data["response"])) {
                    throw "error";
                }
                $response_code_div.empty().append($("<pre>").text("HTTP Code: " + JSON.stringify(data["response"]["code"], null, 2)));
                $response_body_div.empty().append($("<pre>").text(JSON.stringify(data["response"]["body"], null, 2)));
            } catch(e) {
                $response_body_div.html("<pre>Internal server error</pre>");
            }
        }).fail(function () {
            $response_body_div.html("<pre>Internal server error</pre>");
        }).always(function () {
            $send_btn.prop("disabled", false);
            Api_console.stop_progress_bar();
        });
    }

    static change_request_method(method)
    {
        let $request_params_div = $('#request_params_div');
        let $json_body_div = $('#json_body_div');
        switch (method) {
            case 'GET':
                $request_params_div.show().find('input').prop('disabled', false);
                $json_body_div.hide().find('textarea').prop('disabled', true);
                break;
            default:
                $request_params_div.hide().find('input').prop('disabled', true);
                $json_body_div.show().find('textarea').prop('disabled', false);
        }
    }

    static add_request_param(param_count, param_tpl) {
        let param_html = param_tpl.replace(/%i%/g, param_count);
        $('#request_params_div').append(param_html);
        $('.js_remove_request_param_btn').click(function () {
            $(this).closest('.js_request_param_wrapper').remove();
        });
    }

    //TODO create a separate progress bar builder
    static begin_progress_bar() {
        let $progress_bar_current = $('#progress_bar_current');
        $progress_bar_current.width(0);
        let $progress_bar = $('#progress_bar');
        $progress_bar.show();
        let max_width = $progress_bar.width();
        Api_console.progress_timer = setInterval(function () {
            $progress_bar_current.width(function (i, cur_width) {
                return cur_width + max_width / 100;
            });
            if($progress_bar_current.width() >= max_width) {
                clearInterval(Api_console.progress_timer);
            }
        }, 300);
    }

    static stop_progress_bar() {
        clearInterval(Api_console.progress_timer);
        $('#progress_bar_current').width('100%');
        $('#progress_bar').fadeOut(1500);
    }
}
