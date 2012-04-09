<?php

namespace TinyDb;

/**
 * TinyCollection - a collection of TinyOrm objects.
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2012 Tyler Menezes.       Released under the BSD license.
 */
class Collection implements \ArrayAccess, \Countable, \Iterator
{
    protected $model = NULL;
    protected $query = NULL;
    protected $data = NULL;
    protected $position = 0;

    /**
     * Creates a collection
     * @param string $classname The name of the Orm class the collection holds
     * @param Sql    $query     The query used to populate the collection
     */
    public function __construct($classname, Sql $query)
    {
        $this->model = $classname;
        $this->query = $query;

        $data = Db::get_read()->getAll($this->query->get_sql(), NULL, $this->query->get_paramaters(), NULL, MDB2_FETCHMODE_ASSOC);

        if (\PEAR::isError($data)) {
            throw new \Exception($data->getMessage() . ', ' . $data->getDebugInfo());
        }

        $this->data = $data;
    }

    /* Interface Implementations */
    public function count()
    {
        return count($this->data);
        $this->populate();
    }

    public function offsetExists($offset)
    {
        return $this->count() > $offset;
    }

    public function offsetGet($offset)
    {
        $model = new $this->model();
        $model->data_fill($this->data[$offset]);
        return $model;
    }

    public function offsetSet($offset, $val)
    {
        throw new \Exception("Cannot set data in a collection");
    }

    public function offsetUnset($offset)
    {
        throw new \Exception("Cannot unset data in a collection");
    }

    public function current()
    {
        return $this->offsetGet($this->position);
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        $this->position++;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function valid()
    {
        return $this->offsetExists($this->position);
    }
}