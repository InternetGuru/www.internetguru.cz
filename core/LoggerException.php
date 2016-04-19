<?php

namespace IGCMS\Core;

use IGCMS\Core\Logger;

class LoggerException extends Exception {

  public function __construct($m=null, $c=0, Exception $p=null) {
    parent::__construct($m, $c, $p);
    Logger::error($m);
  }

}

?>