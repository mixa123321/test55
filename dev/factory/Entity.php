<?php

namespace dev\factory;

use app\core\db\Mysql;

class Entity extends Factory {

    const COMMAND = 'entity';

    private $table;

    const DIR = __DIR__ . '/../../app/core/entities/';
    const TEMPLATE = __DIR__ . '/templates/entity';

    public function make()
    {
        $this->setTable();
        $fields = [];
        $methods = [];
        $arr = [];
        foreach ($this->getColumns() as $col) {
            $fields[] = 'private $' . $col . ';';
            $cc_field = str_replace('_', '', ucwords($col, '_'));
            $methods[] = '      
    public function get' .  $cc_field . '() 
    {
        return $this->' . $col . ';
    }
                  
    public function set' .  $cc_field . '($' . $col . '): void 
    {
        $this->' . $col . ' = $' . $col . ';
    }';
            $arr[] = "      '". $col ."' => \$this->get" . $cc_field . "(),";
        }

        $fields_temp = implode(PHP_EOL. '   ', $fields);
        $methods_temp = implode(PHP_EOL, $methods);
        $arr_temp = implode(PHP_EOL . ' ', $arr);
        $name = ucfirst($this->name);
        $entity_path = self::DIR . $name . '.php';
        if (is_file($entity_path))
            throw new \Exception("Entity {$name} already exist");
        $template = str_replace("entity_name", $name, $this->template);
        $template = str_replace("entity_fields", $fields_temp, $template);
        $template = str_replace("entity_methods", $methods_temp, $template);
        $template = str_replace("entity_table", $this->table, $template);
        $template = str_replace("entity_array", $arr_temp, $template);
        $result = file_put_contents($entity_path, $template);
        if (!$result)
            throw new \Exception("Failed to create entity " . $name);
        return "Entity {$name} created";

    }

    private function setTable()
    {
        $table = strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $this->name)) . 's';
        if ($this->checkTable($table))
            $this->table = $table;
        else {
            $table = readline('Enter table: ');
            if ($this->checkTable($table))
                $this->table = $table;
            else die('Table not found');
        }
    }

    private function checkTable($table)
    {
        return !!Mysql::fetchScalar("SHOW TABLES LIKE '{$table}'");
    }

    private function getColumns()
    {
        $cols = Mysql::fetchAssoc("DESCRIBE {$this->table}");
        if (empty($cols))
            die('Columns not found in ' . $this->table);
        $columns = [];
        foreach ($cols as $col)
            $columns[] = $col['Field'];
        return $columns;
    }
}