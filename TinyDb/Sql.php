<?php

namespace TinyDb;

require_once(dirname(__FILE__) . '/Db.php');

/**
 * TinySql - a class to represent and execute simple SQL queries.
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2012-2013 Tyler Menezes.       Released under the BSD license.
 */
class Sql
{
    protected $select = true;
    protected $insert = true;
    protected $delete = true;

    protected $from = null;
    protected $into = null;
    protected $update = null;

    protected $selects = array();

    protected $cols = array();
    protected $vals = array();

    protected $sets = array();
    protected $joins = array();
    protected $wheres = array();
    protected $havings = array();
    protected $group_bys = array();
    protected $order_bys = array();
    protected $unions = array();
    protected $limit = null;
    protected $start = null;

    public static $query_count = 0;

    /**
     * Factory-style creator so SQL commands can be created without using a variable
     * @return Sql New SQL connection
     */
    public static function create()
    {
        return new self();
    }

    /**
     * Creates a SELECT query
     * @param  mixed  $what What to select from the database, or null to select *
     * @return Sql          Current Sql statement
     */
    public function select($what = null)
    {
        $this->select = true;

        if (isset($what)) {
            $this->selects[] = $what;
        }

        return $this;
    }

    /**
     * Creates an INSERT statement
     * @return Sql Current Sql statement
     */
    public function insert()
    {
        $this->insert = true;
        return $this;
    }

    /**
     * Sets what table to UPDATE
     * @param  string $what Table name to update
     * @return Sql          Current Sql statement
     */
    public function update($what)
    {
        $this->update = $what;
        return $this;
    }

    /**
     * Creates a DELETE statement
     * @return Sql Current Sql statement
     */
    public function delete()
    {
        $this->delete = true;
        return $this;
    }

    /**
     * Sets what table to SELECT from
     * @param  string $what Table name
     * @return Sql          Current Sql statement
     */
    public function from($what)
    {
        $this->from = $what;
        return $this;
    }

    /**
     * Checks if the query has a from statement
     * @return boolean true if the query has a from statement
     */
    public function has_from()
    {
        return count($this->from) > 0;
    }

    /**
     * Sets what table to INSERT into
     * @param  string $what Table name
     * @param  Array  $cols Optional, field names to insert into
     * @return Sql          Current Sql statement
     */
    public function into($what, $cols = null)
    {
        $this->into = $what;

        if (isset($cols)) {
            $this->cols = $cols;
        }

        return $this;
    }

    /**
     * Sets the values to insert or update (if not using set())
     * @param  mixed  $vals Values to insert or update, or param-wise values
     * @return Sql          Current Sql statement
     */
    public function values($vals)
    {
        if (count(func_get_args()) > 1) {
            $vals = func_get_args();
        }
        $this->vals = $vals;

        return $this;
    }

    /**
     * Adds a SET statement
     * @param  string $set The SET clause, excluding "SET "
     * @return Sql         Current Sql statement
     */
    public function set($set)
    {
        $args = func_get_args();
        array_shift($args);

        if (substr_count($set, '?') !== count($args)) {
            throw new \Exception("Query wildcards must have a 1:1 relation with passed paramaters.");
        }

        $this->sets[] = array(
            'query' => $set,
            'params' => $args
        );

        return $this;
    }

    /**
     * Adds a JOIN clause to the FROM
     * @param  string $what Full JOIN clause, excluding "[(LEFT|RIGHT) ]JOIN "
     * @param  string $type Type of JOIN, e.g. LEFT, RIGHT, INNER, OUTER
     * @return sql          Current Sql statement
     */
    public function join($what, $type = "LEFT")
    {
        $args = func_get_args();
        array_shift($args);

        if (substr_count($what, '?') !== count($args)) {
            throw new \Exception("Query wildcards must have a 1:1 relation with passed paramaters.");
        }

        $this->joins[] = array(
            'what' => $what,
            'type' => $type,
            'params' => $args
        );

        return $this;
    }

    /**
     * Adds a WHERE clause
     * @param  string $query The WHERE clause, excluding "WHERE "
     * @return Sql           Current Sql statement
     */
    public function where($query)
    {
        $args = func_get_args();
        array_shift($args);

        if (substr_count($query, '?') !== count($args)) {
            throw new \Exception("Query wildcards must have a 1:1 relation with passed paramaters.");
        }

        $this->wheres[] = array(
            'query' => $query,
            'params' => $args
        );

        return $this;
    }

    /**
     * Adds a HAVING clause
     * @param  string $query The HAVING clause, excluding "HAVING "
     * @return Sql           Current Sql statement
     */
    public function having($query)
    {
        $args = func_get_args();
        array_shift($args);

        if (substr_count($query, '?') !== count($args)) {
            throw new \Exception("Query wildcards must have a 1:1 relation with passed paramaters.");
        }

        $this->havings[] = array(
            'query' => $query,
            'params' => $args
        );

        return $this;
    }

    /**
     * Adds a GROUP BY clause
     * @param  string $what Field to group by
     * @return Sql          Current Sql statement
     */
    public function group_by($what)
    {
        $this->group_bys[] = $what;

        return $this;
    }


    /**
     * Adds an ORDER BY clause
     * @param  string $what Field to order by
     * @return Sql          Current Sql statement
     */
    public function order_by($what)
    {
        $args = func_get_args();
        array_shift($args);

        if (substr_count($what, '?') !== count($args)) {
            throw new \Exception("Query wildcards must have a 1:1 relation with passed paramaters.");
        }

        $this->order_bys[]= array(
            'what' => $what,
            'params' => $args
        );

        return $this;
    }

    /**
     * Adds a UNION to the select
     * @param  string $what Full SELECT clause to UNION
     * @return Sql          Current Sql statement
     */
    public function union($what)
    {
        $this->unions[] = $what;

        return $this;
    }

    /**
     * Sets the LIMIT statement
     * @param  mixed $param_1  * null to have no limit
     *                         * If $param_2 is null, the limit
     *                         * If $param_2 is not null, the starting point
     *
     * @param  mixed $param_2  * null if there is no limit and/or no starting point
     *                         * Otherwise, the starting point.
     *
     * @return Sql             Current Sql statement
     */
    public function limit($param_1 = null, $param_2 = null)
    {
        if (isset($param_2)) {
            $this->start = $param_1;
            $this->limit = $param_2;
        } else if (isset($param_1)) {
            $this->start = null;
            $this->limit = $param_1;
        } else {
            $this->start = null;
            $this->limit = null;
        }

        return $this;
    }

    protected function get_select()
    {
        $sql = "";
        if ($this->select) {
            $sql .= 'SELECT';
            if (count($this->selects) === 0) {
                $sql .= ' `' . $this->from . '`.*';
            } else {
                foreach ($this->selects as $select) {
                    $sql .= ' ' . $select . ',';
                }
                $sql = substr($sql, 0, strlen($sql) - 1);
            }
            $sql .= "\n";
        }

        return $sql;
    }

    protected function get_from()
    {
        if (isset($this->from)) {
            return "\tFROM `" . $this->from . "`\n";
        } else {
            return "";
        }
    }

    protected function get_insert()
    {
        $sql = "";
        if ($this->insert) {
            $sql .= "INSERT\n";
        }

        return $sql;
    }

    protected function get_into()
    {
        $sql = "";
        if (isset($this->into)) {
            $sql .= "\tINTO `" . $this->into . "`";
            if (count($this->cols) > 0) {
                $sql .= "(";
                foreach ($this->cols as $col) {
                    $sql .= "`$col`, ";
                }
                $sql = substr($sql, 0, strlen($sql) - 2);
                $sql .= ")";
            }
            $sql .= "\n";
        }

        return $sql;
    }

    protected function get_update()
    {
        $sql = "";
        if (isset($this->update)) {
            $sql .= 'UPDATE `' . $this->update . "`\n";
        }
        return $sql;
    }

    protected function get_delete()
    {
        $sql = "";
        if ($this->delete) {
            $sql .= "DELETE\n";
        }

        return $sql;
    }

    protected function get_values()
    {
        $sql = "";
        if (count($this->vals) > 0) {
            $sql .= "\tVALUES (";
            foreach ($this->vals as $val) {
                $sql .= $val . ', ';
            }
            $sql = substr($sql, 0, strlen($sql) - 2);
            $sql .= ")\n";
        }

        return $sql;
    }

    protected function get_set()
    {
        $sql = "";
        if (count($this->sets) > 0) {
            $first = true;
            foreach ($this->sets as $field=>$value) {
                if ($first) {
                    $sql .= "\tSET ";
                    $first = true;
                } else {
                    $sql .= "\n\t    ";
                }
                $sql .= $value['query'] . ", ";
            }
            $sql = substr($sql, 0, strlen($sql) - 2);
            $sql .= "\n";
        }

        return $sql;
    }

    protected function get_where()
    {
        $sql = "";
        if (count($this->wheres) > 0) {
            $first = true;
            foreach ($this->wheres as $where) {
                $sql .= "\t";
                if ($first) {
                    $sql .= 'WHERE';
                    $first = true;
                } else {
                    $sql .= '  AND';
                }
                $sql .= ' (';
                $sql .= $where['query'];
                $sql .= ')' . "\n";
            }
        }

        return $sql;
    }

    protected function get_join()
    {
        $sql = "";
        if (count($this->joins) > 0) {
            foreach ($this->joins as $join) {
                $sql .= "\t" .  $join['type'] . ' JOIN ' . $join['what'] . "\n";
            }
            $sql .= "\n";
        }
        return $sql;
    }

    protected function get_group_by()
    {
        $sql = "";
        if (count($this->group_bys) > 0) {
            $first = true;
            $sql .= "\n\t";
            foreach ($this->group_bys as $group_by) {
                if ($first) {
                    $sql .= 'GROUP BY';
                    $first = true;
                } else {
                    $sql .= ',';
                }

                $sql .= ' ';
                $sql .= $group_by;
            }
            $sql .= "\n";
        }
        return $sql;
    }

    protected function get_having()
    {
        $sql = "";
        if (count($this->havings) > 0) {
            $first = true;
            $sql .= "\n";
            foreach ($this->havings as $having) {
                $sql .= "\t";
                if ($first) {
                    $sql .= 'HAVING';
                    $first = true;
                } else {
                    $sql .= 'AND';
                }
                $sql .= ' (';
                $sql .= $having['query'];
                $sql .= ')' . "\n";
            }
            $sql .= "\n";
        }
        return $sql;
    }

    protected function get_order_by()
    {
        $sql = "";
        if (count($this->order_bys) > 0) {
            $first = true;
            $sql .= "\n\t";
            foreach ($this->order_bys as $order_by) {
                if ($first) {
                    $sql .= 'ORDER BY';
                    $first = true;
                } else {
                    $sql .= ',';
                }

                $sql .= ' ';
                $sql .= $order_by['what'];
            }
            $sql .= "\n";
        }
        return $sql;
    }

    protected function get_union()
    {
        $sql = "";
        if (count($this->unions) > 0){
            foreach ($this->unions as $union) {
                $sql .= "\n\t " . ' UNION ' . '(' . $union . ')';
            }
            $sql .= "\n";
        }
        return $sql;
    }

    protected function get_limit()
    {
        $sql = "";
        if (isset($this->start) && isset($this->limit)) {
            $sql .= "\t" . 'LIMIT ' . intval($this->start) . ',' . intval($this->limit) . "\n";
        } else if (isset($this->limit)) {
            $sql .= "\t" . 'LIMIT ' . intval($this->limit) . "\n";
        }
        return $sql;
    }

    /**
     * Generates a SQL query.
     * @return string Generated SQL query
     */
    public function get_sql()
    {
        $sql = "";

        // SELECT
        $sql .= $this->get_select();

        // INSERT
        $sql .= $this->get_insert();
        $sql .= $this->get_into();

        // UPDATE
        $sql .= $this->get_update();

        // DELETE
        $sql .= $this->get_delete();

        // FROM
        $sql .= $this->get_from();

        // VALUES
        $sql .= $this->get_values();

        // SET
        $sql .= $this->get_set();

        // JOIN
        $sql .= $this->get_join();

        // WHERE
        $sql .= $this->get_where();

        // GROUP BY
        $sql .= $this->get_group_by();

        // HAVING
        $sql .= $this->get_having();

        // ORDER BY
        $sql .= $this->get_order_by();

        // UNION
        $sql .= $this->get_union();

        // LIMIT
        $sql .= $this->get_limit();

        $sql = substr($sql, 0, strlen($sql) - 1); // Strip off trailing \n.
        $sql .= ';';

        return $sql;
    }

    /**
     * Gets the ordered array of paramaters for replacing ?s in a prepared query.
     * @return array    Ordered list of paramaters
     */
    public function get_paramaters()
    {
        $args = array();

        foreach ($this->sets as $set) {
            $args = array_merge($args, $set['params']);
        }

        foreach ($this->joins as $join) {
            $args = array_merge($args, $join['params']);
        }

        foreach ($this->wheres as $where) {
            $args = array_merge($args, $where['params']);
        }

        foreach ($this->havings as $having) {
            $args = array_merge($args, $having['params']);
        }

        foreach ($this->order_bys as $order_by) {
            $args = array_merge($args, $order_by['params']);
        }

        self::$query_count++;

        return $args;
    }

    public function exec($magic = true)
    {
        // Load the proper (read/write) database for the operation.
        $handle = null;
        if ($this->select === true) {
            $handle = Db::get_read();
        } else {
            $handle = Db::get_write();
        }

        $rows = $handle->getAll($this->get_sql(), null, $this->get_paramaters(), null, MDB2_FETCHMODE_ASSOC);

        if (\PEAR::isError($rows)) {
            throw new SqlException($rows->getMessage(), $rows->getDebugInfo(), $this->get_sql(),
                                   $this->get_paramaters());
        }

        if ($this->select === true && count($this->selects) === 1 &&
            strpos($this->selects[0], '(') &&
            count($rows) === 1 && count($rows[0]) === 1 &&
            $magic) {
            return $rows[0][0];
        } else if ($this->select === true && $this->limit === 1) {
            return $rows[0];
        } else if ($this->select === true) {
            return $rows;
        } else if ($this->insert === true) {
            return $handle->lastInsertId();
        } else {
            return null;
        }
    }

    public static function show_columns($table)
    {
        $sql = 'SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`;';
        $describe = Db::get_read()->getAll($sql, null, array(), null, MDB2_FETCHMODE_ASSOC);

        if (\PEAR::isError($describe)) {
            throw new SqlException($describe->getMessage(), $describe->getDebugInfo(), $describe->get_sql(),
                                   $describe->get_paramaters());
        }

        $fields = array();
        foreach ($describe as $field)
        {
            $name = $field['Field'];
            $type_full = $field['Type'];
            $nullable = strtolower($field['Null']) === 'yes';
            $default = $field['Default'];
            $extra = $field['Extra'];
            $key = $field['Key'];

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
                $additional_data = substr($type_full, $start_parenth_location,
                                            $end_parenth_location - $start_parenth_location);

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
                    $length = intval(additional_data);
                    $values = null;
                }
            // Not storing extra data inside the type field...
            } else {
                $type = $type_full;
                $length = null;
                $values = null;
            }

            $fields[$name] = array(
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
        return $this->get_sql();
    }
}
