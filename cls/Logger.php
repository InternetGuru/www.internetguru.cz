<?php

class Logger {
  private $m = array();
  private $f;

  function __construct($message, $type=null, $doLog=true) {
    if(!is_string($type)) $type = "info";
    $this->start_time = microtime(true);
    if(!is_dir(LOG_FOLDER) && !mkdir(LOG_FOLDER,0755,true))
      throw new Exception(sprintf("Unable to create log dir '%s'",LOG_FOLDER));
    $this->f = LOG_FOLDER ."/". date("Ymd") .".log";
    $this->m[] = $_SERVER["REMOTE_ADDR"] .":". $_SERVER["REMOTE_PORT"];
    $this->m[] = "unknown"; // logged user
    $this->m[] = date(DATE_ATOM); // http://php.net/manual/en/class.datetime.php
    $this->m[] = '"'. $_SERVER["REQUEST_METHOD"] ." ". $_SERVER["REQUEST_URI"] ." ". $_SERVER["SERVER_PROTOCOL"] .'"';
    $this->m[] = http_response_code();
    $this->m[] = normalize($type);
    $this->m[] = '"'. $message .'"';
    if($doLog) $this->log();
  }

  public function finished() {
    $this->m[] = round((microtime(true) - $this->start_time)*1000) . "ms";
    $this->log();
  }

  private function log() {
    error_log(implode(" ",$this->m)."\n",3,$this->f);
  }

}

?>