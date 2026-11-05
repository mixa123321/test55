<?php

namespace app\core\helpers;

use app\core\azure\AzureStorageWrapper;
use app\core\Environment;

class FileWrapper
{
    const TYPE_FILE_SYSTEM = 'file_system';
    const TYPE_AZURE = 'azure';
    const CLIENT_MAIN = 'client_main';
    const CLIENT_SFTP = 'client_sftp';

    const ALL_TYPE = [
        self::TYPE_FILE_SYSTEM,
        self::TYPE_AZURE,
    ];

    const ALL_CLIENTS = [
        self::CLIENT_MAIN,
        self::CLIENT_SFTP,
    ];

    private $type;
    private $container;
    private $client;

    function __construct(string $container, string $client = self::CLIENT_MAIN, ?string $type = null)
    {
        $this->type = $type ?? $this->getType();
        if (!in_array($this->type, self::ALL_TYPE))
            throw new \Exception('Wrong type');
        $this->container = $container;
        $this->client = $client;
        if (!in_array($this->client, self::ALL_CLIENTS))
            throw new \Exception('Wrong client');
    }

    public static function getAllowedFolders()
    {
        return [
            File::normalizePath(EXTERNAL_STORAGE) => AzureStorageWrapper::FOLDER_EXTERNAL,
            File::normalizePath(ROOT_STORAGE) => AzureStorageWrapper::FOLDER_ROOT,
            File::normalizePath(ROOT_PATH . RESOURCES_ASSETS_PATH) => AzureStorageWrapper::FOLDER_RESOURCES,
            File::normalizePath(ROOT_PATH . COMMON_FILES) => AzureStorageWrapper::FOLDER_COMMON_FILES,
            File::normalizePath(SFTP_STORAGE) => AzureStorageWrapper::FOLDER_MAIN_SFTP_FILES,
        ];
    }

    private static function getContainerByFolders()
    {
        return [
            File::normalizePath(EXTERNAL_STORAGE) => AZURE_EXTERNAL_CONTAINER,
            File::normalizePath(ROOT_STORAGE) => AZURE_MAIN_CONTAINER,
            File::normalizePath(ROOT_PATH . RESOURCES_ASSETS_PATH) => AZURE_MAIN_CONTAINER,
            File::normalizePath(ROOT_PATH . COMMON_FILES) => AZURE_MAIN_CONTAINER,
            File::normalizePath(SFTP_STORAGE) => AZURE_SFTP_CONTAINER,
        ];
    }

    function getAllowedPath(string $local_absolute_path): ?string
    {
        foreach (self::getAllowedFolders() as $folder => $folder_in_azure)
            if (strpos($local_absolute_path, $folder) === 0)
                return $folder;
        return null;
    }

    /**
     * Local-filesystem fallbacks below (is_file()/fopen()/etc.) take a raw path with no
     * containment check of their own - filterPath() only runs on the Azure branch. Without
     * this, a path built from request input (e.g. Storage::getFile()) containing "../"
     * segments could escape the intended storage folder and read/delete arbitrary files on
     * disk. normalizePath() resolves "." and ".." before the prefix check, so a traversal
     * attempt no longer starts with an allowed folder and is rejected here.
     */
    private function assertPathAllowed(string $path): void
    {
        if ($this->getAllowedPath(File::normalizePath($path)) === null)
            throw new \Exception('Not allowed folder');
    }

    public static function getContainerByPath(string $local_absolute_path): string
    {
        $local_absolute_path = File::normalizePath($local_absolute_path);
        foreach (self::getContainerByFolders() as $folder => $container)
            if (strpos($local_absolute_path, $folder) === 0)
                return $container;
        throw new \Exception('Did not find a container for ' . $local_absolute_path);
    }

    public function filterPath(string $path): string
    {
        $path = File::normalizePath($path);
        $allowed_folder = $this->getAllowedPath($path);
        if ($allowed_folder === null)
            throw new \Exception('Not allowed folder');
        return self::getAllowedFolders()[$allowed_folder] . '/' . trim(substr($path, strlen($allowed_folder)), '/');
    }

    /**
     * Last line of defence for uploads: never write a file the web server could execute
     * (x.php, x.phtml, x.php.csv, .htaccess ...), whatever name the caller built.
     */
    private static function isWritableName(string $path_dest): bool
    {
        if (!File::hasExecutableExtension($path_dest))
            return true;
        Debug::sendDebugInfo('Blocked file write', 'Refused to write executable file type: ' . basename($path_dest), Debug::TEST);
        return false;
    }

    public function writeFilePath(string $path_dest, string $path_from, bool $only_s3 = false): bool
    {
        if (!self::isWritableName($path_dest))
            return false;
        if ($only_s3)
            return self::getAzureClient()->writeBlob($this->container, $this->filterPath($path_dest), $path_from);
        if ($this->type === self::TYPE_AZURE && self::getAzureClient()->writeBlob($this->container, $this->filterPath($path_dest), $path_from))
            return true;
        return copy($path_from, $path_dest);
    }

    public function getFileSizeBlob(string $path, bool $only_s3 = false): int
    {
        if ($only_s3)
            return self::getAzureClient()->sizeBlob($this->container, $this->filterPath($path));
        if ($this->type === self::TYPE_AZURE)
            if ($size = self::getAzureClient()->sizeBlob($this->container, $this->filterPath($path)))
                return $size;
        $this->assertPathAllowed($path);
        return filesize($path);
    }

    public function getMimeType(string $path, bool $only_s3 = false): string
    {
        if ($only_s3)
            return self::getAzureClient()->mimeTypeBlob($this->container, $this->filterPath($path));
        if ($this->type === self::TYPE_AZURE)
            if ($mime_type = self::getAzureClient()->mimeTypeBlob($this->container, $this->filterPath($path)))
                return $mime_type;
        $this->assertPathAllowed($path);
        return mime_content_type($path);
    }

    public function writeFileString(string $path_dest, string $file_string, string $mime_type = 'text/plain', bool $only_s3 = false): bool
    {
        if (!self::isWritableName($path_dest))
            return false;
        if ($only_s3)
            return self::getAzureClient()->writeStringBlob($this->container, $this->filterPath($path_dest), $file_string, $mime_type);
        if ($this->type === self::TYPE_AZURE && self::getAzureClient()->writeStringBlob($this->container, $this->filterPath($path_dest), $file_string, $mime_type))
            return true;
        return (bool)file_put_contents($path_dest, $file_string);
    }

    public function writeFileResource(string $path_dest, $resource_from, bool $only_s3 = false): bool
    {
        if (!self::isWritableName($path_dest))
            return false;
        rewind($resource_from);
        if ($only_s3)
            return self::getAzureClient()->writeResourceBlob($this->container, $this->filterPath($path_dest), $resource_from);
        if ($this->type === self::TYPE_AZURE && self::getAzureClient()->writeResourceBlob($this->container, $this->filterPath($path_dest), $resource_from))
            return true;
        $dest = fopen($path_dest, 'w');
        $result = (bool)stream_copy_to_stream($resource_from, $dest);
        fclose($resource_from);
        return $result;
    }

    public function isFile(string $path, bool $only_s3 = false): bool
    {
        if ($only_s3)
            return self::getAzureClient()->existBlob($this->container, $this->filterPath($path));
        if ($this->type === self::TYPE_AZURE)
            if (self::getAzureClient()->existBlob($this->container, $this->filterPath($path)))
                return true;
        $this->assertPathAllowed($path);
        return is_file($path);
    }

    public function getStream(string $path, bool $only_s3 = false)
    {
        if ($only_s3)
            return self::getAzureClient()->getStreamBlob($this->container, $this->filterPath($path));
        if ($this->type === self::TYPE_AZURE)
            if ($stream = self::getAzureClient()->getStreamBlob($this->container, $this->filterPath($path)))
                return $stream;
        return fopen($path, 'r');
    }

    public function delete(string $path)
    {
        if (file_exists($path))
            unlink($path);
        self::getAzureClient()->deleteBlob($this->container, $this->filterPath($path));
    }

    public function scanDir(string $folder, bool $only_s3 = false): array
    {
        if ($only_s3)
            return $this->getFiles($folder) ?: [];
        if ($this->type === self::TYPE_AZURE) {
            $files = $this->getFiles($folder);
            if ($files !== null)
                return $files;
        }
        return array_diff(scandir($folder) ?: [], array('.', '..'));
    }

    public function glob(string $folder, string $prefix, string $suffix, bool $only_s3 = false): array
    {
        $result = [];
        if ($this->type === self::TYPE_AZURE) {
            foreach ($this->getFiles($folder) as $file) {
                if (!preg_match("/" . preg_quote($prefix) . ".*" . preg_quote($suffix) . "$/", $file))
                    continue;
                $result[] = File::normalizePath($folder . $file);
            }
            if ($only_s3 || $result)
                return $result;
        }
        foreach (glob($folder . $prefix . "*" . $suffix) ?: [] as $path)
            $result[] = File::normalizePath($path);
        return $result;
    }

    private function getFiles(string $folder): ?array
    {
        $azure_folder = $this->filterPath($folder);
        $gen = self::getAzureClient()->blobsList($this->container, $azure_folder);
        $files = [];
        foreach ($gen as $name) {
            if ($name === null) // Ошибка чтения фалов с облака
                return null;
            $dir_len = strlen($azure_folder);
            $suf = ltrim(substr($name, $dir_len, strlen($name) - $dir_len), '/');
            if (preg_match('#/#', $suf)) // Пропускаем вложенные папки
                continue;
            $files[] = $suf;
        }
        return $files;
    }

    public function handleDir(string $dir): FileWrapper
    {
        if ($this->getType() === self::TYPE_FILE_SYSTEM)
            File::handleDir($dir);
        return $this;
    }

    public function createDir(string $dir): FileWrapper
    {
        if ($this->getType() === self::TYPE_FILE_SYSTEM && !is_dir($dir)) {
            mkdir($dir, 0777, true);
            chmod($dir, 0777);
        }
        return $this;
    }

    public function getType(): string
    {
        if (Environment::isProduction())
            return self::TYPE_AZURE;
        return self::TYPE_FILE_SYSTEM;
    }

    protected function getAzureClient(): AzureStorageWrapper
    {
        if ($this->client === self::CLIENT_MAIN)
            return AzureStorageWrapper::getMainClient();
        if ($this->client === self::CLIENT_SFTP)
            throw new \Exception('Not implemented yet');
        throw new \Exception('Wrong S3StorageWrapper client');
    }

}