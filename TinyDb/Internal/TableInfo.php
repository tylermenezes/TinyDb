<?php


namespace TinyDb\Internal;

/**
 * TableInfo - table column info cache
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2013 Tyler Menezes.       Released under the BSD license.
 */
class TableInfo
{
    private static $tables = array();

    public $table_name;
    public function __construct($table_name)
    {
        $this->table_name = $table_name;
    }

    public function table_info()
    {
        if (!isset(self::$tables[$table_name])) {
            self::$tables[$table_name] = \TinyDb\Sql::show_columns($table_name);
        }

        return self::$tables[$table_name];
    }

    public function field_info($key, $subkey = null)
    {
        $table_info = $this->table_info();
        if ($subkey !== null) {
            return $table_info[$key][$subkey];
        } else {
            return return $table_info[$key];
        }
    }

    public function is_integral($key)
    {
        return in_array($this->field_info($key, 'type'),
                            array('tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'serial', 'year'));
    }

    public function is_floating($key)
    {
        return in_array($this->field_info($key, 'type'),
                            array('decimal', 'float', 'double', 'real'));
    }

    public function is_numeric($key)
    {
        return $this->is_integral($key) || $this->is_floating($key);
    }

    public function is_boolean($key)
    {
        return in_array($this->field_info($key, 'type'),
                            array('bit', 'bool'));
    }

    public function is_stringy($key)
    {
        return in_array($this->field_info($key, 'type'),
                            array('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext'));
    }

    public function is_temporal($key)
    {
        return in_array($this->field_info($key, 'type'),
                            array('date', 'datetime', 'timestamp', 'time'));
    }

    public function is_set($key)
    {
        return $this->field_info($key, 'type') === 'set';
    }
}
