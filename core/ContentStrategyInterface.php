<?php

namespace IGCMS\Core;

use IGCMS\Core\HTMLPlus;

interface ContentStrategyInterface {
  /**
   * [getContent description]
   * @param  HTMLPlus $content
   * @return HTMLPlus          changed or same $content
   */
  public function getContent(HTMLPlus $content);
}

?>