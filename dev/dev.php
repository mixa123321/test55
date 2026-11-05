<?php

use dev\factory\Controller;
use dev\factory\Entity;
use dev\factory\Migration;
use dev\factory\Model;
use dev\factory\Task;
use dev\factory\View;
use dev\run\Command;

function factory($obj, $name)
{
    $maker = null;
    switch (strtolower($obj)) {
        case View::COMMAND:
            $maker = new View();
            break;
        case Task::COMMAND:
            $maker = new Task();
            break;
        case Migration::COMMAND:
            $maker = new Migration();
            break;
        case Controller::COMMAND:
            $maker = new Controller();
            break;
        case Model::COMMAND:
            $maker = new Model();
            break;
        case Entity::COMMAND:
            $maker = new Entity();
            break;
    }
    if (!is_object($maker))
        die($obj . ' not found');
    $maker->setName($name);
    if (strlen($maker->name) < 2)
        die('Not correct name');
    try {
        $res = $maker->make();
        echo $res . PHP_EOL;
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
}

function run($command, $arguments)
{
    unset($arguments[0], $arguments[1], $arguments[2]);

    $runner = new Command($command);
    try {
        $runner->run($arguments);
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
}

function multi_run($command, $num = 2, $arguments = [])
{
    unset($arguments[0], $arguments[1], $arguments[2], $arguments[3]);

    $runner = new Command($command);
    try {
        $runner->multi_run($arguments, $num);
    } catch (Exception $e) {
        echo $e->getMessage() . PHP_EOL;
    }
}

    require_once "../robot/boot.php";

    $a = $argv[1] ?? null;
    switch ($a) {
        case 'factory':
            $name = empty($argv[3]) ? readline('Enter name: ') : $argv[3];
            factory($argv[2], $name);
            break;
        case 'run':
            run($argv[2] ?? '', $argv);
            break;
        case 'multi_run':
            multi_run($argv[2] ?? '', (int)($argv[3] ?? 2), $argv);
            break;
        default:
            die('Enter correct command');
    }


