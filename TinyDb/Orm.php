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

    public static $instance = array();

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
        // If the paramaters are passed in ... style, make them into an array
        if (count(func_get_args()) > 1) {
            $lookup = func_get_args();
        }

        if (!isset(static::$table_name) || !isset(static::$primary_key)) {
            throw new \Exception('Classes using TinyDbOrm must have both a primary key and table name set.');
        }

        static::populate_table_layout();

        // Populate the reflector.
        if (!isset(static::$instance[static::$table_name]['reflector'])) {
            static::$instance[static::$table_name]['reflector'] = new \ReflectionClass($this);
        }

        $sql = Sql::create()->select()->from(static::$table_name)->limit(1);

        // If the lookup object is NULL, return an empty object:
        if (!isset($lookup)) {
            return;
        }

        // If the lookup object is an associative array:
        else if (static::is_assoc_array($lookup)) {
            foreach ($lookup as $field=>$value) {
                $sql->where('`' . $field . '` = ?', $value);
            }
        }

        // If the lookup object and pkey are non-associative arrays of the same size:
        else if (is_array(static::$primary_key) && is_array($lookup) && size(static::$primary_key) === size($lookup)) {
            for ($i = 0; $i < size($lookup); $i++) {
                $sql->where('`' . static::$primary_key[$i] . '` = ?', static::unfix_type(static::$primary_key[$i], $lookup[$i]));
            }
        }

        // Cast the lookup object to a string.
        else {
            $sql->where('`' . static::$primary_key . '` = ?', static::unfix_type(static::$primary_key, $lookup));
        }

        // Do the lookup.
        $row = Db::get_read()->getRow($sql->get_sql(), NULL, $sql->get_paramaters(), NULL, MDB2_FETCHMODE_ASSOC);

        // Check if there are any errors.
        if (!isset($row)) {
            throw new NoRecordException();
        }
        self::check_mdb2_error($row);

        $this->data_fill($row);
    }

    /**
     * Checks if an instance of the class exists in the database.
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
    public static function exists($lookup = NULL)
    {
        try {
            new static($lookup);
            return TRUE;
        } catch (NoRecordException $ex) {
            return FALSE;
        }
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
     * Gets an MDB-style timestamp in GMT
     * @param  int    $unix_timestamp Unix timestamp
     * @return string                 MDB timestamp
     */
    private static function mdb_timestamp($unix_timestamp)
    {
        if (!is_int($unix_timestamp)) {
            return FALSE;
        }

        return gmdate('Y-m-d H:i:s', $unix_timestamp);
    }

    /**
     * Gets a UNIX-style timestamp in GMT
     * @param  string $unix_timestamp MDB timestamp
     * @return int                    Unix timestamp
     */
    private static function unmdb_timestamp($mdb_timestamp)
    {
        if (is_int($mdb_timestamp)) {
            return $mdb_timestamp;
        }

        $arr = \MDB2_Date::mdbstamp2Date($mdb_timestamp);
        return gmmktime($arr['hour'], $arr['minute'], $arr['second'], $arr['month'], $arr['day'], $arr['year'], -1);
    }

    /**
     * Creates an object in the database
     * @param array     $properties     Associative array of fields to insert into the database
     */
    public static function create(Array $properties)
    {
        return static::raw_create($properties);
    }

    /**
     * Creates an object in the database
     * @param array     $properties     Associative array of fields to insert into the database
     */
    public static final function raw_create(Array $properties)
    {
        static::populate_table_layout();

        $sql = \TinyDb\Sql::create()->insert()->into(static::$table_name);
        $created_at = self::mdb_timestamp(time());

        foreach (static::$instance[static::$table_name]['table_layout'] as $field=>$details) {
            // Check if this is a magic field; if so, don't allow updates
            if ($field === 'created_at' || $field === 'modified_at') {
                $sql->set("`$field` = ?", $created_at);
            }

            // If there's a defined paramater, add it to the list of updates
            else if (isset($properties[$field])) {
                $sql->set("`$field` = ?", static::unfix_type($field, $properties[$field]));
            }
        }

        $result = Db::get_write()->prepare($sql->get_sql());
        self::check_mdb2_error($result);
        $result = $result->execute($sql->get_paramaters());
        self::check_mdb2_error($result);

        if (is_string(static::$primary_key) && isset($properties[static::$primary_key])) {
            $id = $properties[static::$primary_key];
        } else if (is_array(static::$primary_key)) {
            $id = array();
            foreach (static::$primary_key as $key) {
                if (isset($properties[$key])) {
                    $id[$key] = $properties[$key];
                } else {
                    $id[$key] = Db::get_write()->lastInsertID();
                }
            }
        } else {
            $id = Db::get_write()->lastInsertID();
        }

        if (!isset($id)) {
            throw new \Exception("Error in creating row.");
        }

        return new static($id);
    }

    /**
     * Syncs the object with the database
     */
    public function update()
    {
        if (count($this->needing_update) === 0) {
            return; // No updates to do
        }

        $this->check_deleted();


        $sql = \TinyDb\Sql::create()->update(static::$table_name);
        $sql = $this->where_this($sql);

        // Build a list of updates to do
        foreach ($this->needing_update as $field) {
            $sql->set("`$field` = ?", static::unfix_type($field, $this->$field));
        }

        // Update modified timestamp
        if (isset($this->modified_at)) {
            $this->modified_at = time();
            $sql->set('`modified_at` = ?', self::mdb_timestamp($this->modified_at));
        }

        // Update the database
        $result = Db::get_write()->prepare($sql->get_sql());
        self::check_mdb2_error($result);
        $result = $result->execute($sql->get_paramaters());
        self::check_mdb2_error($result);

        // Clear the list of updates to make
        $this->needing_update = array();
    }

    /**
     * Deletes the object from the database
     */
    public function delete()
    {
        $this->check_deleted();

        $sql = \TinyDb\Sql::create()->delete()->from(static::$table_name);
        $sql = $this->where_this($sql);

        Db::get_write()->prepare($sql->get_sql())->execute($sql->get_paramaters());
        $this->is_deleted = TRUE;
    }

    /**
     * Checks if the passed instance is equal to the current instance by table and primary key.
     * @param  Model   $to_compare  Model to check equality with
     * @return boolean              Result
     */
    public function equals($to_compare)
    {
        if (static::$table_name != $to_compare::$table_name || static::$primary_key != $to_compare::$primary_key) {
            return FALSE;
        }

        if (is_array(static::$primary_key)) {
            foreach (static::$primary_key as $key) {
                if ($this->$key != $to_compare->$key) {
                    return FALSE;
                }
            }
        } else {
            if ($this->{static::$primary_key} != $to_compare->{$to_compare::$primary_key}) {
                return FALSE;
            }
        }

        return TRUE;
    }

    /**
     * Appends a where statement to a Sql query to select the current object by primary key
     * @param  Sql $sql Sql query to add selector
     * @return Sql      Sql query with selector
     */
    protected function where_this($sql)
    {
        if (is_array(static::$primary_key)) {
            foreach (static::$primary_key as $key) {
                $sql->where("`$key` = ?", $this->$key);
            }
        } else {
            $sql->where('`' . static::$primary_key . '` = ?', $this->{static::$primary_key});
        }

        return $sql;
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
        if (static::$instance[static::$table_name]['reflector']->hasMethod($getter_name)) {
            return $this->$getter_name();
        }

        // If we're trying to get a field in the table, allow it
        else if (isset(static::$instance[static::$table_name]['table_layout'][$key])) {
            return $this->$key;
        }

        // Otherwise, don't let the user get the param
        else {
            throw new \TinyDb\AccessException("Read access to paramater $key is not allowed.");
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
            throw new \TinyDb\ValidationException("$key did not pass validation.");
        }

        $setter_name = '__set_' . $key;

        // Don't allow changes to pks or timestamps
        if ($key === 'created_at' || $key === 'modified_at' ||
            (is_array(static::$primary_key) &&in_array($key, static::$primary_key)) ||
            (!is_array(static::$primary_key) && $key === static::$primary_key)) {

            throw new \TinyDb\AccessException("Write access to paramater $key is not allowed.");
        }

        // If there's a defined setter, call it
        else if (static::$instance[static::$table_name]['reflector']->hasMethod($setter_name)) {
            $this->$setter_name($val);
        }

        // If we're trying to set a field in the table, allow it, and autotypecast
        else if (isset(static::$instance[static::$table_name]['table_layout'][$key])) {
            $this->$key = $val;

            $this->invalidate($key);
        }

        // Otherwise, don't let the user set the param
        else {
            throw new \TinyDb\AccessException("Write access to paramater $key is not allowed.");
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
        // Check if it's marked optional
        if (static::$instance[static::$table_name]['reflector']->hasProperty('__optional_' . $key) &&
            $this->{"__optional_$key"} === TRUE &&
            ($val === NULL || $val === '')) {
            return TRUE;
        }

        $validator_name = '__validate_' . $key;
        // If there's a defined validation method, call it
        if (static::$instance[static::$table_name]['reflector']->hasMethod($validator_name)) {
            return $this->$validator_name($val);
        }

        // If there's a defined validation string, validate for that type
        else if (static::$instance[static::$table_name]['reflector']->hasProperty($validator_name)) {
            switch($this->$validator_name)
            {
                case 'string':
                case 'str':
                    return is_string($val);
                case 'integer':
                case 'int':
                    return is_int($val);
                case 'boolean':
                case 'bool':
                    return is_bool($val);
                case 'email':
                    return preg_match("/\w+([-+.']\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/", $val);
                case 'url':
                    return preg_match(
                            '/^(https?):\/\/'.                                         // protocol
                            '(([a-z0-9$_\.\+!\*\'\(\),;\?&=-]|%[0-9a-f]{2})+'.         // username
                            '(:([a-z0-9$_\.\+!\*\'\(\),;\?&=-]|%[0-9a-f]{2})+)?'.      // password
                            '@)?(?#'.                                                  // auth requires @
                            ')((([a-z0-9]\.|[a-z0-9][a-z0-9-]*[a-z0-9]\.)*'.                      // domain segments AND
                            '[a-z][a-z0-9-]*[a-z0-9]'.                                 // top level domain  OR
                            '|((\d|[1-9]\d|1\d{2}|2[0-4][0-9]|25[0-5])\.){3}'.
                            '(\d|[1-9]\d|1\d{2}|2[0-4][0-9]|25[0-5])'.                 // IP address
                            ')(:\d+)?'.                                                // port
                            ')(((\/+([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)*'. // path
                            '(\?([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)'.      // query string
                            '?)?)?'.                                                   // path and query string optional
                            '(#([a-z0-9$_\.\+!\*\'\(\),;:@&=-]|%[0-9a-f]{2})*)?'.      // fragment
                            '$/i',
                            $val
                            );
                case 'phone':
                    return preg_match("/1?\s*\W?\s*([2-9][0-8][0-9])\s*\W?\s*([2-9][0-9]{2})\s*\W?\s*([0-9]{4})(\se?x?t?(\d*))?/", $val);
                case 'ssn':
                    return preg_match("/^(\d{3}-?\d{2}-?\d{4}|XXX-XX-XXXX)$/", $val);
                case 'date':
                case 'time';
                case 'datetime':
                    return mktime($val) > 0;
                default:
                    return FALSE;
            }
        }

        // If this is in the table structure, use that to validate
        else if (isset(static::$instance[static::$table_name]['table_layout'][$key])) {
            // Try to cast it to its proper type using fixval, then check if it's typewise the same
            try {
                return static::fix_type($key, $val) == $val;
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
            throw new \Exception('Cannot access a deleted object');
        }
    }

    /**
     * Populates the structure of the model from the database into a late static binding
     */
    public static function populate_table_layout()
    {
        // Populate the table layout.
        if (!isset(static::$instance[static::$table_name]['table_layout'])) {
            $sql = 'SHOW COLUMNS FROM `' . static::$table_name . '`;';
            $describe = Db::get_read()->getAll($sql, NULL, array(), NULL, MDB2_FETCHMODE_ASSOC);
            self::check_mdb2_error($describe);
            static::$instance[static::$table_name]['table_layout'] = array();
            foreach ($describe as $field) {
                $key = $field['Key'] !== ''? $field['Key'] : NULL;
                $default = $field['Default'] !== '' ? $field['Default'] : NULL;
                $type = $field['Type'];
                $values = array();
                $length = NULL;

                if (strpos($type, '(')) {
                    list($type_name, $type_details) = explode('(', $type);

                    $type_details = substr($type_details, 0, strrpos($type_details, ')'));
                    $type_name = strtolower($type_name);

                    if ($type_name == 'set' || $type_name == 'enum') {
                        $new_values = explode(',', $type_details);
                        foreach ($new_values as $val) {
                            $val = substr($val, 1, strlen($val) - 2);
                            $val = str_replace('\\\\', '\\', $val);
                            $val = str_replace('\'\'', '\'', $val);
                            $values[] = $val;
                        }
                    } else {
                        $length = $type_details;
                    }

                    $type = $type_name;
                }

                $type = strtolower($type);

                if (!preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]+$/', $field['Field'])) {
                    throw new \Exception('Table "' . static::$table_name . '" could not be bound because "' . $field['Field'] .
                                         '" is not a valid variable name in PHP.');
                }

                static::$instance[static::$table_name]['table_layout'][$field['Field']] = (Object)array(
                                                                                                    'type' => $type,
                                                                                                    'null' => $field['Null'] == 'YES',
                                                                                                    'default' => $default,
                                                                                                    'key' => $key,
                                                                                                    'values' => $values,
                                                                                                    'length' => $length,
                                                                                                    'auto_increment' => $field['Extra'] == 'auto_increment'
                                                                                                );
            }
        }

        return static::$instance[static::$table_name]['table_layout'];
    }

    /**
     * Auto-casts the value into the proper type for its field
     * @param string $key Name of the paramater
     * @param mixed  $val Value to autocast
     */
    protected static function fix_type($key, $val)
    {
        if (isset(static::$instance[static::$table_name]['table_layout'][$key])) {
            $type = static::$instance[static::$table_name]['table_layout'][$key]->type;

            switch ($type) {
                case 'bit':
                case 'bool':
                case 'tinyint':
                    return $val == 1;
                case 'date':
                case 'datetime':
                case 'timestamp':
                case 'time':
                    return self::unmdb_timestamp($val);
                case 'int':
                case 'smallint':
                case 'mediumint':
                case 'bigint':
                case 'decimal':
                case 'float':
                case 'double':
                case 'real':
                case 'year':
                    return intval($val);
                case 'tinytext':
                case 'text':
                case 'mediumtext':
                case 'longtext':
                case 'blob':
                case 'tinyblob':
                case 'mediumblob':
                case 'longblob':
                case 'enum':
                case 'set':
                case 'varchar':
                case 'char':
                case 'binary':
                case 'varbinary':
                    return strval($val);
                default:
                    return $val;
            }
        } else {
            return $val;
        }
    }

    /**
     * Auto-casts the value back into the proper type for its field for the db
     * @param string $key Name of the paramater
     * @param mixed  $val Value to autocast
     */
    protected static function unfix_type($key, $val)
    {
        if (isset(static::$instance[static::$table_name]['table_layout'][$key])) {
            $type = static::$instance[static::$table_name]['table_layout'][$key]->type;

            if(substr($type, 0, 8) === 'datetime' || substr($type, 0, 4) === 'date' || substr($type, 0, 4) === 'time') {
                return self::mdb_timestamp($val);
            } else {
               return $val;
            }
        } else {
            return $val;
        }
    }

    private static function check_mdb2_error($result)
    {
        if (\PEAR::isError($result)) {
            throw new \Exception($result->getMessage() . ', ' . $result->getDebugInfo());
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

    public function __toString()
    {
        return strval($this->{static::$primary_key});
    }

    public function __destruct()
    {
        $this->update();
    }
}
