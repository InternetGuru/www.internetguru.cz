<?php

use IGCMS\Core\Cms;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugins;

try {
  include("init.php");

  Cms::checkAuth();
  $plugins = new Plugins();

  foreach ($plugins->getIsInterface("IGCMS\\Core\\ResourceInterface") as $ri) {
    if ($ri::isSupportedRequest(getCurLink())) {
      $ri::handleRequest();
    }
  }
  throw new Exception(_("File not found"), 404);

} catch (Exception $e) {

  $errno = $e->getCode() ? $e->getCode() : 500;
  $m = $e->getMessage();
  if (CMS_DEBUG) {
    $m = sprintf("%s in %s on line %s", $m, $e->getFile(), $e->getLine());
  }
  #if(class_exists("IGCMS\Core\ErrorPage")) new ErrorPage($m, $errno);

  if ($errno >= 500) {
    Logger::alert($m);
  }
  http_response_code($errno);
  echo $m;

}

?>
