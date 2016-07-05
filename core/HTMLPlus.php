<?php

namespace IGCMS\Core;

use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use Exception;
use DOMComment;
use DOMXPath;
use DateTime;

class HTMLPlus extends DOMDocumentPlus {
  private $headings = array();
  private $defaultAuthor = null;
  private $defaultCtime = null;
  private $defaultHeading = null;
  private $defaultNs = null;
  private $defaultDesc = null;
  private $defaultKw = null;
  private $errors = array();
  private $status = 0;
  private $statuses;
  const STATUS_UNKNOWN = 0;
  const STATUS_VALID = 1;
  const STATUS_INVALID = 2;
  const STATUS_REPAIRED = 3;
  const RNG_FILE = "HTMLPlus.rng";

  function __construct($version="1.0", $encoding="utf-8") {
    parent::__construct($version, $encoding);
    $this->statuses = array(_("Unknown"), _("Valid"), _("Invalid"), _("Repaired"));
    $this->status = self::STATUS_UNKNOWN;
    $c = new DateTime("now");
    $this->defaultCtime = $c->format(DateTime::W3C);
    $this->defaultNs = HOST;
  }

  public function __set($vName, $vValue) {
    if(!is_null($vValue) && (!is_string($vValue) || !strlen($vValue)))
      throw new Exception(_("Variable value must be non-empty string or null"));
    switch($vName) {
      case "defaultCtime":
      case "defaultAuthor":
      case "defaultHeading":
      case "defaultDesc":
      case "defaultKw":
      $this->$vName = $vValue;
    }
  }

  public function __clone() {
    $doc = new HTMLPlus();
    $root = $doc->importNode($this->documentElement, true);
    $doc->appendChild($root);
    return $doc;
  }

  public function getStatus() {
    return $this->statuses[$this->status];
  }

  public function getErrors() {
    return $this->errors;
  }

  public function processVariables(Array $variables, $ignore=array("h" => array("id"))) {
    $newContent = parent::processVariables($variables, $ignore);
    return $newContent->ownerDocument;
  }

  public function processFunctions(Array $functions, Array $variables=array(), $ignore=array("h" => array("id"))) {
    parent::processFunctions($functions, $variables, $ignore);
  }

  public function applySyntax() {
    $extend = array(/*"strong", "em", "ins", "del", "sub", "sup", "a",*/ "h", "desc");

    // hide noparse
    $noparse = array();
    foreach($this->getNodes($extend) as $n)
      $noparse = array_merge($noparse, $this->parseSyntaxNoparse($n));

    // proceed syntax translation
    foreach($this->getNodes() as $n) $this->parseSyntaxCodeTag($n);
    foreach($this->getNodes() as $n) $this->parseSyntaxCode($n);
    foreach($this->getNodes($extend) as $n) $this->parseSyntaxVariable($n);
    foreach($this->getNodes($extend) as $n) $this->parseSyntaxComment($n);

    // restore noparse
    foreach($noparse as $n) {
      $n[0]->nodeValue = $n[1];
      /*$newNode = $this->createTextNode($n[1]);
      $n[0]->parentNode->insertBefore($newNode, $n[0]);
      $n[0]->parentNode->removeChild($n[0]);*/
    }
  }

  private function getNodes($extend = array()) {
    $nodes = array();
    foreach(array_merge(array("p", "dt", "dd", "li"), $extend) as $eNam) {
      foreach($this->getElementsByTagName($eNam) as $e) {
        $nodes[] = $e;
      }
    }
    return $nodes;
  }

  private function parseSyntaxNoparse(DOMElementPlus $n) {
    $noparse = array();
    $pat = "/<noparse>(.+?)<\/noparse>/";
    $p = preg_split($pat, $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(count($p) < 2) return $noparse;
    $n->nodeValue = "";
    foreach($p as $i => $v) {
      if($i % 2 == 0) $text = $n->ownerDocument->createTextNode($v);
      else {
        $text = $n->ownerDocument->createTextNode("");
        $noparse[] = array($text, $v);
      }
      $n->appendChild($text);
    }
    return $noparse;
  }

  private function parseSyntaxCodeTag(DOMElementPlus $n) {
    $pat = "/<code(?: [a-z]+)?>((?:.|\n)+?)<\/code>/m";
    $p = preg_split($pat, $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(count($p) < 2) return;
    $defaultValue = $n->nodeValue;
    $n->nodeValue = "";
    foreach($p as $i => $v) {
      if($i % 2 == 0) $n->appendChild($n->ownerDocument->createTextNode($v));
      else {
        $s = array("&bdquo;", "&ldquo;", "&rdquo;", "&lsquo;", "&rsquo;");
        $r = array('"', '"', '"', "'", "'");
        $v = str_replace($s, $r, translateUtf8Entities($v, true));
        $newNode = $this->createElement("code", translateUtf8Entities($v));
        if(preg_match("/<code ([a-z]+)>/", $defaultValue, $match)) {
          $newNode->setAttribute("class", $match[1]);
        }
        $n->appendChild($newNode);
      }
    }
  }

  private function parseSyntaxCode(DOMElementPlus $n) {
    $pat = "/(?:&lsquo;|&rsquo;|'){2}(.+?)(?:&lsquo;|&rsquo;|'){2}/";
    $src = translateUtf8Entities($n->nodeValue, true);
    $p = preg_split($pat, $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(count($p) < 2) return;
    $n->nodeValue = "";
    foreach($p as $i => $v) {
      if($i % 2 == 0) $n->appendChild($n->ownerDocument->createTextNode($v));
      else {
        $s = array("&bdquo;", "&ldquo;", "&rdquo;", "&lsquo;", "&rsquo;");
        $r = array('"', '"', '"', "'", "'");
        $v = str_replace($s, $r, $v);
        $newNode = $this->createElement("code", translateUtf8Entities($v));
        $n->appendChild($newNode);
      }
    }
  }

  private function parseSyntaxVariable(DOMElementPlus $n) {
    //if(strpos($n->nodeValue, 'cms-') === false) return;
    foreach(explode('\$', $n->nodeValue) as $src) {
      $p = preg_split('/\$('.VARIABLE_PATTERN.")/", $src, -1, PREG_SPLIT_DELIM_CAPTURE);
      if(count($p) < 2) return;
      $defVal = $n->nodeValue;
      $n->nodeValue = "";
      foreach($p as $i => $v) {
        if($i % 2 == 0) $n->appendChild($n->ownerDocument->createTextNode($v));
        else {
          // <p>$varname</p> -> <p var="varname"/>
          // <p><strong>$varname</strong></p> -> <p><strong var="varname"/></p>
          // else
          // <p>aaa $varname</p> -> <p>aaa <em var="varname"/></p>
          if($defVal == "\$$v") {
            $n->setAttribute("var", $v);
          } else {
            $newNode = $this->createElement("em");
            $newNode->setAttribute("var", $v);
            $n->appendChild($newNode);
          }
        }
      }
    }
  }

  private function parseSyntaxComment(DOMElementPlus $n) {
    $p = preg_split('/\(\(\s*(.+)\s*\)\)/', $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if(count($p) < 2) return;
    $n->nodeValue = "";
    foreach($p as $i => $v) {
      if($i % 2 == 0) $n->appendChild($n->ownerDocument->createTextNode($v));
      else $n->appendChild($this->createComment(" $v "));
    }
  }

  public function validatePlus($repair = false) {
    $i = 0;
    $hash = hash(FILE_HASH_ALGO, $this->saveXML());
    $version = 3; // increment if validatePlus changes
    $cacheKey = apc_get_key("HTMLPlus/validatePlus/$hash/$version");
    if(apc_exists($cacheKey)) $i = apc_fetch($cacheKey);
    #var_dump("$hash found $i");
    $this->headings = $this->getElementsByTagName("h");
    $this->status = self::STATUS_VALID;
    $this->errors = array();
    try {
      switch($i) {
        case 0: $this->validateRoot($repair); $i++;
        case 1: $this->validateSections($repair); $i++;
        case 2: $this->validateLang($repair); $i++;
        case 3: $this->validateHid($repair); $i++;
        case 4: $this->validateHsrc($repair); $i++;
        case 5: $this->validateHempty($repair); $i++;
        case 6: $this->validateDl($repair); $i++;
        case 7: $this->validateDates($repair); $i++;
        case 8: $this->validateAuthor($repair); $i++;
        case 9: $this->validateFirstHeadingAuthor($repair); $i++;
        case 10: $this->validateFirstHeadingCtime($repair); $i++;
        case 11: $this->validateBodyNs($repair); $i++;
        case 12: $this->validateDesc($repair); $i++;
        case 13: $this->relaxNGValidatePlus(); $i++;
      }
    } catch(Exception $e) {
      $this->status = self::STATUS_INVALID;
      throw $e;
    } finally {
      if(!count($this->errors)) {
        apc_store_cache($cacheKey, $i, "validatePlus");
      }
    }
    if(count($this->errors)) $this->status = self::STATUS_REPAIRED;
  }

  private function errorHandler($message, $repair) {
    if(!$repair) throw new Exception($message);
    $this->errors[] = $message;
  }

  private function validateDesc($repair) {
    $invalid = array();
    foreach($this->headings as $h) {
      $desc = $h->nextElement;
      if(is_null($desc)
        || $desc->nodeName != "desc"
        || !strlen(trim($desc->nodeValue))) {
        $invalid[] = $h->getAttribute("id");
      }
    }
    if(empty($invalid)) return;
    $message = sprintf(_("Heading description missing or empty: %s"), implode(", ", $invalid));
    $this->errorHandler($message, false);
  }

  private function validateBodyNs($repair) {
    $b = $this->documentElement;
    if($b->hasAttribute("ns")) return;
    $h = $this->headings->item(0);
    if($h->hasAttribute("ns")) {
      $this->defaultNs = $h->getAttribute("ns");
      $h->removeAttribute("ns");
    }
    $message = _("Body attribute 'ns' missing");
    $this->errorHandler($message, $repair && !is_null($this->defaultNs));
    $b->setAttribute("ns", $this->defaultNs);
  }

  private function validateFirstHeadingAuthor($repair) {
    $h = $this->headings->item(0);
    if($h->hasAttribute("author")) return;
    $message = _("First heading attribute 'author' missing");
    $this->errorHandler($message, $repair && !is_null($this->defaultAuthor));
    $h->setAttribute("author", $this->defaultAuthor);
  }

  private function validateFirstHeadingCtime($repair) {
    $h = $this->headings->item(0);
    if($h->hasAttribute("ctime")) return;
    $message = _("First heading attribute 'ctime' missing");
    $this->errorHandler($message, $repair && !is_null($this->defaultCtime));
    $h->setAttribute("ctime", $this->defaultCtime);
  }

  public function relaxNGValidatePlus($f=null) {
    return parent::relaxNGValidatePlus(LIB_FOLDER."/".self::RNG_FILE);
  }

  private function validateRoot($repair) {
    if(is_null($this->documentElement)) {
      $message = _("Root element not found");
      $this->errorHandler($message, false);
    }
    if($this->documentElement->nodeName != "body") {
      $message = _("Root element must be 'body'");
      $this->errorHandler($message, $repair);
      $this->documentElement->rename("body");
    }
    if(!$this->documentElement->hasAttribute("lang")
      && !$this->documentElement->hasAttribute("xml:lang")) {
      $message = _("Attribute 'xml:lang' is missing in element body");
      $this->errorHandler($message, $repair);
      $this->documentElement->setAttribute("xml:lang", _("en"));
    }
    $fe = $this->documentElement->firstElement;
    if(!is_null($fe) && $fe->nodeName == "section") {
      $message = _("Element section cannot be empty");
      $this->errorHandler($message, $repair);
      $this->addTitleElements($this->documentElement);
      return;
    }
    $hRoot = 0;
    foreach($this->documentElement->childNodes as $e) {
      if($e->nodeType != XML_ELEMENT_NODE) continue;
      if($e->nodeName != "h") continue;
      if($hRoot++ == 0) continue;
      $message = _("There must be exactly one heading in body element");
      $this->errorHandler($message, $repair);
      break;
    }
    if($hRoot == 1) return;
    if($hRoot == 0) {
      $message = _("Missing heading in body element");
      $this->errorHandler($message, $repair);
      $this->documentElement->appendChild($this->createElement("h"));
      return;
    }
    $children = array();
    foreach($this->documentElement->childNodes as $e) $children[] = $e;
    $s = $this->createElement("section");
    foreach($children as $e) $s->appendChild($e);
    $s->appendChild($this->createTextNode("  "));
    $this->documentElement->appendChild($s);
    $this->documentElement->appendChild($this->createTextNode("\n"));
    $this->addTitleElements($s);
  }

  private function addTitleElements(DOMElementPlus $el) {
    $first = $el->firstElement;
    $el->insertBefore($this->createTextNode("\n  "), $first);
    $el->insertBefore($this->createElement("h", _("Web title")), $first);
    $el->insertBefore($this->createTextNode("\n  "), $first);
    $el->insertBefore($this->createElement("desc", _("Web description")), $first);
    $el->insertBefore($this->createTextNode("\n  "), $first);
  }

  private function validateSections($repair) {
    $emptySect = array();
    foreach($this->getElementsByTagName("section") as $s) {
      if(!count($s->childElementsArray)) $emptySect[] = $s;
    }
    if(!count($emptySect)) return;
    $message = _("Empty section(s) found");
    $this->errorHandler($message, $repair);
    foreach($emptySect as $s) $s->stripTag(_("Empty section deleted"));
  }

  private function validateLang($repair) {
    $xpath = new DOMXPath($this);
    $langs = $xpath->query("//*[@lang]");
    if(!$langs->length) return;
    $message = _("Lang attribute without XML namespace");
    $this->errorHandler($message, $repair);
    foreach($langs as $n) {
      if(!$n->hasAttribute("xml:lang"))
        $n->setAttribute("xml:lang", $n->getAttribute("lang"));
      $n->removeAttribute("lang");
    }
  }

  public function repairIds() {
    $this->errors = array();
    $this->validateHid(true);
  }

  private function validateHid($repair) {
    $hIds = array();
    foreach($this->headings as $h) {
      $id = $h->getAttribute("id");
      if(!strlen($id)) {
        $message = sprintf(_("Heading attribute id empty or missing: %s"), $h->nodeValue);
        $this->errorHandler($message, $repair);
        $h->setUniqueId();
      } elseif(!isValidId($id)) {
        $message = sprintf(_("Invalid heading attribute id '%s'"), $id);
        $this->errorHandler($message, $repair);
        $h->setUniqueId();
      } elseif(array_key_exists($id, $hIds)) {
        $message = sprintf(_("Duplicit heading attribute id '%s'"), $id);
        $this->errorHandler($message, $repair);
        $h->setUniqueId();
      }
      $hIds[$h->getAttribute("id")] = null;
    }
  }

  private function validateHsrc($repair) {
    $invalid = array();
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("src")) continue;
      if(preg_match("#^[a-z][a-z0-9_/.-]*\.html$#", $h->getAttribute("src"))) continue;
      $invalid[] = $h->getAttribute("src");
    }
    if(empty($invalid)) return;
    $message = sprintf(_("Invalid src format: %s"), implode(", ", $invalid));
    $this->errorHandler($message, false);
  }

  private function validateHempty($repair) {
    foreach($this->headings as $h) {
      if(strlen(trim($h->nodeValue))) continue;
      $message = _("Heading content must not be empty");
      $this->errorHandler($message, $repair && !is_null($this->defaultHeading));
      $h->nodeValue = $this->defaultHeading;
    }
  }

  private function validateDl($repair) {
    $dts = array();
    foreach($this->getElementsByTagName("dt") as $dt) $dts[] = $dt;
    foreach($dts as $dt) {
      $nextElement = $dt->nextElement;
      if(!is_null($nextElement) && $nextElement->tagName == "dd") continue;
      $this->errorHandler(_("Element dt following sibling must be dd"), $repair);
      $dd = $this->createElement("dd");
      $dd->appendChild($this->newDOMComment(_("Created empty element dd")));
      if(is_null($nextElement)) $dt->parentNode->appendChild($dd);
      else $dt->parentNode->insertBefore($dd, $nextElement);
    }
  }

  private function validateAuthor($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("author")) continue;
      if(strlen(trim($h->getAttribute("author")))) continue;
      $this->errorHandler(_("Attribute 'author' cannot be empty"), $repair);
      $h->parentNode->insertBefore($this->newDOMComment(_("Removed empty attribute 'author'")), $h);
      $h->removeAttribute("author");
    }
  }

  private function validateDates($repair) {
    foreach($this->headings as $h) {
      $ctime = null;
      $mtime = null;
      if($h->hasAttribute("ctime")) $ctime = $h->getAttribute("ctime");
      if($h->hasAttribute("mtime")) $mtime = $h->getAttribute("mtime");
      if(is_null($ctime) && is_null($mtime)) continue;
      if(is_null($ctime)) $ctime = $h->getAncestorValue("ctime", "h");
      if(is_null($ctime)) {
        $this->errorHandler(_("Attribute 'mtime' requires 'ctime'"), $repair);
        $ctime = $mtime;
        $h->setAttribute("ctime", $ctime);
      }
      $ctime_date = $this->createDate($ctime);
      if(is_null($ctime_date)) {
        $this->errorHandler(_("Invalid 'ctime' attribute format"), $repair);
        $h->parentNode->insertBefore($this->newDOMComment(sprintf(_("Removed attribute ctime '%s'")), $ctime), $h);
        $h->removeAttribute("ctime");
      }
      if(is_null($mtime)) return;
      $mtime_date = $this->createDate($mtime);
      if(is_null($mtime_date)) {
        $this->errorHandler(_("Invalid 'mtime' attribute format"), $repair);
        $h->parentNode->insertBefore($this->newDOMComment(sprintf(_("Removed attribute mtime '%s'")), $mtime), $h);
        $h->removeAttribute("mtime");
      }
      if($mtime_date < $ctime_date) {
        $this->errorHandler(_("Attribute 'mtime' cannot be lower than 'ctime'"), $repair);
        $h->parentNode->insertBefore($this->newDOMComment(sprintf(_("Removed attribute mtime '%s'")), $mtime), $h);
        $h->removeAttribute("mtime");
      }
    }
  }

  private function newDOMComment($comment) {
    return new DOMComment(" $comment ");
  }

  private function createDate($d) {
    $date = new DateTime();
    $date->setTimestamp(strtotime($d));
    $date_errors = DateTime::getLastErrors();
    if($date_errors['warning_count'] + $date_errors['error_count'] > 0) {
      return null;
    }
    return $date;
  }

}
?>