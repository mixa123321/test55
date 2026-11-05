<?php

namespace app\core\helpers\formatters;

use Exception;

class FormatterCSV extends FormatterFile
{
    private $head = [];
    private $separator = ',';

    public function __construct($path = null, $mode = 'w', $flush = true)
    {
        try {
            $this->checkMode($mode);
            $this->path = !empty($path) ? $path : 'php://output';
            $output = fopen($this->path, $mode);
            if ($output === false)
                throw new Exception('Can`t open resource ' . $this->path);
            $this->output = $output;
            if ($flush)
                flush();
            if (!empty($this->path) && !in_array($mode, ['w', 'a']))
                $this->handleHead();
        } catch (Exception $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function __destruct()
    {
        if ($this->output != false)
            fclose($this->output);
    }

    public function setSeparator($separator)
    {
        $this->separator = $separator;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    private function handleHead()
    {
        $counter = 0;
        $head = [];
        while (!feof($this->output)) {
            if ($counter === 1)
                break;
            $head = fgetcsv($this->output, 5000);
            ++$counter;
        }
        if (!empty($head))
            $this->setHeader($head);
    }

    public function setHeader($head)
    {
        $this->head = $head;
    }

    /** @throws Exception */
    public function addHeader(array $head)
    {
        $this->checkError();
        if ($this->hasHeader())
            throw new Exception('Exist header');
        if (empty($head))
            return;
        fputcsv($this->output, $head, $this->separator);
        $this->head = $head;
    }

    public function hasHeader(): bool
    {
        return !empty($this->head);
    }

    /**
     * @throws Exception
     */
    public function addData($data)
    {
        foreach ($data as $item)
            $this->addRow($item);
    }

    /**
     * @throws Exception
     */
    public function addRow(array $data, string $escape = "\\")
    {
        $this->checkError();
        if (!$this->hasHeader())
            throw new Exception('Empty header');
        fputcsv($this->output, $data, $this->separator, '"', $escape);
    }

    public function selfRemove(): void
    {
        unlink($this->path);
    }

    public static function handle($name, $data, $head = null): bool
    {
        if (empty($data))
            return false;
        try {
            if (is_null($head))
                $head = array_keys(array_values($data)[0]);
            $csv = new self(EXTERNAL_STORAGE . DIRECTORY_SEPARATOR . (empty($name) ? time() : $name) . '.csv');
            $csv->addHeader($head);
            $csv->addData($data);
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }
}