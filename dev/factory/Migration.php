<?php

namespace dev\factory;

use app\core\db\Mysql;

class Migration extends Factory {

    const COMMAND = 'migration';

    const DIR = __DIR__ . '/../../sql/migrations/log/';
    const TEMPLATE = __DIR__ . '/templates/migration';

    public function make()
    {
        $name = "Migration" . time() . $this->name;
        $is_name_exists = Mysql::fetchScalar(
            "SELECT id FROM migrations_log WHERE name = '" . addslashes($name) . "'"
        );
        $migration_path = self::DIR . $name . '.php';
        if ($is_name_exists || is_file($migration_path))
            throw new \Exception("Migration {$name} is already exists");

        $migration_template = str_replace("migration_template", $name, $this->template);
        $result = file_put_contents($migration_path, $migration_template);
        if (!$result)
            throw new \Exception("Failed to create migration file");
        return "Migration {$name} created";
    }

}