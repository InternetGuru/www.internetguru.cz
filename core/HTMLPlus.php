<?php

namespace IGCMS\Core;

use DateTime;
use DOMComment;
use DOMDocument;
use DOMXPath;
use Exception;
use XSLTProcessor;

/**
 * Class HTMLPlus
 * @package IGCMS\Core
 *
 * @property string $defaultAuthor
 * @property string $defaultId
 * @property string $defaultCtime
 * @property string $defaultHeading
 * @property string $defaultDesc
 * @property string $defaultKw
 */
class HTMLPlus extends DOMDocumentPlus {
  /**
   * @var int
   */
  const STATUS_UNKNOWN = 0;
  /**
   * @var int
   */
  const STATUS_VALID = 1;
  /**
   * @var int
   */
  const STATUS_INVALID = 2;
  /**
   * @var int
   */
  const STATUS_REPAIRED = 3;
  /**
   * @var string
   */
  const RNG_FILE = "HTMLPlus.rng";
  /**
   * @var string
   */
  const PREPARE_TO_VALIDATE_FILE = "prepareToValidate.xsl";
  /**
   * @var bool
   */
  const USE_APC = true;
  /**
   * @var \DOMNodeList
   */
  private $headings = [];
  /**
   * @var string|null
   */
  private $defaultAuthor = null;
  /**
   * @var string|null
   */
  private $defaultId = null;
  /**
   * @var string|null
   */
  private $defaultCtime = null;
  /**
   * @var string|null
   */
  private $defaultHeading = null;
  /**
   * @var string|null
   */
  private $defaultNs = null;
  /**
   * @var string|null
   */
  private $defaultDesc = null;
  /**
   * @var string|null
   */
  private $defaultKw = null;
  /**
   * @var array
   */
  private $errors = [];
  /**
   * @var int
   */
  private $status = 0;
  /**
   * @var array
   */
  private $statuses;

  /**
   * HTMLPlus constructor.
   * @param string $version
   * @param string $encoding
   */
  function __construct ($version = "1.0", $encoding = "utf-8") {
    parent::__construct($version, $encoding);
    $this->statuses = [_("Unknown"), _("Valid"), _("Invalid"), _("Repaired")];
    $this->status = self::STATUS_UNKNOWN;
    $c = new DateTime("now");
    $this->defaultCtime = $c->format(DateTime::W3C);
    $this->defaultNs = HOST;
  }

  /**
   * @param string $vName
   * @param string $vValue
   * @throws Exception
   */
  public function __set ($vName, $vValue) {
    if (!is_null($vValue) && (!is_string($vValue) || !strlen($vValue))) {
      throw new Exception(_("Variable value must be non-empty string or null"));
    }
    switch ($vName) {
      case "defaultCtime":
      case "defaultAuthor":
      case "defaultId":
      case "defaultHeading":
      case "defaultDesc":
      case "defaultKw":
        $this->$vName = $vValue;
    }
  }

  /**
   * @return HTMLPlus
   */
  public function __clone () {
    $doc = new HTMLPlus();
    $root = $doc->importNode($this->documentElement, true);
    $doc->appendChild($root);
    return $doc;
  }

  /**
   * @return string
   */
  public function getStatus () {
    return $this->statuses[$this->status];
  }

  /**
   * @return array
   */
  public function getErrors () {
    return $this->errors;
  }

  /**
   * @param array $variables
   * @param array $ignore
   * @return DOMDocumentPlus
   */
  public function processVariables (Array $variables, $ignore = ["h" => ["id"]]) {
    $newContent = parent::processVariables($variables, $ignore);
    return $newContent->ownerDocument;
  }

  /**
   * @param array $functions
   * @param array $ignore
   */
  public function processFunctions (Array $functions, $ignore = ["h" => ["id"]]) {
    parent::processFunctions($functions, $ignore);
  }

  public function applySyntax () {
    $extend = [/*"strong", "em", "ins", "del", "sub", "sup", "a",*/
      "h", "desc",
    ];

    // hide noparse
    $noparse = [];
    foreach ($this->getNodes($extend) as $n)
      $noparse = array_merge($noparse, $this->parseSyntaxNoparse($n));

    // proceed syntax translation
    foreach ($this->getNodes() as $n) $this->parseSyntaxCodeTag($n);
    foreach ($this->getNodes() as $n) $this->parseSyntaxCode($n);
    foreach ($this->getNodes($extend) as $n) $this->parseSyntaxVariable($n);
    foreach ($this->getNodes($extend) as $n) $this->parseSyntaxComment($n);

    // restore noparse
    foreach ($noparse as $n) {
      $n[0]->nodeValue = $n[1];
      /*$newNode = $this->createTextNode($n[1]);
      $n[0]->parentNode->insertBefore($newNode, $n[0]);
      $n[0]->parentNode->removeChild($n[0]);*/
    }
  }

  /**
   * @param array $extend
   * @return DOMElementPlus[]
   */
  private function getNodes ($extend = []) {
    $nodes = [];
    foreach (array_merge(["p", "dt", "dd", "li"], $extend) as $eNam) {
      foreach ($this->getElementsByTagName($eNam) as $e) {
        $nodes[] = $e;
      }
    }
    return $nodes;
  }

  /**)
   * @param DOMElementPlus $n
   * @return array
   */
  private function parseSyntaxNoparse (DOMElementPlus $n) {
    $noparse = [];
    $pat = "/<noparse>(.+?)<\/noparse>/";
    $p = preg_split($pat, $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($p) < 2) {
      return $noparse;
    }
    $n->nodeValue = "";
    foreach ($p as $i => $v) {
      if ($i % 2 == 0) {
        $text = $n->ownerDocument->createTextNode($v);
      } else {
        $text = $n->ownerDocument->createTextNode("");
        $noparse[] = [$text, $v];
      }
      $n->appendChild($text);
    }
    return $noparse;
  }

  /**
   * @param DOMElementPlus $n
   */
  private function parseSyntaxCodeTag (DOMElementPlus $n) {
    $pat = "/<code(?: [a-z]+)?>((?:.|\n)+?)<\/code>/m";
    $p = preg_split($pat, $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($p) < 2) {
      return;
    }
    $defaultValue = $n->nodeValue;
    $n->nodeValue = "";
    foreach ($p as $i => $v) {
      if ($i % 2 == 0) {
        $n->appendChild($n->ownerDocument->createTextNode($v));
      } else {
        $s = ["&bdquo;", "&ldquo;", "&rdquo;", "&lsquo;", "&rsquo;"];
        $r = ['"', '"', '"', "'", "'"];
        $v = str_replace($s, $r, translateUtf8Entities($v, true));
        $newNode = $this->createElement("code", translateUtf8Entities($v));
        if (preg_match("/<code ([a-z]+)>/", $defaultValue, $match)) {
          $newNode->setAttribute("class", $match[1]);
        }
        $n->appendChild($newNode);
      }
    }
  }

  /**
   * @param DOMElementPlus $n
   */
  private function parseSyntaxCode (DOMElementPlus $n) {
    $pat = "/(?:&lsquo;|&rsquo;|'){2}(.+?)(?:&lsquo;|&rsquo;|'){2}/";
    $src = translateUtf8Entities($n->nodeValue, true);
    $p = preg_split($pat, $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($p) < 2) {
      return;
    }
    $n->nodeValue = "";
    foreach ($p as $i => $v) {
      if ($i % 2 == 0) {
        $n->appendChild($n->ownerDocument->createTextNode($v));
      } else {
        $s = ["&bdquo;", "&ldquo;", "&rdquo;", "&lsquo;", "&rsquo;"];
        $r = ['"', '"', '"', "'", "'"];
        $v = str_replace($s, $r, $v);
        $newNode = $this->createElement("code", translateUtf8Entities($v));
        $n->appendChild($newNode);
      }
    }
  }

  /**
   * @param DOMElementPlus $n
   */
  private function parseSyntaxVariable (DOMElementPlus $n) {
    //if(strpos($n->nodeValue, 'cms-') === false) return;
    foreach (explode('\$', $n->nodeValue) as $src) {
      $p = preg_split('/\$('.VARIABLE_PATTERN.")/", $src, -1, PREG_SPLIT_DELIM_CAPTURE);
      if (count($p) < 2) {
        return;
      }
      $defVal = $n->nodeValue;
      $n->nodeValue = "";
      foreach ($p as $i => $v) {
        if ($i % 2 == 0) {
          $n->appendChild($n->ownerDocument->createTextNode($v));
        } else {
          // <p>$varname</p> -> <p var="varname"/>
          // <p><strong>$varname</strong></p> -> <p><strong var="varname"/></p>
          // else
          // <p>aaa $varname</p> -> <p>aaa <em var="varname"/></p>
          if ($defVal == "\$$v") {
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

  /**
   * @param DOMElementPlus $n
   */
  private function parseSyntaxComment (DOMElementPlus $n) {
    $p = preg_split('/\(\(\s*(.+)\s*\)\)/', $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($p) < 2) {
      return;
    }
    $n->nodeValue = "";
    foreach ($p as $i => $v) {
      if ($i % 2 == 0) {
        $n->appendChild($n->ownerDocument->createTextNode($v));
      } else {
        $n->appendChild($this->createComment(" $v "));
      }
    }
  }

  /**
   * @param bool $repair
   * @throws Exception
   */
  public function validatePlus ($repair = false) {
    $i = 0;
    $hash = hash(FILE_HASH_ALGO, $this->saveXML());
    $version = 6; // increment if validatePlus changes
    $cacheKey = apc_get_key("HTMLPlus/validatePlus/$hash/$version");
    if (self::USE_APC && apc_exists($cacheKey)) {
      $i = apc_fetch($cacheKey);
    }
    #var_dump("$hash found $i");
    $this->headings = $this->getElementsByTagName("h");
    $this->status = self::STATUS_VALID;
    $this->errors = [];
    try {
      switch ($i) {
        case 0:
          if ($repair) {
            $this->validateRoot();
          }
          $i++;
        case 1:
          if ($repair) {
            $this->validateBodyAttributes();
          }
          $i++;
        case 2:
          if ($repair) {
            $this->validateSections();
          }
          $i++;
        case 3:
          if ($repair) {
            $this->validateLang();
          }
          $i++;
        case 4:
          // $this->validateHsrc();
          $i++;
        case 5:
          if ($repair) {
            $this->validateHempty();
          }
          $i++;
        case 6:
          if ($repair) {
            $this->validateDl();
          }
          $i++;
        case 7:
          $this->validateDates($repair);
          $i++;
        case 8:
          // if ($repair) {
          //   $this->validateAuthor();
          // }
          $i++;
        case 9:
          $this->validateFirstHeadingAuthor($repair);
          $i++;
        case 10:
          if ($repair) {
            $this->validateFirstHeadingId();
          }
          $i++;
        case 11:
          if ($repair) {
            $this->validateFirstHeadingCtime();
          }
          $i++;
        case 12:
          $this->validateHid($repair);
          $i++;
        case 13:
          if ($repair) {
            $this->validateBodyNs();
          }
          $i++;
        case 14:
          if ($repair) {
            $this->validateDesc();
          }
          $i++;
        case 15:
          $this->relaxNGValidatePlus();
          $i++;
      }
    } catch (Exception $exc) {
      $this->status = self::STATUS_INVALID;
      throw $exc;
    } finally {
      if (self::USE_APC && !count($this->errors)) {
        apc_store_cache($cacheKey, $i, "validatePlus");
      }
    }
    if (count($this->errors)) {
      $this->status = self::STATUS_REPAIRED;
    }
  }

  private function validateRoot () {
    if (is_null($this->documentElement)) {
      $message = _("Root element not found");
      $this->errorHandler($message, false);
    }
    if ($this->documentElement->nodeName != "body") {
      $message = _("Root element must be 'body'");
      $this->errorHandler($message, true);
      $this->documentElement->rename("body");
    }
    if (!$this->documentElement->hasAttribute("lang")
      && !$this->documentElement->hasAttribute("xml:lang")
    ) {
      $message = _("Attribute 'xml:lang' is missing in element body");
      $this->errorHandler($message, true);
      $this->documentElement->setAttribute("xml:lang", _("en"));
    }
    $fe = $this->documentElement->firstElement;
    if (!is_null($fe) && $fe->nodeName == "section") {
      $message = _("Element section cannot be empty");
      $this->errorHandler($message, true);
      $this->addTitleElements($this->documentElement);
      return;
    }
    $hRoot = 0;
    foreach ($this->documentElement->childNodes as $e) {
      if ($e->nodeType != XML_ELEMENT_NODE) {
        continue;
      }
      if ($e->nodeName != "h") {
        continue;
      }
      if ($hRoot++ == 0) {
        continue;
      }
      $message = _("There must be exactly one heading in body element");
      $this->errorHandler($message, true);
      break;
    }
    if ($hRoot == 1) {
      return;
    }
    if ($hRoot == 0) {
      $message = _("Missing heading in body element");
      $this->errorHandler($message, true);
      $this->documentElement->appendChild($this->createElement("h"));
      return;
    }
    $children = [];
    foreach ($this->documentElement->childNodes as $e) $children[] = $e;
    $s = $this->createElement("section");
    foreach ($children as $e) $s->appendChild($e);
    $s->appendChild($this->createTextNode("  "));
    $this->documentElement->appendChild($s);
    $this->documentElement->appendChild($this->createTextNode("\n"));
    $this->addTitleElements($s);
  }

  /**
   * @param string $message
   * @param bool $repair
   * @throws Exception
   */
  private function errorHandler ($message, $repair) {
    if (!$repair) {
      throw new Exception($message);
    }
    $this->errors[] = $message;
  }

  /**
   * @param DOMElementPlus $el
   */
  private function addTitleElements (DOMElementPlus $el) {
    $first = $el->firstElement;
    $el->insertBefore($this->createTextNode("\n  "), $first);
    $el->insertBefore($this->createElement("h", _("Web title")), $first);
    $el->insertBefore($this->createTextNode("\n  "), $first);
    $el->insertBefore($this->createElement("desc", _("Web description")), $first);
    $el->insertBefore($this->createTextNode("\n  "), $first);
  }

  private function validateBodyAttributes () {
    $validAttributes = ["id", "class", "title", "fn", "var", "xml:lang", "lang", "ns"];
    foreach ($this->documentElement->attributes as $attrName => $attr) {
      if (in_array($attrName, $validAttributes) || strpos($attrName, "data-") === 0) {
        continue;
      }
      $message = sprintf(_("Invalid body attribute '%s'"), $attrName);
      $this->errorHandler($message, true);
      $this->documentElement->stripAttr($attrName);
    }
  }

  private function validateSections () {
    $emptySect = [];
    foreach ($this->getElementsByTagName("section") as $s) {
      if (!count($s->childElementsArray)) {
        $emptySect[] = $s;
      }
    }
    if (!count($emptySect)) {
      return;
    }
    $message = _("Empty section(s) found");
    $this->errorHandler($message, true);
    foreach ($emptySect as $s) $s->stripTag(_("Empty section deleted"));
  }

  private function validateLang () {
    $xpath = new DOMXPath($this);
    $langs = $xpath->query("//*[@lang]");
    if (!$langs->length) {
      return;
    }
    $message = _("Lang attribute without XML namespace");
    $this->errorHandler($message, true);
    /** @var DOMElementPlus $n */
    foreach ($langs as $n) {
      if (!$n->hasAttribute("xml:lang")) {
        $n->setAttribute("xml:lang", $n->getAttribute("lang"));
      }
      $n->removeAttribute("lang");
    }
  }

  private function validateHsrc () {
    $invalid = [];
    /** @var DOMElementPlus $h */
    foreach ($this->headings as $h) {
      if (!$h->hasAttribute("src")) {
        continue;
      }
      if (preg_match("#^[a-z][a-z0-9_/.-]*\.html$#", $h->getAttribute("src"))) {
        continue;
      }
      $invalid[] = $h->getAttribute("src");
    }
    if (empty($invalid)) {
      return;
    }
    $message = sprintf(_("Invalid src format: %s"), implode(", ", $invalid));
    $this->errorHandler($message, false);
  }

  private function validateHempty () {
    foreach ($this->headings as $h) {
      if (strlen(trim($h->nodeValue))) {
        continue;
      }
      $message = _("Heading content must not be empty");
      $this->errorHandler($message, !is_null($this->defaultHeading));
      $h->nodeValue = $this->defaultHeading;
    }
  }

  private function validateDl () {
    $dts = [];
    foreach ($this->getElementsByTagName("dt") as $dt) $dts[] = $dt;
    /** @var DOMElementPlus $dt */
    foreach ($dts as $dt) {
      $nextElement = $dt->nextElement;
      if (!is_null($nextElement) && $nextElement->tagName == "dd") {
        continue;
      }
      $this->errorHandler(_("Element dt following sibling must be dd"), true);
      $dt->parentNode->insertBefore($dt->ownerDocument->createElement("dd", "n/a"), $nextElement);
    }
  }

  /**
   * TODO refactor Attribute 'mtime' requires 'ctime'
   * @param bool $repair
   */
  private function validateDates ($repair) {
    /** @var DOMElementPlus $h */
    foreach ($this->headings as $h) {
      $ctime = null;
      $mtime = null;
      if ($h->hasAttribute("ctime")) {
        $ctime = $h->getAttribute("ctime");
      }
      if ($h->hasAttribute("mtime")) {
        $mtime = $h->getAttribute("mtime");
      }
      if (is_null($ctime) && is_null($mtime)) {
        continue;
      }
      if (is_null($ctime)) {
        $ctime = $h->getAncestorValue("ctime", "h");
      }
      if (is_null($ctime)) {
        $this->errorHandler(_("Attribute 'mtime' requires 'ctime'"), $repair);
        $ctime = $mtime;
        $h->setAttribute("ctime", $ctime);
      }
      $ctime_date = $this->createDate($ctime);
      if (is_null($ctime_date)) {
        $this->errorHandler(_("Invalid 'ctime' attribute format"), $repair);
        $h->parentNode->insertBefore($this->newDOMComment(sprintf(_("Removed attribute ctime '%s'"))), $h);
        $h->removeAttribute("ctime");
      }
      if (is_null($mtime)) {
        return;
      }
      $mtime_date = $this->createDate($mtime);
      if (is_null($mtime_date)) {
        $this->errorHandler(_("Invalid 'mtime' attribute format"), $repair);
        $h->parentNode->insertBefore($this->newDOMComment(sprintf(_("Removed attribute mtime '%s'"))), $h);
        $h->removeAttribute("mtime");
      }
      if ($mtime_date < $ctime_date) {
        $this->errorHandler(_("Attribute 'mtime' cannot be lower than 'ctime'"), $repair);
        $h->parentNode->insertBefore($this->newDOMComment(sprintf(_("Removed attribute mtime '%s'"))), $h);
        $h->removeAttribute("mtime");
      }
    }
  }

  /**
   * @param string $d
   * @return DateTime|null
   */
  private function createDate ($d) {
    $date = new DateTime();
    $date->setTimestamp(strtotime($d));
    $date_errors = DateTime::getLastErrors();
    if ($date_errors['warning_count'] + $date_errors['error_count'] > 0) {
      return null;
    }
    return $date;
  }

  /**
   * @param string $comment
   * @return DOMComment
   */
  private function newDOMComment ($comment) {
    return new DOMComment(" $comment ");
  }

  private function validateAuthor () {
    /** @var DOMElementPlus $h */
    foreach ($this->headings as $h) {
      if (!$h->hasAttribute("author")) {
        continue;
      }
      if (strlen(trim($h->getAttribute("author")))) {
        continue;
      }
      $this->errorHandler(_("Attribute 'author' cannot be empty"), true);
      $h->parentNode->insertBefore($this->newDOMComment(_("Removed empty attribute 'author'")), $h);
      $h->removeAttribute("author");
    }
  }

  /**
   * @param bool $repair
   */
  private function validateFirstHeadingAuthor ($repair) {
    /** @var DOMElementPlus $h */
    $h = $this->headings->item(0);
    if ($h->hasAttribute("author")) {
      return;
    }
    $message = _("First heading attribute 'author' missing");
    $this->errorHandler($message, $repair && !is_null($this->defaultAuthor));
    $h->setAttribute("author", $this->defaultAuthor);
  }

  private function validateFirstHeadingId () {
    /** @var DOMElementPlus $h */
    $h = $this->headings->item(0);
    if ($h->hasAttribute("id")) {
      return;
    }
    $message = _("First heading attribute 'id' missing");
    $this->errorHandler($message, !is_null($this->defaultId));
    $h->setAttribute("id", $this->defaultId);
  }

  private function validateFirstHeadingCtime () {
    /** @var DOMElementPlus $h */
    $h = $this->headings->item(0);
    if ($h->hasAttribute("ctime")) {
      return;
    }
    $message = _("First heading attribute 'ctime' missing");
    $this->errorHandler($message, !is_null($this->defaultCtime));
    $h->setAttribute("ctime", $this->defaultCtime);
  }

  /**
   * @param bool $repair
   */
  private function validateHid ($repair) {
    $hIds = [];
    $anchors = $this->getElementsByTagName("a");
    /** @var DOMElementPlus $h */
    foreach ($this->headings as $h) {
      $id = $h->getAttribute("id");
      $message = null;
      if (!strlen($id)) {
        $message = sprintf(_("Heading attribute id empty or missing: %s"), $h->nodeValue);
      } elseif (!isValidId($id)) {
        $message = sprintf(_("Invalid heading attribute id '%s'"), $id);
      } elseif (array_key_exists($id, $hIds)) {
        $message = sprintf(_("Duplicit heading attribute id '%s'"), $id);
      }
      if (!is_null($message)) {
        $this->errorHandler($message, $repair);
        $newId = $h->setUniqueId();
        /** @var DOMElementPlus $a */
        foreach ($anchors as $a) {
          $href = $a->getAttribute("href");
          if (substr($href, -strlen("#$id")) != "#$id") {
            continue;
          }
          $a->setAttribute("href", str_replace("#$id", "#$newId", $href));
        }
      }
      $hIds[$h->getAttribute("id")] = null;
    }
  }

  private function validateBodyNs () {
    $b = $this->documentElement;
    if ($b->hasAttribute("ns")) {
      return;
    }
    /** @var DOMElementPlus $h */
    $h = $this->headings->item(0);
    if ($h->hasAttribute("ns")) {
      $this->defaultNs = $h->getAttribute("ns");
      $h->removeAttribute("ns");
    }
    $message = _("Body attribute 'ns' missing");
    $this->errorHandler($message, !is_null($this->defaultNs));
    $b->setAttribute("ns", $this->defaultNs);
  }

  private function validateDesc () {
    $invalid = [];
    /** @var DOMElementPlus $h */
    foreach ($this->headings as $h) {
      $desc = $h->nextElement;
      if (is_null($desc)
        || $desc->nodeName != "desc"
        || !strlen(trim($desc->nodeValue))
      ) {
        $invalid[$h->getAttribute('id')] = $h;
      }
    }
    if (empty($invalid)) {
      return;
    }
    $message = sprintf(_("Heading description missing or empty: %s"), implode(", ", array_keys($invalid)));
    $this->errorHandler($message, true);
    foreach ($invalid as $headingId => $h) {
      $this->repairDesc($h);
    }
  }

  /**
   * @param DOMElementPlus $h
   */
  private function repairDesc (DOMElementPlus $h) {
    $next = $h->nextElement;
    if ($next->nodeName == 'p') {
      $next->rename('desc');
      return;
    }
    $desc = new DOMElementPlus('desc');
    $desc->nodeValue = 'n/a';
    $h->parentNode->insertBefore($desc, $next);
  }

  /**
   * @param string|null $f
   * @param DOMDocument|null $doc
   * @return bool
   * @throws Exception
   */
  public function relaxNGValidatePlus ($f = null, DOMDocument $doc = null) {
    $proc = new XSLTProcessor();
    $xsl = XMLBuilder::load(LIB_DIR."/".self::PREPARE_TO_VALIDATE_FILE);
    if (!@$proc->importStylesheet($xsl)) {
      throw new Exception(sprintf("File '%s' is invalid", self::PREPARE_TO_VALIDATE_FILE));
    }
    $docToValidation = is_null($doc) ? $this : $doc;
    if (($preparedDoc = @$proc->transformToDoc($docToValidation)) === false) {
      throw new Exception(sprintf("File '%s' transformation fail", self::PREPARE_TO_VALIDATE_FILE));
    }
    return parent::relaxNGValidatePlus(LIB_FOLDER."/".self::RNG_FILE, $preparedDoc);
  }

  public function repairIds () {
    $this->errors = [];
    $this->validateHid(true);
  }

}

?>
