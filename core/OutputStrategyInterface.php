<?php

namespace IGCMS\Core;

interface OutputStrategyInterface {
  public function getOutput(HTMLPlus $content);
}

?>