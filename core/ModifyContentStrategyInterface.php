<?php

namespace IGCMS\Core;

/**
 * Interface ModifyContentStrategyInterface
 * @package IGCMS\Core
 */
interface ModifyContentStrategyInterface {
  /**
   * @param HTMLPlus $content
   */
  public function modifyContent(HTMLPlus $content);
}

?>