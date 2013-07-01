<?php

namespace TinyDb;
use TinyDb\Internal;
use TinyDb\Internal\SqlDataAdapters;

require_once(dirname(__FILE__) . '/Internal/require.php');


/**
 * Query - a class to represent and execute SQL queries.
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2012-2013 Tyler Menezes.       Released under the BSD license.
 */
class Query
{
    protected $query_builder;
    public static $query_builder_functions = array(
                    'select', 'insert', 'update', 'delete',
                    'from', 'into',
                    'values', 'set',
                    'join',
                    'where', 'having',
                    'group_by', 'order_by',
                    'union', 'limit');
    protected $cache = true;

    protected static $query_cache;

    /**
     * Creates a new query object
     * @param boolean $cache If true (default), cached results from prior select queries will be used when
     *                       available.
     */
    public function __construct($cache = true)
    {
        if (!isset(self::$query_cache)) {
            self::$query_cache = new \TinyDb\Internal\Query\Cache();
        }

        $this->query_builder = new \TinyDb\Internal\Query\Builder();
        $this->cache = $cache;
    }

    public static function create()
    {
        return new self();
    }

    public function __call($name, $args)
    {
        // If it's a query-builder function, use it to build the query.
        if (in_array($name, self::$query_builder_functions)) {
            $this->query_builder = call_user_func_array(array($this->query_builder, $name), $args);
            return $this;
        }
    }

    /**
     * Executes the query and returns an associative array of rows
     * @param  boolean $magic If true (default), if the result array is (1,1) and the query was for a special
     *                        method (e.g. COUNT(*)) the result will be returned as a raw primative, or if
     *                        the query was a limit(1), the first row will be returned directly.
     *
     *                        Set this to false if you want easily predictable behavior when adjusting the
     *                        select or limit parameters from user input. You probably shouldn't ever be doing
     *                        that, however, so the default is to just do what you probably want.
     * @return array          Query result
     */
    public function exec($magic = true)
    {
        // Load the proper (read/write) database for the operation.
        $handle = null;
        if ($this->query_builder->get_query_type() === 'select') {
            $handle = Db::get_read();
        } else {
            $handle = Db::get_write();
        }

        $rows = $handle->getAll($this->query_builder->get_sql(), null,
                                $this->query_builder->get_paramaters(), null, MDB2_FETCHMODE_ASSOC);

        if (\PEAR::isError($rows)) {
            throw new SqlException($rows->getMessage(), $rows->getDebugInfo(), $this->get_sql(),
                                   $this->get_paramaters());
        }



        if ($this->query_builder->get_query_type() === 'select' && count($this->query_builder->get_selects()) === 1 &&
            count($rows) === 1 && count($rows[0]) === 1 &&
            $magic) {
            return $rows[0][array_keys($rows[0])[0]];
        } else if ($this->query_builder->get_query_type() === 'select' && $this->query_builder->get_limit() === 1 && $magic) {
            return new \TinyDb\Internal\Query\Result($rows[0]);
        } else if ($this->query_builder->get_query_type() === 'select') {
            return new \TinyDb\Internal\Query\Result($rows);
        } else if ($this->query_builder->get_query_type() === 'insert') {
            return $handle->lastInsertId();
        } else {
            return null;
        }
    }

    /**
     * Gets a list of tables available in the database
     * @return array List of tables available in the database
     */
    public static function show_tables()
    {
        $sql = 'SHOW TABLES;';
        $handle = DB::get_write();
        $rows = $handle->getAll($sql, null, null, null, MDB2_FETCHMODE_ORDERED);
        if (\PEAR::isError($rows)) {
            throw new SqlException($rows->getMessage(), $rows->getDebugInfo(), $sql, array());
        }
        $tables = array();
        foreach ($rows as $row) {
            $tables[] = $row[0];
        }
        return $tables;
    }

    /**
     * Checks if a table exists
     *
     * @param string $name The table to check for existence.
     * @return bool
     */
    public static function table_exists($name)
    {
        return in_array($name, self::show_tables());
    }

    /**
     * Creates a table.
     * @param  string $name   Table name
     * @param  array  $fields List of fields; an array of fieldname: [type:string, null:boolean, key:string]
     * @param  string $engine Engine type, usually InnoDb or (in fairly rare cases) MyISAM
     */
    public static function create_table($name, $fields, $engine = 'InnoDb')
    {
        $name = str_replace('`', '\\`', $name);
        $sql = 'CREATE TABLE `' . $name . '` (' . "\n";
        $keys = array();
        foreach ($fields as $k => $info) {
            if (!isset($info['type'])) {
                throw new \InvalidArgumentException('type is required for creating tables');
            }

            $sql .= "\t$k ";
            $sql .= $info['type'];
            if (isset($info['null']) && $info['null']) {
                $sql .= ' NULL';
            } else {
                $sql .= ' NOT NULL';
            }

            if (isset($info['auto_increment']) && $info['auto_increment']) {
                $sql .= ' AUTO_INCREMENT';
            }

            if (isset($info['default'])) {
                $type = (strpos($info['type'], '(') !== false ? substr($info['type'], 0, strpos($info['type'], '(')) : $info['type']);
                $default = Internal\SqlDataAdapters::encode($type, $info['default']);
                if (is_string($default)) {
                    $default = '"' . str_replace('"', '\\"', $info['default']) . '"';
                }

                $sql .= ' DEFAULT ' . $default;
            }

            if (isset($info['key']) && $info['key']) {
                $keys[strtolower(trim($info['key']))][] = $k;
            }
            $sql .= ",\n";
        }

        // Add in the keys
        foreach ($keys as $type => $fields)
        {
            $sql .= "\t" . strtoupper($type) . ' KEY ' . '(';
            foreach ($fields as $field) {
                $sql .= '`' . $field . '`, ';
            }
            $sql = substr($sql, 0, strlen($sql) - 2);
            $sql .= "),\n";
        }

        $sql = substr($sql, 0, strlen($sql) - 2) . "\n";
        $sql .= ') ENGINE=' . $engine . ';';
        $handle = DB::get_write();
        $rows = $handle->getOne($sql, null, null, null, MDB2_FETCHMODE_ASSOC);
        if (\PEAR::isError($rows)) {
            throw new SqlException($rows->getMessage(), $rows->getDebugInfo(), $sql, array());
        }
    }

    /**
     * Drops a table
     * @param  string $name Name of the table to drop
     */
    public static function drop_table($name)
    {
        $name = str_replace('`', '\\`', $name);
        $sql = 'DROP TABLE `' . $name . '`;';
        $handle = DB::get_write();
        $rows = $handle->getOne($sql, null, null, null, MDB2_FETCHMODE_ASSOC);
        if (\PEAR::isError($rows)) {
            throw new SqlException($rows->getMessage(), $rows->getDebugInfo(), $sql, array());
        }
    }

    /**
     * Executes a SHOW COLUMNS FROM `table` query and returns column details
     * @param  string $table The table name to get the columns from
     * @return array         Column details, an array-of-arrays of [name, type, length, values, nullable,
     *                       default, extra, auto_increment, key].
     */
    public static function show_columns($table)
    {
        $sql = 'SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`;';
        $describe = Db::get_read()->getAll($sql, null, array(), null, MDB2_FETCHMODE_ASSOC);

        if (\PEAR::isError($describe)) {
            throw new SqlException($describe->getMessage(), $describe->getDebugInfo(), '', $sql);
        }

        $fields = array();
        foreach ($describe as $field)
        {
            $name = $field['Field'];
            $type_full = $field['Type'];
            $nullable = strtolower($field['Null']) === 'yes';
            $default = $field['Default'];
            $extra = $field['Extra'];
            $key = strtolower($field['Key']);

            if ($key === 'pri') {
                $key = 'primary';
            }

            // Convert truthy-false to real false if the column isn't a primary key
            if (!$key) {
                $key = false;
            }

            $auto_increment = strtolower($extra) === 'auto_increment';

            $type = null;
            $length = null;
            $values = null;

            // Because MySQL stores additional data in the field type (length for numeric types, enumerations
            // for the enum and set types), we need to pull them out here.
            if (strpos($type_full, '(') !== false) {
                $start_parenth_location = strpos($type_full, '(');
                $end_parenth_location = strrpos($type_full, ')');

                $type = strtolower(substr($type_full, 0, $start_parenth_location));
                $additional_data = substr($type_full, $start_parenth_location + 1,
                                            $end_parenth_location - $start_parenth_location - 1);

                // Enums and sets store their enumerations
                if ($type === 'enum' || $type === 'set') {

                    // this is stored as 'hi','won\'t you be my friend?', so we'll strip off the crap here.
                    $values = array();
                    $new_values = explode(',', $additional_data);
                    foreach ($new_values as $val) {
                        $val = substr($val, 1, strlen($val) - 2);
                        $val = str_replace('\\\\', '\\', $val);
                        $val = str_replace('\'\'', '\'', $val);
                        $values[] = $val;
                    }
                    $length = null;

                // Decimal types have two lengths, before and after the decimal
                } else if ($type === 'decimal') {
                    $lengths = explode(',', $additional_data);
                    $length = array(intval($lengths[0]), intval($lengths[1]));

                // The length
                } else {
                    $length = intval($additional_data);
                    $values = null;
                }
            // Not storing extra data inside the type field...
            } else {
                $type = $type_full;
                $length = null;
                $values = null;
            }

            $fields[$name] = (object)array(
                'name' => $name,
                'type' => $type,
                'length' => $length,
                'values' => $values,
                'nullable' => $nullable,
                'default' => $default,
                'extra' => $extra,
                'auto_increment' => $auto_increment,
                'key' => $key
            );
        }

        return $fields;
    }

    public function __toString()
    {
        return $this->query_builder->get_sql();
    }
}
