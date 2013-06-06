<?php

namespace TinyDb\Internal;

require_once(dirname(__FILE__) . '/require.php');

class Collection implements \ArrayAccess, \Countable, \Iterator
{
    protected $class;
    protected $rows;
    protected $instantiated_models = array();
    public function __construct($class, $rows)
    {
        $this->class = $class;
        $this->rows = $rows;
    }

    /* # Implementations */

    /* ## ArrayAccess */
    public function offsetExists($offset)
    {
        return is_int($offset) && count($this) > $offset;
    }

    public function offsetGet($offset)
    {
        if (!isset($this->instantiated_models[$offset])) {
            $class = $this->class;
            $this->instantiated_models[$offset] = new $class($this->rows[$offset]);
        }
        return $this->instantiated_models[$offset];
    }

    public function offsetSet($offset, $val)
    {
        throw new \TinyDb\AccessException($offset);
    }

    public function offsetUnset($offset)
    {
        throw new \TinyDb\AccessException($offset);
    }

    /* ## Countable */
    public function count()
    {
        return count($this->rows);
    }

    /* ## Iterator */
    private $iterator_current = 0;
    public function current()
    {
        return $this[$this->iterator_current];
    }

    public function key()
    {
        return $this->iterator_current;
    }

    public function next()
    {
        $this->iterator_current += 1;
    }

    public function rewind()
    {
        $this->iterator_current = 0;
    }

    public function valid()
    {
        return isset($this[$this->iterator_current]);
    }
}
