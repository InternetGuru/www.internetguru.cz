<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
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
    $cfg = $this->getDOMPlus();
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
    $toStrip = array();
    foreach($anchors as $a) {
      if(strpos($a->getAttribute("href"), "mailto:") === false) continue;
      $address = substr($a->getAttribute("href"), 7);
      $a->addClass("emailbreaker");
      $a->removeAttribute("href");
      $spanAddr = $content->createElement("span");
      $spanAddr->setAttribute("class", "addr");
      $spanAddr->nodeValue = str_replace($pat, $rep, $address);
      $replaced = $this->replace($address, $spanAddr, $a);
      if(!$replaced) {
        $spanAddr->addClass("del");
        $a->appendChild($spanAddr);
      }
      $a->rename("span");
      $appendJs = true;
    }
    if($appendJs) $this->appendJs($pat, $rep);
    return $content;
  }

  private function replace($pat, DOMElementPlus $rep, DOMElementPlus $ele) {
    $replaced = false;
    foreach($ele->childNodes as $chld) {
      if($chld->nodeType == XML_ELEMENT_NODE) {
        $r = $this->replace($pat, $rep, $chld);
        if(!$replaced) $replaced = $r;
        continue;
      }
      if(strpos($chld->nodeValue, $pat) === false) continue;
      foreach(explode($pat, $chld->nodeValue) as $k => $part) {
        if($k % 2 != 0) $ele->appendChild($rep);
        else $ele->appendChild($ele->ownerDocument->createTextNode($part));
      }
      $replaced = true;
    }
    return $replaced;
  }

  private function getAddrEl(DOMElementPlus $e, $addr) {
    $addrEl = null;
    foreach($e->childNodes as $child) {
      if($child->nodeType == 1) {
        $addrEl = getAddrEl($child, $addr);
        if(!is_null($addrEl)) return $addrEl;
      }
      if(strpos($child->nodeValue, $addr) !== false) return $child;
    }
    return $addrEl;
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
