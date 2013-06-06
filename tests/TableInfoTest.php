<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class TableInfoTest extends PHPUnit_Framework_TestCase
{
    protected $table_info;
    public function setUp()
    {
        \TinyDb\Db::set('mysql://root:foobar@localhost/foobar');
        try { \TinyDb\Query::drop_table('foobar'); } catch (\Exception $ex) {}
        \TinyDb\Query::create_table('foobar', array(
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

        $this->table_info = new \TinyDb\Internal\TableInfo('foobar');
    }

    public function testInfo()
    {
        $uname_info = $this->table_info->field_info('username');
        $this->assertEquals('username', $uname_info->name);
        $this->assertEquals('varchar', $uname_info->type);
        $this->assertEquals(255, $uname_info->length);
        $this->assertEquals(null, $uname_info->values);
        $this->assertEquals(false, $uname_info->nullable);
        $this->assertEquals(null, $uname_info->default);
        $this->assertEquals(null, $uname_info->extra);
        $this->assertEquals(false, $uname_info->auto_increment);
        $this->assertEquals('primary', $uname_info->key);

        $pwd_info = $this->table_info->field_info('password');
        $this->assertEquals('password', $pwd_info->name);
        $this->assertEquals(true, $pwd_info->nullable);

        $foobar_info = $this->table_info->field_info('foobar');
        $this->assertEquals('foobar', $foobar_info->name);
        $this->assertEquals(array('foo', 'bar'), $foobar_info->values);
        $this->assertEquals(null, $foobar_info->length);

        $xyz_info = $this->table_info->field_info('xyz');
        $this->assertEquals('xyz', $xyz_info->name);
        $this->assertEquals('int', $xyz_info->type);
        $this->assertEquals(11, $xyz_info->length);
    }

    public function testUtils()
    {
        $this->assertEquals(false, $this->table_info->is_numeric('username'));
        $this->assertEquals(false, $this->table_info->is_integral('username'));
        $this->assertEquals(true, $this->table_info->is_numeric('xyz'));
        $this->assertEquals(true, $this->table_info->is_integral('xyz'));
        $this->assertEquals(true, $this->table_info->is_set('foobar'));
    }

    public function testGetPkey()
    {
        $this->assertEquals('username', $this->table_info->primary_key);

        try { \TinyDb\Query::drop_table('foo3'); } catch (\Exception $ex) {}
        \TinyDb\Query::create_table('foo3', array(
            'id1' => array(
                'type' => 'VARCHAR(255)',
                'key' => 'PRIMARY'
            ),
            'id2' => array(
                'type' => 'VARCHAR(255)',
                'key' => 'PRIMARY'
            )
        ));

        $t = new \TinyDb\Internal\TableInfo('foo3');
        $this->assertEquals(array('id1', 'id2'), $t->primary_key);

        \TinyDb\Query::drop_table('foo3');
    }

    public function testCache()
    {
        try { \TinyDb\Query::drop_table('foo2'); } catch (\Exception $ex) {}
        \TinyDb\Query::create_table('foo2', array(
            'username' => array(
                'type' => 'VARCHAR(255)'
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

        $mem_use_notable = memory_get_usage();
        new \TinyDb\Internal\TableInfo('foo2');
        $mem_use_table = memory_get_usage();
        $this->assertGreaterThan($mem_use_notable, $mem_use_table);

        $alloc_space = $mem_use_table - $mem_use_notable;

        for ($i = 0; $i < 50000; $i++) {
            $f = new \TinyDb\Internal\TableInfo('foo2');
            unset($f);
        }

        $mem_use_lots_tables = memory_get_usage();
        $delta = $mem_use_lots_tables - $mem_use_table;
        $this->assertLessThan($alloc_space * 5000, $delta);

        \TinyDb\Query::drop_table('foo2');
    }

    public function tearDown()
    {
        \TinyDb\Query::drop_table('foobar');
    }
}
