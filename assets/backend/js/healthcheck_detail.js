class HealthcheckDetail {
    static init()
    {
        $("#date-from").datetimepicker({
            "format": "YYYY-MM-DD HH:mm:ss",
        });
        $("#date-to").datetimepicker({
            "format": "YYYY-MM-DD HH:mm:ss",
        });

        $(".js-detail").on("click", function () {
            let id = $(this).data("id");
            $.ajax({
                url: "/Healthcheck/getData",
                method: "GET",
                data: {id: id},
                dataType: "json",
                success: function (json) {
                    let request = json.request;
                    let response = json.response;
                    let html = "";

                    for (let i in request) {
                        let number = parseInt(i) + 1;
                        html += HealthcheckDetail.getOneBlock(request[i], "Request " + number);
                        html += HealthcheckDetail.getOneBlock(response[i], "Response " + number);
                    }

                    $('#healthcheck_details').find(".modal-body").html(html);
                },
            });
        });
    }

    static getOneBlock(data, title)
    {
        let body = "";
        for (let key in data) {
            if (data.hasOwnProperty(key)) {
                let content = data[key];
                if (typeof content !== 'object') {
                    try {
                        content = JSON.parse(content);
                    } catch (e) {
                    }
                }
                if (typeof content === 'object') {
                    content = JSON.stringify(content, null, 4);
                }

                body += "<div class='row'>" +
                    "<div class='col-sm-2'>" + key + "</div>" +
                    "<div class='col-sm-10'><pre>" + html_escape(content) + "</pre></div>" +
                    "</div>";
            }
        }

        let tpl = $("#msg-tpl").html();
        tpl = tpl.replace("{title}", title);
        tpl = tpl.replace("{body}", body);

        return tpl;
    }
}