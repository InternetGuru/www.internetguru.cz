<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
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
    $cfg = $this->getXML();
    $idel = $cfg->getElementById("ga_id", "var");
    if(!strlen($idel->nodeValue)) $idel = $cfg->getElementById(DOMAIN, "group");
    if(is_null($idel)) $idel = $cfg->getElementById("other", "group");
    $ga_id = $idel->nodeValue;
    // disable if superadmin
    if(Cms::isSuperUser()) {
      Cms::getOutputStrategy()->addJs("// ".sprintf(_("GA disabled (ga_id = %s)"), $ga_id), 1, "body");
      return;
    }
    $jsfile = $this->pluginDir."/".$cfg->getElementById("jsfile", "var")->nodeValue;
    Cms::getOutputStrategy()->addJs("var ga_id = '$ga_id';", 1, "body");
    Cms::getOutputStrategy()->addJsFile($jsfile, 1, "body");
  }

}

?>