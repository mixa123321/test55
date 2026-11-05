function addClick(hash, package_id)
{
    $.ajax({
        url: "/tracking/add_action",
        method: "POST",
        dataType: "json",
        data: {
            hash: hash,
            package_id: package_id
        },
        success: function (json) {  },
        error: function (json) {  }
    })
}
