<?php

namespace TinyDb;

require_once('MDB2.php');
require_once('MDB2/Extended.php');
require_once('MDB2/Date.php');

class Db
{
    protected static $read = NULL;
    protected static $write = NULL;

    public static function get_read()
    {
        return isset(self::$read)? self::$read : self::get_write();
    }

    public static function get_write()
    {
        if (!isset(self::$write)) {
            throw new \Exception("Database not set!");
        } else {
            return self::$write;
        }
    }

    public static function set($write, $read = NULL) {
        if (is_string($write)) {
            $write = \MDB2::connect($write);
        }

        if (is_string($read)) {
            $read = \MDB2::connect($read);
        }

        if (\PEAR::isError($write)) {
            throw new \Exception($write->getMessage() . ', ' . $write->getDebugInfo());
        }

        if (isset($read) && \PEAR::isError($read)) {
            throw new \Exception($read->getMessage() . ', ' . $read->getDebugInfo());
        }

        // Force MDB2 to use the extended module, and enable case sensitivity
        if (isset($write)) {
            $write->loadModule('Extended');
            $write->setOption('portability', MDB2_PORTABILITY_ALL & ~MDB2_PORTABILITY_FIX_CASE);
        }

        if (isset($read)) {
            $read->loadModule('Extended');
            $read->setOption('portability', MDB2_PORTABILITY_ALL & ~MDB2_PORTABILITY_FIX_CASE);
        }

        self::$write = $write;
        self::$read = $read;
    }
}