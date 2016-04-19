<?php

namespace IGCMS\Core;

use IGCMS\Core\HTMLPlus;

interface ImportStrategyInterface {
  public function import($filePath);
  public function importHTMLPlus(HTMLPlus $content);
}

?>