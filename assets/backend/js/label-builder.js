class LabelBuilder {
    static init() {
        $("#carrier").on("change", function () {
            let labelName = $("#labelName");
            let carrier = $(this).val();
            labelName.html('');

            if (carrier) {
                let type = $("#carrierType").val();
                let labels = {};
                if (type === "return") {
                    labels = globalReturnLabelName;
                } else if (type === "direct") {
                    labels = globalDirectLabelName;
                }

                $.each(labels[carrier], function (i, val) {
                    let option = $('<option value="' + i + '"' + (i === globalCurrentLabelName ? ' selected' : '') + '>'
                        + val +
                        '</option>');
                    labelName.append(option);
                });
            }
        });

        $("#carrierType").on("change", function () {
            let carrier = $("#carrier");
            let type = $(this).val();
            carrier.html('');

            let items = {};
            if (type === "return") {
                items = globalReturnCarriers;
            } else if (type === "direct") {
                items = globalDirectCarriers;
            }

            $.each(items, function (i, val) {
                let option = $('<option value="' + i + '"' + (i === globalCurrentCarrier ? ' selected' : '') + '>' +
                    val +
                    '</option>');
                carrier.append(option);
            });

            carrier.trigger("change");
        }).trigger("change");

        $("#labelType").on("change", function () {
            if ($(this).val() === "zpl") {
                $("#labelDpi").attr("disabled", false);
            } else {
                $("#labelDpi").attr("disabled", true);
            }
        }).trigger("change");
    }
}