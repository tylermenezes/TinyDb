<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class AccessManagerTest extends PHPUnit_Framework_TestCase
{
    protected $access_manager;
    public function setUp()
    {
        $class = new AccessManagerTestClass();
        $this->access_manager = new \TinyDb\Internal\AccessManager(new \ReflectionObject($class));
        $class->unset_everything();
    }

    public function testVisibility()
    {
        $this->assertEquals(999999, $this->access_manager->get_publicity('none_var'));
        $this->assertEquals(T_PUBLIC, $this->access_manager->get_publicity('default_var'));
        $this->assertEquals(T_PUBLIC, $this->access_manager->get_publicity('public_var'));
        $this->assertEquals(T_PROTECTED, $this->access_manager->get_publicity('protected_var'));
        $this->assertEquals(T_PRIVATE, $this->access_manager->get_publicity('private_var'));
    }

    public function testDocblock()
    {
        $this->assertEquals(array('foobar' => 'bar foo', 'var' => 'string'), $this->access_manager->get_information('default_var'));
    }
}

class AccessManagerTestClass
{
    /**
     * Foobar bar foo
     * @foobar bar foo
     * @var string
     */
    var $default_var = 'foo';
    public $public_var = 'foo';
    protected $protected_var = 'foo';
    private $private_var = 'foo';

    public function unset_everything()
    {
        unset($this->default_var);
        unset($this->public_var);
        unset($this->protected_var);
        unset($this->private_var);
    }
}
