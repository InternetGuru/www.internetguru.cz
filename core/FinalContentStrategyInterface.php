<?php

namespace IGCMS\Core;

interface FinalContentStrategyInterface {
  /**
   * @param  DOMDocumentPlus $content
   * @return DOMDocumentPlus
   */
  public function getContent(DOMDocumentPlus $content);
}

?>