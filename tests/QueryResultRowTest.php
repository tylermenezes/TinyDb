<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class QueryResultRowTest extends PHPUnit_Framework_TestCase
{
    protected $row_raw;
    protected $row;

    public function setUp()
    {
        $this->row_raw = array('foo' => 1, 'bar' => 'two');
        $this->row = new \TinyDb\Internal\Query\Result\Row($this->row_raw);
    }

    public function testIntIndexes()
    {
        $this->assertEquals(1, $this->row[0]);
        $this->assertEquals('two', $this->row[1]);
    }

    public function testStrIndexes()
    {
        $this->assertEquals(1, $this->row['foo']);
        $this->assertEquals('two', $this->row['bar']);
    }

    public function testCount()
    {
        $this->assertEquals(2, count($this->row));
    }

    public function testExists()
    {
        $this->assertEquals(false, isset($this->row['xyz']));
        $this->assertEquals(true, isset($this->row['foo']));
        $this->assertEquals(true, isset($this->row[0]));
        $this->assertEquals(false, isset($this->row[5]));
    }

    public function testIterator()
    {
        $i = 0;
        foreach ($this->row as $k => $v) {
            $this->assertEquals(array_keys($this->row_raw)[$i], $k);
            $this->assertEquals($this->row_raw[$k], $v);
            $i++;
        }
    }
}
