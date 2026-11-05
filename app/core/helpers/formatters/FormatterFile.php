<?php

namespace app\core\helpers\formatters;

use Exception;
use http\Exception\InvalidArgumentException;

abstract class FormatterFile
{

    protected $path;
    protected $output;
    protected $error = '';

    public const MODES = ['w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'];

    public function __destruct()
    {
        if ($this->output != false)
            fclose($this->output);
    }

    public function selfRemove(): void
    {
        if (!is_file($this->path))
            return;
        unlink($this->path);
    }

    protected function checkMode($mode)
    {
        if (!in_array($mode, self::MODES))
            throw new InvalidArgumentException(sprintf("mode '%s' not supported", $mode));
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function existFile(): bool
    {
        return is_file($this->path);
    }

    /**
     * @throws Exception
     */
    public function checkError()
    {
        if (!empty($this->error))
            throw new Exception('Found error: ' . $this->error);
    }

}