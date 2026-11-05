<?php

namespace app\core\azure;

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\Models\CommitBlobBlocksOptions;
use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;
use MicrosoftAzure\Storage\Blob\Models\Block;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use GuzzleHttp\Exception\ConnectException;

class AzureStorageWrapper
{
    const FOLDER_EXTERNAL = 'external';
    const FOLDER_ROOT = 'root';
    const FOLDER_RESOURCES = 'resources';
    const FOLDER_COMMON_FILES = 'common_files';
    const FOLDER_MAIN_SFTP_FILES = '';

    private $client;
    private static $clients = [];

    private function __construct(BlobRestProxy $client)
    {
        $this->client = $client;
    }

    public static function getMainClient()
    {
        return self::getClient(AZURE_MAIN_CONNECT);
    }

    public static function getClient(string $connectionString): AzureStorageWrapper
    {
        $hash = md5($connectionString);
        if (!empty(self::$clients[$hash]))
            return new self(self::$clients[$hash]);
        self::$clients[$hash] = BlobRestProxy::createBlobService($connectionString);;
        return new self(self::$clients[$hash]);
    }

    public function blobsList(string $container, string $prefix = '', int $limit = 0, bool $return_name = true): ?\Generator
    {
        try {
            $prefix = trim($prefix, '/');
            do {
                $options = new ListBlobsOptions();
                if ($limit)
                    $options->setMaxResults($limit);
                if ($prefix)
                    $options->setPrefix($prefix);
                if (!empty($next_marker))
                    $options->setMarker($next_marker);
                $blobs = $this->client->listBlobs($container, $options);
                foreach ($blobs->getBlobs() as $blob) {
                    yield $return_name ? $blob->getName() : $blob;
                }
                if ($limit)
                    break;
            } while ($next_marker = $blobs->getNextMarker());
        } catch (ServiceException $exception) {
            if (in_array($exception->getCode(), [404, 403]))
                yield null;
            throw $exception;
        } catch (ConnectException $exception) {
            yield null;
        }
    }

    public function getStreamBlob(string $container, $path)
    {
        try {
            return $this->client->getBlob($container, $path)->getContentStream();
        } catch (ServiceException $exception) {
            if (in_array($exception->getCode(), [404, 403]))
                return null;
            throw $exception;
        } catch (ConnectException $exception) {
            return null;
        }
    }

    public function existBlob(string $container, string $path): bool
    {
        try {
            $this->client->getBlobMetadata($container, $path);
        } catch (ServiceException $exception) {
            if (in_array($exception->getCode(), [404, 403]))
                return false;
            throw $exception;
        } catch (ConnectException $exception) {
            return false;
        }
        return true;
    }

    public function sizeBlob(string $container, string $path): int
    {
        try {
            $properties = $this->client->getBlobProperties($container, $path);
        } catch (ServiceException $exception) {
            if(in_array($exception->getCode(), [404, 403]))
                return 0;
            throw $exception;
        } catch (ConnectException $exception) {
            return 0;
        }
        return $properties->getProperties()->getContentLength();
    }

    public function mimeTypeBlob(string $container, string $path): string
    {
        try {
            $properties = $this->client->getBlobProperties($container, $path);
        } catch (ServiceException $exception) {
            if(in_array($exception->getCode(), [404, 403]))
                return '';
            throw $exception;
        } catch (ConnectException $exception) {
            return '';
        }
        return $properties->getProperties()->getContentType();
    }

    public function writeStringBlob(string $container, string $blob, string $string_data, string $mime_type): bool
    {
        try {
            $options = new CommitBlobBlocksOptions();
            $options->setContentType($mime_type);
            $blocks = [];
            foreach (str_split($string_data, $this->client->getBlockSize()) as $i => $str) {
                $block_id = $this->getBlockId($i + 1);
                $this->client->createBlobBlock($container, $blob, $block_id, $str);
                $blocks[] = new Block($block_id, 'Uncommitted');
            }
            $this->client->commitBlobBlocks($container, $blob, $blocks, $options);
        } catch (ServiceException $exception) {
            if(in_array($exception->getCode(), [404, 403, 400]))
                return false;
            throw $exception;
        } catch (ConnectException $exception) {
            return false;
        }
        return true;
    }

    public function writeBlob(string $container, string $blob, string $path): bool
    {
        try {
            $options = new CommitBlobBlocksOptions();
            $options->setContentType(mime_content_type($path));
            $handle = fopen($path, 'r');
            $i = 0;
            $blocks = [];
            while (!feof($handle)) {
                $block_id = $this->getBlockId(++$i);
                $this->client->createBlobBlock($container, $blob, $block_id, fread($handle, $this->client->getBlockSize()));
                $blocks[] = new Block($block_id, 'Uncommitted');
            }
            $this->client->commitBlobBlocks($container, $blob, $blocks, $options);
        } catch (ServiceException $exception) {
            if(in_array($exception->getCode(), [404, 403, 400]))
                return false;
            throw $exception;
        } catch (ConnectException $exception) {
            return false;
        }
        return true;
    }

    public function writeResourceBlob(string $container, string $blob, $resource): bool
    {
        try {
            $options = new CommitBlobBlocksOptions();
            $options->setContentType(mime_content_type($resource));
            $i = 0;
            $blocks = [];
            while (!feof($resource)) {
                $block_id = $this->getBlockId(++$i);
                $this->client->createBlobBlock($container, $blob, $block_id, fread($resource, $this->client->getBlockSize()));
                $blocks[] = new Block($block_id, 'Uncommitted');
            }
            $this->client->commitBlobBlocks($container, $blob, $blocks, $options);
            fclose($resource);
        } catch (ServiceException $exception) {
            if(in_array($exception->getCode(), [404, 403, 400]))
                return false;
            throw $exception;
        } catch (ConnectException $exception) {
            return false;
        }
        return true;
    }

    public function deleteBlob(string $container, string $path): void
    {
        try {
            $this->client->deleteBlob($container, $path);
        } catch (ServiceException $exception) {
            if(in_array($exception->getCode(), [404, 403]))
                return;
            throw $exception;
        } catch (ConnectException $exception) {
            return;
        }
    }

    protected function getBlockId($i)
    {
        return str_pad($i, 32, "0", STR_PAD_LEFT);
    }
}