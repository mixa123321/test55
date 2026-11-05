<?php

namespace app\core\helpers\formatters;

use Exception;

class FormatterText extends FormatterFile
{

    public function __construct($path = null, $mode = 'w+')
    {
        try {
            $this->checkMode($mode);
            $this->path = !empty($path) ? $path : 'php://output';
            $output = fopen($this->path, $mode);
            if ($output === false)
                throw new Exception('Can`t open resource ' . $this->path);
            $this->output = $output;
        } catch (Exception $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function addContent($content)
    {
        fwrite($this->output, $content . PHP_EOL);
    }

}