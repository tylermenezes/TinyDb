<?php

namespace TinyDb;

class SqlException extends \Exception
{
    protected $this->debug = null;
    protected $this->sql = null;
    protected $this->paramaters = null;

    public function getMessage()
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
        $this->message = $message;
        $this->debug = $debug;
        $this->sql = $sql;
        $this->paramaters = $paramaters;
    }
}
