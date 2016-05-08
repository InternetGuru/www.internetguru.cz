<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\FinalContentStrategyInterface;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use SplObserver;
use SplSubject;

class EmailBreaker extends Plugin implements SplObserver, FinalContentStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
  }

  public function getContent(DOMDocumentPlus $content) {
    $cfg = $this->getXML();
    $appendJs = false;
    $pat = array();
    $rep = array();
    foreach($cfg->getElementsByTagName("replace") as $replace) {
      try {
        $pattern = $replace->getRequiredAttribute("pattern");
      } catch(Exception $e) {
        Logger::user_warning($e->getMessage());
        continue;
      }
      if(!strlen($replace->nodeValue)) {
        Logger::user_warning(_("Element replace missing value"));
        continue;
      }
      $pat[] = $pattern;
      $rep[] = $replace->nodeValue;
    }
    $anchors = array();
    foreach($content->getElementsByTagName("a") as $a) $anchors[] = $a;
    foreach($anchors as $a) {
      if(strpos($a->getAttribute("href"), "mailto:") === false) continue;
      $address = substr($a->getAttribute("href"), 7);
      $container = $content->createElement("span");
      $container->setAttribute("class", "emailbreaker");
      $spanAddr = $content->createElement("span");
      $spanAddr->setAttribute("class", "addr");
      $spanAddr->nodeValue = str_replace($pat, $rep, $address);
      if(strpos($a->nodeValue, $address) !== false) {
        $val = $a->nodeValue;
        foreach(explode($address, $val) as $k => $part) {
          if($k % 2 != 0) $container->appendChild($spanAddr);
          else $container->appendChild($a->ownerDocument->createTextNode($part));
        }
      } else {
        $spanAddr->addClass("del");
        $spanAddr->nodeValue = " ".$spanAddr->nodeValue;
        $container->nodeValue = $a->nodeValue;
        $container->appendChild($spanAddr);
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
    Cms::getOutputStrategy()->addJsFile($this->pluginDir."/".(new \ReflectionClass($this))->getShortName().".js");
    Cms::getOutputStrategy()->addJs("EmailBreaker.init({
      rep: [".implode(",", $jsRep)."]
    });");
  }

}

?>