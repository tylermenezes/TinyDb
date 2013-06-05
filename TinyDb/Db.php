<?php

namespace TinyDb;
require_once(dirname(__FILE__) . '/Internal/require.php');

/**
 * TinyDbConnection - a class to store DB connections.
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2012-2013 Tyler Menezes.       Released under the BSD license.
 */
class Db
{
    protected static $read = array();
    protected static $write = array();

    /**
     * Gets a database connection for read commands
     * @return \MDB2 MDB2 connection for reading from the database
     */
    public static function get_read()
    {
        if (count(self::$read < 1)) {
            return self::get_write();
        } else {
            return self::$read[rand(0,count(self::$read) - 1)];
        }
    }

    /**
     * Gets a database connection for write commands
     * @return \MDB2 MDB2 connection for writing to the database
     */
    public static function get_write()
    {
        if (count(self::$write) < 1) {
            throw new ConnectionException("No database set!");
        } else {
            return self::$write[rand(0,count(self::$write) - 1)];
        }
    }

    /**
     * Returns an MDB2 connection from a connection string
     * @param  string $connection_strings MDB2 connection string or strings
     * @return \MDB2                      MDB2 database connection
     */
    private static function get_connections_from_strings($connection_strings)
    {
        // If there aren't any connection strings, don't return any connections.
        // This is mostly to avoid complicating the next branch, and to make it slightly more clear what's
        // going on.
        if ($connection_strings === null) {
            return array();
        }

        // If they only passed in one string, make it an array
        if (!is_array($connection_strings)) {
            $connection_strings = array($connection_strings);
        }

        $connections = array();
        foreach ($connection_strings as $connection_string) {
            $connection = \MDB2::connect($connection_string);

            if (\PEAR::isError($connection)) {
                throw new ConnectionException($connection->getMessage() . ',' . $connection->getDebugInfo());
            }

            // The extended module will make everything slightly nicer to work with.
            $connection->loadModule('Extended');

            // Enable case-sensitivity
            $connection->setOption('portability', MDB2_PORTABILITY_ALL & ~MDB2_PORTABILITY_FIX_CASE);

            $connections[] = $connection;
        }

        return $connections;
    }

    /**
     * Sets the read connection to use for TinyDb.
     * @param string $connection_strings MDB2 connection string or strings
     */
    public static function set_read($connection_strings)
    {
        self::$read = self::get_connections_from_strings($connection_strings);
    }

    /**
     * Sets the write connection to use for TinyDb.
     * @param string $connection_strings MDB2 connection string or strings
     */
    public static function set_write($connection_strings)
    {
        self::$write = self::get_connections_from_strings($connection_strings);
    }

    /**
     * Sets the database connection to use for TinyDb. If only write is specified, it will be used for both
     * read and write operations.
     * @param string $write MDB2 connection string or strings for writing to the database
     * @param string $read  Optional, MDB2 connection string or strings for reading from the database
     */
    public static function set($write, $read = NULL) {
        self::set_read($read);
        self::set_write($write);
    }
}
