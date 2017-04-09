<?php

namespace IGCMS\Core;
/**
 * Interface ResourceInterface
 * @package IGCMS\Core
 */
interface ResourceInterface {
  /**
   * @param string|null $filePath
   * @return bool
   */
  public static function isSupportedRequest ($filePath = null);

  /**
   * @return void
   */
  public static function handleRequest ();
}

?>
