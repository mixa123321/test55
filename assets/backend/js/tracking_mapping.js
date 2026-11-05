class Tracking_mapping {
    static init() {
        $(".js-status").on("change", function () {
            $(this).next(".input-group-btn").find(".btn").removeClass("disabled");
            $(this).closest(".input-group").next(".alert").addClass("hidden");
        });

        $(".js-update").on("click", function () {
            if ($(this).hasClass("disabled") === false) {
                let parent = $(this).closest("td");

                $.ajax({
                    url: "/tracking_mapping/save",
                    method: "POST",
                    data: {
                        id: parent.find(".js-id").val(),
                        status: parent.find(".js-status").val()
                    },
                    dataType: "json",
                    success: function (json) {
                        let clazz = "alert-danger";

                        if (json.error === false) {
                            parent.find(".btn").addClass("disabled");
                            clazz = "alert-success";
                        }

                        parent
                            .find(".alert")
                            .removeClass("alert-success alert-danger")
                            .addClass(clazz)
                            .text(json.msg)
                            .removeClass("hidden")
                        ;
                        parent.closest("tr").removeClass("bg-warning");
                    },
                    error: function () {
                        parent
                            .find(".alert")
                            .removeClass("alert-success alert-danger")
                            .addClass("alert-danger")
                            .text("Server error")
                            .removeClass("hidden")
                        ;
                    }
                });
            }
        });
    }
}