<?php

namespace app\core\helpers\formatters;

use app\core\helpers\Debug;
use Exception;
use ZipArchive;

class FormatterZip extends FormatterFile
{

    /**
     * @var $output ZipArchive
     */

    public function __construct($path)
    {
        try {
            $this->output = new ZipArchive;
            $res = $this->output->open($path, ZipArchive::CREATE);
            if ($res !== TRUE) {
                $message = self::message($res);
                Debug::sendDebugInfo('FormatterZip', 'Path: ' . $path . ' -> ' . $message);
                throw new Exception($message);
            }
            $this->path = $path;
        } catch (Exception $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function addFile($file_path, $file_name = null)
    {
        if (!is_file($file_path))
            return;
        if (empty($file_name))
            $file_name = basename($file_path);
        $this->output->addFile($file_path, $file_name);
    }

    public function addFromString($file_path, $content)
    {
        if (!is_string($content) || empty($content))
            return;
        $this->output->addFromString($file_path, $content);
    }

    public function __destruct()
    {
        if (!empty($this->output))
            $this->output->close();
    }

    private static $zipErrors = [
        ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
        ZipArchive::ER_RENAME => 'Renaming temporary file failed',
        ZipArchive::ER_CLOSE => 'Closing zip archive failed',
        ZipArchive::ER_SEEK => 'Seek error',
        ZipArchive::ER_READ => 'Read error',
        ZipArchive::ER_WRITE => 'Write error',
        ZipArchive::ER_CRC => 'CRC error',
        ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
        ZipArchive::ER_NOENT => 'No such file',
        ZipArchive::ER_EXISTS => 'File already exists',
        ZipArchive::ER_OPEN => 'Can\'t open file',
        ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
        ZipArchive::ER_ZLIB => 'Zlib error',
        ZipArchive::ER_MEMORY => 'Malloc failure',
        ZipArchive::ER_CHANGED => 'Entry has been changed',
        ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
        ZipArchive::ER_EOF => 'Premature EOF',
        ZipArchive::ER_INVAL => 'Invalid argument',
        ZipArchive::ER_NOZIP => 'Not a zip archive',
        ZipArchive::ER_INTERNAL => 'Internal error',
        ZipArchive::ER_INCONS => 'Zip archive inconsistent',
        ZipArchive::ER_REMOVE => 'Can\'t remove file',
        ZipArchive::ER_DELETED => 'Entry has been deleted',
    ];

    public static function message($code): string
    {
        return self::$zipErrors[$code] ?? 'An unknown error has occurred ('.intval($code).')';
    }

    /**
     * @throws Exception
     */
    public static function extract($path, $extract_path, $filename = '')
    {
        if (!file_exists($path))
            throw new Exception('File not found');
        $zip = new ZipArchive;
        $res = $zip->open($path);
        if ($res !== TRUE)
            throw new Exception(self::message($res));
        $zip->extractTo($extract_path, $filename);
        $zip->close();
    }

}