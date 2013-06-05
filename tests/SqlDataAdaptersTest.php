<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class SqlDataAdaptersTest extends PHPUnit_Framework_TestCase
{
    private $tz;
    public function setUp()
    {
        $this->tz = date_default_timezone_get();
        date_default_timezone_set('America/Los_Angeles');
    }

    public function tearDown()
    {
        date_default_timezone_set($this->tz);
    }

    public function testEncode()
    {
        $this->assertEquals(true, \TinyDb\Internal\SqlDataAdapters::encode('bit', 1));
        $this->assertEquals(false, \TinyDb\Internal\SqlDataAdapters::encode('bit', 0));
        $this->assertEquals('2013-06-05 21:52:40', \TinyDb\Internal\SqlDataAdapters::encode('date', 1370469160));
        $this->assertEquals(2014, \TinyDb\Internal\SqlDataAdapters::encode('year', '2014'));
        $this->assertEquals(2014, \TinyDb\Internal\SqlDataAdapters::encode('int', '2014'));
        $this->assertEquals(80.07, \TinyDb\Internal\SqlDataAdapters::encode('float', '80.07'));
        $this->assertEquals('t', \TinyDb\Internal\SqlDataAdapters::encode('char', 'tinydb'));
        $this->assertEquals('tinydb', \TinyDb\Internal\SqlDataAdapters::encode('varchar', 'tinydb'));
        $this->assertEquals('foo,bar,foobar', \TinyDb\Internal\SqlDataAdapters::encode('set', array('foo', 'bar', 'foobar')));
    }

    public function testDecode()
    {
        $this->assertEquals(true, \TinyDb\Internal\SqlDataAdapters::decode('bit', 1));
        $this->assertEquals(1370469160, \TinyDb\Internal\SqlDataAdapters::decode('date', '2013-06-05 21:52:40'));
    }
}
