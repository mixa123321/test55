<?php

namespace dev\factory;

use app\core\helpers\Str;

class Task extends Factory {

    const COMMAND = 'task';

    const DIR = __DIR__ . '/../../robot/commands/';
    const TEMPLATE = __DIR__ . '/templates/command';

    public function setName($name)
    {
        $name = mb_ereg_replace("([~,;.?!\/ ])", '', $name);
        $this->name = ucfirst(Str::toCamelCase($name));
    }

    public function make()
    {
        $task_path = self::DIR . $this->name . '.php';
        if (is_file($task_path))
            return "Task {$this->name} already exist";
        $template = str_replace("command_name", $this->name, $this->template);
        $result = file_put_contents($task_path, $template);
        if (!$result)
            throw new \Exception("Failed to create task " . $this->name);
        return "Task {$this->name} created";
    }

}