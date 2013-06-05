<?php

namespace TinyDb\Internal;

/**
 * SqlDataAdapters - methods for converting data between SQL and PHP values
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2012-2013 Tyler Menezes.       Released under the BSD license.
 */
class SqlDataAdapters
{
    /**
     * Encodes the value for use in the database
     * @param string $column_type  MySQL column type
     * @param mixed  $val          Value to encode
     */
    public static function encode($column_type, $val)
    {
        switch ($column_type) {
            // The truthy types!
            case 'bit':
            case 'bool':
                return $val == true;

            // The temporal types:
            case 'date':
            case 'datetime':
            case 'timestamp':
            case 'time':
                return self::encode_timestamp($val);
            case 'year':
                return intval($val);


            // The integer types!
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
            case 'serial': // alias for bigint unsigned not null auto_increment unique...
                return intval($val);

            // The floating types!
            case 'decimal':
            case 'float':
            case 'double':
            case 'real':
                return floatval($val);

            // The stringy types!
            case 'char':
                return substr($val, 0, 1);
            case 'varchar':
            case 'tinytext':
            case 'text':
            case 'mediumtext':
            case 'longtext':
                return strval($val);

            // Everything else
            case 'binary':
            case 'varbinary':
            case 'tinyblob':
            case 'mediumblob':
            case 'blob':
            case 'longblob':
                return $val;

            case 'enum':
                return strval($val);

            case 'set':
                if (is_array($val)) {
                    return implode(',', $val);
                } else {
                    return strval($val);
                }

            default:
                return $val;
        }
    }

    /**
     * Decodes the value for use in PHP
     * @param string $column_type  MySQL column type
     * @param mixed  $val          Value to decode
     */
    public static function decode($column_type, $val)
    {
        switch ($column_type) {
            // A few specific overrides...
            case 'bit':
            case 'bool':
                return $val == 1;

            case 'date':
            case 'datetime':
            case 'timestamp':
            case 'time':
                return self::decode_timestamp($val);

            case 'set':
                return explode(',', $val);

            // Everything else just became a string, and will end out the same by running it through encode.
            default:
                return self::encode($column_type, $val);
        }
    }

    /**
     * Gets a SQL-style timestamp in GMT
     * @param  int    $unix_timestamp Unix timestamp
     * @return string                 SQL timestamp
     */
    private static function encode_timestamp($unix_timestamp)
    {
        if (!is_int($unix_timestamp)) {
            return false;
        }

        return gmdate('Y-m-d H:i:s', $unix_timestamp);
    }

    /**
     * Gets a UNIX-style timestamp in GMT
     * @param  string $unix_timestamp SQL timestamp
     * @return int                    Unix timestamp
     */
    private static function decode_timestamp($mdb_timestamp)
    {
        if (is_int($mdb_timestamp)) {
            return $mdb_timestamp;
        }

        $arr = \MDB2_Date::mdbstamp2Date($mdb_timestamp);
        return gmmktime($arr['hour'], $arr['minute'], $arr['second'], $arr['month'], $arr['day'], $arr['year'], -1);
    }
}
