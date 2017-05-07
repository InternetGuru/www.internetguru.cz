<?php

namespace IGCMS\Core;
/**
 * Interface OutputStrategyInterface
 * @package IGCMS\Core
 */
interface OutputStrategyInterface {
  /**
   * @param HTMLPlus $content
   * @return string
   */
  public function getOutput (HTMLPlus $content);
}

?>
