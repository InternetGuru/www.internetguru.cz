<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use SplObserver;
use SplSubject;


class GA extends Plugin implements SplObserver {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
    $this->init();
  }

  private function init() {
    // setup ga_id
    $ga_id = $this->getDOMPlus()->getElementById("ga_id");
    if(!strlen($ga_id->nodeValue)) {
      $ga_id = $this->getDOMPlus()->getElementById(DOMAIN);
    }
    if(is_null($ga_id)) {
      $ga_id = $this->getDOMPlus()->getElementById("other");
    }
    $ga_id = $ga_id->nodeValue;
    // ga_id validation
    if(!preg_match("/^UA-\d+-\d+$/", $ga_id)) {
      Logger::log(sprintf(_("Invalid ga_id format '%s'"), $ga_id), Logger::LOGGER_WARNING);
      return;
    }
    // disable if superadmin
    if(Cms::isSuperUser()) {
      Cms::getOutputStrategy()->addJs("// ".sprintf(_("GA (%s) disabled if administrator logged"), $ga_id), 1, "body");
      return;
    }
    // add js into html
    Cms::getOutputStrategy()->addJs("var ga_id = '$ga_id';", 1, "body");
    foreach($this->getDOMPlus()->getElementsByTagName("jsFile") as $jsFile) {
      $user = !$jsFile->hasAttribute("readonly");
      $f = $this->pluginDir."/".$jsFile->nodeValue;
      Cms::getOutputStrategy()->addJsFile($f, 1, "body", $user);
    }
  }

}

?>