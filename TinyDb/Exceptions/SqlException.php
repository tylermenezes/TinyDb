<?php

namespace TinyDb;

class SqlException extends \Exception
{
    protected $debug = null;
    protected $sql = null;
    protected $paramaters = null;

    public function __toString()
    {
        $message = "Couldn't execute SQL. " . $this->message . ' ' . $this->debug . " ";
        $message .= "The SQL was: \n";
        $message .= $this->sql . "\n";
        if ($this->paramaters !== null && count($this->paramaters) > 0) {
            $message .= "Parameters for replacement were: \n";
            $message .= $this->paramaters;
        }
    }

    public function __construct($message, $debug, $sql, $paramaters = null)
    {
        $message = "Couldn't execute SQL. " . $message . ' ' . $debug . " ";
        $message .= "The SQL was: \n";
        $message .= $sql . "\n";
        $this->message = $message;
        $this->debug = $debug;
        $this->sql = $sql;
        $this->paramaters = $paramaters;
    }
}
