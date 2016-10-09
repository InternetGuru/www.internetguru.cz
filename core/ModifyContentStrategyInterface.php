<?php

namespace IGCMS\Core;

interface ModifyContentStrategyInterface {
  /**
   * @param  HTMLPlus $content
   */
  public function modifyContent(HTMLPlus $content);
}

?>