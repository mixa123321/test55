$(function () {

    document.getElementById('create_form').addEventListener('submit', (e) => {
        e.preventDefault();
        location.href = `/bundles/bundles_add?supplier_id=${+document.getElementById('create_supplier_id').value}`;
    });

    document.getElementById('table-bundles').addEventListener('click', ({target}) => {
        if (target.tagName === 'SPAN' || target.tagName === 'I')
            target = target.closest('button')
        if (target === null)
            return;
        const bundle_id_f = () => +target.closest('tr').getAttribute('id').replace('bundle-', '');
        if (target.classList.contains('js-orders-btn')) {
            const loader = document.getElementById('ob-loading');
            const ordersBundleTable = document.getElementById('ordersBundleTable');
            ordersBundleTable.style.display = 'none';
            const ordersBundleTBODY = ordersBundleTable.querySelector('tbody');
            ordersBundleTBODY.innerHTML = '';

            loader.style.display = '';
            loader.className = 'alert-info';
            loader.innerText = 'Loading...';

            const set_stat = (in_progress = 0, despatched = 0, cancelled = 0) => {
                document.getElementById('orders_in_progress').innerText = in_progress.toString();
                document.getElementById('orders_despatched').innerText = despatched.toString();
                document.getElementById('orders_canceled').innerText = cancelled.toString();
            }
            set_stat();
            $.ajax({
                url: "/bundles/get_bundle_orders",
                method: "POST",
                dataType: 'json',
                data: {
                    id: bundle_id_f()
                },
                success: function (response) {
                    loader.style.display = 'none';
                    if (response.stat !== undefined) {
                        set_stat(
                            response.stat.in_progress || 0,
                            response.stat.despatched || 0,
                            response.stat.cancelled || 0
                        )
                    }
                    if (response.orders !== undefined && response.orders.length > 0) {
                        ordersBundleTable.style.display = '';
                        const rows = response.orders.reduce((temp, order) => {
                            temp += `<tr>
                                        <td>${html_escape(order.ref)} (#${html_escape(order.id)})<br>${html_escape(order.ref2)}</td>
                                        <td>${html_escape(order.create_date)}</td>
                                        <td>${html_escape(order.type_create)}</td>
                                        <td>${html_escape(order.quantity)}</td>
                                        <td><span class="badge badge-${html_escape(order.status_color)}">${html_escape(order.status)}</span></td>
                                    </tr>`;
                            return temp;
                        }, '')

                        ordersBundleTable.insertAdjacentHTML('afterbegin', rows)
                    }
                },
                error: function (json) {
                    set_stat();
                    loader.className = 'alert-danger';
                    loader.innerText = 'Server error...';
                    ordersBundleTable.style.display = 'none';
                }
            });
            $('#ordersBundleModalInfo').modal('show');
        }

        if (target.classList.contains('js-delete-btn')) {
            $('#deleteBundleModalConfirm').modal('show');
            $('#bundle_id').val(bundle_id_f());
        }
    });
});