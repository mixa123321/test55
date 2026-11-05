<?php
/**
 * @var $authUser User
 * @var $user_jobs array
 * @var $current_page int
 * @var $total int
 * @var $total_pages int
 * @var $types array
 * @var $statuses array
 * @var $in_date string
 * @var $current_filters string,
 */

use app\backend\models\UserJobs;
use app\core\entities\User;
use app\core\entities\UserJob;
use app\core\helpers\Html;
use app\core\helpers\View;
use app\core\helpers\modal\ConfirmModal;

$adminPage = empty($adminPage) ? false : $adminPage;

?>

<style>
    .modal-dialog-fix,
    .modal-content-fix {
        height: calc(100% - 30px);;
    }

    .modal-body-fix {
        max-height: calc(100% - 130px);
        overflow-y: scroll;
    }

    #btn_up {
        padding: 8px 10px;
        opacity: .4;
        border-radius: 5px;
    }
</style>

<div class="container-fluid p-sm-4 p-2">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">User jobs</h1>
    </div>
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <div class="row w-100">
                <div class="col">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-server"></i> Active list (<span id="total_count"><?= $total ?></span> job<?= $total > 1 ? 's' : '' ?>)
                    </h6>
                </div>
                <div class="col-auto">
                    <?php if ($authUser->isRoleSA() && !$adminPage) { ?>
                        <a href="/user_jobs/admin"
                           class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1">
                                    <span class="icon text-white-50">
                                        <i class="fas fa-fw fa-clipboard-list"></i>
                                    </span>
                            <span class="text text-white flex-fill">manage jobs</span>
                        </a>
                    <?php } ?>
                </div>
                <?php if (!empty($user_jobs) && !$adminPage) { ?>
                    <form action="/user_jobs/clear" name="clear_queue" method="POST">
                        <input type="hidden" name="done" value="0">
                        <div class="col-auto"><button onclick="event.preventDefault(); $('[name=done]').val(1); clear_queue.submit();"
                                                      class="btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1">
                                <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-clipboard-list"></i>
                                </span>
                                <span class="text text-white flex-fill">clear done jobs</span>
                            </button></div>
                        <div class="col-auto"><button onclick="event.preventDefault(); $('[name=done]').val(0); clear_queue.submit();"
                                                      class="btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                            >
                                <span class="icon text-white-50">
                                    <i class="fas fa-fw fa-clipboard-list"></i>
                                </span>
                                <span class="text text-white flex-fill">clear queue</span>
                            </button></div>
                    </form>
                <?php } ?>
            </div>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="form-row mb-3 pb-0">
                    <div class="form-group col-3 mb-0">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="number" id="id"
                                   name="id"
                                   value="<?= View::toHtml(!empty($_GET['id']) ?  $_GET['id'] : '') ?>"
                                   min="0"
                                   placeholder=" "
                            />
                            <label for="id">id</label>
                        </div>
                    </div>
                    <div class="form-group col-3 mb-0">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'type',
                            ];
                            echo Html::dropDownList('type', $types,
                                !empty($_GET['type']) ? $_GET['type'] : null, $options);
                            ?>
                            <label for="type">Type</label>
                        </div>
                    </div>
                    <?php if ($adminPage && !empty($users)) { ?>
                        <div class="form-group col-3 mb-0">
                            <div class="form-group-element">
                                <?php
                                $options = [
                                    'class' => 'form-control form-control-lg custom-select',
                                    'id' => 'user_id',
                                ];
                                echo Html::dropDownList('user_id', $users,
                                    !empty($_GET['user_id']) ? $_GET['user_id'] : null, $options);
                                ?>
                                <label for="type">user</label>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="form-group col-3 mb-0">
                        <div class="form-group-element">
                            <?php
                            $options = [
                                'class' => 'form-control form-control-lg custom-select',
                                'id' => 'status',
                            ];
                            echo Html::dropDownList('status', $statuses,
                                !empty($_GET['status']) ? $_GET['status'] : null, $options);
                            ?>
                            <label for="type">Status</label>
                        </div>
                    </div>
                </div>
                <div class="form-row mb-3 pb-0">
                    <div class="form-group col-3 mb-0">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="create_date"
                                   name="create_date"
                                   max="<?= View::toHtml($in_date) ?>"
                                   value="<?= View::toHtml(!empty($_GET['create_date']) ?  $_GET['create_date'] : '') ?>"/>
                            <label for="create_date">Create date</label>
                        </div>
                    </div>
                    <div class="form-group col-3 mb-0">
                        <div class="form-group-element">
                            <input class="form-control form-control-lg" type="date" id="update_date"
                                   name="update_date"
                                   max="<?= View::toHtml($in_date) ?>"
                                   value="<?= View::toHtml(!empty($_GET['update_date']) ?  $_GET['update_date'] : '') ?>"/>
                            <label for="create_date">Update date</label>
                        </div>
                    </div>
                </div>
                <div class="text-right mb-3">
                    <a href="/user_jobs<?= $adminPage ? '/admin' : '' ?>" class="btn btn-lg btn-outline-orange mr-1">Clear</a>
                    <button type="submit" class="btn btn-lg btn-orange ml-1">Filter</button>
                </div>
            </form>
            <?php if (!empty($user_jobs)) { ?>
                <?php View::pagination('/user_jobs/' . ($adminPage ? 'admin' : '') . '?' . $current_filters . '&', 1, $total_pages, $current_page, UserJobs::LIMIT_PER_PAGE); ?>
                <div class="row justify-content-center mt-2 mb-2 p-0">
                    <div class="col-md-12">
                        <table class="table table-hover mb-0" id="user_jobs">
                            <colgroup>
                                <col width="5%">
                                <?php if ($adminPage) { ?>
                                    <col width="*">
                                <?php } ?>
                                <col width="10%">
                                <col width="21%">
                                <col width="17%">
                                <col width="17%">
                                <col width="18%">
                                <col width="8%">
                                <col width="*">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Id</th>
                                <?php if ($adminPage) { ?>
                                    <th>User</th>
                                <?php } ?>
                                <th>Type</th>
                                <th>Addition</th>
                                <th>Create date</th>
                                <th>Update date</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($user_jobs as $user_job) { ?>
                                <tr id="user_queue-<?= $user_job['id'] ?>">
                                    <td><?= $user_job['id'] ?></td>
                                    <?php if ($adminPage) { ?>
                                        <td><?= View::toHtml($user_job['user']) ?></td>
                                    <?php } ?>
                                    <td><span class="badge badge-light"><?= View::toHtml($user_job['type']) ?></span></td>
                                    <td><?= $user_job['addition'] ?></td>
                                    <td><?= View::toHtml(date('jS M Y H:i', strtotime($user_job['create_date']))) ?></td>
                                    <td class="col-update-date"><?= View::toHtml(date('jS M Y H:i',
                                            strtotime($user_job['update_date']))) ?></td>
                                    <td>
                                        <div class="row">
                                            <div class="col-8">
                                                <div class="form-group">
                                                    <input type="range"
                                                           class="form-control-range mt-1 col-progress-range"
                                                           disabled
                                                           min="0" max="100"
                                                           value="0"
                                                           style="background: red"
                                                    >
                                                </div>
                                            </div>
                                            <div class="col-4 pl-0">
                                                <span class="col-progress-number"></span>%
                                            </div>
                                        </div>

                                    </td>
                                    <td>
                                        <span class="col-status"></span>
                                    </td>
                                    <td class="col-actions">
                                        <div class="action-download"></div>
                                        <div class="action-notifications"></div>
                                        <div class="action-abort"></div>
                                        <div class="action-delete"></div>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php
                View::pagination('/user_jobs/' . ($adminPage ? 'admin' : '') . '?' . $current_filters . '&', 1, $total_pages, $current_page, UserJobs::LIMIT_PER_PAGE);
            } else
                echo Html::warning('No user jobs found');
            ?>
        </div>
    </div>
</div>

<?php
$modal_modal_remove = new ConfirmModal('modal_remove', 'Remove');
echo $modal_modal_remove->addContent('Are you sure you want to delete user job <span id="remove_user_job"></span>')
    ->addForm('/user_jobs/delete', ConfirmModal::METHOD_POST)
    ->changeYes(ConfirmModal::TYPE_SUBMIT, ConfirmModal::BUTTON_DONE)
    ->addCustomButtons('<input type="hidden" name="job_id" id="remove_user_job_id">' . $modal_modal_remove->getButtons())
    ->put();
?>

<div class="modal" tabindex="-1" role="dialog" id="showNotifications">
    <div class="modal-dialog modal-dialog-fix modal-lg" role="document">
        <div class="modal-content modal-content-fix">
            <div class="modal-header">
                <h5 class="modal-title">Job notifications</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body modal-body-fix pb-0">
                <div class="row mb-3 status_tabs">
                    <div class="col">
                        <button onclick="showQueueNotifications(user_job_id)"
                                class="btn btn-outline-primary w-100">
                            all
                            <span id="notes-all" class="badge badge-light"></span>
                        </button>
                    </div>
                    <div class="col">
                        <button onclick="showQueueNotifications(user_job_id, 'success')"
                                class="btn btn-outline-success w-100">
                            success
                            <span id="notes-success" class="badge badge-light"></span>
                        </button>
                    </div>
                    <div class="col">
                        <button onclick="showQueueNotifications(user_job_id, 'warning')"
                                class="btn btn-outline-warning w-100">
                            warning
                            <span id="notes-warning" class="badge badge-light"></span>
                        </button>
                    </div>
                    <div class="col">
                        <button onclick="showQueueNotifications(user_job_id, 'danger')"
                                class="btn btn-outline-danger w-100">
                            danger
                            <span id="notes-danger" class="badge badge-light"></span>
                        </button>
                    </div>
                </div>
                <div id="note_response"></div>
                <table class="table table-hover" id="modalNotifications">
                    <thead>
                    <tr>
                        <th>Create Date</th>
                        <th>Message</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal-footer px-1 justify-content-between">
                <div class="col">
                    <button id="btn_up"
                            onclick="$('.modal-body').show().scrollTop(0);"
                            class="btn btn-secondary btn-icon-split d-none">
                        <i class="fas fa-angle-up"></i>
                    </button>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-icon-split" data-dismiss="modal" type="button">
                        <span class="text">Close</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $('.modal-body').scroll(function () {
        const cl_list = document.getElementById('btn_up').classList;
        if ($(this).scrollTop() < 100) {
            if (!cl_list.contains('d-none'))
                cl_list.add('d-none')
        } else if (cl_list.contains('d-none'))
            cl_list.remove('d-none')
    })

    let user_jobs = <?= View::toJs($user_jobs) ?>;

    let user_job_ids = new Set();

    if (user_jobs.length > 0) {
        user_jobs.forEach(uj => user_job_ids.add(+uj.id))
    }

    const user_jobs_table = document.getElementById('user_jobs');
    if (user_jobs_table)
        user_jobs_table.addEventListener('click', e => {
            let target = e.target;
            if (e.target.tagName === 'I' || e.target.tagName === 'SPAN')
                target = e.target.closest('button');
            if (!target)
                return;
            const job_id = +target.closest('tr').id.replace('user_queue-', '');
            if (job_id > 0)
                switch ($(target).data('action')) {
                    case 'abort':
                        return abortQueue(job_id);
                    case 'delete':
                        return deleteQueue(job_id);
                    case 'notifications':
                        return showQueueNotifications(job_id);
                }
        });

    function sendAjax(url, data, scs) {
        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: data,
            success: res => scs ? scs(res) : location.reload()
        })
    }

    function abortQueue(job_id) {
        sendAjax('/user_jobs/abort', {job_id})
    }

    function deleteQueue(job_id) {
        document.getElementById('remove_user_job').textContent = job_id;
        document.getElementById('remove_user_job_id').value = job_id;
        <?= $modal_modal_remove->show(); ?>
    }

    let user_job_id = null;

    function toggleActiveBtn(type) {
        for (let t of ['all', 'success', 'warning', 'danger']) {
            let span = document.getElementById('notes-' + t);
            if (!span)
                continue;
            let btn = span.closest('button');
            if (btn) {
                const list = btn.classList;
                const check = t === type;
                if (t === 'all')
                    t = 'secondary'
                if (check) {
                    if (list.contains('btn-outline-' + t))
                        list.remove('btn-outline-' + t)
                    if (!list.contains('btn-' + t))
                        list.add('btn-' + t)
                } else {
                    if (list.contains('btn-' + t))
                        list.remove('btn-' + t)
                    if (!list.contains('btn-outline-' + t))
                        list.add('btn-outline-' + t)
                }
            }
        }
    }

    const note_response = document.getElementById('note_response');

    function showQueueNotifications(job_id = user_job_id, type) {
        toggleActiveBtn(type ? type : 'all');
        note_response.innerHTML = '';
        user_job_id = job_id;
        const handleNotes = (res, to_count = !type) => {
            const tbody = document.querySelector('#modalNotifications tbody');
            tbody.innerHTML = '';
            let counter = {
                all: 0,
                success: 0,
                warning: 0,
                danger: 0
            }
            if (res.notes !== undefined && res.notes.length > 0) {
                res.notes.forEach(note => {
                    tbody.insertAdjacentHTML('beforeend', `<tr>
                                                                <td>${html_escape(note.create_date)}</td>
                                                                <td><span class="text-${html_escape(note.type)}">${html_escape(note.msg)}</span></td>
                                                            </tr>`);
                    if (to_count)
                        !counter[note.type] ? counter[note.type] = 1 : counter[note.type]++;
                });
                counter.all = res.notes.length;
            } else note_response.innerHTML = '<p class="alert alert-info">Notifications not found</p>';
            if (to_count)
                Object.entries(counter).forEach(c => {
                    const span = document.getElementById('notes-' + c[0]);
                    if (span) {
                        span.innerText = c[1];
                        span.closest('button').disabled = !c[1];
                    }

                })
        }
        sendAjax('/user_jobs/get_notifications', {job_id, type}, handleNotes);
        $('#showNotifications').modal('show')
    }

    document.addEventListener('DOMContentLoaded', () => {
        const enumJob = (job) => {
            const user_job_id = +job[0];
            const data = job[1]
            const col_update_date = user_jobs_table.querySelector(`tr#user_queue-${user_job_id} .col-update-date`);
            if (col_update_date && col_update_date.innerText !== data.update_date)
                col_update_date.innerText = data.update_date;
            const col_process_range = user_jobs_table.querySelector(`tr#user_queue-${user_job_id} .col-progress-range`);
            const col_process_number = user_jobs_table.querySelector(`tr#user_queue-${user_job_id} .col-progress-number`);
            if (col_process_range && col_process_number && (+col_process_number.innerText !== +data.progress || data.progress === 0)) {
                col_process_range.value = +data.progress;
                col_process_number.innerText = data.progress;
            }
            const col_status = user_jobs_table.querySelector(`tr#user_queue-${user_job_id} .col-status`);
            if (col_status && col_status.innerText !== data.status) {
                col_status.innerText = data.status
                col_status.className = `badge badge-${data.style} col-status`
            }
            const col_actions = user_jobs_table.querySelector(`tr#user_queue-${user_job_id} .col-actions`);
            if (col_actions) {
                const a_download = col_actions.querySelector('.action-download');
                if (+data.download > 0 && $.trim(a_download.innerHTML).length < 1) {
                    a_download.insertAdjacentHTML('afterbegin', actionTemplates.download(data.download));
                }
                const a_notifications = col_actions.querySelector('.action-notifications');
                if (+data.notifications > 0 && $.trim(a_notifications.innerHTML).length < 1)
                    a_notifications.insertAdjacentHTML('afterbegin', actionTemplates.notifications());
                const a_abort = col_actions.querySelector('.action-abort');
                const a_delete = col_actions.querySelector('.action-delete');
                if (data.status === '<?= UserJob::STATUS_PROGRESS ?>') {
                    a_delete.innerHTML = ''
                    if ($.trim(a_abort.innerHTML).length < 1)
                        a_abort.insertAdjacentHTML('afterbegin', actionTemplates.abort());
                } else {
                    a_abort.innerHTML = ''
                    if ($.trim(a_delete.innerHTML).length < 1)
                        a_delete.insertAdjacentHTML('afterbegin', actionTemplates.delete());
                }
            }
        }
        let admin = <?= $adminPage ? 1 : 0 ?>;
        const handleActiveRows = active_ids => {
            const ids = new Set(active_ids.map(i => +i));
            if (ids.size === 0 && !admin)
                document.location.reload();
            const rows = user_jobs_table.querySelectorAll('tbody tr');
            if (rows.length > 0)
                rows.forEach(row => {
                    const row_id = +row.id.replace('user_queue-','');
                    if (!ids.has(row_id) && !row.classList.contains('table-danger'))
                        row.classList.add('table-danger')
                })
        }
        const updatePage = (init = false) => sendAjax('/user_jobs/get_async_data',
            {
                init,
                admin,
                user_job_ids: Array.from(user_job_ids)
            },
            res => {
            if (res.result !== 'success')
                return;
            if (Object.keys(res.user_jobs).length > 0)
                Object.entries(res.user_jobs).forEach(enumJob);
            if (!init && res.active_ids !== undefined)
                handleActiveRows(res.active_ids);
        })
        if (user_jobs.length > 0) {
            updatePage(true)
            setInterval(updatePage, 30000)
        }
    })

    const actionTemplates = {
        download: (file_job_id) => `<a class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                            href="/user_jobs/download?file_job_id=${parseInt(file_job_id, 10)}"
                                        >
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-clipboard-list"></i>
                                            </span>
                                            <span class="text text-white flex-fill">Download</span>
                                        </a>`,
        notifications: () => `<button class="btn btn-primary btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                                data-action="notifications">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-clipboard-list"></i>
                                            </span>
                                            <span class="text text-white flex-fill">notifications</span>
                                        </button>`,
        abort: () => `<button class="btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                                data-action="abort">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-stop"></i>
                                            </span>
                                            <span class="text text-white flex-fill">abort</span>
                                        </button>`,
        delete: () => `<button class="btn btn-danger btn-block btn-icon-split justify-content-start text-nowrap mt-0 mb-1"
                                                data-action="delete">
                                            <span class="icon text-white-50">
                                                <i class="fas fa-fw fa-trash-alt"></i>
                                            </span>
                                            <span class="text text-white flex-fill">delete</span>
                                        </button>`
    }
</script>