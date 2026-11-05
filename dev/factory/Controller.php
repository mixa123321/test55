<?php

namespace dev\factory;

class Controller extends Factory {

    const COMMAND = 'controller';

    const DIR = __DIR__ . '/../../app/backend/controllers/';
    const TEMPLATE = __DIR__ . '/templates/controller';

    public function make()
    {
        $name = ucfirst($this->name);
        $controller_path = self::DIR . $name . '.php';
        if (is_file($controller_path))
            throw new \Exception("Controller {$name} already exist");
        $template = str_replace("controller_name", $name, $this->template);
        $result = file_put_contents($controller_path, $template);
        if (!$result)
            throw new \Exception("Failed to create controller " . $name);
        return "Controller {$name} created";
    }

}