<?php

namespace TinyDb\Internal;

require_once(dirname(__FILE__) . '/require.php');

trait Properties
{
    private $tinydb_internal_properties_reflector = null;
    private function tinydb_internal_properties_get_reflector()
    {
        if ($this->tinydb_internal_properties_reflector === null) {
            $this->tinydb_internal_properties_reflector = new \ReflectionClass($this);
        }
        return $this->tinydb_internal_properties_reflector;
    }

    public function __get($k)
    {
        $getter_method = 'get_' . $k;
        if ($this->tinydb_internal_properties_get_reflector()->hasMethod($getter_method)) {
            return $this->$getter_method();
        } else {
            throw new \TinyDb\AccessException('Could not access property ' . $k);
        }
    }

    public function __set($k, $v)
    {
        $setter_method = 'set_' . $k;
        if ($this->tinydb_internal_properties_get_reflector()->hasMethod($setter_method)) {
            $this->$setter_method($v);
        } else {
            throw new \TinyDb\AccessException('Could not access property ' . $k);
        }
    }
}
