<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class PropertiesTest extends PHPUnit_Framework_TestCase
{
    public function testProperties()
    {
        $test = new PropertiesTestClass();
        $this->assertEquals('foobar', $test->foo);
        $this->assertEquals(false, $test->xyz);
        $test->xyz = true;
        $this->assertEquals(true, $test->xyz);
    }
}

class PropertiesTestClass
{
    use \TinyDb\Internal\Properties;

    protected $xyz = false;
    protected $foo = 'foo';

    public function get_foo()
    {
        return 'foobar';
    }

    public function get_xyz()
    {
        return $this->xyz;
    }

    public function set_xyz($val)
    {
        $this->xyz = $val;
    }
}
