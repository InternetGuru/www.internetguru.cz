<?php

namespace IGCMS\Core;

/** @noinspection PhpClassNamingConventionInspection */
/**
 * Interface FinalContentStrategyInterface
 * @package IGCMS\Core
 */
interface FinalContentStrategyInterface {
  /**
   * @param  DOMDocumentPlus $content
   * @return DOMDocumentPlus
   */
  public function getContent (DOMDocumentPlus $content);
}
