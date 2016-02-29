<?php

namespace IGCMS\Core;

use IGCMS\Core\DOMDocumentPlus;

interface FinalContentStrategyInterface {
  /**
   * @param  DOMDocumentPlus $content
   * @return DOMDocumentPlus
   */
  public function getContent(DOMDocumentPlus $content);
}

?>