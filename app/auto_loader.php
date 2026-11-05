<?php

spl_autoload_register(function ($class_name) {
    $file = ROOT_PATH . '/' . str_replace('\\', DIRECTORY_SEPARATOR, ltrim($class_name, '\\')) . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
}, true, true);
