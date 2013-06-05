<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class QueryTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        \TinyDb\Db::set('mysql://root:foobar@localhost/foobar');
        \TinyDb\Query::create_table('foobar', array(
            'username' => array(
                'type' => 'VARCHAR(255)'
            ),
            'password' => array(
                'type' => 'VARCHAR(255)',
                'null' => TRUE
            )
        ));
    }

    public function testSelect()
    {
        // $this->assertEquals(4, \TinyDb\Query::create()->select('2+2')->exec());
        // $this->assertEquals(array('2+2' => 4, '4+4' => 8), \TinyDb\Query::create()->select('2+2, 4+4')->limit(1)->exec());
        // $this->assertEquals(array(array('2+2' => 4, '4+4' => 8)), \TinyDb\Query::create()->select('2+2, 4+4')->exec());
    }

    public function testInsert()
    {
        \TinyDb\Query::create()->insert()->into('foobar')->values('test', 'foo')->exec();

    }

    public function testParamsFail()
    {
        $this->setExpectedException('InvalidArgumentException');
        $query = new \TinyDb\Query();
        $query->select('*')->from('foobar')->where('foo = ?');
    }

    public function tearDown()
    {
        \TinyDb\Query::drop_table('foobar');
    }
}
