<?php

namespace app\backend\controllers;

use app\backend\models\FileJobs;
use app\backend\models\UserJobNotifications;
use app\backend\models\UserJobs as UserJobModel;
use app\core\entities\UserJob;
use app\core\exceptions\RedirectException;
use app\core\helpers\Arr;
use app\core\helpers\FileWrapper;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\statical\WithDate;

class UserJobs extends AuthController
{
    /** @throws */
    public function index()
    {
        if (!UserJobModel::hasUserQueue($this->user->getId())) {
            unset($_SESSION['user_queue']);
            View::addFlash('user_jobs', 'warning', 'User jobs not found');
            throw new RedirectException('/');
        }
        $page = !empty($_GET['c']) ? intval($_GET['c']) : 1;
        $filter_data = $_GET;
        unset($filter_data['c']);
        $res = UserJobModel::getListByUserId($this->warehouse, $this->user->getId(), $page, $filter_data);
        $user_jobs = $res['user_jobs'] ?? [];
        if (!empty($user_jobs && !empty($this->time_diff)))
            array_walk($user_jobs, function(&$job) {
                if (!empty($job['create_date']))
                    $job['create_date'] = $this->handleTimezone($job['create_date']);
                if (!empty($job['update_date']))
                    $job['update_date'] = $this->handleTimezone($job['update_date']);
            });
        $data = [
            'user_jobs' => $user_jobs,
            'current_page' => $page,
            'total' => (int)$res['total'],
            'total_pages' => ceil($res['total'] / UserJobModel::LIMIT_PER_PAGE),
            'types' => ['all' => 'All'] + UserJob::TYPES,
            'statuses' =>  ['all' => 'All'] + UserJob::STATUSES,
            'in_date' => $this->getInDate(),
            'current_filters' => http_build_query($filter_data),
        ];
        $this->render($data, 'warehouse/user_jobs');
    }

    public function admin()
    {
        $page = !empty($_GET['c']) ? intval($_GET['c']) : 1;
        $filter_data = $_GET;
        unset($filter_data['c']);
        $res = UserJobModel::getList($this->warehouse, $page, $filter_data);
        $user_jobs = $res['user_jobs'] ?? [];
        if (!empty($user_jobs) && !empty($this->time_diff))
            array_walk($user_jobs, function(&$job) {
                if (!empty($job['create_date']))
                    $job['create_date'] = $this->handleTimezone($job['create_date']);
                if (!empty($job['update_date']))
                    $job['update_date'] = $this->handleTimezone($job['update_date']);
            });
        $data = [
            'adminPage' => true,
            'user_jobs' => $user_jobs,
            'current_page' => $page,
            'total' => (int)$res['total'],
            'total_pages' => ceil($res['total'] / UserJobModel::LIMIT_PER_PAGE),
            'types' => ['all' => 'All'] + UserJob::TYPES,
            'statuses' => ['all' => 'All'] + UserJob::STATUSES,
            'users' => ['all' => 'All'] + Arr::map(UserJobModel::getUserList($this->logistic_company->getId()), 'id', 'login'),
            'in_date' => $this->getInDate(),
            'current_filters' => http_build_query($filter_data),
        ];
        $this->render($data, 'warehouse/user_jobs');
    }

    /** @throws */
    public function abort()
    {
        if (Request::isAjax()) {
            if ($user_job_id = Request::getPositiveId($_POST, 'job_id')) {
                $user_job = UserJobModel::getById($user_job_id);
                if (empty($user_job)) {
                    View::addFlash('user_jobs', 'danger', 'User job not found');
                } else {
                    $user_job->setStatus(UserJob::STATUS_ABORT);
                    $user_job->setUpdateDate(time());
                    $user_job->save(['id', 'status', 'update_date']);
                    View::addFlash('user_jobs', 'danger', 'User job #'.$user_job->getId().' is aborted!');
                    return $this->response = json_encode(['success']);
                }
            }
            return $this->response = json_encode(['error']);
        }
        throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
    }

    /** @throws */
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_job_id = Request::getPositiveId($_POST, 'job_id');
            if ($user_job_id > 0 && !empty(UserJobModel::getById($user_job_id))) {
                UserJobModel::remove($user_job_id);
                View::addFlash('user_jobs', 'danger', 'User job #'.$user_job_id.' removed!');
            } else
                View::addFlash('user_jobs', 'danger', 'User job not found');
        }
        throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
    }

    /** @throws */
    public function getNotifications()
    {
        if (Request::isAjax()) {
            if (!empty($_POST['job_id']) && (int) $_POST['job_id'] > 0) {
                $notes = UserJobNotifications::getByUserJobId($_POST['job_id'],
                    !empty($_POST['type']) ? $_POST['type'] : null);
                if (!empty($notes))
                    array_walk($notes, function(&$note) {
                        if (!empty($this->time_diff))
                            $note['create_date'] = $this->handleTimezone($note['create_date']);
                        $note['create_date'] = date(WithDate::DATE_FRONT_FORMAT, strtotime($note['create_date']));
                    });
                return $this->response = json_encode([
                    'notes' => $notes
                ]);
            }
            return $this->response = json_encode(['error']);
        }
        throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
    }

    /** @throws */
    public function clear()
    {
        $all = empty($_POST['done']);
        UserJobModel::clearByUserId($this->user->getId(), $all);
        if ($all && !empty($_SESSION['user_queue'])) {
            unset($_SESSION['user_queue']);
        }
        View::addFlash('user_jobs', 'success', $all ? 'User queue cleared' : 'Cleared completed jobs');
        throw new RedirectException($all ? '/' : '/user_jobs');
    }

    /** @throws */
    public function download()
    {
        $file_job_id = Request::getPositiveId(null, 'file_job_id');
        $file_job = FileJobs::getById($file_job_id);
        if (!empty($file_job))
            $file_wrapper = (new FileWrapper(FileWrapper::getContainerByPath($file_job->getPath())));
        if (empty($file_job) || !$file_wrapper->isFile($file_job->getPath())) {
            View::addFlash('user_jobs', 'danger', 'File error');
            throw new RedirectException('/user_jobs');
        }
        $user_job = UserJobModel::getById($file_job->getUserJobId());
        if (!empty($user_job) && ($user_job->getUserId() == $this->user->getId() || $this->user->isRoleSA())) {
            $filePath = $file_job->getPath();
            $fileArr = explode(DIRECTORY_SEPARATOR, $filePath);
            $fileName = array_pop($fileArr);
            set_time_limit(0);
            header('Content-Encoding: UTF-8');
            header('Content-Type: application/excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$fileName.'"');
            echo stream_get_contents($file_wrapper->getStream($file_job->getPath()));
        } else {
            View::addFlash('user_jobs', 'danger', 'Invalid user job');
            throw new RedirectException('/user_jobs');
        }
    }

    /** @throws */
    public function getAsyncData()
    {
        if (Request::isAjax()) {
            $init = !empty($_POST['init']) && $_POST['init'] === 'true';
            $admin = !empty($_POST['admin']);
            $user_job_ids = [];
            if (!empty($_POST['user_job_ids']))
                $user_job_ids = is_array($_POST['user_job_ids'])
                                        ? array_map('intval', $_POST['user_job_ids'])
                                        : [(int)$_POST['user_job_ids']];

            list($user_jobs, $active_ids) = UserJobModel::getAsyncData($user_job_ids, $this->user->getId(), $init, $admin);
            if (!empty($user_jobs))
                array_walk($user_jobs, function(&$job) {
                    if (!empty($this->time_diff))
                        $job['update_date'] = $this->handleTimezone($job['update_date']);
                    $job['update_date'] = date(WithDate::DATE_FRONT_FORMAT, strtotime($job['update_date']));
                });
            $this->response = json_encode(['result' => 'success', 'user_jobs' => $user_jobs, 'active_ids' => $active_ids]);
            return;
        }
        throw new RedirectException($_SERVER['HTTP_REFERER'] ?? '/');
    }
}
