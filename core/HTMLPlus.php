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
    $ctime = new DateTime("now");
    $this->defaultCtime = $ctime->format(DateTime::W3C);
    $this->defaultNs = HTTP_HOST;
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
    foreach ($this->getNodes($extend) as $node)
      $noparse = array_merge($noparse, $this->parseSyntaxNoparse($node));

    // proceed syntax translation
    foreach ($this->getNodes() as $node) $this->parseSyntaxCodeTag($node);
    foreach ($this->getNodes() as $node) $this->parseSyntaxCode($node);
    foreach ($this->getNodes($extend) as $node) $this->parseSyntaxVariable($node);
    foreach ($this->getNodes($extend) as $node) $this->parseSyntaxComment($node);

    // restore noparse
    foreach ($noparse as $node) {
      $node[0]->nodeValue = $node[1];
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
      foreach ($this->getElementsByTagName($eNam) as $ele) {
        $nodes[] = $ele;
      }
    }
    return $nodes;
  }

  /**)
   * @param DOMElementPlus $ele
   * @return array
   */
  private function parseSyntaxNoparse (DOMElementPlus $ele) {
    $noparse = [];
    $pattern = "/<noparse>(.+?)<\/noparse>/";
    $stringArray = preg_split($pattern, $ele->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($stringArray) < 2) {
      return $noparse;
    }
    $ele->nodeValue = "";
    foreach ($stringArray as $pos => $value) {
      if ($pos % 2 == 0) {
        $text = $ele->ownerDocument->createTextNode($value);
      } else {
        $text = $ele->ownerDocument->createTextNode("");
        $noparse[] = [$text, $value];
      }
      $ele->appendChild($text);
    }
    return $noparse;
  }

  /**
   * @param DOMElementPlus $ele
   */
  private function parseSyntaxCodeTag (DOMElementPlus $ele) {
    $pat = "/<code(?: [a-z]+)?>((?:.|\n)+?)<\/code>/m";
    $arr = preg_split($pat, $ele->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($arr) < 2) {
      return;
    }
    $defaultValue = $ele->nodeValue;
    $ele->nodeValue = "";
    foreach ($arr as $pos => $value) {
      if ($pos % 2 == 0) {
        $ele->appendChild($ele->ownerDocument->createTextNode($value));
      } else {
        $search = ["&bdquo;", "&ldquo;", "&rdquo;", "&lsquo;", "&rsquo;"];
        $replace = ['"', '"', '"', "'", "'"];
        $value = str_replace($search, $replace, to_utf8($value, true));
        $newNode = $this->createElement("code", to_utf8($value));
        if (preg_match("/<code ([a-z]+)>/", $defaultValue, $match)) {
          $newNode->setAttribute("class", $match[1]);
        }
        $ele->appendChild($newNode);
      }
    }
  }

  /**
   * @param DOMElementPlus $n
   */
  private function parseSyntaxCode (DOMElementPlus $n) {
    $pat = "/(?:&lsquo;|&rsquo;|'){2}(.+?)(?:&lsquo;|&rsquo;|'){2}/";
    $src = to_utf8($n->nodeValue, true);
    $arr = preg_split($pat, $src, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($arr) < 2) {
      return;
    }
    $n->nodeValue = "";
    foreach ($arr as $pos => $value) {
      if ($pos % 2 == 0) {
        $n->appendChild($n->ownerDocument->createTextNode($value));
        continue;
      }
      $search = ["&bdquo;", "&ldquo;", "&rdquo;", "&lsquo;", "&rsquo;"];
      $replace = ['"', '"', '"', "'", "'"];
      $value = str_replace($search, $replace, $value);
      $newNode = $this->createElement("code", to_utf8($value));
      $n->appendChild($newNode);
    }
  }

  /**
   * @param DOMElementPlus $n
   */
  private function parseSyntaxVariable (DOMElementPlus $n) {
    //if(strpos($n->nodeValue, 'cms-') === false) return;
    foreach (explode('\$', $n->nodeValue) as $src) {
      $arr = preg_split('/\$('.VARIABLE_PATTERN.")/", $src, -1, PREG_SPLIT_DELIM_CAPTURE);
      if (count($arr) < 2) {
        return;
      }
      $defVal = $n->nodeValue;
      $n->nodeValue = "";
      foreach ($arr as $pos => $value) {
        if ($pos % 2 == 0) {
          $n->appendChild($n->ownerDocument->createTextNode($value));
          continue;
        }
        // <p>$varname</p> -> <p var="varname"/>
        // <p><strong>$varname</strong></p> -> <p><strong var="varname"/></p>
        // else
        // <p>aaa $varname</p> -> <p>aaa <em var="varname"/></p>
        if ($defVal == "\$$value") {
          $n->setAttribute("var", $value);
        } else {
          $newNode = $this->createElement("em");
          $newNode->setAttribute("var", $value);
          $n->appendChild($newNode);
        }
      }
    }
  }

  /**
   * @param DOMElementPlus $n
   */
  private function parseSyntaxComment (DOMElementPlus $n) {
    $arr = preg_split('/\(\(\s*(.+)\s*\)\)/', $n->nodeValue, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (count($arr) < 2) {
      return;
    }
    $n->nodeValue = "";
    foreach ($arr as $pos => $value) {
      if ($pos % 2 == 0) {
        $n->appendChild($n->ownerDocument->createTextNode($value));
        continue;
      }
      $n->appendChild($this->createComment(" $value "));
    }
  }

  /**
   * @param bool $repair
   * @throws Exception
   */
  public function validatePlus ($repair = false) {
    $iter = 0;
    $hash = hash(FILE_HASH_ALGO, $this->saveXML());
    $version = 6; // increment if validatePlus changes
    $cacheKey = apc_get_key("HTMLPlus/validatePlus/$hash/$version");
    if (self::USE_APC && apc_exists($cacheKey)) {
      $iter = apc_fetch($cacheKey);
    }
    #var_dump("$hash found $i");
    $this->headings = $this->getElementsByTagName("h");
    $this->status = self::STATUS_VALID;
    $this->errors = [];
    try {
      switch ($iter) {
        case 0:
          if ($repair) {
            $this->validateRoot();
          }
          $iter++;
        case 1:
          if ($repair) {
            $this->validateBodyAttrs();
          }
          $iter++;
        case 2:
          if ($repair) {
            $this->validateSections();
          }
          $iter++;
        case 3:
          if ($repair) {
            $this->validateLang();
          }
          $iter++;
        case 4:
          // $this->validateHsrc();
          $iter++;
        case 5:
          if ($repair) {
            $this->validateHempty();
          }
          $iter++;
        case 6:
          if ($repair) {
            $this->validateDl();
          }
          $iter++;
        case 7:
          $this->validateDates($repair);
          $iter++;
        case 8:
          // if ($repair) {
          //   $this->validateAuthor();
          // }
          $iter++;
        case 9:
          $this->validateHeadingAuthor($repair);
          $iter++;
        case 10:
          if ($repair) {
            $this->validateHeadingId();
          }
          $iter++;
        case 11:
          if ($repair) {
            $this->validateHeadingCtime();
          }
          $iter++;
        case 12:
          $this->validateHid($repair);
          $iter++;
        case 13:
          if ($repair) {
            $this->validateBodyNs();
          }
          $iter++;
        case 14:
          if ($repair) {
            $this->validateDesc();
          }
          $iter++;
        case 15:
          $this->relaxNGValidatePlus();
          $iter++;
      }
    } catch (Exception $exc) {
      $this->status = self::STATUS_INVALID;
      throw $exc;
    } finally {
      if (self::USE_APC && !count($this->errors)) {
        apc_store_cache($cacheKey, $iter, "validatePlus");
      }
    }
    if (count($this->errors)) {
      $this->status = self::STATUS_REPAIRED;
    }
  }

  /**
   * @throws Exception
   */
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
    $ele = $this->documentElement->firstElement;
    if (!is_null($ele) && $ele->nodeName == "section") {
      $message = _("Element section cannot be empty");
      $this->errorHandler($message, true);
      $this->addTitleElements($this->documentElement);
      return;
    }
    $hRoot = 0;
    foreach ($this->documentElement->childNodes as $ele) {
      if ($ele->nodeType != XML_ELEMENT_NODE) {
        continue;
      }
      if ($ele->nodeName != "h") {
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
    foreach ($this->documentElement->childNodes as $ele) {
      $children[] = $ele;
    }
    $sect = $this->createElement("section");
    foreach ($children as $ele) $sect->appendChild($ele);
    $sect->appendChild($this->createTextNode("  "));
    $this->documentElement->appendChild($sect);
    $this->documentElement->appendChild($this->createTextNode("\n"));
    $this->addTitleElements($sect);
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

  /**
   * @throws Exception
   */
  private function validateBodyAttrs () {
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

  /**
   * @throws Exception
   */
  private function validateSections () {
    $emptySect = [];
    foreach ($this->getElementsByTagName("section") as $sect) {
      if (!count($sect->childElementsArray)) {
        $emptySect[] = $sect;
      }
    }
    if (!count($emptySect)) {
      return;
    }
    $message = _("Empty section(s) found");
    $this->errorHandler($message, true);
    foreach ($emptySect as $sect) {
      $sect->stripTag(_("Empty section deleted"));
    }
  }

  /**
   * @throws Exception
   */
  private function validateLang () {
    $xpath = new DOMXPath($this);
    $langs = $xpath->query("//*[@lang]");
    if (!$langs->length) {
      return;
    }
    $message = _("Lang attribute without XML namespace");
    $this->errorHandler($message, true);
    /** @var DOMElementPlus $ele */
    foreach ($langs as $ele) {
      if (!$ele->hasAttribute("xml:lang")) {
        $ele->setAttribute("xml:lang", $ele->getAttribute("lang"));
      }
      $ele->removeAttribute("lang");
    }
  }

  /**
   * @throws Exception
   */
  private function validateHsrc () {
    $invalid = [];
    /** @var DOMElementPlus $heading */
    foreach ($this->headings as $heading) {
      if (!$heading->hasAttribute("src")) {
        continue;
      }
      if (preg_match("#^[a-z][a-z0-9_/.-]*\.html$#", $heading->getAttribute("src"))) {
        continue;
      }
      $invalid[] = $heading->getAttribute("src");
    }
    if (empty($invalid)) {
      return;
    }
    $message = sprintf(_("Invalid src format: %s"), implode(", ", $invalid));
    $this->errorHandler($message, false);
  }

  /**
   * @throws Exception
   */
  private function validateHempty () {
    foreach ($this->headings as $heading) {
      if (strlen(trim($heading->nodeValue))) {
        continue;
      }
      $message = _("Heading content must not be empty");
      $this->errorHandler($message, !is_null($this->defaultHeading));
      $heading->nodeValue = $this->defaultHeading;
    }
  }

  /**
   * @throws Exception
   */
  private function validateDl () {
    $dts = [];
    foreach ($this->getElementsByTagName("dt") as $dterm) $dts[] = $dterm;
    /** @var DOMElementPlus $dterm */
    foreach ($dts as $dterm) {
      $nextElement = $dterm->nextElement;
      if (!is_null($nextElement) && $nextElement->tagName == "dd") {
        continue;
      }
      $this->errorHandler(_("Element dt following sibling must be dd"), true);
      $dterm->parentNode->insertBefore($dterm->ownerDocument->createElement("dd", "n/a"), $nextElement);
    }
  }

  /**
   * TODO refactor Attribute 'mtime' requires 'ctime'
   * @param bool $repair
   * @throws Exception
   */
  private function validateDates ($repair) {
    /** @var DOMElementPlus $heading */
    foreach ($this->headings as $heading) {
      $ctime = null;
      $mtime = null;
      if ($heading->hasAttribute("ctime")) {
        $ctime = $heading->getAttribute("ctime");
      }
      if ($heading->hasAttribute("mtime")) {
        $mtime = $heading->getAttribute("mtime");
      }
      if (is_null($ctime) && is_null($mtime)) {
        continue;
      }
      if (is_null($ctime)) {
        $ctime = $heading->getAncestorValue("ctime", "h");
      }
      if (is_null($ctime)) {
        $this->errorHandler(_("Attribute 'mtime' requires 'ctime'"), $repair);
        $ctime = $mtime;
        $heading->setAttribute("ctime", $ctime);
      }
      $ctime_date = $this->createDate($ctime);
      if (is_null($ctime_date)) {
        $this->errorHandler(_("Invalid 'ctime' attribute format"), $repair);
        $heading->parentNode->insertBefore($this->newDOMComment(sprintf(_("Removed attribute ctime '%s'"))), $heading);
        $heading->removeAttribute("ctime");
      }
      if (is_null($mtime)) {
        return;
      }
      $mtime_date = $this->createDate($mtime);
      if (is_null($mtime_date)) {
        $this->errorHandler(_("Invalid 'mtime' attribute format"), $repair);
        $heading->parentNode->insertBefore($this->newDOMComment(sprintf(_("Removed attribute mtime '%s'"))), $heading);
        $heading->removeAttribute("mtime");
      }
      if ($mtime_date < $ctime_date) {
        $this->errorHandler(_("Attribute 'mtime' cannot be lower than 'ctime'"), $repair);
        $heading->parentNode->insertBefore($this->newDOMComment(sprintf(_("Removed attribute mtime '%s'"))), $heading);
        $heading->removeAttribute("mtime");
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

  /**
   * @throws Exception
   */
  private function validateAuthor () {
    /** @var DOMElementPlus $heading */
    foreach ($this->headings as $heading) {
      if (!$heading->hasAttribute("author")) {
        continue;
      }
      if (strlen(trim($heading->getAttribute("author")))) {
        continue;
      }
      $this->errorHandler(_("Attribute 'author' cannot be empty"), true);
      $heading->parentNode->insertBefore($this->newDOMComment(_("Removed empty attribute 'author'")), $heading);
      $heading->removeAttribute("author");
    }
  }

  /**
   * @param bool $repair
   * @throws Exception
   */
  private function validateHeadingAuthor ($repair) {
    /** @var DOMElementPlus $heading */
    $heading = $this->headings->item(0);
    if ($heading->hasAttribute("author")) {
      return;
    }
    $message = _("First heading attribute 'author' missing");
    $this->errorHandler($message, $repair && !is_null($this->defaultAuthor));
    $heading->setAttribute("author", $this->defaultAuthor);
  }

  /**
   * @throws Exception
   */
  private function validateHeadingId () {
    /** @var DOMElementPlus $heading */
    $heading = $this->headings->item(0);
    if ($heading->hasAttribute("id")) {
      return;
    }
    $message = _("First heading attribute 'id' missing");
    $this->errorHandler($message, !is_null($this->defaultId));
    $heading->setAttribute("id", $this->defaultId);
  }

  /**
   * @throws Exception
   */
  private function validateHeadingCtime () {
    /** @var DOMElementPlus $heading */
    $heading = $this->headings->item(0);
    if ($heading->hasAttribute("ctime")) {
      return;
    }
    $message = _("First heading attribute 'ctime' missing");
    $this->errorHandler($message, !is_null($this->defaultCtime));
    $heading->setAttribute("ctime", $this->defaultCtime);
  }

  /**
   * @param bool $repair
   * @throws Exception
   */
  private function validateHid ($repair) {
    $hIds = [];
    $anchors = $this->getElementsByTagName("a");
    /** @var DOMElementPlus $heading */
    foreach ($this->headings as $heading) {
      $hId = $heading->getAttribute("id");
      $message = null;
      if (!strlen($hId)) {
        $message = sprintf(_("Heading attribute id empty or missing: %s"), $heading->nodeValue);
      } elseif (!is_valid_id($hId)) {
        $message = sprintf(_("Invalid heading attribute id '%s'"), $hId);
      } elseif (array_key_exists($hId, $hIds)) {
        $message = sprintf(_("Duplicit heading attribute id '%s'"), $hId);
      }
      if (!is_null($message)) {
        $this->errorHandler($message, $repair);
        $newId = $heading->setUniqueId();
        /** @var DOMElementPlus $anchor */
        foreach ($anchors as $anchor) {
          $href = $anchor->getAttribute("href");
          if (substr($href, -strlen("#$hId")) != "#$hId") {
            continue;
          }
          $anchor->setAttribute("href", str_replace("#$hId", "#$newId", $href));
        }
      }
      $hIds[$heading->getAttribute("id")] = null;
    }
  }

  /**
   * @throws Exception
   */
  private function validateBodyNs () {
    $body = $this->documentElement;
    if ($body->hasAttribute("ns")) {
      return;
    }
    /** @var DOMElementPlus $heading */
    $heading = $this->headings->item(0);
    if ($heading->hasAttribute("ns")) {
      $this->defaultNs = $heading->getAttribute("ns");
      $heading->removeAttribute("ns");
    }
    $message = _("Body attribute 'ns' missing");
    $this->errorHandler($message, !is_null($this->defaultNs));
    $body->setAttribute("ns", $this->defaultNs);
  }

  /**
   * @throws Exception
   */
  private function validateDesc () {
    $invalid = [];
    /** @var DOMElementPlus $heading */
    foreach ($this->headings as $heading) {
      $desc = $heading->nextElement;
      if (is_null($desc)
        || $desc->nodeName != "desc"
        || !strlen(trim($desc->nodeValue))
      ) {
        $invalid[$heading->getAttribute('id')] = $heading;
      }
    }
    if (empty($invalid)) {
      return;
    }
    $message = sprintf(_("Heading description missing or empty: %s"), implode(", ", array_keys($invalid)));
    $this->errorHandler($message, true);
    foreach ($invalid as $headingId => $heading) {
      $this->repairDesc($heading);
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
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (!@$proc->importStylesheet($xsl)) {
      throw new Exception(sprintf("File '%s' is invalid", self::PREPARE_TO_VALIDATE_FILE));
    }
    $docToValidation = is_null($doc) ? $this : $doc;
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    if (($preparedDoc = @$proc->transformToDoc($docToValidation)) === false) {
      throw new Exception(sprintf("File '%s' transformation fail", self::PREPARE_TO_VALIDATE_FILE));
    }
    return parent::relaxNGValidatePlus(LIB_FOLDER."/".self::RNG_FILE, $preparedDoc);
  }

  /**
   * @throws Exception
   */
  public function repairIds () {
    $this->errors = [];
    $this->validateHid(true);
  }

}
