<?php

namespace IGCMS\Core;
/**
 * Interface ResourceInterface
 * @package IGCMS\Core
 */
interface ResourceInterface {
  /**
   * @param string $filePath
   * @return bool
   */
  public static function isSupportedRequest ($filePath);

  /**
   * @return void
   */
  public static function handleRequest ();
}

?>
