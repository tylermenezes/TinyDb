<?php

namespace TinyDb\Internal\Query\Result;

class Row implements \ArrayAccess, \Countable, \Iterator
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    private function getKey($k)
    {
        if (is_int($k)) {
            $keys = array_keys($this->data);
            if (count($keys) > $k) {
                return $keys[$k];
            }
        } else {
            if (array_key_exists($k, $this->data)) {
                return $k;
            }
        }

        // The key didn't exist!
        throw new \TinyDb\AccessException($k);
    }

    /* # Implementations */

    /* ## ArrayAccess */
    public function offsetExists($offset)
    {
        try {
            $this->getKey($offset);
            return true;
        } catch (\TinyDb\AccessException $ex) {
            return false;
        }
    }

    public function offsetGet($offset)
    {
        return $this->data[$this->getKey($offset)];
    }

    public function offsetSet($offset, $val)
    {
        $this->data[$this->getKey($offset)] = $val;
    }

    public function offsetUnset($offset)
    {
        throw new \TinyDb\AccessException($offset);
    }

    /* ## Countable */
    public function count()
    {
        return count($this->data);
    }

    /* ## Iterator */
    private $iterator_current = 0;
    public function current()
    {
        return $this[$this->iterator_current];
    }

    public function key()
    {
        return $this->getKey($this->iterator_current);
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
