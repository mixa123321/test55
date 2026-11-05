<?php

namespace app\backend\controllers;

use app\backend\models\Orders;
use app\backend\models\SKUs;
use app\backend\models\Suppliers as SuppliersModel;
use app\backend\models\Upload as UploadModel;
use app\core\entities\FileJob;
use app\core\ErrorHandler;
use app\core\exceptions\RedirectException;
use app\core\helpers\File;
use app\core\helpers\FileWrapper;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\Queue;

class Upload extends AuthController
{
    /** @throws */
    public function SKUsASN()
    {
        if (empty($_GET['back']) || empty($_GET['data'])) {
            View::addFlash('upload', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $this->disableWarehouseChange();
        $data = [
            'title' => 'ASN SKUs',
            'title2' => 'Add SKUs to your ASN from a CSV file',
            'example' => 'asn_upload_example',
            'data' => addslashes($_GET['data']),
            'back' => addslashes($_GET['back']),
            'upl_data' => 'ASNSKUs',
        ];
        $this->render($data, 'upload/upload_csv');
    }

    /** @throws */
    public function SKUsOrder()
    {
        if (empty($_GET['back']) || empty($_GET['data'])) {
            View::addFlash('upload', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $data = [
            'title' => 'Order SKUs',
            'title2' => 'Add SKUs to your order from a CSV file',
            'example' => 'manifest_upload_example',
            'data' => addslashes($_GET['data']),
            'back' => addslashes($_GET['back']),
            'upl_data' => 'OrderSKUs',
        ];
        $this->render($data, 'upload/upload_csv');
    }

    /** @throws */
    public function Orders()
    {
        try {
            list(
                'suppliers' => $suppliers,
                'supplier_id' => $supplier_id,
                'users' => $users,
            ) = Orders::getAvailableSuppliersAndUsersForOrdersCreation(
                $this->warehouse,
                $this->logistic_company,
                $this->user,
                $_GET['supplier_id'] ?? '',
                $_GET['user_id'] ?? '',
            );
        } catch (\Exception $e) {
            View::addFlash('Orders', 'danger', $e->getMessage());
            throw new RedirectException('/orders');
        }
        $data = [
            'title' => 'Orders',
            'title2' => 'Add orders from a CSV file',
            'example' => 'order_upload_example',
            'data' => '[]',
            'back' => '/orders',
            'upl_data' => 'Orders',
            'suppliers' => $suppliers,
            'users' => $users,
            'supplier_id' => $supplier_id,
        ];
        $this->render($data, 'upload/upload_csv');
    }

    /** @throws */
    public function SKUs()
    {
        if (!isset($_GET['supplier_id']) || empty($_GET['supplier_id']) || $_GET['supplier_id'] == 'all')
            $supplier_id = $this->user->isRoleSA()
                ? []
                : (array_keys(SuppliersModel::getSuppliersBySupplierSide($this->warehouse_id, $this->user->isRoleSupplierAdmin(), $this->user->getId()))[0] ?? []);
        else
            $supplier_id = (int)$_GET['supplier_id'];
        if (!empty($supplier_id) && empty($this->available_suppliers[$supplier_id])) {
            View::addFlash('upload', 'danger', 'Access denied');
            throw new RedirectException('/SKUs/?' . (!empty($_SESSION[SKUs::FILTER_PARAM]) ? $_SESSION[SKUs::FILTER_PARAM] : ''));
        }
        $data = [
            'title' => 'SKUs',
            'title2' => 'Add SKUs from a CSV file',
            'example' => 'SKUs_upload_example',
            'data' => base64_encode(json_encode([])),
            'back' => '/SKUs/?' . (!empty($_SESSION[SKUs::FILTER_PARAM]) ? $_SESSION[SKUs::FILTER_PARAM] : ''),
            'back_params' => json_encode(['supplier' => $supplier_id]),
            'upl_data' => 'SKUs',
            'suppliers' => !$this->user->isRoleSupplierUser() ? SuppliersModel::getSuppliers($this->logistic_company->getId(), $this->warehouse_id, $this->user->hasManagerAccess() ? 0 : $this->user->getId()) : [],
        ];
        $this->render($data, 'upload/upload_csv');
    }

    /** @throws */
    public function file()
    {
        if (empty($_POST['back']) || empty($_POST['data']) || empty($_FILES['upload_file']) || empty($_POST['upl_data'])) {
            View::addFlash('upload_file', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $back = addslashes($_POST['back']);
        $back_params = isset($_POST['back_params']) ? json_decode($_POST['back_params'], true) : [];
        $form = addslashes($_POST['data']);
        $end_url = $back . '/?' . http_build_query(array_merge($back_params, ['data' => $form]));
        if ($_FILES['upload_file']['error'] != 0) {
            View::addFlash('upload_file', 'danger', ErrorHandler::errorFileUpload($_FILES['upload_file']['error']));
            throw new RedirectException($end_url);
        }
        list($open, $res) = UploadModel::loadCSV($_FILES['upload_file']['tmp_name']);
        if (!$open) {
            View::addFlash('upload_file', 'danger', $res);
            throw new RedirectException($end_url);
        }
        if ($_POST['upl_data'] == 'Orders') {
            $subDirs = $this->user->getId() . DIRECTORY_SEPARATOR;
            if (!File::isCSV(File::getMimeType($_FILES['upload_file']['tmp_name']))) {
                View::addFlash('upload_file', 'danger', "File '{$_FILES['upload_file']['name']}' is not csv type");
                throw new RedirectException($end_url);
            }
            (new FileWrapper(FileWrapper::getContainerByPath(CSV_STORAGE)))->handleDir(CSV_STORAGE . $subDirs);
            $csv_file = $subDirs . time() . '_' . File::safeFileName($_FILES['upload_file']['name'], 'orders', 'csv');
            if (!File::moveUploadedFile($_FILES['upload_file']['tmp_name'], CSV_STORAGE . $csv_file)) {
                View::addFlash('upload_file', 'danger', "Write file '{$_FILES['upload_file']['name']}' is failed");
                throw new RedirectException($end_url);
            }
            if (count($res[1]) > UploadModel::MAX_LINES_ORDERS) {
              /*
               $user_job_id = Queue::addJobToSplitFile([
                    'file' => addslashes(CSV_STORAGE . $csv_file),
                    'type' => FileJob::TYPE_ORDERS,
                    'user_id' => $this->user->getId(),
                    'warehouse_id' => $this->warehouse_id,
                ]);
                $_SESSION['user_queue'] = true;
                $_SESSION['user_queue_flash'] = true;
                View::addFlash(
                    'upload_file',
                    'warning',
                    'Quantity of orders more than ' . UploadModel::MAX_LINES_ORDERS . '. The file will be processed in the queue. Job #' . $user_job_id);
              */

                View::addFlash(
                    'upload_file',
                    'warning',
                    'The file you are uploading is too large, it exceeds the 500-row limit, try splitting it into several parts and uploading again'
                );
                throw new RedirectException($end_url);
            }
        }
        $form = json_decode(base64_decode(addslashes($form)), true) ?? [];
        if (isset($_POST['supplier_id']))
            $form = array_merge($form, ['supplier' => $_POST['supplier_id']]);
        elseif (!empty($form['order']['supplier_id']))
            $form = array_merge($form, ['supplier' => $form['order']['supplier_id']]);
        if (!empty($_POST['user_id']))
            $form = array_merge($form, ['user' => $_POST['user_id']]);
        $func = 'app\backend\models\Upload::file' . addslashes($_POST['upl_data']);
        if ($_POST['upl_data'] == 'Orders')
            $form['file_name'] = $_FILES['upload_file']['name'];
        list($res, $form) = $func($res[0], $res[1], $form, $this->warehouse, $this->user);
        foreach (['danger', 'success', 'warning'] as $type_message)
            if (!empty($res[1][$type_message]))
                View::addFlash('upload_file', $type_message, $res[1][$type_message]);
        if (!$res[0])
            throw new RedirectException($end_url);
        throw new RedirectException($form == -1 ? ("/SKUs/?" . (!empty($_SESSION[SKUs::FILTER_PARAM]) ? $_SESSION[SKUs::FILTER_PARAM] : ''))
            : ($back . '?' . http_build_query(array_merge($back_params, ['data' => $form]))));
    }
    
    /** @throws */
    public function fileAjax()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_FILES['upload_file']) || empty($_POST['handler'])) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Invalid information. `handler` and `upload_file` are required fields.',
            ]);
        }
        $handler = $_POST['handler'];
        $form = $_POST['form'] ?? [];
        if (!is_array($form))
            $form = json_decode($form, true) ?: [];
        if ($_FILES['upload_file']['error'] != 0)
            return $this->jsonResponse([
                'status' => 'error',
                'message' => ErrorHandler::errorFileUpload($_FILES['upload_file']['error']),
            ]);
        list($open, $res) = UploadModel::loadCSV($_FILES['upload_file']['tmp_name']);
        if (!$open)
            return $this->jsonResponse(['status' => 'error', 'message' => $res]);
        if ($handler == 'Orders') {
            if (!$this->user->isRoleSupplierUser())
                return $this->jsonResponse(['status' => 'error', 'message' => 'Access denied']);
            $subDirs = $this->user->getId() . DIRECTORY_SEPARATOR;
            if (!File::isCSV(File::getMimeType($_FILES['upload_file']['tmp_name'])))
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => "File '{$_FILES['upload_file']['name']}' is not csv type",
                ]);
            (new FileWrapper(FileWrapper::getContainerByPath(CSV_STORAGE)))->handleDir(CSV_STORAGE . $subDirs);
            $csv_file = $subDirs . time() . '_' . File::safeFileName($_FILES['upload_file']['name'], 'orders', 'csv');
            if (!File::moveUploadedFile($_FILES['upload_file']['tmp_name'], CSV_STORAGE . $csv_file))
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => "Write file '{$_FILES['upload_file']['name']}' is failed",
                ]);
            if (count($res[1]) > UploadModel::MAX_LINES_ORDERS) {
                $user_job_id = Queue::addJobToSplitFile([
                    'file' => addslashes(CSV_STORAGE . $csv_file),
                    'type' => FileJob::TYPE_ORDERS,
                    'user_id' => $this->user->getId(),
                    'warehouse_id' => $this->warehouse_id,
                ]);
                $_SESSION['user_queue'] = true;
                $_SESSION['user_queue_flash'] = true;
                return $this->jsonResponse([
                    'status' => 'success',
                    'message' => 'Quantity of orders more than ' . UploadModel::MAX_LINES_ORDERS
                        . '. The file will be processed in the queue. Job #' . $user_job_id,
                ]);
            }
        }
        if (isset($_POST['supplier_id']))
            $form = array_merge($form, ['supplier' => $_POST['supplier_id']]);
        elseif (!empty($form['order']['supplier_id']))
            $form = array_merge($form, ['supplier' => $form['order']['supplier_id']]);
        $func = 'app\backend\models\Upload::file' . addslashes($handler);
        try {
            list($res, $form) = $func($res[0], $res[1], $handler == 'Orders' ? $_FILES['upload_file']['name'] : $form, $this->warehouse, $this->user);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
                'form' => $form,
                'filter_param' => empty($_SESSION[SKUs::FILTER_PARAM]) ? '' : $_SESSION[SKUs::FILTER_PARAM],
            ]);
        }
        return $this->jsonResponse([
            'status' => $res[0] ? 'success' : 'error',
            'message' => $res[0] ? 'Successfully uploaded' : 'Error',
            'result' => $res[1],
            'form' => $form,
            'filter_param' => empty($_SESSION[SKUs::FILTER_PARAM]) ? '' : $_SESSION[SKUs::FILTER_PARAM],
        ]);
    }

    /** @throws */
    public function filesAjax()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        if (empty($_FILES['upload_file']) || empty($_POST['handler'])) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Invalid information. `handler` and `upload_file` are required fields.',
            ]);
        }
        $handler = $_POST['handler'];
        $form = $_POST['form'] ?? [];
        if (!is_array($form))
            $form = json_decode($form, true) ?: [];
        $files = $_FILES['upload_file'];
        $errors = array_filter($files['error']);
        if (!empty($errors))
            return $this->jsonResponse([
                'status' => 'error',
                'message' => implode(', ', array_map('ErrorHandler::errorFileUpload', $errors)),
            ]);
        if (isset($_POST['supplier_id']))
            $form = array_merge($form, ['supplier' => $_POST['supplier_id']]);
        elseif (!empty($form['order']['supplier_id']))
            $form = array_merge($form, ['supplier' => $form['order']['supplier_id']]);
        $func = 'app\backend\models\Upload::fileMultiple' . addslashes($handler);
        if (!is_callable($func))
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Invalid handler',
            ]);
        try {
            list($res, $form) = $func($files, $form, $this->warehouse, $this->user);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
                'form' => $form,
                'filter_param' => empty($_SESSION[SKUs::FILTER_PARAM]) ? '' : $_SESSION[SKUs::FILTER_PARAM],
            ]);
        }
        return $this->jsonResponse([
            'status' => $res[0] ? 'success' : 'error',
            'message' => $res[0] ? 'Successfully uploaded' : 'Error',
            'result' => $res[1],
            'form' => $form,
            'filter_param' => empty($_SESSION[SKUs::FILTER_PARAM]) ? '' : $_SESSION[SKUs::FILTER_PARAM],
        ]);
    }

    /** @throws */
    public function demo()
    {
        if (empty($_GET['csv'])) {
            View::addFlash('upload', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        $csv_path = ROOT_PATH . COMMON_FILES . '/' . addslashes($_GET['csv']) . '.csv';
        if (!File::isPathWithin($csv_path, ROOT_PATH . COMMON_FILES)) {
            View::addFlash('upload', 'danger', 'Invalid information');
            throw new RedirectException('/');
        }
        return File::outputFile($csv_path, OUTPUT_ATTACHMENT);
    }
}
