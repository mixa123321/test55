<?php

namespace dev\factory;

class View extends Factory {

    const COMMAND = 'view';

    const DIR = __DIR__ . '/../../app/backend/views/';
    const TEMPLATE = __DIR__ . '/templates/view';

    public function make()
    {
        $this->handlePath();
        $view_path = self::DIR . $this->name . '.php';
        if (is_file($view_path))
            throw new \Exception("View {$this->name} already exist");
        $template = str_replace('{view_name}', $this->name, $this->template);
        $result = file_put_contents($view_path, $template);
        if (!$result)
            throw new \Exception("Failed to create view " . $this->name);
        return "View {$this->name} created";
    }

    public function setName($name)
    {
        $this->name = mb_ereg_replace("([~,;.?!\\\ ])", '', $name);
    }

    private function handlePath()
    {
        $pathArr = explode('/', $this->name);
        if (count($pathArr) <= 1)
            $pathArr = explode("\\", $this->name);
        if (count($pathArr) > 1) {
            array_pop($pathArr);
            $path = self::DIR . implode(DIRECTORY_SEPARATOR, $pathArr);
            if (!is_dir($path))
                mkdir($path, 0777, true);
        }

    }

}