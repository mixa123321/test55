<?php

namespace dev\factory;

class Model extends Factory {

    const COMMAND = 'model';

    const DIR = __DIR__ . '/../../app/backend/models/';
    const DIR_CORE = __DIR__ . '/../../app/core/models/';
    const TEMPLATE = __DIR__ . '/templates/model';
    const TEMPLATE_CORE = __DIR__ . '/templates/model_core';

    private $template_core;

    public function __construct()
    {
        parent::__construct();
        $this->setTemplateCore();
    }

    public function setTemplateCore()
    {
        $template_core = file_get_contents(static::TEMPLATE_CORE);
        if (empty($template_core))
            die("View template core is missing");
        $this->template_core = $template_core;
    }

    public function make()
    {
        $name = ucfirst($this->name);

        $model_path = self::DIR . $name . '.php';
        $model_path_core = self::DIR_CORE . $name . '.php';
        if (is_file($model_path) || is_file($model_path_core))
            throw new \Exception("Model {$name} already exist");

        $template_core = str_replace("model_name_core", $name, $this->template_core);
        $result_core = file_put_contents($model_path_core, $template_core);
        if (!$result_core)
            throw new \Exception("Failed to create core model " . $name);
        $template = str_replace("model_name", $name, $this->template);
        $result = file_put_contents($model_path, $template);
        if (!$result)
            throw new \Exception("Failed to create model " . $name);
        return "Model {$name} created";
    }

}