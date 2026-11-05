<?php

namespace app\backend\controllers;

use app\core\exceptions\RedirectException;
use app\core\helpers\File;
use app\core\helpers\Request;
use app\core\helpers\View;
use app\core\models\IntegrationDocs;
use app\core\helpers\FileWrapper;

class IntegrationDocuments extends AuthController
{
    public function index()
    {
        $filters = [];
        if (!$this->user->isRoleSA())
            $filters['is_available'] = 1;
        $data = [
            'documents' => IntegrationDocs::getAll($filters),
        ];
        $this->render($data, 'utility/integration_documents');
    }

    /** @throws */
    public function add()
    {
        if (empty($_POST['doc_name'])) {
            View::addFlash('integration_documents', 'danger', 'Document name required');
            throw new RedirectException("/integration_documents");
        }
        if (empty($_FILES['file']) || $_FILES['file']['error']) {
            View::addFlash('integration_documents', 'danger', 'Document file upload failed');
            throw new RedirectException("/integration_documents");
        }
        $result = IntegrationDocs::create(
            [
                'doc_name' => $_POST['doc_name'],
                'doc_description' => $_POST['doc_description'],
                'file' => $_FILES['file']
            ]
        );
        if (!empty($result['error']))
            View::addFlash('integration_documents', 'danger', $result['error']);
        else
            View::addFlash('integration_documents', 'success', 'Document created');
        throw new RedirectException("/integration_documents");
    }

    /** @throws */
    public function download()
    {
        $id = Request::getPositiveId();
        $document = IntegrationDocs::getById($id);
        $path = IntegrationDocs::DOC_PATH . DIRECTORY_SEPARATOR . $document['filename'];
        $container = FileWrapper::getContainerByPath($path);
        if (empty($document) || !(new FileWrapper($container))->isFile($path)) {
            View::addFlash('integration_documents', 'danger', "Document does not exist");
            throw new RedirectException("/integration_documents");
        }
        File::outputFile(IntegrationDocs::DOC_PATH . '/' . $document['filename']);
    }

    /** @throws */
    public function changeStatus()
    {
        $id = Request::getPositiveId();
        $document = IntegrationDocs::getById($id);
        if (empty($document)) {
            View::addFlash('integration_documents', 'danger', "Document does not exist");
            throw new RedirectException("/integration_documents");
        }
        $is_available = $_GET['is_available'] ? 1 : 0;
        IntegrationDocs::changeStatus($id, $is_available);
        View::addFlash('integration_documents', 'success', "Document {$document['doc_name']} updated");
        throw new RedirectException("/integration_documents");
    }

    /** @throws */
    public function delete()
    {
        $id = Request::getPositiveId($_POST);
        $document = IntegrationDocs::getById($id);
        if (empty($document)) {
            View::addFlash('integration_documents', 'danger', "Document does not exist");
            throw new RedirectException("/integration_documents");
        }
        IntegrationDocs::delete($id);
        View::addFlash('integration_documents', 'warning', "Document {$document['doc_name']} deleted");
        throw new RedirectException("/integration_documents");
    }
}