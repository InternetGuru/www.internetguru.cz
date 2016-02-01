<?php

interface FinalContentStrategyInterface {
  /**
   * @param  DOMDocumentPlus $content
   * @return DOMDocumentPlus
   */
  public function getContent(DOMDocumentPlus $content);
}

?>