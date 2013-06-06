<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class OrmTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        \TinyDb\Db::set('mysql://root:foobar@localhost/foobar');
        try { \TinyDb\Query::drop_table('OrmTest'); } catch (\Exception $ex) {}
        try { \TinyDb\Query::drop_table('OrmTestExtern'); } catch (\Exception $ex) {}
        \TinyDb\Query::create_table('OrmTest', array(
            'username' => array(
                'type' => 'VARCHAR(255)',
                'key' => 'primary'
            ),
            'password' => array(
                'type' => 'VARCHAR(255)',
                'null' => TRUE
            ),
            'externID' => array(
                'type' => "int",
                'null' => true
            ),
            'foobar' => array(
                'type' => "set('foo','bar')"
            ),
            'xyz' => array(
                'type' => 'int'
            )
        ));
        \TinyDb\Query::create_table('OrmTestExtern', array(
            'externID' => array(
                'type' => "int",
                'key' => 'primary',
                'auto_increment' => true
            ),
            'name' => array(
                'type' => 'varchar(255)'
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

    public function testGetterSetter()
    {
        $user = new OrmTestClass(array(
            'username' => 'tylermenezes',
            'password' => 'Hunter5',
            'foobar' => 'foo',
            'xyz' => 10
        ));

        $this->assertEquals('bar', $user->bar);
        $user->bar = 'hhh';
        $this->assertEquals('hhh', $user->ggg);

        $user->delete();
    }

    public function testExterns()
    {
        $extern = new OrmTestExternClass(array(
            'name' => 'Tyler'
        ));
        $user = new OrmTestClass(array(
            'username' => 'tylermenezes2',
            'password' => 'Hunter5',
            'foobar' => 'foo',
            'externID' => $extern->id,
            'xyz' => 10
        ));

        $this->assertEquals('Tyler', $user->extern->name);

        $extern->delete();
        $user->delete();
    }

    public function tearDown()
    {
        \TinyDb\Query::drop_table('OrmTest');
        \TinyDb\Query::drop_table('OrmTestExtern');
    }
}

class OrmTestClass extends \TinyDb\Orm
{
    public static $table_name = 'OrmTest';

    public $username;
    protected $password;
    public $foobar;
    public $xyz;

    /**
     * External thing
     * @foreign OrmTestExternClass extern
     */
    public $externID;

    public $ggg = false;

    public function get_bar()
    {
        return 'bar';
    }

    public function set_bar($value)
    {
        $this->ggg = $value;
    }
}

class OrmTestExternClass extends \TinyDb\Orm
{
    public static $table_name = 'OrmTestExtern';

    public $externID;
    public $name;
}
