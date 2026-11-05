<?php

namespace app\core\models;

use app\core\db\QueryBuilder;
use app\core\dbal\SafeMysql;
use app\core\helpers\File;
use app\core\helpers\FileWrapper;

class IntegrationDocs
{
    const DOC_PATH = ROOT_PATH . RESOURCES_ASSETS_PATH . '/integration_docs';
    const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip', 'png', 'jpg', 'jpeg'];

    public static function getAll(array $filters = [])
    {
        $query = new QueryBuilder();
        $query->setSelectFrom("SELECT * FROM integration_docs");
        if (!empty($filters['is_available'])) {
            $query->addWhere("is_available=1");
        }
        $query->setOrderBy('is_available, date_created DESC');
        return (new SafeMysql())->fetchAll($query->build());
    }

    public static function create(array $data)
    {
        if (!is_dir(self::DOC_PATH)) {
            mkdir(self::DOC_PATH, 0777, true);
            chmod(self::DOC_PATH, 0777);
        }
        // DOC_PATH is served directly by the web server: allow document/image types only, never html/svg/xml/scripts
        $filename = File::safeFileName((string)$data['file']['name'], 'document');
        if (!in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true))
            return ['error' => 'Allowed document types: ' . implode(', ', self::ALLOWED_EXTENSIONS)];
        $filesize = filesize($data['file']['tmp_name']);
        if (empty($filename) || (new FileWrapper(FileWrapper::getContainerByPath(self::DOC_PATH)))->isFile(self::DOC_PATH . DIRECTORY_SEPARATOR . $filename))
            return ['error' => "Document {$filename} already exists!"];
        if (!$filesize)
            return ['error' => "Document {$filename} is empty!"];
        if (empty($data['doc_name']))
            return ['error' => "Document name is empty!"];
        if (!File::moveUploadedFile($data['file']['tmp_name'], self::DOC_PATH . DIRECTORY_SEPARATOR . $filename))
            return ['error' => "Document {$filename} upload failed!"];
        (new SafeMysql())->execute("INSERT INTO integration_docs 
                SET doc_name = :doc_name, 
                doc_description = :doc_description, 
                filename = :filename, 
                date_created = :date_created", [
            'doc_name' => $data['doc_name'],
            // optional in the form, NOT NULL column (legacy wrote '')
            'doc_description' => (string)($data['doc_description'] ?? ''),
            'filename' => $filename,
            'date_created' => time(),
        ]);
        return ['success' => 1];
    }

    public static function getById($id)
    {
        return (new SafeMysql())->fetchOne("SELECT * FROM integration_docs WHERE id = :id", ['id' => (int)$id]);
    }

    public static function changeStatus($id, $is_available)
    {
        (new SafeMysql())->execute("UPDATE integration_docs 
                                SET is_available = :is_available
                                WHERE id = :id", [
            'is_available' => (int)$is_available,
            'id' => (int)$id,
        ]);
    }

    public static function delete($id)
    {
        $filename = (new SafeMysql())->fetchScalar("SELECT filename FROM integration_docs WHERE id = :id", ['id' => (int)$id]);
        (new FileWrapper(FileWrapper::getContainerByPath(self::DOC_PATH)))->delete(self::DOC_PATH . '/' . $filename);
        (new SafeMysql())->execute("DELETE FROM integration_docs WHERE id = :id", ['id' => (int)$id]);
    }
}