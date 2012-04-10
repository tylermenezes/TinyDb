<?php

namespace TinyDb;

/**
 * TinyORM - a tiny orm.
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2012 Tyler Menezes.       Released under the BSD license.
 */
abstract class Orm
{
    public static $table_name = NULL;
    public static $primary_key = NULL;

    protected static $table_layout = NULL;
    protected static $reflector = NULL;

    protected $needing_update = array();
    protected $is_deleted = FALSE;

    protected $created_at = NULL;
    protected $modified_at = NULL;

    /**
     * Creates an instance of the class.
     * @param mixed     $lookup     * If the paramater is null, the object will be uninitialized. Otherwise:
     *                              * If the paramater is an associative array, and a lookup will be performed
     *                                on the database for (WHERE `key` = 'val' AND `key` = 'val' ...). The
     *                                first result will be returned.
     *                              * If the paramater is a non-associative array, and the primary key is also
     *                                an array, the paramater will be treated as values for the primary keys,
     *                                and populated as specified above.
     *                              * If the paramater is not an array, or the table has a single primary key,
     *                                the paramater will be cast as a string, and be used as a match for the
     *                                primary key. The first result will populate the database.
     */
    public function __construct($lookup = NULL)
    {
        if (!isset(static::$table_name) || !isset(static::$primary_key)) {
            throw new \Exception('Classes using TinyDbOrm must have both a primary key and table name set.');
        }

        static::populate_table_layout();

        // Populate the reflector.
        if (!isset(static::$reflector)) {
            static::$reflector = new \ReflectionClass($this);
        }

        $sql = Sql::create()->select()->from(static::$table_name)->limit(1);

        // If the lookup object is NULL, return an empty object:
        if (!isset($lookup)) {
            return;
        }

        // If the lookup object is an associative array:
        else if (self::is_assoc_array($lookup)) {
            foreach ($lookup as $field=>$value) {
                $sql->where('`' . $field . '` = ?', $value);
            }
        }

        // If the lookup object and pkey are non-associative arrays of the same size:
        else if (is_array(static::$primary_key) && is_array($lookup) && size(static::$primary_key) === size($lookup)) {
            for ($i = 0; $i < size($lookup); $i++) {
                $sql->where('`' . static::$primary_key[$i] . '` = ?', $lookup[$i]);
            }
        }

        // Cast the lookup object to a string.
        else {
            $sql->where('`' . static::$primary_key . '` = ?', strval($lookup));
        }

        // Do the lookup.
        $row = Db::get_read()->getRow($sql->get_sql(), NULL, $sql->get_paramaters(), NULL, MDB2_FETCHMODE_ASSOC);

        // Check if there are any errors.
        if (!isset($row)) {
            throw new \Exception('Record not found.');
        }
        if (\PEAR::isError($row)) {
            throw new \Exception($row->getMessage() . ', ' . $row->getDebugInfo());
        }

        $this->data_fill($row);
    }

    /**
     * Populates the object from row data
     * @param  array  $row The row data
     */
    public function data_fill($row)
    {
        foreach ($row as $field=>$value) {
            $this->$field = $this->fix_type($field, $value);
        }
    }

    /**
     * Creates an object in the database
     * @param array     $properties     Associative array of fields to insert into the database
     */
    public static function create(Array $properties)
    {
        static::populate_table_layout();

        $updates = array();
        $created_at = time();

        foreach (static::$table_layout as $field=>$type) {
            // Check if this is a magic field; if so, don't allow updates
            if ($field === 'created_at') {
                $updates['created_at'] = \MDB2_Date::unix2Mdbstamp($created_at);
            } else if ($field === 'modified_at') {
                $updates['modified_at'] = \MDB2_Date::unix2Mdbstamp($created_at);
            } else if ($field == static::$primary_key) {
                continue;
            }

            // All fields should be set (even if they're only set to NULL)
            else if (!isset($properties[$field])) {
                throw new \Exception("$field must be set!");
            }

            // Add it to the list of updates
            else {
                $updates[$field] = $properties[$field];
            }
        }

        $result = Db::get_write()->autoExecute(static::$table_name, $updates, MDB2_AUTOQUERY_INSERT);
        if (\PEAR::isError($result)) {
            throw new \Exception($result->getMessage() . ', ' . $result->getDebugInfo());
        }

        return new static(Db::get_write()->lastInsertID());
    }

    /**
     * Syncs the object with the database
     */
    public function update()
    {
        $this->check_deleted();

        // Build a list of updates to do
        $updates = array();
        foreach ($this->needing_update as $field) {
            $updates[$field] = $this->$field;
        }

        // Update modified timestamp
        if (isset($this->modified_at)) {
            $this->modified_at = time();
            $updates['modified_at'] = \MDB2_Date::unix2Mdbstamp($this->modified_at);
        }

        // Update the database
        $result = Db::get_write()->autoExecute(static::$table_name, $updates, MDB2_AUTOQUERY_UPDATE, static::$primary_key . ' = ' . Db::get_write()->quote($this->{static::$primary_key}));
        if (\PEAR::isError($result)) {
            throw new \Exception($result->getMessage() . ', ' . $result->getDebugInfo());
        }

        // Clear the list of updates to make
        $this->needing_update = array();
    }

    /**
     * Deletes the object from the database
     */
    public function delete()
    {
        $this->check_deleted();

        $sql = 'DELETE FROM `' . static::$table_name . '` WHERE `' . static::$primary_key . '` = ?';
        Db::get_write()->prepare($sql)->execute(array($this->{static::$primary_key}));
        $this->is_deleted = TRUE;
    }

    /**
     * Magic PHP function for getting paramaters
     * @param  string $key Name of the paramater
     * @return mixed       Value of the paramater
     */
    public function __get($key)
    {
        $this->check_deleted();
        $getter_name = '__get_' . $key;

        // If there's a defined getter, call it
        if (static::$reflector->hasMethod($getter_name)) {
            return $this->$getter_name();
        }

        // If we're trying to get a field in the table, allow it
        else if (isset(static::$table_layout[$key])) {
            return $this->$key;
        }

        // Otherwise, don't let the user get the param
        else {
            throw new \Exception("Read access to paramater $key is not allowed.");
        }
    }

    /**
     * Magic PHP function for setting paramaters
     * @param string $key Name of the paramater
     * @param mixed  $val Value to set the paramater
     */
    public function __set($key, $val)
    {
        $this->check_deleted();

        if (!$this->__validate($key, $val)) {
            throw new \Exception('Paramater did not pass validation.');
        }

        $setter_name = '__set_' . $key;

        // Don't allow changes to pks or timestamps
        if ($key === 'created_at' || $key === 'modified_at' ||
            (is_array(static::$primary_key) &&in_array($key, static::$primary_key)) ||
            (!is_array(static::$primary_key) && $key === static::$primary_key)) {

            throw new \Exception("Write access to paramater $key is not allowed.");
        }

        // If there's a defined setter, call it
        else if (static::$reflector->hasMethod($setter_name)) {
            $this->$setter_name($key, $val);
        }

        // If we're trying to set a field in the table, allow it, and autotypecast
        else if (isset(static::$table_layout[$key])) {
            $this->$key = $val;

            $this->invalidate($key);
        }

        // Otherwise, don't let the user set the param
        else {
            throw new \Exception("Write access to paramater $key is not allowed.");
        }
    }

    /**
     * Attempts to validate the given key
     * @param  string $key Key to validate on
     * @param  mixed  $val Value to check
     * @return bool        TRUE if the value passes all checks, otherwise FALSE
     */
    protected function __validate($key, $val)
    {
        $validator_name = '__validate_' . $key;
        // If there's a defined validation method, call it
        if (static::$reflector->hasMethod($validator_name)) {
            return $this->$validator_name($val);
        }

        // If there's a defined validation string, validate for that type
        else if (static::$reflector->hasProperty($validator_name)) {
            switch($this->$validator_name)
            {
                case 'string':
                case 'str':
                    return is_string($val);
                case 'integer':
                case 'int':
                    return is_int($val);
                case 'email':
                    return preg_match("\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*", $val);
                case 'phone':
                    return preg_match("1?\s*\W?\s*([2-9][0-8][0-9])\s*\W?\s*([2-9][0-9]{2})\s*\W?\s*([0-9]{4})(\se?x?t?(\d*))?", $val);
                case 'ssn':
                    return preg_match("^(\d{3}-?\d{2}-?\d{4}|XXX-XX-XXXX)$", $val);
                case 'date':
                case 'time';
                case 'datetime':
                    return mktime($val) > 0;
                default:
                    return FALSE;
            }
        }

        // If this is in the table structure, use that to validate
        else if (static::$table_layout[$key]) {
            // Try to cast it to its proper type using fixval, then check if it's typewise the same
            try {
                return fix_val($key, $val) === $key;
            } catch(Exception $ex) {
                return FALSE;
            }
        }

        // No validation, so it's okay
        return TRUE;
    }

    /**
     * Marks a field as needing an update in the database
     * @param  string $field The name of the field to invalidate
     */
    protected function invalidate($field)
    {
        if (!in_array($field, $this->needing_update)) {
            $this->needing_update[] = $field;
        }
    }

    /**
     * Checks if the object was deleted in the database (to be called
     * before performing any action on the data of the object).
     */
    protected function check_deleted()
    {
        if ($this->is_deleted)
        {
            throw new Exception('Cannot access a deleted object');
        }
    }

    /**
     * Populates the structure of the model from the database into a late static binding
     */
    protected static function populate_table_layout()
    {
        // Populate the table layout.
        if (!isset(static::$table_layout)) {
            $sql = 'SHOW COLUMNS FROM `' . static::$table_name . '`;';
            $describe = Db::get_read()->getAll($sql, NULL, array(), NULL, MDB2_FETCHMODE_ASSOC);
            static::$table_layout = array();
            foreach ($describe as $field) {
                static::$table_layout[$field['Field']] = $field['Type'];
            }
        }

        return static::$table_layout;
    }

    /**
     * Auto-casts the value into the proper type for its field
     * @param string $key Name of the paramater
     * @param mixed  $val Value to autocast
     */
    protected function fix_type($key, $val)
    {
        if (isset(static::$table_layout[$key])) {
            $type = strtolower(self::$table_layout[$key]);

            if (substr($type, 0, 3) === 'int') {
                return intval($val);
            } else if(substr($type, 0, 7) === 'varchar' || substr($type, 0, 4) == 'text') {
                return strval($val);
            } else if(substr($type, 0, 8) === 'datetime') {
                return \MDB2_Date::mdbstamp2Unix($val);
            } else {
               return $val;
            }
        } else {
            return $val;
        }
    }

    /**
     * Checks if an object is an associative array.
     *
     * Via http://stackoverflow.com/questions/173400
     * @param  mixed    $arr    The object to check
     * @return boolean          True if the object is an associative array, otherwise false.
     */
    private function is_assoc_array($arr)
    {
        return is_array($arr) && (array_keys($arr) !== range(0, count($arr) - 1));
    }
}