<?php

use IGCMS\Core\Cms;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugins;

try {
  include("init.php");

  Cms::checkAuth();
  $plugins = new Plugins();

  foreach ($plugins->getIsInterface("IGCMS\\Core\\ResourceInterface") as $resInt) {
    if ($resInt::isSupportedRequest(get_link())) {
      $resInt::handleRequest();
    }
  }
  throw new Exception(_("File not found"), 404);

} catch (Exception $exc) {

  $errno = $exc->getCode() ? $exc->getCode() : 500;
  $mgs = $exc->getMessage();
  if (CMS_DEBUG) {
    $mgs = sprintf("%s in %s on line %s", $mgs, $exc->getFile(), $exc->getLine());
  }
  #if(class_exists("IGCMS\Core\ErrorPage")) new ErrorPage($m, $errno);

  if ($errno >= 500) {
    Logger::alert($mgs);
  }
  http_response_code($errno);
  echo $mgs;

}
