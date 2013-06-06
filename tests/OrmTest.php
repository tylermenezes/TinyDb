<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class OrmTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        \TinyDb\Db::set('mysql://root:foobar@localhost/foobar');
        try { \TinyDb\Query::drop_table('OrmTest'); } catch (\Exception $ex) {}
        \TinyDb\Query::create_table('OrmTest', array(
            'username' => array(
                'type' => 'VARCHAR(255)',
                'key' => 'primary'
            ),
            'password' => array(
                'type' => 'VARCHAR(255)',
                'null' => TRUE
            ),
            'foobar' => array(
                'type' => "set('foo','bar')"
            ),
            'xyz' => array(
                'type' => 'int'
            )
        ));
    }

    public function testCreate()
    {
        $user = new OrmTestClass(array(
            'username' => 'tylermenezes',
            'password' => 'Hunter5',
            'foobar' => 'foo',
            'xyz' => 10
        ));

        $this->assertEquals('tylermenezes', $user->username);
        $this->assertEquals(array('foo'), $user->foobar);
        $this->assertEquals(10, $user->xyz);

        $user->delete();
    }

    public function testCollection()
    {
        new OrmTestClass(array(
            'username' => 'tylermenezes',
            'password' => 'Hunter5',
            'foobar' => 'foo',
            'xyz' => 10
        ));

        new OrmTestClass(array(
            'username' => 'tylermenezes2',
            'password' => 'Hunter6',
            'foobar' => 'foo',
            'xyz' => 10
        ));

        $names = array('tylermenezes', 'tylermenezes2');

        $i = 0;
        foreach (OrmTestClass::find()->all() as $row) {
            $this->assertEquals($names[$i], $row->username);
            $row->delete();
            $i++;
        }

    }

    public function testFind()
    {
        new OrmTestClass(array(
            'username' => 'tylermenezes',
            'password' => 'Hunter5',
            'foobar' => 'foo',
            'xyz' => 10
        ));

        $user = OrmTestClass::find()->where('username = ?', 'tylermenezes')->one();
        $this->assertEquals('tylermenezes', $user->username);

        $user->delete();
        $this->assertEquals(0, \TinyDb\Query::create()->select('COUNT(*)')->from('OrmTest')->exec());
    }

    public function testUpdate()
    {
        $user = new OrmTestClass(array(
            'username' => 'tylermenezes',
            'password' => 'Hunter5',
            'foobar' => 'foo',
            'xyz' => 10
        ));

        $user->xyz = 110;
        $user->update();

        $user = OrmTestClass::find('tylermenezes');
        $this->assertEquals(110, $user->xyz);

        $user->delete();
        $this->assertEquals(0, \TinyDb\Query::create()->select('COUNT(*)')->from('OrmTest')->exec());
    }

    public function testDelete()
    {
        $user = new OrmTestClass(array(
            'username' => 'tylermenezes',
            'password' => 'Hunter5',
            'foobar' => 'foo',
            'xyz' => 10
        ));
        $this->assertEquals(1, \TinyDb\Query::create()->select('COUNT(*)')->from('OrmTest')->exec());
        $user->delete();
        $this->setExpectedException('\TinyDb\NoRecordException');
        $user->username;

        $this->assertEquals(0, \TinyDb\Query::create()->select('COUNT(*)')->from('OrmTest')->exec());
    }

    public function tearDown()
    {
        \TinyDb\Query::drop_table('OrmTest');
    }
}

class OrmTestClass extends \TinyDb\Orm
{
    public static $table_name = 'OrmTest';

    public $username;
    protected $password;
    public $foobar;
    public $xyz;
}
