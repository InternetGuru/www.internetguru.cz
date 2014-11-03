<?php

interface ContentStrategyInterface {
  /**
   * [getContent description]
   * @param  HTMLPlus $content
   * @return HTMLPlus          changed or same $content
   */
  public function getContent(HTMLPlus $content);
}

?>