<?php

namespace TinyDb\Internal\Query;

require_once(dirname(__FILE__) . '/../require.php');

use \TinyDb\Internal\Query\Result;

class Result implements \ArrayAccess, \Countable, \Iterator
{
    use \TinyDb\Internal\Properties;

    protected $rows = array();

    public function __construct($rows)
    {
        foreach ($rows as $row) {
            $this->rows[] = new Result\Row($row);
        }
    }

    public function get_dimensions()
    {
        $x = count($this->rows);
        if ($x === 0) {
            $y = 0;
        } else {
            $y = count($this->rows[0]);
        }

        return array($x, $y);
    }

    /* # Implementations */

    /* ## ArrayAccess */
    public function offsetExists($offset)
    {
        return is_int($offset) && count($this) > $offset;
    }

    public function offsetGet($offset)
    {
        return $this->rows[$offset];
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
