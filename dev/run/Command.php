<?php

namespace dev\run;

class Command
{

    public $command;
    private $commands = [];

    private const DIR = __DIR__ . '/../../robot/tasks/';

    public static $aliases = [];

    public function __construct($command)
    {
        $this->scanTaskFolder(self::DIR);
        $this->addAliases();

        if (empty($this->commands[$command]))
            die('Script not found');
        $commandArr = $this->commands[$command];
        if (count($commandArr) > 1) {
            $this->command = $this->askScriptKey($commandArr);
        } else $this->command = $commandArr[0];
    }

    public function run($arguments)
    {
        $escapedArguments = array_map('escapeshellarg', $arguments);

        echo shell_exec('php ' . escapeshellarg($this->command) . ' ' . implode(' ', $escapedArguments));
    }

    public function multi_run($arguments, $num)
    {
        for ($i = 1; $i <= $num; $i++)
            $this->run($arguments);
    }

    private function askScriptKey($commands)
    {
        foreach ($commands as $key => $script)
            echo '      ' . ($key + 1) . ': ' . $script . PHP_EOL;
        $commandKey = (int)readline('Enter script number: ') - 1;
        if (in_array($commandKey, array_keys($commands)))
            return $commands[$commandKey];
        else {
            echo ($commandKey + 1) . ' not exist' . PHP_EOL;
            return $this->askScriptKey($commands);
        }
    }

    private function scanTaskFolder($path)
    {
        $dir = scandir($path);
        if (!empty($dir))
            foreach ($dir as $item)
                if (!in_array($item, ['.', '..'])) {
                    if (is_file($path . $item))
                        $this->commands[$this->explodeCommand($item)][] = $path . $item;
                    if (is_dir($path . $item))
                        $this->scanTaskFolder($path . $item . DIRECTORY_SEPARATOR);
                }
    }

    private function explodeCommand($file)
    {
        $fileArr = explode('.', $file);
        return array_shift($fileArr);
    }

    private function addAliases()
    {
        foreach (self::$aliases as $alias => $script_name) {
            $script = self::DIR . $script_name . '.php';
            if (is_file($script))
                $this->commands[$alias][] = $script;
            else
                echo 'Script for alias ' . $alias . ' not found';
        }

    }
}