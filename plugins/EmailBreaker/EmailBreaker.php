<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\FinalContentStrategyInterface;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class EmailBreaker
 * @package IGCMS\Plugins
 */
class EmailBreaker extends Plugin implements SplObserver, FinalContentStrategyInterface {
  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($subject->getStatus() != STATUS_PROCESS) {
      return;
    }
    if ($this->detachIfNotAttached("HtmlOutput")) {
      return;
    }
  }

  /**
   * @param DOMDocumentPlus $content
   * @return DOMDocumentPlus
   * @throws Exception
   */
  public function getContent (DOMDocumentPlus $content) {
    $cfg = self::getXML();
    $pat = [];
    $rep = [];
    /** @var DOMElementPlus $replace */
    foreach ($cfg->getElementsByTagName("replace") as $replace) {
      try {
        $pattern = $replace->getRequiredAttribute("pattern");
      } catch (Exception $exc) {
        Logger::user_warning($exc->getMessage());
        continue;
      }
      if (!strlen($replace->nodeValue)) {
        Logger::user_warning(_("Element replace missing value"));
        continue;
      }
      $pat[] = $pattern;
      $rep[] = $replace->nodeValue;
    }
    $anchors = [];
    /** @var DOMElementPlus $aElm */
    foreach ($content->getElementsByTagName("a") as $aElm) {
      if (strpos($aElm->getAttribute("href"), "mailto:") === false) {
        continue;
      }
      $anchors[] = $aElm;
    }
    foreach ($anchors as $aElm) {
      $address = substr($aElm->getAttribute("href"), 7);
      $aElm->addClass("emailbreaker");
      $aElm->removeAttribute("href");
      $spanAddr = $content->createElement("span");
      $spanAddr->setAttribute("class", "addr");
      $spanAddr->nodeValue = str_replace($pat, $rep, $address);
      $replaced = $this->replace($address, $spanAddr, $aElm);
      if (!$replaced) {
        $spanAddr->addClass("del");
        $aElm->appendChild($spanAddr);
      }
      $aElm->rename("span");
    }
    if (count($anchors)) {
      $this->appendJs($pat, $rep);
    }
    return $content;
  }

  /**
   * @param string $pat
   * @param DOMElementPlus $rep
   * @param DOMElementPlus $ele
   * @return bool
   */
  private function replace ($pat, DOMElementPlus $rep, DOMElementPlus $ele) {
    $replaced = false;
    foreach ($ele->childNodes as $chld) {
      if ($chld->nodeType == XML_ELEMENT_NODE) {
        $childReplaced = $this->replace($pat, $rep, $chld);
        if (!$replaced) {
          $replaced = $childReplaced;
        }
        continue;
      }
      if (strpos($chld->nodeValue, $pat) === false) {
        continue;
      }
      foreach (explode($pat, $chld->nodeValue) as $key => $part) {
        if ($key > 0) {
          $ele->insertBefore($rep, $chld);
        }
        $ele->insertBefore($ele->ownerDocument->createTextNode($part), $chld);
      }
      $ele->removeChild($chld);
      $replaced = true;
    }
    return $replaced;
  }

  /**
   * @param array $patArray
   * @param array $rep
   */
  private function appendJs (array $patArray, array $rep) {
    $jsRep = [];
    foreach ($patArray as $key => $pattern) {
      $jsRep[] = "['$pattern','{$rep[$key]}']";
    }
    Cms::getOutputStrategy()->addJsFile($this->pluginDir."/".$this->className.".js");
    Cms::getOutputStrategy()->addJs(
      "require('IGCMS.EmailBreaker', function () {
  IGCMS.EmailBreaker.init({
    rep: [".implode(",", $jsRep)."]
  });
})"
    );
  }

}
