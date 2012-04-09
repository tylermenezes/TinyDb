<?php

namespace TinyDb;

require_once('MDB2.php');
require_once('MDB2/Extended.php');
require_once('MDB2/Date.php');

class Db
{
    protected static $read = NULL;
    protected static $write = NULL;


    /**
     * Gets a database connection for read commands
     * @return \MDB2 MDB2 connection for reading from the database
     */
    public static function get_read()
    {
        return isset(self::$read)? self::$read : self::get_write();
    }

    /**
     * Gets a database connection for write commands
     * @return \MDB2 MDB2 connection for writing to the database
     */
    public static function get_write()
    {
        if (!isset(self::$write)) {
            throw new \Exception("Database not set!");
        } else {
            return self::$write;
        }
    }

    /**
     * Sets the database connection to use for TinyDb.
     * @param \MDB2 $write MDB2 connection for writing to the database
     * @param \MDB2 $read  Optional, read-only MDB2 connection to be used in read-only commands
     */
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