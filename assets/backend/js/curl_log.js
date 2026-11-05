$(function () {
    $('#date_from').change(function () {
        var dateTo, dateFrom = new Date(date_from.value);
        date_to.min = date_from.value;
        if (date_to.value.trim() != '') {
            dateTo = new Date(date_to.value);
            if (dateFrom > dateTo)
                date_to.value = '';
        }
    });

    let $modal = $('#detailsModal');
    const $modal_blocks = {
        info: $modal.find('#modal-info'),
        request: $modal.find('#modal-request'),
        response: $modal.find('#modal-response')
    };

    $('.js-details-btn').click(function () {
        $modal.modal('show');
        $modal_blocks.info.html('<h1>Loading...</h1>');
        $modal_blocks.request.html('');
        $modal_blocks.response.html('');

        $.ajax({
            url: "/log/curl_details",
            method: "GET",
            data: {id: $(this).data('record-id')},
            dataType: "json",
            success: function (response) {
                try {
                    const res = escapeHtml(response)
                    $modal_blocks.info.html(renderInfo(res));
                    $modal_blocks.request.html(renderRequest(res));
                    $modal_blocks.response.html(renderResponse(res));
                } catch (e) {
                    $modal_blocks.info.html(e.toString())
                    $modal_blocks.request.html('');
                    $modal_blocks.response.html('');
                }
            },
            error: function (json) {
                $modal_blocks.info.html('<h1>Error. Please try again</h1>');
            }
        });
    });

    function renderInfo({url, created_at, http_code}) {
        return `<div>
            <p><span style="font-weight: bold;">URL</span> ${url}</p>
            <p><span style="font-weight: bold;">created</span> ${created_at}</p>
            <p><span style="font-weight: bold;">HTTP Code</span> ${http_code}</p>
        </div>`
        }

    function renderRequest({get_params, request}) {
        return `<div>
                    <p><span style="font-weight: bold;">GET:</span> ${get_params || ''}</p>
                    <p><span style="font-weight: bold;">Request Body:</span><span style="size: 0.8em;"> ${request || ''}</span></p>
                </div>`
    }

    function renderResponse({response_headers, response}) {
        return `<div>
                    <p><span style="font-weight: bold;">Response Headers</span> ${response_headers || ''}</p>
                    <p><span style="font-weight: bold;">Response</span><span style="size: 0.8em;"> ${response || ''}</span></p> 
                </div>`
    }

    function escapeHtml(obj) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        let new_data = {}
        Object.entries(obj).forEach(item => {
            try {
                new_data[item[0]] = item[1].toString().replace(/[&<>"']/g, m => map[m])
            } catch (e) {
                new_data[item[0]] = ''
            }
        })
        return new_data;
    }
});