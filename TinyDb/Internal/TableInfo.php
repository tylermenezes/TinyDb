<?php

namespace TinyDb\Internal;

require_once(dirname(__FILE__) . '/require.php');

/**
 * TableInfo - table column info cache
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2013 Tyler Menezes.       Released under the BSD license.
 */
class TableInfo
{
    use Properties;

    private static $tables = array();

    public $table_name;
    public function __construct($table_name)
    {
        $this->table_name = $table_name;
    }

    public function table_info()
    {
        if (!isset(self::$tables[$this->table_name])) {
            self::$tables[$this->table_name] = \TinyDb\Query::show_columns($this->table_name);
        }

        return self::$tables[$this->table_name];
    }

    public function field_info($key, $subkey = null)
    {
        $table_info = $this->table_info();
        if ($subkey !== null) {
            return $table_info[$key]->$subkey;
        } else {
            return $table_info[$key];
        }
    }

    private $primary_key = null;
    public function get_primary_key()
    {
        if (!isset($this->primary_key)) {
            $this->primary_key = array();
            foreach ($this->table_info() as $k => $v) {
                if ($v->key === 'primary') {
                    $this->primary_key[] = $k;
                }
            }

            if (count($this->primary_key) === 1) {
                $this->primary_key = $this->primary_key[0];
            } else if (count($this->primary_key) === 0) {
                $this->primary_key = null;
            }
        }
        return $this->primary_key;
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
