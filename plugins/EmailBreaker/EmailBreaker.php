<?php

class EmailBreaker extends Plugin implements SplObserver, FinalContentStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
  }

  public function getContent(DOMDocumentPlus $content) {
    $cfg = $this->getDOMPlus();
    $appendJs = false;
    $pat = array();
    $rep = array();
    foreach($cfg->getElementsByTagName("replace") as $replace) {
      if(!$replace->hasAttribute("pattern")) {
        Logger::log(_("Element replace missing attribute pattern"), Logger::LOGGER_WARNING);
        continue;
      }
      if(!strlen($replace->nodeValue)) {
        Logger::log(_("Element replace missing value"), Logger::LOGGER_WARNING);
        continue;
      }
      $pat[] = $replace->getAttribute("pattern");
      $rep[] = $replace->nodeValue;
    }
    $anchors = array();
    foreach($content->getElementsByTagName("a") as $a) $anchors[] = $a;
    foreach($anchors as $a) {
      if(strpos($a->getAttribute("href"), "mailto:") === false) continue;
      $address = substr($a->getAttribute("href"), 7);
      $container = $content->createElement("span");
      $container->setAttribute("class", "emailbreaker");
      $brokenAddress = $content->createElement("span");
      $brokenAddress->setAttribute("class", "addr");
      $brokenAddress->nodeValue = str_replace($pat, $rep, $address);
      if(strpos($a->nodeValue, $address) !== false) {
        $val = $a->nodeValue;
        foreach(explode($address, $val) as $k => $part) {
          if($k % 2 != 0) $container->appendChild($brokenAddress);
          else $container->appendChild($a->ownerDocument->createTextNode($part));
        }
      }
      else {
        $container->nodeValue .= $a->nodeValue." ";
        $container->appendChild($brokenAddress);
      }
      $a->nodeValue = "";
      $a->appendChild($container);
      $a->stripTag();
      $appendJs = true;
    }
    if($appendJs) $this->appendJs($pat, $rep);
    return $content;
  }

  private function appendJs($pat, $rep) {
    $jsRep = array();
    foreach($pat as $k => $p) {
      $jsRep[] = "['$p','{$rep[$k]}']";
    }
    Cms::getOutputStrategy()->addJsFile($this->pluginDir."/".get_class($this).".js");
    Cms::getOutputStrategy()->addJs("EmailBreaker.init({
      rep: [".implode(",", $jsRep)."]
    });");
  }

}

?>