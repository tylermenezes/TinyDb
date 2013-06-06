<?php

namespace TinyDb;
require_once(dirname(__FILE__) . '/Internal/require.php');


/**
 * TinyOrm - a tiny orm.
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2012-2013 Tyler Menezes.       Released under the BSD license.
 */
abstract class Orm
{
    public static $table_name = null;

    private $tinydb_needing_update = array();
    private $tinydb_is_deleted = false;
    private $tinydb_access_manager = null;
    private $tinydb_rowdata = null;

    public function __construct($new_data_or_datafill)
    {
        if (is_a($new_data_or_datafill, '\\TinyDb\\Internal\\Query\\Result\\Row')) {
            $this->tinydb_datafill($new_data_or_datafill);
        } else {
            $this->tinydb_create($new_data_or_datafill);
        }
    }

    public static function find($pkey = null)
    {
        if (count(func_get_args()) > 1) {
            $pkey = func_get_args();
        }

        if ($pkey !== null) {
            return static::tinydb_add_where_for_pkey($pkey, new \TinyDb\Internal\Query\Model(get_called_class()))->one();
        } else {
            return new \TinyDb\Internal\Query\Model(get_called_class());
        }
    }

    public function __get($key)
    {
        $this->tinydb_check_deleted();

        $visibility = $this->tinydb_access_manager->get_publicity($key);
        $current_scope = $this->tinydb_get_calling_scope();
        if ($current_scope < $visibility || !static::tinydb_get_table_info()->field_info($key)) {
            throw new \TinyDb\AccessException('Could not access ' . $key);
        }

        return \TinyDb\Internal\SqlDataAdapters::decode(static::tinydb_get_table_info()->field_info($key)->type,
                                                        $this->tinydb_rowdata[$key]);
    }

    public function __set($key, $value)
    {
        $this->tinydb_check_deleted();

        $visibility = $this->tinydb_access_manager->get_publicity($key);
        $current_scope = $this->tinydb_get_calling_scope();
        if ($current_scope < $visibility || !static::tinydb_get_table_info()->field_info($key)) {
            throw new \TinyDb\AccessException('Could not access ' . $key);
        }

        if (static::tinydb_get_table_info()->field_info('modified_at') !== null) {
            $this->tinydb_rowdata['modified_at'] =
                        \TinyDb\Internal\SqlDataAdapters::encode(static::tinydb_get_table_info()->field_info('modified_at')->type, time());
        }


        $value = \TinyDb\Internal\SqlDataAdapters::encode(static::tinydb_get_table_info()->field_info($key)->type, $value);
        $this->tinydb_rowdata[$key] = $value;
        $this->tinydb_invalidate($key);
    }

    /**
     * Updates the database
     */
    public function update()
    {
        if (!$this->tinydb_is_deleted) {
            $query = $this->tinydb_add_where(\TinyDb\Query::create()->update(static::$table_name));
            foreach ($this->tinydb_needing_update as $field)
            {
                $query->set('`' . $field . '` = ?', $this->tinydb_rowdata[$field]);
            }
            $query->exec();
            $this->tinydb_needing_update = array();
        }
    }

    /**
     * Deletes the object
     */
    public function delete()
    {
        $this->tinydb_add_where(\TinyDb\Query::create()->delete()->from(static::$table_name))->exec();
        $this->tinydb_is_deleted = true;
    }

    /* # Object instantiation logic */

    /**
     * Creates the object in the database and populates the object data.
     * @param  array  $data Associative array of data to fill the object with
     */
    private function tinydb_create($data)
    {
            $values = array();
            foreach (static::tinydb_get_table_info()->table_info() as $field => $info) {
                if (isset($data[$field])) {
                    $values[$field] = $data[$field];
                } else if ($field === 'created_at' || $field === 'modified_at') {
                    $values[$field] = time();
                } else if (!$info->nullable) {
                    throw new \InvalidArgumentException($field . ' is required when creating this object.');
                }

                // If a value was set, encode it properly
                if (isset($values[$field])) {
                    $values[$field] = \TinyDb\Internal\SqlDataAdapters::encode($info->type, $values[$field]);
                }
            }

            $id = \TinyDb\Query::create()->insert()->into(static::$table_name, array_keys($values))->values(array_values($values))->exec();

            // If the primary key is composite, generate the primary key
            if (is_array(static::tinydb_get_table_info()->primary_key)) {
                $id = array();
                foreach (static::tinydb_get_table_info()->primary_key as $field) {
                    $id[$field] = $data[$field];
                }
            }

            $query = \TinyDb\Query::create()->select('*')->from(static::$table_name);
            $query = static::tinydb_add_where_for_pkey($id, $query);
            $row = $query->exec()[0];
            $this->tinydb_datafill($row);
    }

    /**
     * Populates the class with the given row data
     * @param  Internal\Query\Result\Row $data Result row data
     */
    private function tinydb_datafill($data)
    {
        // Fix up access information
        $this->tinydb_access_manager = new \TinyDb\Internal\AccessManager($this->tinydb_get_reflector());
        foreach (static::tinydb_get_table_info()->table_info() as $name => $field) {
            unset($this->$name);
        }

        // Set the information from the database
        $this->tinydb_rowdata = $data;
    }

    /* # Helpers */
    /* ## Query Helpers */

    /**
     * Adds a WHERE clause for the current object.
     * @param  object $query The query to add the where clause on
     */
    private function tinydb_add_where($query)
    {
        $pkey = static::tinydb_get_table_info()->primary_key;
        if (is_array($pkey)) {
            $val = array();
            foreach ($pkey as $field) {
                $val[$field] = $this->$field;
            }
        } else {
            $val = $this->$pkey;
        }

        return static::tinydb_add_where_for_pkey($val, $query);
    }

    /**
     * Appends a WHERE clause onto a query.
     * @param mixed  $provided_key Value for the primary key, either a primitive, or an array for a composite key.
     * @param object $query        The query object to append
     */
    private static function tinydb_add_where_for_pkey($provided_key, $query)
    {
        $pkey = static::tinydb_get_table_info()->primary_key;
        if (is_array($pkey)) {
            if (count($pkey) > count($provided_key)) {
                throw new \InvalidArgumentException('Length of provided values does not match length of primary key.');
            }

            if (self::tinydb_is_assoc($provided_key)) {
                foreach ($pkey as $field) {
                    if (!isset($provided_key[$field])) {
                        throw new \InvalidArgumentException($field . ' is part of the primary key but was not provided.');
                    }

                    $query = $query->where('`' . $pkey[$i] . '` = ?', $provided_key[$field]);
                }
            } else {
                for ($i = 0; $i < count($pkey); $i++) {
                    $query = $query->where('`' . $pkey[$i] . '` = ?', $provided_key[$i]);
                }
            }
        } else {
            if (!is_string($provided_key) && !is_numeric($provided_key)) {
                throw new \InvalidArgumentException('Provided key must be a primitive.');
            }
            $query = $query->where('`' . $pkey . '` = ?', $provided_key);
        }

        return $query;
    }

    /* ## Validation Helpers */

    /**
     * Checks if the object was deleted, and throws an exception if so.
     */
    private function tinydb_check_deleted()
    {
        if ($this->tinydb_is_deleted) {
            throw new \TinyDb\NoRecordException('Object was deleted.');
        }
    }

    /**
     * Invalidates a field, forcing an update with the database
     * @param  string $key Name of the field to update
     */
    private function tinydb_invalidate($key)
    {
        $this->tinydb_needing_update[] = $key;
    }

    /* ## Misc */

    /**
     * Gets the scope of the calling class relative to the current class.
     * @return int Visibility of the calling class into methods in this class. One of T_PUBLIC, T_PROTECTED, or T_PRIVATE.
     */
    private function tinydb_get_calling_scope()
    {
        $class_name = debug_backtrace(null, 3)[2]['class'];
        $current_class_name = get_class($this);
        if ($class_name === $current_class_name) {
            return T_PRIVATE;
        } else if (is_a($class_name, $current_class_name, true)) {
            return T_PROTECTED;
        } else {
            return T_PUBLIC;
        }
    }

    /**
     * Gets the table information from the table description cache
     * @return Internal\TableInfo Table description
     */
    private static function tinydb_get_table_info()
    {
        return new \TinyDb\Internal\TableInfo(static::$table_name);
    }

    private $tinydb_reflector;
    /**
     * Gets a reflector for the class
     * @return ReflectionObject Reflector for the current class
     */
    private function tinydb_get_reflector()
    {
        if (!isset($this->tinydb_reflector)) {
            $this->tinydb_reflector = new \ReflectionObject($this);
        }

        return $this->tinydb_reflector;
    }

    /**
     * Checks if an array is associative
     * @param  array   $array Array to check
     * @return boolean        True if the array is associative.
     */
    private static function tinydb_is_assoc($array)
    {
      return (bool)count(array_filter(array_keys($array), 'is_string'));
    }
}
