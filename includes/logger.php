<?php

class LgLogger
{
    const ERROR_LEVEL = 255;
    const DEBUG = 1;
    const NOTICE = 2;
    const WARNING = 4;
    const ERROR = 8;
    
    const DEFAULT_FILENAME = "LinkGreen-product-import.log";

    static protected $instance;
    static protected $enabled = false;
    static protected $filename;
    
    protected $file;
    

    protected function __construct($full_log_filename = null) {
        if ($full_log_filename !== null) {
            self::setFileName($full_log_filename);
        }

        if (!$this->file = fopen(self::getFileName(), 'a+')) {
            throw new LgLoggerException(sprintf("Could not open file '%s' for writing.", self::getFileName()));
        }
    }
    
    public function __destruct() {
        fclose($this->file);
    }
    

    static protected function getInstance() {
        if (!self::hasInstance()) {
            self::$instance = new self(self::getFileName());
        }
		
        return self::$instance;
    }
    
    static protected function hasInstance() {
        return self::$instance instanceof self;
    }

    
    static public function setFileName($filename) {
        self::$filename = $filename;
    }
    
    static public function getFileName() {
        if (self::$filename == null) {
            self::$filename = dirname(__FILE__).self::DEFAULT_FILENAME;
        }
		
        return self::$filename;
    }
    

    static public function enableIf($condition = true) {
        if ((bool) $condition) {
            self::$enabled = true;
        }
    }
    
    static public function disable() {
        self::$enabled = false;
    }

        
    static public function writeIf($condition, $message, $level = self::DEBUG) {
        if ($condition) {
            self::writeLog($message, $level);
        }
    }

    static public function writeIfEnabled($message, $level = self::DEBUG) {
        if (self::$enabled) {
            self::writeLog($message, $level);
        }
    }
    
    static public function writeIfEnabledAnd($condition, $message, $level = self::DEBUG) {
        if (self::$enabled) {
            self::writeIf($condition, $message, $level);
        }
    }
        

    static public function writeLog($message, $level = self::DEBUG) {
        self::getInstance()->writeLine($message, $level);
    }
    
    protected function writeLine($message, $level) {
        if ($level & self::ERROR_LEVEL) {
            $date = new DateTime();
            $en_tete = $date->format('d/m/Y H:i:s');

            switch($level)
            {
            case self::NOTICE:
                $en_tete = sprintf("%s (notice)", $en_tete);
                break;
            case self::WARNING:
                $en_tete = sprintf("%s WARNING", $en_tete);
                break;
            case self::ERROR:
                $en_tete = sprintf("\n%s **ERROR**", $en_tete);
                break;
            }
            
            if ( is_array($message) || is_object($message) ) 
                $message = print_r($message, true);
            else 
                $message = sprintf("%s -- %s\n",  $en_tete, $message);
            
            fwrite($this->file, $message);
        }
    }
}

class LgLoggerException extends RuntimeException
{
}
