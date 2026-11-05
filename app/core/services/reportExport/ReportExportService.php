<?php

namespace app\core\services\reportExport;

use app\backend\models\SFTPExports;
use app\backend\models\Suppliers;
use app\core\entities\ReportExport;
use app\core\entities\SFTPExport;
use app\core\entities\SFTPReport;
use app\core\Environment;
use app\core\helpers\Arr;
use app\core\helpers\File;
use app\core\models\Channels;
use app\core\models\ReportExports;
use app\core\models\SFTPReports;
use app\core\Storage;
use app\core\helpers\FileWrapper;
use app\core\models\FTPUsers;

abstract class ReportExportService
{
    public $fields;
    public $warehouse;
    /** @var $sftp SFTPExport */
    public $sftp;

    /** @var $report ReportExport */
    protected $report;
    /** @var $sftp_report SFTPReport */
    protected $sftp_report;
    private $filename;
    private $output;
    private $id = 'id';

    public function __destruct()
    {
        if (!is_null($this->output))
            fclose($this->output);
    }

    /** @throws \Exception */
    public function __construct($supplier_id, $warehouse_id)
    {
        $this->setFields();
        try {
            $this->sftp = SFTPExports::getBySupplierId($supplier_id);
            if (empty($this->sftp) || !$this->sftp->getIsActive())
                throw new \Exception('SFTP is not configured for supplier');
            $warehouses = Arr::maps(Suppliers::getWarehousesById($supplier_id), 'id', ['id', 'name']);
            if (empty($warehouses) || !in_array($warehouse_id, array_keys($warehouses)))
                throw new \Exception('Warehouse is not configured for supplier');
            $this->warehouse = $warehouses[$warehouse_id];
            $this->report = ReportExports::getByMethod($this->getMethod());
            if (empty($this->report))
                throw new \Exception('Export is not configured');
            $this->sftp_report = SFTPReports::getBySFTPExportReport($this->sftp->getId(), $this->report->getId());
            if (empty($this->sftp_report) || !$this->sftp_report->getIsActive())
                throw new \Exception('Report is not configured');
            if ($this->sftp_report->getDateTime())
                $this->fields[] = 'date_time';
        } catch (\Exception $e) {
            Channels::sendMessageToTelegram("SFTP (Supplier #$supplier_id)" . (Environment::isDev() ? ' (test)' : ''), $e->getMessage());
            throw $e;
        }
    }

    public function getMethod()
    {
        return get_class($this);
    }

    /** @throws \Exception */
    protected function makeFile($name)
    {
        $path = Storage::toExternal(SUPPLIERS_STORAGE) . $this->sftp->getSupplierId();
        File::handleDir($path);
        $path .= DIRECTORY_SEPARATOR . $this->warehouse['id'];
        File::handleDir($path);
        $this->filename =  $path . DIRECTORY_SEPARATOR . $name . '.csv';
        $output = fopen($this->filename, 'w');
        if ($output === false)
            throw new \Exception('File ' . $name . '.csv error');
        $this->output = $output;
        $this->addRow($this->fields);
    }

    public function execute(): ReportExportService
    {
        $last_id = 0;
        while (($last_id = $this->handle($last_id)) !== null);
        return $this;
    }

    /** @throws \Exception */
    public function toSFTP()
    {
        $path = SFTP_STORAGE . DIRECTORY_SEPARATOR . FTPUsers::clearLogin($this->sftp->getLogin(), $this->sftp->getSupplierId()) . DIRECTORY_SEPARATOR . FTPUsers::DIR_REPORT . DIRECTORY_SEPARATOR . $this->warehouse['name'];
        $filename = basename($this->filename);
        $file_wrapper = new FileWrapper(FileWrapper::getContainerByPath($path));
        $file_wrapper->handleDir($path);
        if (!$file_wrapper->writeFilePath($path . DIRECTORY_SEPARATOR . $filename, $this->filename))
            throw new \Exception('Unable to copy file');
    }

    private function handle($last_id = 0): ?int
    {
        $items = $this->getData($last_id);
        if (empty($items))
            return null;
        $date_time = date('d/m/Y H:i');
        foreach ($items as $item) {
            $row = [];
            foreach ($this->fields as $field)
                $row[$field] = $item[$field] ?? '';
            if ($this->sftp_report->getDateTime())
                $row['date_time'] = $date_time;
            $this->addRow($row);
            if ($item == end($items))
                $last_id = (int)$item[$this->id];
        }
        return $last_id;
    }

    abstract protected function getData($last_id): array;
    abstract protected function setFields();

    public function getReportName(): string
    {
        return str_replace(' ', '', $this->report->getName());
    }

    public function getFrequency(): int
    {
        return $this->sftp_report->getFrequency();
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    private function addRow($row)
    {
        fputcsv($this->output, $row);
    }
}