<?php

use app\core\db\Mysql;
use app\core\Environment;
use app\core\helpers\Debug;
use app\core\Router;
use app\core\services\crypto\KeyVaultConfig;
use app\core\Storage;

define('ROOT_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR . '..');
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('CONFIG_PATH', APP_PATH . DIRECTORY_SEPARATOR . 'config');

require_once __DIR__ . '/auto_loader.php';
require_once __DIR__ . '/../composer_vendors/autoload.php';

if (file_exists(CONFIG_PATH . '/env/dev.php'))
    require_once CONFIG_PATH . '/env/dev.php';
elseif (file_exists(CONFIG_PATH . '/env/stage.php'))
    require_once CONFIG_PATH . '/env/stage.php';
else
    require_once CONFIG_PATH . '/env/production.php';

// Secret constants (DB passwords, Azure Storage, Consero, ...) from Azure Key Vault, for environments that opt in
KeyVaultConfig::load();
if (Environment::isProduction())
    Mysql::runQuery("SET sql_mode='IGNORE_SPACE';");

require_once __DIR__ . '/config/config.php';

Storage::init();

register_shutdown_function(function () {
    $error = error_get_last();
    if (!empty($error["type"]) && in_array($error["type"], [E_ERROR, E_PARSE]))
        if (!Environment::isDev()) {
            Debug::sendDebugInfo(
                "Shutdown error",
                $error["message"] . " in " . $error["file"] . " on line " . $error["line"]
            );
            Router::internalError();
        }
});
