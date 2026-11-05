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
    $('.js-group-by-btn').click(function () {
        $(this).find('input').prop("checked", true);
        $(this).closest('form').submit();
    });

    if(fig_count) {
        google.charts.load('current', {packages: ['corechart', 'bar']});
        google.charts.setOnLoadCallback(drawBarChart);
    }

    function drawBarChart() {
        var data = google.visualization.arrayToDataTable(global_chart_data);

        var options = {
            height: global_chart_data.length * 50,
            legend: { position: 'none' },
            chartArea:{left:'10%',top:0,width:'70%',height:'95%'},
            hAxis: {
                minValue: 0
            },
            bar: {
                'groupWidth': '60%'
            },
            colors: ['#1a183c']
        };
        var chart = new google.visualization.BarChart(document.getElementById('chart-container'));
        chart.draw(data, options);
    }
});