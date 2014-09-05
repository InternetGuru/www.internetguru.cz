<?php

class Logger {

  function __construct($message,$type="error") {
    $f = LOG_FOLDER ."/". date("Ymd") .".log";
    $m[] = $_SERVER["REMOTE_ADDR"] .":". $_SERVER["REMOTE_PORT"];
    $m[] = "unknown"; // logged user
    $m[] = date(DATE_ATOM); // http://php.net/manual/en/class.datetime.php
    $m[] = '"'. $_SERVER["REQUEST_METHOD"] ." ". $_SERVER["REQUEST_URI"] ." ". $_SERVER["SERVER_PROTOCOL"] .'"';
    $m[] = http_response_code();
    $m[] = normalize($type);
    $m[] = '"'. $message .'"' ."\n";
    error_log(implode(" ",$m),3,$f);
  }


}

?>