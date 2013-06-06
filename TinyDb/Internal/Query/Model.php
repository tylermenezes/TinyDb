<?php

namespace TinyDb\Internal\Query;

require_once(dirname(__FILE__) . '/../require.php');

class Model
{
    protected $query = null;
    protected $class = null;
    public function __construct($class)
    {
        $this->class = $class;
        $this->query = new \TinyDb\Query();
        $this->query = $this->query->select('`' . $class::$table_name . '`.*')->from($class::$table_name);
    }

    public function one()
    {
        $data = $this->query->limit(1)->exec(false);
        $class = $this->class;
        return new $class($data[0]);
    }

    public function all()
    {
        $data = $this->query->exec(false);
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
