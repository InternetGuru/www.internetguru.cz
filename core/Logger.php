<?php

class Logger {
  private $message;
  private $type;
  private $start_time;

  function __construct($message, $type=null, $delay=-1) {
    if(!is_dir(LOG_FOLDER) && !mkdir(LOG_FOLDER,0755,true))
      throw new Exception(sprintf("Unable to create log dir '%s'",LOG_FOLDER));
    if(!is_string($type)) $type = "info";
    $this->message = $message;
    $this->type = $type;
    $this->start_time = microtime(true);
    if($delay < 0) $this->log();
    else $this->start_time -= $delay;
  }

  private function getCaller() {
    $callers = debug_backtrace();
    $c = array();
    if(isset($callers[3]['class'])) $c[] = $callers[3]['class'];
    if(isset($callers[3]['function'])) $c[] = $callers[3]['function'];
    return implode(".",$c);
  }

  public function finished() {
    $finished = round((microtime(true) - $this->start_time)*1000) . "ms";
    $this->log($finished);
  }

  private function log($finished=null) {
    $logFile = LOG_FOLDER ."/". date("Ymd") .".log";
    if(isset($_SERVER["REMOTE_ADDR"],$_SERVER["REMOTE_PORT"]))
      $msg[] = $_SERVER["REMOTE_ADDR"] .":". $_SERVER["REMOTE_PORT"];
    else
      $msg[] = "0.0.0.0:0000";
    $msg[] = "unknown"; // logged user
    $msg[] = date(DATE_ATOM); // http://php.net/manual/en/class.datetime.php
    if(isset($_SERVER["REQUEST_METHOD"],$_SERVER["REQUEST_URI"],$_SERVER["SERVER_PROTOCOL"]))
      $msg[] = '"'. $_SERVER["REQUEST_METHOD"] ." ". $_SERVER["REQUEST_URI"] ." ". $_SERVER["SERVER_PROTOCOL"] .'"';
    else {
      $msg[] = '"UNKNOWN UNKNOWN UNKNOWN"';
    }
    $msg[] = http_response_code();
    $msg[] = normalize($this->type);
    $msg[] = '"'. $this->message .'"';
    $msg[] = '['. $this->getCaller() .']';
    $msg[] = $finished;
    error_log(implode(" ",$msg)."\n",3,$logFile);
  }

}

?>