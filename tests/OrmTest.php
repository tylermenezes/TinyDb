<?php

require_once(dirname(__FILE__) . '/../TinyDb/Internal/require.php');

class OrmTest extends PHPUnit_Framework_TestCase
{
}

class OrmTestClass extends \TinyDb\Orm
{
    public static $table_name = 'foobar';

}
