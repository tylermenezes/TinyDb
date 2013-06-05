<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class QueryBuilderTest extends PHPUnit_Framework_TestCase
{
    public function testSelect()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = bar');
        $this->assertEquals($query->get_query_type(), 'select');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` WHERE (foo = bar);');
    }

    public function testInsert()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->insert();
        $this->assertEquals($query->get_query_type(), 'insert');
    }

    public function testInsertPositional()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->insert()->into('foobar')->values('xyz', 'foo', 'bar', 10);
        $this->assertEquals($this->clean_sql($query->get_sql()), "INSERT INTO `foobar` VALUES ('xyz', 'foo', 'bar', 10);");
    }

    public function testInsertArray()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->insert()->into('foobar')->values(array('xyz', 'foo', 'bar', 10));
        $this->assertEquals($this->clean_sql($query->get_sql()), "INSERT INTO `foobar` VALUES ('xyz', 'foo', 'bar', 10);");
    }

    public function testUpdate()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->update('foobar')->set('foo = bar');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'UPDATE `foobar` SET foo = bar;');
    }

    public function testDelete()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->delete()->from('foobar')->where('foo = bar');
        $this->assertEquals($query->get_query_type(), 'delete');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'DELETE FROM `foobar` WHERE (foo = bar);');
    }

    public function testJoin()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = bar')->join('foobar USING (foo = bar)');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` LEFT JOIN foobar USING (foo = bar) WHERE (foo = bar);');

        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = bar')->join('foobar USING (foo = bar)', 'OUTER');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` OUTER JOIN foobar USING (foo = bar) WHERE (foo = bar);');
    }

    public function testHaving()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = bar')->having('no = yes');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` WHERE (foo = bar) HAVING (no = yes);');
    }

    public function testWhere()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = bar')->where('no = yes');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` WHERE (foo = bar) AND (no = yes);');
    }

    public function testGroupBy()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = bar')->group_by('foo');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` WHERE (foo = bar) GROUP BY foo;');
    }

    public function testOrderBy()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = bar')->order_by('foo ASC');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` WHERE (foo = bar) ORDER BY foo ASC;');
    }

    public function testLimit()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = bar')->limit(10);
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` WHERE (foo = bar) LIMIT 10;');
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = bar')->limit(10, 100);
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` WHERE (foo = bar) LIMIT 10,100;');
    }

    public function testParams()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = ?', 'where')->join('foo USING (x = ?)', 'LEFT', 'join')->having('xyz = ?', 'having')->order_by('?', 'order');
        $this->assertEquals($this->clean_sql($query->get_sql()), 'SELECT * FROM `foobar` LEFT JOIN foo USING (x = ?) WHERE (foo = ?) HAVING (xyz = ?) ORDER BY ?;');
        $this->assertEquals($query->get_paramaters(), array('join', 'where', 'having', 'order'));
    }

    public function testParamsFail()
    {
        $this->setExpectedException('InvalidArgumentException');
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foobar')->where('foo = ?');
    }

    private function clean_sql($sql)
    {
        $sql = preg_replace('/[\r\n]*/', '', $sql);
        $sql = preg_replace('/\t+/', ' ', $sql);
        $sql = preg_replace('/ {2,}/', ' ', $sql);
        return $sql;
    }
}
