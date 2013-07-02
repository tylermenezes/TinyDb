<?php

namespace TinyDb;
require_once(dirname(__FILE__) . '/Internal/require.php');


/**
 * TinyOrm - a tiny orm.
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2012-2013 Tyler Menezes.       Released under the BSD license.
 */
abstract class Orm implements \JsonSerializable
{
    public static $table_name = null;

    private $tinydb_needing_update = array();
    private $tinydb_is_deleted = false;
    private $tinydb_access_manager = null;
    private $tinydb_rowdata = null;
    private $tinydb_defined_methods = array('get_id', 'get_json', 'equals', 'update', 'delete');

    public function __construct($new_data_or_datafill)
    {
        if (is_a($new_data_or_datafill, '\\TinyDb\\Internal\\Query\\Result\\Row')) {
            $this->tinydb_datafill($new_data_or_datafill);
        } else {
            $this->tinydb_create($new_data_or_datafill);
        }
    }

    /**
     * Finds an existing instance of the model
     * @param  [mixed] $pkey Primary key to lookup by. If null, will return a Query which should be terminated with one() or all().
     * @return mixed         Class instance, or Collection
     */
    public static function find($pkey = null)
    {
        if (count(func_get_args()) > 1) {
            $pkey = func_get_args();
        }

        if ($pkey !== null) {
            return static::one($pkey);
        } else {
            return new \TinyDb\Internal\Query\Model(get_called_class());
        }
    }

    /**
     * Gets a single instance of the model given the primary key
     * @param  mixed  $pkey Primary key to lookup by
     * @return self         Returned object
     */
    public static function one($pkey)
    {
        if (count(func_get_args()) > 1) {
            $pkey = func_get_args();
        }

        return static::tinydb_add_where_for_pkey($pkey, new \TinyDb\Internal\Query\Model(get_called_class()))->one();
    }

    /**
     * Used for storing foreign key lookups which have already been done
     * @var array
     */
    private $tinydb_extern_cache = array();
    /**
     * PHP magic getter
     * @param  string $key Key to get
     * @return mixed       Value of key
     */
    public function __get($key)
    {
        $this->tinydb_check_deleted();

        if ($this->tinydb_getset_is_table_field($key)) {
            return \TinyDb\Internal\SqlDataAdapters::decode(static::tinydb_get_table_info()->field_info($key)->type,
                                                            $this->tinydb_rowdata[$key]);
        } else if ($this->tinydb_getset_is_method("get_$key")) {
            $method_name = "get_$key";
            return $this->$method_name();
        } else if ($this->tinydb_getset_is_foreign($key)) {
            $extern = $this->tinydb_access_manager->get_extern($key);
            if (!isset($this->tinydb_extern_cache[$key])) {
                $class = $extern['class'];
                if ($this->$extern['name']) {
                    $this->tinydb_extern_cache[$key] = $class::one($this->$extern['name']);
                } else {
                    $this->tinydb_extern_cache[$key] = null;
                }
            }
            return $this->tinydb_extern_cache[$key];
        }
    }

    /**
     * PHP magic setter
     * @param string $key   Key to set
     * @param mixed  $value Value to set key to
     */
    public function __set($key, $value)
    {
        $this->tinydb_check_deleted();

        if ($this->tinydb_getset_is_table_field($key)) {
            // Update modified_at time if it exists
            if (static::tinydb_get_table_info()->field_info('modified_at') !== null) {
                $this->tinydb_rowdata['modified_at'] =
                            \TinyDb\Internal\SqlDataAdapters::encode(static::tinydb_get_table_info()->field_info('modified_at')->type,
                                                                     time());
            }

            // Update the value
            $value = \TinyDb\Internal\SqlDataAdapters::encode(static::tinydb_get_table_info()->field_info($key)->type, $value);
            $this->tinydb_rowdata[$key] = $value;
            $this->tinydb_invalidate($key);
        } else if ($this->tinydb_getset_is_method("set_$key")) {
            $method_name = "set_$key";
            $this->$method_name($value);
        } else if ($this->tinydb_getset_is_foreign($key)) {
            $extern = $this->tinydb_access_manager->get_extern($key);
            unset($this->tinydb_extern_cache[$key]);
            $this->$extern['name'] = $value->id;
        } else {
            $this->$key = $value;
        }
    }

    /**
     * Magic PHP function for checking if a parameter is set
     * @param  string  $key Name of the parameter
     * @return boolean      True if the parameter is set, false otherwise
     */
    public function __isset($key)
    {
        return ($this->tinydb_getset_is_table_field($key) || $this->tinydb_getset_is_method("get_$key") ||
                $this->tinydb_getset_is_foreign($key));
    }

    /**
     * Updates the database
     */
    public function update()
    {
        if (!$this->tinydb_is_deleted && count($this->tinydb_needing_update) > 0) {
            $query = $this->tinydb_add_where(\TinyDb\Query::create()->update(static::$table_name));
            foreach ($this->tinydb_needing_update as $field)
            {
                $query->set('`' . $field . '` = ?', $this->tinydb_rowdata[$field]);
            }
            $query->exec();
            $this->tinydb_needing_update = array();
        }
    }

    public function __destruct()
    {
        $this->update();
    }

    /**
     * Deletes the object
     */
    public function delete()
    {
        $this->tinydb_add_where(\TinyDb\Query::create()->delete()->from(static::$table_name))->exec();
        $this->tinydb_is_deleted = true;
    }

    /* # Predefined magic getters */

    /**
     * Gets the object's primary key values. Magic getter for $obj->id
     * @return mixed Primary key values; either a string or, for a composite primary key, an array
     */
    public function get_id()
    {
        $pkey = static::tinydb_get_table_info()->primary_key;
        if (is_array($pkey)) {
            $val = array();
            foreach ($pkey as $field) {
                $val[$field] = \TinyDb\Internal\SqlDataAdapters::decode(static::tinydb_get_table_info()->field_info($field)->type,
                    $this->tinydb_rowdata[$field]);
            }
        } else {
            $val = \TinyDb\Internal\SqlDataAdapters::decode(static::tinydb_get_table_info()->field_info($pkey)->type,
                    $this->tinydb_rowdata[$pkey]);
        }


        return $val;
    }

    /**
     * Gets a JSON representation of the object. Magic getter for $obj->json. Shortcut for json_encode($obj).
     * @return string JSON representation of the object
     */
    public function get_json()
    {
        return json_encode($this);
    }

    /* # Interfaces */

    /**
     * Checks if the two classes are equal
     * @param  static $to_compare Instance to test for equality
     * @return boolean            True if the instances are equal, false otherwise
     */
    public function equals(self $to_compare)
    {
        $class = get_class($to_compare);
        return ($class::$table_name === static::$table_name) && ($this->id === $to_compare->id);
    }

    /**
     * Returns a version of the object which is JSON-serializable
     * @return object JSON-serializable object
     */
    public function jsonSerialize()
    {
        return $this->tinydb_get_serializable_data();
    }

    /* # Object instantiation logic */

    /**
     * Creates the object in the database and populates the object data.
     * @param  array  $data Associative array of data to fill the object with
     */
    protected function tinydb_create($data)
    {
            $values = array();
            foreach (static::tinydb_get_table_info()->table_info() as $field => $info) {
                if (isset($data[$field])) {
                    if ($this->tinydb_getset_is_method("create_$field", true)) {
                        $method_name = "create_$field";
                        $values[$field] = $this->$method_name($data[$field]);
                    } else {
                        $values[$field] = $data[$field];
                    }
                } else if ($field === 'created_at' || $field === 'modified_at') {
                    $values[$field] = time();
                } else if (!$info->nullable && !$info->auto_increment && !isset($info->default)) {
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
            // If the primary key was provided, use that
            } else if (isset($values[static::tinydb_get_table_info()->primary_key])) {
                $id = $values[static::tinydb_get_table_info()->primary_key];
            }

            $query = \TinyDb\Query::create()->select('*')->from(static::$table_name);
            $query = static::tinydb_add_where_for_pkey($id, $query);
            $rows = $query->exec();
            $this->tinydb_datafill($rows[0]);
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
        return static::tinydb_add_where_for_pkey($this->get_id(), $query);
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

                    $query = $query->where('`' . $field . '` = ?', $provided_key[$field]);
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

    /**
     * Checks if the given key is a foreign key relation
     * @param  string $name Key name
     * @return boolean      True if the field is a foreign key relation, false otherwise
     */
    private function tinydb_getset_is_foreign($name)
    {
        return $this->tinydb_access_manager->get_extern($name) !== null;
    }

    /**
     * Checks if the given key is a getter/setter method. Throws an exception if it's a method which isn't accessible from the calling scope
     * @param  string $name Function name
     * @return boolean      True if the field is a getter/setter, false otherwise.
     */
    private function tinydb_getset_is_method($name, $no_visibility_check = false)
    {
        if ($this->tinydb_get_reflector()->hasMethod($name)) {
            $method = $this->tinydb_get_reflector()->getMethod($name);

            $current_scope = $this->tinydb_get_calling_scope(1);
            $visibility = T_PUBLIC;
            if ($method->isProtected()) {
                $visibility = T_PROTECTED;
            } else if ($method->isPrivate()) {
                $visibility = T_PRIVATE;
            }

            if (!$no_visibility_check && $current_scope < $visibility) {
                return false;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if the given key is a table field. Throws an exception if it's a table field which isn't accessible from the calling scope.
     * @param  string $field Field name
     * @return boolean       True if the field is in the table, false otherwise.
     */
    private function tinydb_getset_is_table_field($field)
    {
        if (static::tinydb_get_table_info()->field_info($field) !== null) {
            $visibility = $this->tinydb_access_manager->get_publicity($field);
            $current_scope = $this->tinydb_get_calling_scope(1);
            if ($current_scope < $visibility) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    /* ## Misc */

    /**
     * Gets a serializeable representation of the object
     * @return object Object containing all serializable properties of the object
     */
    private function tinydb_get_serializable_data()
    {
        $serializable_array = array();

        // Add database data
        foreach ($this->tinydb_rowdata as $field => $val) {
            if ($this->tinydb_access_manager->get_publicity($field) === T_PUBLIC) {
                $serializable_array[$field] = $val;
            }
        }

        // Add other misc object data
        foreach ($this->tinydb_get_reflector()->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $k = $prop->getName();
            $v = $this->$k;
            $serializable_array[$k] = $v;
        }

        // Add data from magic getters and setters
        foreach ($this->tinydb_get_reflector()->getMethods(\ReflectionProperty::IS_PUBLIC) as $meth) {
            $name = $meth->getName();
            if (preg_match('/^get_[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/D', $name) && !in_array($name, $this->tinydb_defined_methods)) {
                $serializable_array[substr($name, 4)] = $this->$name();
            }
        }

        ksort($serializable_array);

        return $serializable_array;
        return (object)$serializable_array;
    }

    /**
     * Gets the scope of the calling class relative to the current class.
     * @return int Visibility of the calling class into methods in this class. One of T_PUBLIC, T_PROTECTED, or T_PRIVATE.
     */
    private function tinydb_get_calling_scope($stack_modifier = 0)
    {
        $class_name = debug_backtrace(null, 3 + $stack_modifier)[2 + $stack_modifier]['class'];
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
