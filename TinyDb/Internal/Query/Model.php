<?php

namespace TinyDb\Internal\Query;

class Model
{
    $query = null;
    $class = null;
    public function __construct($class)
    {
        $this->class = $class;
        $this->query = new \TinyDb\Query();
        $this->query->select('`' . $class::$table_name . '`.*')->from($class::$table_name);
    }

    public function one()
    {
        $data = $query->limit(1)->exec(false);
        $class = $this->class;
        return new $class($data[0]);
    }

    public function many()
    {
        $data = $query->exec(false);
        return new \TinyDb\Internal\Collection($this->class, $data);
    }

    public function __call($name, $args)
    {
        if (in_array($name, \TinyDb\Query::$query_builder_functions)) {
            $this->query = call_user_func_array(array($this->query, $name), $args);
            return $this;
        }
    }
}
