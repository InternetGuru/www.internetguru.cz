<?php

namespace IGCMS\Core;

use IGCMS\Core\HTMLPlus;

interface ModifyContentStrategyInterface {
  /**
   * @param  HTMLPlus $content
   */
  public function modifyContent(HTMLPlus $content);
}

?>