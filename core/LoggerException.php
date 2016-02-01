<?php

class LoggerException extends Exception {

  public function __construct($m=null, $c=0, Exception $p=null) {
    parent::__construct($m, $c, $p);
    Logger::log($m, "exception");
  }

}

?>