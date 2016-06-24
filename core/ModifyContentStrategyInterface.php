<?php

namespace IGCMS\Core;

use IGCMS\Core\HTMLPlus;

interface ModifyContentStrategyInterface {
  /**
   * @param  HTMLPlus $content
   * @return HTMLPlus          changed or same $content
   */
  public function modifyContent(HTMLPlus $content);
}

?>