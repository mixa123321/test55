<?php


namespace app\core\services\archiving;

use app\core\dbal\SafeMysql;
use app\core\helpers\Arr;
use app\core\helpers\File;
use app\core\helpers\formatters\FormatterCSV;
use app\core\helpers\formatters\FormatterZip;
use app\core\statical\WithDate;
use Exception;
use ZipArchive;
use app\core\helpers\FileWrapper;

abstract class ArchivingCSVService
{

    const KEY = 'id';

    const DESTROY_CHUNK_SIZE = 1000;

    const DATE_FORMAT = WithDate::DATE_SIMPLE_FORMAT;

    public static $fields = [];

    private static $console_output = false;

    public static function setConsoleOutput()
    {
        self::$console_output = true;
    }

    /** @var $csv FormatterCSV */
    protected $csv;

    const DIR_LOGS = 'logs';
    const DIR_MOVEMENT = 'movement';
    const DIR_CRON_JOBS = 'cron_jobs';
    const DIR_SKU_HISTORY = 'SKU_history';
    const DIR_CURL_LOGS = 'curl_logs';
    const DIR_SHOP_INVENTORY_LOGS = 'shop_inventory_logs';

    const DIRS = [
        self::DIR_SKU_HISTORY,
        self::DIR_CRON_JOBS,
        self::DIR_CURL_LOGS,
        self::DIR_LOGS,
        self::DIR_MOVEMENT,
        self::DIR_SHOP_INVENTORY_LOGS,
    ];

    protected $filename;
    protected $zip_path;
    protected $ids = [];

    public function __destruct()
    {
        if ($this->csv instanceof FormatterCSV) {
            $this->csv->selfRemove();
            unlink($this->zip_path);
        }
    }

    /**
     * @throws Exception
     */
    protected function makeFile($dir, $name)
    {
        $path = ARCHIVE_STORAGE . $dir;
        File::handleDir($path);
        $this->filename =  File::normalizePath($path . DIRECTORY_SEPARATOR . $name . '.csv');
        $this->csv = new FormatterCSV($this->filename);
        $this->csv->addHeader(static::$fields);
    }

    /**
     * @throws Exception
     */
    public function execute(): ArchivingCSVService
    {
        if (self::$console_output)
            echo 'Execute archiving service' . PHP_EOL;
        $last_id = 0;
        while (($last_id = $this->handle($last_id)) !== null);
        if (self::$console_output)
            echo 'Executed' . PHP_EOL;
        return $this;
    }

    public function archiving()
    {
        if (self::$console_output)
            echo 'Start archiving' . PHP_EOL;
        $filename = basename($this->filename);
        $zip_name = str_replace('.csv', '.zip', $filename);
        $zip_dir = str_replace($filename, '',  $this->filename);
        $this->zip_path = $zip_dir . $zip_name;
        if(file_exists($this->zip_path))
            unlink($this->zip_path);

        $zip = new FormatterZip($this->zip_path);
        $error = $zip->getError();
        if (!empty($error))
            die($error);
        $zip->addFile($this->csv->getPath());
        unset($zip);
        if (!(new FileWrapper(FileWrapper::getContainerByPath($this->zip_path)))->writeFilePath($this->zip_path, $this->zip_path, true)) {
            echo 'Did not write ' . $zip_dir . $zip_name . PHP_EOL;
            die;
        }
        if ($this->ids)
            $this->destroyItems($this->ids);
        if (self::$console_output)
            echo 'Archived to ' . $zip_dir . $zip_name . PHP_EOL;
    }

    /**
     * @throws Exception
     */
    public function handle($last_id = 0): ?int
    {
        if (!($this->csv instanceof FormatterCSV))
            throw new Exception('CSV not found');

        $items = $this->getData($last_id);
        if (empty($items)) {
            if (self::$console_output)
                echo 'data not found' . PHP_EOL;
            return null;
        }


        foreach ($items as $item) {
            $this->ids[] = ['id' => $item['id']];
            $row = [];
            foreach (static::$fields as $field)
                $row[$field] = trim($item[$field] ?? '', "\x00..\x1F");
            $this->csv->addRow($row, "\0");
            if ($item == end($items))
                $last_id = (int)$item[static::KEY] ?? null;
        }
        if (self::$console_output) {
            echo 'handled part with ' . count($items) . ' items' . PHP_EOL;
            echo 'Destroy items: ';
        }
        if (self::$console_output)
            echo 'done' . PHP_EOL;
        return $last_id;
    }

    abstract public function getData($last_id): array;

    protected function destroyByIds(string $sql, array $items, array $params = []): void
    {
        $mysql = new SafeMysql();
        foreach (array_chunk(Arr::column($items, 'id'), self::DESTROY_CHUNK_SIZE) as $ids)
            $mysql->execute($sql, ['ids' => $ids] + $params);
    }

    abstract public function destroyItems($items);
}