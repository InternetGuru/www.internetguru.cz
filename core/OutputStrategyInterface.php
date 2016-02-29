<?php

namespace IGCMS\Core;

use IGCMS\Core\HTMLPlus;

interface OutputStrategyInterface {
  public function getOutput(HTMLPlus $content);
}

?>