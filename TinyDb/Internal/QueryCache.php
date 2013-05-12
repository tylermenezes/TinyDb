<?php

/**
 * QueryCache - Caches queries intelligently
 *
 * @author      Tyler Menezes <tylermenezes@gmail.com>
 * @copyright   Copyright (c) 2013 Tyler Menezes.       Released under the BSD license.
 */
class QueryCache
{
    protected $cache = array();

    public function invalidate_table($table_name)
    {
        if (isset($this->cache[$table_name])) {
            unset($this->cache[$table_name]);
        }
    }

    public function get_cached($query)
    {
        // Is the query a select, and do we have cache entries for that table?
        if ($query->get_query_type() === 'select' &&
            isset($this->cache[$query->get_from()])) {
            $table_cache = $this->cache[$query->get_from()];
            $hash = $this->get_query_hash($query);

            // Do we have a query matching this hash?
            if (isset($table_cache[$hash])) {
                $cache_entry = $table_cache[$hash];

                // Do we have enough records to construct the result set?
                if ($cache_entry['limit'] >= $query->get_limit()) {
                    // Cache hit!
                    $results = $cache_entry['results'];
                    return array_slice($results, 0, min(count($results), $query->get_limit()));
                }
            }
        }

        // Cache miss.
        return null;
    }

    public function add($query, $results)
    {
        $hash = $this->get_query_hash($query);
        $limit = $query->get_limit();

        if (!isset($this->cache[$query->get_from()])) {
            $this->cache[$query->get_from()] = array();
        }

        $this->cache[$query->get_from()][$hash] = array(
            'hash' => $hash,
            'limit' => $limit,
            'results' => $results
        );
    }

    protected function get_query_hash($query)
    {
        $hashable_objects = array('selects', 'wheres', 'havings', 'order_bys', 'group_bys', 'unions',
                                  'joins', 'start');

        $hash = '';
        foreach($hashable_objects as $obj_name) {
            $method_name = 'get_' . $obj_name;
            $obj = $query->$method_name();

            $hash .= $this->get_subhash($obj);
        }

        return hash('sha1', $hash);
    }

    protected function get_subhash($object)
    {
        if (is_array($object)) {
            sort($object, SORT_STRING); // Why is this mutable? When will sort() fail?! Why doesn't it throw?
        }

        $hashable = serialize($object);
        return hash('sha1', $hashable);
    }
}
