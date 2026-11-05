<?php
namespace app\backend\controllers;
use app\core\entities\Queue;
use app\core\exceptions\RedirectException;
use app\core\helpers\File;
use app\core\helpers\FileWrapper;
use app\core\helpers\FPDIFacade;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\BulkLogic;
use app\core\models\OrderDocs;
use app\core\models\Queues as QueuesModal;
use app\frontend\models\PrintLabel;
use app\backend\models\Pack;
use app\backend\models\Pagination;
class Queues extends AuthController
{
    public function bulkList()
    {
        if (!$this->user->isRoleSA() && !$this->user->isRoleWorker()) {
            View::addFlash('queues', 'danger', 'Access denied');
            throw new RedirectException('/');
        }
        $filters = ['type' => Queue::TYPE_BULK_PRINT, 'warehouse_id' => $this->warehouse_id];
        if ($this->user->isRoleWorker())
            $filters['user_id'] = $this->user->getId();
        $pagination = new Pagination($_GET['c'] ?? 1);
        $pagination->setTotalCount(QueuesModal::getCountByFilters($filters));
        $queues_ids = QueuesModal::getByFilters($filters, $pagination);
        $queues = QueuesModal::getBulkData($queues_ids, $this->warehouse->getTimezone());
        $this->renderBulk($queues, Pack::PACKING_MULTI, $pagination);
    }

    public function bulk()
    {
        $id = Request::getPositiveId();
        if (!$id) {
            View::addFlash('queues', 'danger', 'Access denied');
            throw new RedirectException('/');
        }
        $queues = QueuesModal::getBulkData([$id], $this->warehouse->getTimezone());
        if ($error = QueuesModal::validateDataBeforeShow($queues, $this->warehouse_id, $this->user)) {
            View::addFlash('queues', 'danger', $error);
            throw new RedirectException('/');
        }
        $this->renderBulk($queues, Pack::PACKING_SINGLE);
    }

    public function getProcessData()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');;
        $ids = $_POST['ids'] ?? [];
        if (!$ids)
            return $this->response = ['data' => [], 'error' => 'Empty data'];
        $queues = QueuesModal::getBulkData($ids, $this->warehouse->getTimezone());
        if ($error = QueuesModal::validateDataBeforeShow($queues, $this->warehouse_id, $this->user)) {
            View::addFlash('queues', 'danger', $error);
            throw new RedirectException('/');
        }
        return $this->response = ['data' => ['queues' => $queues], 'error' => ''];
    }

    public function toNew()
    {
        if (!Request::isAjax())
            throw new RedirectException('/forbidden');
        $id = Request::getPositiveId($_POST);
        $status = QueuesModal::getStatus($id);
        if (!in_array($status, [Queue::STATUS_ERROR, Queue::STATUS_DONE]))
            return $this->response = ['data' => [], 'error' => 'Wrong status'];
        QueuesModal::clearProgress($id);
        QueuesModal::addErrorIds($id, []);
        QueuesModal::setStatus($id, $status, Queue::STATUS_NEW);
        return $this->response = ['data' => [], 'error' => ''];
    }

    public function printDocuments()
    {
        $bulk_queue_id = Request::getPositiveId($_GET, 'bulk_queue_id');
        $order_id = $_GET['order_id'] ?? '';
        if (!$bulk_queue_id || !$order_id)
            die('Requested documents not found');

        $queues = QueuesModal::getBulkData([$bulk_queue_id], $this->warehouse->getTimezone());
        if ($error = QueuesModal::validateDataBeforeShow($queues, $this->warehouse_id, $this->user))
            die($error);
        $queue = $queues[$bulk_queue_id] ?? null;
        if (empty($queue) || !in_array($queue['status'], [Queue::STATUS_DONE, Queue::STATUS_ERROR], true) || empty($queue['document_orders']))
            die('Requested documents not found');

        if ($order_id !== BulkLogic::PRINT_ALL) {
            $order_id = (int)$order_id;
            if (empty($queue['document_orders'][$order_id]))
                die('Requested documents not found');
            $this->showOrderDocument($order_id);
            return;
        }

        $documents = [];
        foreach ($queue['json_data']['order_ids'] as $queue_order_id) {
            $queue_order_id = (int)$queue_order_id;
            if (empty($queue['document_orders'][$queue_order_id]))
                continue;
            $document = OrderDocs::getByOrderId($queue_order_id);
            if (!empty($document) && $document->existFile())
                $documents[] = $document->getFullPath();
        }

        if (!$documents)
            die('Requested documents not found');
        $this->showMergedDocuments($documents, 'Bulk_documents_' . $bulk_queue_id . '.pdf');
    }

    protected function renderBulk(array $queues, string $type, ?Pagination $pagination = null)
    {
        $this->render([
            'queues' => $queues,
            'type' => $type,
            'print_types' => PrintLabel::ALL_TYPES,
            'document_print_types' => [PrintLabel::TYPE_PDF => PrintLabel::TYPE_PDF],
            'total_records' => $pagination ? $pagination->getTotalCount() : 1,
            'total_pages' => $pagination ? $pagination->getPageCount() : 0,
            'current_page' => $pagination ? $pagination->getCurrentPage() : 0,
        ], 'queues/bulk');
    }

    private function showOrderDocument(int $order_id): void
    {
        $document = OrderDocs::getByOrderId($order_id);
        if (empty($document) || !$document->existFile())
            die('Requested document not found');
        $path = $document->getFullPath();
        $filename = $this->getDocumentFilename($document->getName());
        File::outputStoredDocument($path, $filename);
    }

    private function showMergedDocuments(array $paths, string $filename): void
    {
        $pdf = FPDIFacade::new();
        $temps = [];
        foreach ($paths as $path) {
            $temp = tmpfile();
            $temps[] = $temp;
            fwrite($temp, stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($path)))->getStream($path)));
            $tmp_file = stream_get_meta_data($temp)['uri'];
            $page_count = $pdf->setSourceFile($tmp_file);
            for ($i = 1; $i <= $page_count; $i++) {
                $tpl = $pdf->importPage($i, '/MediaBox');
                $size = $pdf->getTemplateSize($tpl);
                $pdf->addPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($tpl);
            }
        }
        $pdf->Output('I', $filename, true);
    }

    private function getDocumentFilename(string $name): string
    {
        $filename_arr = explode('_', $name);
        if (count($filename_arr) > 1)
            array_shift($filename_arr);
        return implode('_', $filename_arr);
    }
}
