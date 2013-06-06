<?php

namespace TinyDb\Internal;

require_once(dirname(__FILE__) . '/require.php');

class AccessManager
{
    private $reflector = null;
    private $properties = array();
    public function __construct($reflector)
    {
        $this->reflector = $reflector;

        foreach ($reflector->getProperties() as $property) {
            $this->properties[$property->getName()] = $property;
        }
    }

    public function get_publicity($property)
    {
        if (!isset($this->properties[$property])) {
            return 999999;
        } else if ($this->properties[$property]->isPrivate()) {
            return T_PRIVATE;
        } else if ($this->properties[$property]->isProtected()) {
            return T_PROTECTED;
        } else {
            return T_PUBLIC;
        }
    }

    public function get_information($property)
    {
        if (!isset($this->properties[$property])) {
            return array();
        } else {
            $comment = $this->properties[$property]->getDocComment();
            if ($comment === null) {
                return $comment;
            }

            // Cleanup
            $newlines = [];
            foreach (explode("\n", $comment) as $line) {
                $newlines[] = ltrim($line, "\n\t* ");
            }
            $str = implode("\n", $newlines);
            $str = substr($str, 2, strlen($str) - 4);
            $str = trim($str, "\n*");

            // Get the information
            $properties = array();
            foreach (explode("\n", $str) as $line) {
                if (substr($line, 0, 1) === '@') {
                    $line = ltrim($line, "@\t ");
                    list($k, $v) = preg_split("/[\t ]+/", $line, 2);
                    $properties[$k] = $v;
                }
            }

            return $properties;
        }
    }
}
