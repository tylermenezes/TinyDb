<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class QueryResultTest extends PHPUnit_Framework_TestCase
{
    protected $rows_raw;
    protected $rows;

    public function setUp()
    {
        $this->rows_raw = array(array('foo' => 1, 'bar' => 'two'), array('foo' => 2, 'bar' => 'sixty'));
        $this->rows = new \TinyDb\Internal\Query\Result($this->rows_raw);
    }

    public function testCount()
    {
        $this->assertEquals(2, count($this->rows));
    }

    public function testExists()
    {
        $this->assertEquals(true, isset($this->rows[0]));
        $this->assertEquals(false, isset($this->rows[5]));
        $this->assertEquals(false, isset($this->rows['xyz']));
    }
}
