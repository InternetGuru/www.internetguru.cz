<?php

namespace IGCMS\Core;

interface ResourceInterface {
  public static function isSupportedRequest($filePath=null);
  public static function handleRequest();
}

?>