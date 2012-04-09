<?php

namespace TinyDb;

/**
 * TinySQL - a class to represent simple SQL queries.
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2012 Tyler Menezes.       Released under the BSD license.
 */
class Sql
{
    protected $selects = array();
    protected $from = NULL;
    protected $joins = array();
    protected $wheres = array();
    protected $havings = array();
    protected $group_bys = array();
    protected $order_bys = array();
    protected $unions = array();
    protected $limit = NULL;
    protected $start = NULL;

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
     * @param  mixed  $what What to select from the database, or NULL to select *
     * @return Sql          Current Sql statement
     */
    public function select($what = NULL)
    {
        if (isset($what)) {
            $this->selects[] = $what;
        }

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
     * Adds a JOIN clause to the FROM
     * @param  string $what Full JOIN clause, excluding "[(LEFT|RIGHT) ]JOIN "
     * @param  string $type Type of JOIN, e.g. LEFT, RIGHT, INNER, OUTER
     * @return sql          Current Sql statement
     */
    public function join($what, $type = "LEFT")
    {
        $args = func_get_args();
        array_shift($args);

        if (substr_count($query, '?') !== count($args)) {
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

        if (substr_count($query, '?') !== count($args)) {
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
     * @param  mixed $param_1  * NULL to have no limit
     *                         * If $param_2 is NULL, the limit
     *                         * If $param_2 is not NULL, the starting point
     *
     * @param  mixed $param_2  * NULL if there is no limit and/or no starting point
     *                         * Otherwise, the starting point.
     *
     * @return Sql             Current Sql statement
     */
    public function limit($param_1 = NULL, $param_2 = NULL)
    {
        if (isset($param_2)) {
            $this->start = $param_1;
            $this->limit = $param_2;
        } else if (isset($param_1)) {
            $this->start = NULL;
            $this->limit = $param_1;
        } else {
            $this->start = NULL;
            $this->limit = NULL;
        }

        return $this;
    }

    /**
     * Generates a SQL query.
     * @return string Generated SQL query
     */
    public function get_sql()
    {
        // Preflight checks
        if (!isset($this->from)) {
            throw new \Exception("Cannot generate a query without a table.");
        }

        // Add SELECT
        $sql = 'SELECT';
        if (count($this->selects) === 0) {
            $sql .= ' `' . $this->from . '`.*';
        } else {
            foreach ($this->selects as $select) {
                $sql .= ' ' . $select . ',';
            }
            $sql = substr($sql, 0, strlen($sql) - 1);
        }
        $sql .= "\n";

        // Add FROM
        $sql .= "\t" . 'FROM `' . $this->from . '`' . "\n";

        // Add JOIN
        if (count($this->joins) > 0) {
            foreach ($this->joins as $join) {
                $sql .= "\t" .  $join['type'] . ' JOIN ' . $join['what'] . "\n";
            }
        }

        $sql .= "\n";

        // Add WHERE
        if (count($this->wheres) > 0) {
            $first = TRUE;
            foreach ($this->wheres as $where) {
                $sql .= "\t";
                if ($first) {
                    $sql .= 'WHERE';
                    $first = FALSE;
                } else {
                    $sql .= 'AND';
                }
                $sql .= ' (';
                $sql .= $where['query'];
                $sql .= ')' . "\n";
            }
            $sql .= "\n";
        }

        // Add GROUP BY
        if (count($this->group_bys) > 0) {
            $first = TRUE;
            $sql .= "\n\t";
            foreach ($this->group_bys as $group_by) {
                if ($first) {
                    $sql .= 'GROUP BY';
                    $first = FALSE;
                } else {
                    $sql .= ',';
                }

                $sql .= ' ';
                $sql .= $group_by;
            }
            $sql .= "\n";
        }

        // Add HAVING
        if (count($this->havings) > 0) {
            $first = TRUE;
            $sql .= "\n";
            foreach ($this->havings as $having) {
                $sql .= "\t";
                if ($first) {
                    $sql .= 'HAVING';
                    $first = FALSE;
                } else {
                    $sql .= 'AND';
                }
                $sql .= ' (';
                $sql .= $having['query'];
                $sql .= ')' . "\n";
            }
            $sql .= "\n";
        }

        // Add ORDER BY
        if (count($this->order_bys) > 0) {
            $first = TRUE;
            $sql .= "\n\t";
            foreach ($this->order_bys as $order_by) {
                if ($first) {
                    $sql .= 'ORDER BY';
                    $first = FALSE;
                } else {
                    $sql .= ',';
                }

                $sql .= ' ';
                $sql .= $order_by['what'];
            }
            $sql .= "\n";
        }

        // Add UNION
        if (count($this->unions) > 0){
            foreach ($this->unions as $union) {
                $sql .= "\n\t " . ' UNION ' . '(' . $union . ')';
            }
            $sql .= "\n";
        }

        // Add LIMIT
        $sql .= "\t";
        if (isset($this->start) && isset($this->limit)) {
            $sql .= 'LIMIT ' . intval($this->start) . ',' . intval($this->limit);
        } else if (isset($this->limit)) {
            $sql .= 'LIMIT ' . intval($this->limit);
        }

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

        return $args;
    }

    public function __toString()
    {
        return $this->get_sql();
    }
}