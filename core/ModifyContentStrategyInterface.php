<?php

namespace IGCMS\Core;

/** @noinspection PhpClassNamingConventionInspection */
/**
 * Interface ModifyContentStrategyInterface
 * @package IGCMS\Core
 */
interface ModifyContentStrategyInterface {
  /**
   * @param HTMLPlus $content
   */
  public function modifyContent (HTMLPlus $content);
}
