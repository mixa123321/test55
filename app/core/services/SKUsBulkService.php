<?php

namespace app\core\services;

use app\backend\models\SKUHistories;
use app\backend\models\SKUs;
use app\core\entities\File;
use app\core\entities\SKU;
use app\core\entities\User;
use app\core\ErrorHandler;
use app\core\models\FileJobs;
use app\core\Queue;
use app\core\helpers\File as FileHelper;
use app\core\helpers\FileWrapper;
use app\core\models\config\SKUsConfig;

class SKUsBulkService
{
    private const PATH = 'bulk';

    public const FIELD_ID = 'id';
    private const FIELD_PHOTO = 'photo';
    private const FIELD_PHOTO_SRC = 'photo_src';

    public const MAX_LINES = 500;

    public static $fields = [
        self::FIELD_ID => 'ID',
        'name' => 'name',
        'gs1' => 'gs1',
        'cost_value' => 'cost value',
        'cost_currency' => 'cost currency',
        'market_value' => 'market value',
        'market_currency' => 'market currency',
        'hs_code' => 'hs code',
        'season' => 'season',
        'customer_ref' => 'customer ref',
        'customer_ref2' => 'customer ref2',
        self::FIELD_PHOTO_SRC => 'photo source',
        'unique_customer_ref' => 'unique customer ref',
        'country_id' => 'country code',
        'DDS' => 'DDS',
        'exemption_TARIC' => 'exemption TARIC',
        'ns_pid' => 'ns_pid',
        's_pid' => 's_pid',
    ];

    // fields that are not columns on the SKU entity itself (stored via SKUsConfig) -
    // excluded from the generic SKU::toArray()/save() diffing, handled separately in handleSKU()
    private const CONFIG_FIELDS = ['ns_pid', 's_pid'];

    public $active_fields = [];

    private $file;
    private $error;
    private $message;

    /** @var $user User */
    private $user;
    private $warehouse_id;

    private $queue = false;

    public function __construct(File $file)
    {

        $this->file = $file;
        $this->checkFile();

        if (!empty($this->error))
            $this->active_fields = [];
    }

    public function prepareData(array $data = null): void
    {
        if (is_null($data)) {
            $this->active_fields = array_keys(self::$fields);
            return;
        }

        foreach ($data as $key => $field)
            if (array_key_exists($key, self::$fields))
                self::$fields[$key] = $field;
        foreach (array_keys(self::$fields) as $key)
            if (!array_key_exists($key, $data))
                unset(self::$fields[$key]);

        $this->active_fields = array_keys(self::$fields);
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function setWarehouseId($warehouse_id)
    {
        $this->warehouse_id = (int)$warehouse_id;
    }

    public function setQueue()
    {
        $this->queue = true;
    }

    public function getHeadCSV(): array
    {
        return $this->execute( true);
    }

    public function execute($get_head = false): array
    {
        if (($handle = fopen($this->file->getTmpName(), "r")) === false) {
            $err = error_get_last();
            $this->error = $err['message'];
            return [false, null];
        }
        $head = $this->getHead($handle);
        if (is_null($head))
            return [false, null];

        if ($get_head)
            return [true, $head];

        if (!array_key_exists(self::FIELD_ID, self::$fields)) {
            $this->error = 'Id is required';
            return [false, null];
        }

        if (!$this->queue) {
            if (!$this->storeFile())
                return [false, null];

            if ($this->handleQueue()) {
                return [true, null];
            }
        }

        $result = [];
        $row = 0;
        while (($res = fgetcsv($handle)) !== false)
            if (!empty($res) && !empty($res[0])) {
                $SKU = $this->getSKUByRow($head, $res);
                $config_data = $this->getConfigFieldsByRow($head, $res);
                $result[] = $this->handleSKU($SKU, ++$row, $config_data);
            }

        fclose($handle);
        return [true, $result];
    }

    private function getHead($handle): ?array
    {
        $head = fgetcsv($handle);
        if ($head === false) {
            fclose($handle);
            $this->error = 'Absent header of CSV';
            return null;
        }
        $head = array_map(function($item) {
            return trim($item);
        }, $head);

        if (!$this->checkHead($head))
            return null;
        return $head;
    }

    private function checkHead($data): bool
    {
        if (count($data) != count(array_filter($data))) {
            $this->error = 'Header has empty values';
            return false;
        }
        if(count(array_unique($data)) < count($data)) {
            $this->error = 'Header has duplicates';
            return false;
        }
        return true;
    }

    private function storeFile(): bool
    {
        $this->chmodPath();
        $folder = $this->user->getId() . DIRECTORY_SEPARATOR . self::PATH . DIRECTORY_SEPARATOR;
        $name = time() . '_' . FileHelper::safeFileName((string)$this->file->getName(), 'skus', 'csv');
        $path = CSV_STORAGE .  $folder . $name;
        if(FileHelper::moveUploadedFile($this->file->getTmpName(), $path)) {
            $this->file->setPath($folder . $name);
            $this->file->setTmpName($path);
        }
        return $this->handleFile();
    }

    private function handleFile(): bool
    {
        if (empty($this->file->getTmpName())) {
            $this->error = 'File upload failed';
            return false;
        }

        $fp = stream_get_contents((new FileWrapper(FileWrapper::getContainerByPath($this->file->getTmpName())))->getStream($this->file->getTmpName()));
        $fp = array_filter(explode("\n", $fp));

        if (count($fp) < 2) {
            $this->error = 'Empty data';
            return false;
        }

        $this->file->setRows(count($fp) - 1);

        return true;
    }

    private function chmodPath()
    {
        if (empty($this->user))
            return;
        $user_id = $this->user->getId();
        FileHelper::handleDir(CSV_STORAGE . $user_id);
        $path = $user_id . DIRECTORY_SEPARATOR . self::PATH;
        FileHelper::handleDir(CSV_STORAGE . $path);
    }

    private function getSKUByRow($head, $data): SKU
    {
        $SKU_data = [];
        foreach (self::$fields as $key => $field) {
            $ident = array_search($field, $head);
            $SKU_data[$key] = $ident !== false ? trim($data[$ident]) : '';
        }
        return new SKU($SKU_data);
    }

    // ns_pid/s_pid aren't SKU entity columns (stored via SKUsConfig), so they're read
    // separately from the CSV row rather than through SKU::toArray()/save()
    private function getConfigFieldsByRow($head, $data): array
    {
        $config_data = [];
        foreach (self::CONFIG_FIELDS as $key) {
            if (!isset(self::$fields[$key])) {
                $config_data[$key] = null;
                continue;
            }
            $ident = array_search(self::$fields[$key], $head);
            $config_data[$key] = $ident !== false ? trim($data[$ident]) : null;
        }
        return $config_data;
    }

    private function saveConfigFields(int $SKU_id, array $config_data): bool
    {
        $configs = $changed = [];
        if ($config_data['ns_pid'] !== null && $config_data['ns_pid'] !== SKUsConfig::getNsPid($SKU_id)) {
            $configs[] = SKUsConfig::nsPid($SKU_id, $config_data['ns_pid']);
            $changed[] = 'ns_pid';
        }
        if ($config_data['s_pid'] !== null && $config_data['s_pid'] !== SKUsConfig::getSPid($SKU_id)) {
            $configs[] = SKUsConfig::sPid($SKU_id, $config_data['s_pid']);
            $changed[] = 's_pid';
        }
        if (empty($configs))
            return false;
        SKUsConfig::saveConfigs($configs);
        return true;
    }

    private function handleSKU(SKU $SKU, $row, array $config_data = []): array
    {
        $exist_SKU = $this->getExistSKU($SKU->getId());
        if (empty($exist_SKU))
            return ['errors' => ['SKU #' . $SKU->getId() . ' (' . $row . '): not found'], 'result' => []];

        $SKU->setSupplierId($exist_SKU->getSupplierId());
        $errorsSKU = $this->validateSKU($SKU);

        if (!empty($errorsSKU))
            return ['errors' => $errorsSKU, 'result' => []];

        $changes = $this->getChanges($SKU, $exist_SKU);
        $config_changed = $this->saveConfigFields($SKU->getId(), $config_data);

        if (!empty($changes)) {
            $fields = array_keys(self::$fields);
            if (array_key_exists(self::FIELD_PHOTO_SRC, $changes)) {
                $SKU->setPhoto(null);
                $fields = array_merge($fields, [self::FIELD_PHOTO]);
            }
            $SKU->save($fields);
            SKUHistories::updateParams($SKU->getId(), $this->user->getId(), $changes, $this->warehouse_id, $this->file->getPath());
            return ['errors' => [], 'result' => ['SKU #' . $SKU->getId() . ': updated']];
        }
        if ($config_changed)
            return ['errors' => [], 'result' => ['SKU #' . $SKU->getId() . ': updated']];
        return ['errors' => [], 'result' => ['SKU #' . $SKU->getId() . ': not changes']];
    }

    private function validateSKU(SKU $SKU): array
    {
        $errorsSKU = [];
        $fields = array_keys(self::$fields);
        $errors = array_merge(SKUs::validate($SKU, false, false), SKUs::validateUniq($SKU));
        if (!empty($errors)) {
            foreach ($errors as $e_key => $error)
                if (!in_array($error['field'], $fields))
                    unset($errors[$e_key]);
                else
                    $errorsSKU[] = 'SKU #' . $SKU->getId() . ': ' . $error['message'];
        }
        return $errorsSKU;
    }

    /**
     * @param  SKU  $SKU
     * @param  SKU  $exist_SKU
     * @return array
     */
    private function getChanges(SKU $SKU, SKU $exist_SKU): array
    {
        $changes = [];
        $SKU_arr = $SKU->toArray();
        $exist_SKU_arr = $exist_SKU->toArray();
        foreach (array_keys(self::$fields) as $field) {
            if ($field === self::FIELD_ID)
                continue;
            $old = $exist_SKU_arr[$field] ?? '';
            $new = $SKU_arr[$field] ?? '';
            if ($new != $old)
                $changes[$field] = [$old, $new];
        }
        return $changes;
    }

    private function getExistSKU($id): ?SKU
    {
        if (empty($this->user))
            return null;

        if ($this->user->isRoleSA()) {
            return SKUs::getById($id);
        } elseif ($this->user->hasManagerAccess()) {
            return SKUs::getByIdAndWarehouse($id, $this->warehouse_id);
        } else {
            return SKUs::getByIdAndUser($id, $this->user->getId());
        }
    }

    private function checkFile()
    {
        if (empty($this->file))
            $this->error = 'File not found';

        if ($this->file->getError() != 0)
            $this->error = ErrorHandler::errorFileUpload($this->file->getError());

        if (!in_array($this->file->getType(), FileHelper::getCSVMimes()))
            $this->error = 'Not CSV format';
    }

    public function getError()
    {
        return $this->error;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function handleQueue(): bool
    {
        if (is_null($this->file->getRows()))
            return false;
        if ((int)$this->file->getRows() > self::MAX_LINES) {
            $data = [
                'user_id' => $this->user->getId(),
                'warehouse_id' => $this->warehouse_id,
                'fields' => self::$fields
            ];
            $user_job_id = Queue::addJobToBulkUpdateSKU($data);
            FileJobs::addNew($this->file->getTmpName(), $user_job_id);
            $this->message = 'Quantity of SKUs more than ' . self::MAX_LINES . '. The file will be processed in the queue. Job #' . $user_job_id;
            return true;
        }
        return false;
    }
}