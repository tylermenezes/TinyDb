<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class QueryCacheTest extends PHPUnit_Framework_TestCase
{
    public function testSelectHit()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foo')->where('x = y');

        $results = array(array('foobar'), array('barfoo'));

        $queryCache = new \TinyDb\Internal\Query\Cache();
        $queryCache->add($query, $results);
        $this->assertEquals($results, $queryCache->get_cached($query));
    }

    public function testCacheMiss()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foo')->where('x = y');
        $results = array(array('foobar'), array('barfoo'));

        $queryCache = new \TinyDb\Internal\Query\Cache();
        $this->assertEquals(null, $queryCache->get_cached($query));

        $queryCache->add($query, $results);

        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foo')->where('x = x');
        $this->assertEquals(null, $queryCache->get_cached($query));
    }

    public function testCacheMissParams()
    {
        $queryCache = new \TinyDb\Internal\Query\Cache();

        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foo')->where('x = ?', 1);
        $results = array(array('foobar'), array('barfoo'));

        $queryCache->add($query, $results);

        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foo')->where('x = ?', 2);
        $this->assertEquals(null, $queryCache->get_cached($query));
    }

    public function testCacheClear()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foo')->where('x = y');

        $results = array(array('foobar'), array('barfoo'));

        $queryCache = new \TinyDb\Internal\Query\Cache();
        $queryCache->add($query, $results);
        $queryCache->invalidate_table('foo');
        $this->assertEquals(null, $queryCache->get_cached($query));
    }

    public function testExtraResultsHit()
    {
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foo')->where('x = y')->limit(10);
        $results = array(array('foobar'), array('barfoo'));
        $queryCache = new \TinyDb\Internal\Query\Cache();
        $queryCache->add($query, $results);
        $query = new \TinyDb\Internal\Query\Builder();
        $query->select('*')->from('foo')->where('x = y')->limit(1);
        $this->assertEquals(array($results[0]), $queryCache->get_cached($query));
    }
}
