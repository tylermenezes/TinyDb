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
    public function __construct($classname, $query_or_model_array)
    {
        $this->model = $classname;

        if (is_array($query_or_model_array)) {
            $this->data = $query_or_model_array;
        } else {
            $this->query = $query_or_model_array;

            if (!$this->query->has_from()) {
                $this->query->select($classname::$table_name . '.*')->from($classname::$table_name);
            }

            $data = Db::get_read()->getAll($this->query->get_sql(), NULL, $this->query->get_paramaters(), NULL, MDB2_FETCHMODE_ASSOC);

            if (\PEAR::isError($data)) {
                throw new \Exception($data->getMessage() . ', ' . $data->getDebugInfo() . "\n" . $this->query);
            }

            $this->data = $data;

            foreach ($this->data as $row) {
                if ((array)$classname::$primary_key === $classname::$primary_key) {
                    $lookup = array();
                    foreach ($classname::$primary_key as $key) {
                        $lookup[$key] = $row[$key];
                    }
                } else {
                    $lookup = $row[$classname::$primary_key];
                }
                Orm::$instance[$classname::$table_name]['data_cache'][json_encode($lookup)] = $row;
            }
        }
    }

    /**
     * Executes a function on each element of the collection.
     * @param  callable  $lambda  A function taking one paramater - the model
     * @return array              Array of responses
     */
    public function each($lambda)
    {
        $resp = array();
        foreach ($this as $model) {
            $resp[] = $lambda($model);
        }

        return $resp;
    }

    /**
     * Finds all elements with a given filter function
     * @param  callable  $lambda  A function taking one paramater - the model - which returns TRUE if it should be included
     * @return Collection         Filtered collection
     */
    public function find($lambda)
    {
        $models = $this->each(function($model) use($lambda){
            if ($lambda($model) === TRUE) {
                return $model;
            }
        });

        $models = array_merge(array(), array_filter($models));

        return new Collection($this->model, $models);
    }

    /**
     * Gets the first model matching a given filter function
     * @param  callable  $lambda  A function taking one paramater - the model - which returns TRUE if it should be included
     * @return Model              First model matching the filter, or NULL
     */
    public function find_one($lambda)
    {
        $all = $this->find($lambda);
        if (count($all) > 0) {
            return $all[0];
        } else {
            return NULL;
        }
    }

    /**
     * Filters all elements not matching a  given filter function. (A mutable version of find).
     * @param  callable  $lambda  A function taking one paramater - the model - which returns TRUE if it should be included
     * @return Collection         Current collection
     */
    public function filter($lambda)
    {
        $this->data = $this->find($lambda)->data;
        return $this;
    }

    /**
     * Removes the given object from the collection
     * @param  Model      $model_to_remove  Model to remove
     * @return Collection                   Current collection
     */
    public function remove($model_to_remove)
    {
        $this->filter(function($model) use($model_to_remove){
            return (!$model->equals($model_to_remove));
        });

        return $this;
    }

    /**
     * Returns TRUE if any model matches the given filter function, false otherwise.
     * @param  callable  $lambda  A function taking one paramater - the model - which returns TRUE if it matches
     * @return boolean            TRUE if the collection contains at least one Model matching the query, FALSE otherwise
     */
    public function contains($lambda)
    {
        return count($this->find($lambda)) > 0;
    }

    /* Interface Implementations */
    public function count()
    {
        return count($this->data);
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
