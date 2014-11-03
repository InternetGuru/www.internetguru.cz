<?php

interface ImportStrategyInterface {
  public function import($filePath);
  public function importHTMLPlus(HTMLPlus $content);
}

?>