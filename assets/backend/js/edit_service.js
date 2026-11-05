class Edit_service {
    static init() {
        $('table.credentials-table').gridFilter({
            filters: [
                {
                    input: '#credentials_key',
                    attribute: 'key'
                },
                {
                    input: '#user_filter',
                    attribute: 'user'
                }
            ],
        });

        $("#barcode_ranges").on("click", ".range-delete", function (e) {
            e.preventDefault();

            if (confirm("Delete range?")) {
                let btn = $(this);
                let id = $(this).data("id");

                $.ajax({
                    url: "/barcode_range/delete",
                    method: "POST",
                    data: {
                        id: id
                    },
                    dataType: "html",
                    success: function (result) {
                        if (result === "Success") {
                            btn.closest("tr").remove();
                        } else {
                            alert("Internal server error");
                        }
                    }
                });
            }
        });

        $(".range-add").on("click", function (e) {
            e.preventDefault();

            let tr = $(this).closest("tr");
            let data = {
                carrier_id: $("#carrier_id").val(),
                barcode_group: tr.find('input[name="barcode_group"]').val(),
                user_id: tr.find('select[name="client"]').val(),
                range_begin: tr.find('input[name="start"]').val(),
                range_end: tr.find('input[name="end"]').val(),
            };

            $.ajax({
                url: "/barcode_range/add",
                method: "POST",
                data: data,
                success: function (result) {
                    if ($.isNumeric(result)) {
                        let start = tr.find('input[name="start"]').val();
                        let end = tr.find('input[name="end"]').val();
                        let key = tr.find('input[name="barcode_group"]').val();
                        if (key.length === 0) {
                            key = "default";
                        }

                        let tpl = $("#range-tpl").html();

                        tpl = tpl.replace("{client}", tr.find('select[name="client"] option:selected').text());
                        tpl = tpl.replace("{key}", key);
                        tpl = tpl.replace("{start}", start);
                        tpl = tpl.replace("{end}", end);
                        tpl = tpl.replace("{left}", end - start + 1);
                        tpl = tpl.replace("{id}", result);

                        tr.before(tpl);
                    } else {
                        alert("Internal server error");
                    }
                }
            })
        });

        $("#carrier_id,#kind").on("change", function () {
            let additionalTypes = $("#type_additional");
            let carrier_id = $("#carrier_id").val();
            let carrierKind = $("#kind").val();
            additionalTypes.html('');

            if (globalCarriersList[carrier_id] === undefined) {
                return;
            }

            let carrier_name = globalCarriersList[carrier_id];

            let items = {};
            if (carrierKind === "return") {
                if (globalCarrierReturnsAdditionalTypes[carrier_name] === undefined) {
                    return;
                }
                items = globalCarrierReturnsAdditionalTypes[carrier_name];
            } else {
                if (globalCarrierDirectAdditionalTypes[carrier_name] === undefined) {
                    return;
                }
                items = globalCarrierDirectAdditionalTypes[carrier_name];
            }

            $.each(items, function (key, value) {
                let option = $(
                    '<option value="' + key + '"' + (key === globalCurrentAdditionalType ? ' selected' : '') + '>' +
                    value +
                    '</option>'
                );
                additionalTypes.append(option);
            });
        }).trigger("change");
    }
}