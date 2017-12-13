<?php

namespace IGCMS\Plugins;

use DOMDocument;
use DOMElement;
use DOMImplementation;
use DOMXPath;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\OutputStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use IGCMS\Core\XMLBuilder;
use SplObserver;
use SplSubject;
use XSLTProcessor;

/**
 * Class HtmlOutput
 * @package IGCMS\Plugins
 */
class HtmlOutput extends Plugin implements SplObserver, OutputStrategyInterface {
    /**
   * @var string
   */
  const APPEND_HEAD = "head"; // String filename => Int priority
    /**
   * @var string
   */
  const APPEND_BODY = "body"; // String filename => Int priority
  /**
   * @var string
   */
  const FAVICON = "favicon.ico";
  /**
   * @var int
   */
  const DEFAULT_PRIORITY = 50;
  /**
   * @var array
   */
  private $jsFiles = [];
  /**
   * @var array
   */
  private $jsFilesPriority = [];
  /**
   * @var array
   */
  private $cssFiles = [];
  /**
   * @var array
   */
  private $cssFilesPriority = [];
  /**
   * @var array
   */
  private $transformationsPriority = [];
  /**
   * @var array
   */
  private $transformations = [];
  /**
   * @var string|null
   */
  private $favIcon = null;
  /**
   * @var DOMDocumentPlus|null
   */
  private $cfg = null;

  /**
   * HtmlOutput constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 0);
    Cms::setOutputStrategy($this);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if ($this->detachIfNotAttached("FileHandler")) {
      return;
    }
    if ($subject->getStatus() != STATUS_PROCESS) {
      return;
    }
    $this->cfg = $this->getXML();
    $this->registerThemes($this->cfg);
    if (is_null($this->favIcon)) {
      $this->favIcon = findFile($this->pluginDir."/".self::FAVICON);
    }
  }

  /**
   * @param DOMDocumentPlus $cfg
   */
  private function registerThemes (DOMDocumentPlus $cfg) {

    // add default xsl
    $this->addTransformation($this->pluginDir."/".$this->className.".xsl", 1);

    // add template files
    $theme = $cfg->getElementById("theme");
    if (!is_null($theme)) {
      $themeId = $theme->nodeValue;
      $t = $cfg->getElementById($themeId);
      if (is_null($t)) {
        Logger::user_warning(sprintf(_("Theme '%s' not found"), $themeId));
      } else {
        $this->addThemeFiles($t);
      }
    }

    // add root template files
    $this->addThemeFiles($cfg->documentElement);
  }

  /**
   * @param string $filePath
   * @param int $priority
   */
  public function addTransformation ($filePath, $priority = self::DEFAULT_PRIORITY) {
    if (isset($this->transformations[$filePath])) {
      return;
    }
    Cms::addVariableItem("transformations", $filePath);
    $this->transformations[$filePath] = [
      "priority" => $priority,
      "file" => $filePath,
    ];
    $this->transformationsPriority[$filePath] = $priority;
  }

  /**
   * @param DOMElementPlus|null $e
   */
  private function addThemeFiles (DOMElementPlus $e = null) {
    foreach ($e->childElementsArray as $n) {
      if ($n->nodeValue == "" || in_array($n->nodeName, ["var", "themes"])) {
        continue;
      }
      try {
        switch ($n->nodeName) {
          case "xslt":
            $this->addTransformation($n->nodeValue, 5);
            break;
          case "jsFile":
            $user = !$n->hasAttribute("readonly");
            $append = self::APPEND_HEAD;
            $priority = self::DEFAULT_PRIORITY;
            if ($n->hasAttribute("append")) {
              $append = $n->getAttribute("append");
            }
            if ($n->hasAttribute("priority")) {
              $priority = $n->getAttribute("priority");
            }
            $ieIfComment = ($n->hasAttribute("if") ? $n->getAttribute("if") : null);
            $ifXpath = ($n->hasAttribute("if-xpath") ? $n->getAttribute("if-xpath") : false);
            $this->addJsFile($n->nodeValue, $priority, $append, $user, $ieIfComment, $ifXpath);
            break;
          case "stylesheet":
            $media = ($n->hasAttribute("media") ? $n->getAttribute("media") : false);
            $ieIfComment = ($n->hasAttribute("if") ? $n->getAttribute("if") : null);
            $ifXpath = ($n->hasAttribute("if-xpath") ? $n->getAttribute("if-xpath") : false);
            $this->addCssFile($n->nodeValue, $media, self::DEFAULT_PRIORITY, true, $ieIfComment, $ifXpath);
            break;
          case "favicon":
            $this->favIcon = findFile($n->nodeValue);
        }
      } catch (Exception $e) {
        Logger::user_warning(sprintf(_("File %s of type %s not found"), $n->nodeValue, $n->nodeName));
      }
    }
  }

  /**
   * @param string $filePath
   * @param int $priority
   * @param string $append
   * @param bool $user
   * @param null $ieIfComment
   * @param bool $ifXpath
   */
  public function addJsFile ($filePath, $priority = self::DEFAULT_PRIORITY, $append = self::APPEND_HEAD, $user = false, $ieIfComment = null, $ifXpath = false) {
    if (isset($this->jsFiles[$filePath])) {
      return;
    }
    Cms::addVariableItem("javascripts", $filePath);
    #if(findFile($filePath, $user) === false) throw new Exception();
    $this->jsFiles[$filePath] = [
      "file" => $filePath,
      "append" => $append,
      "content" => "",
      "user" => $user,
      "ifXpath" => $ifXpath,
      "if" => $ieIfComment,
    ];
    $this->jsFilesPriority[$filePath] = $priority;
  }

  /**
   * @param string $filePath
   * @param bool $media
   * @param int $priority
   * @param bool $user
   * @param string|null $ieIfComment
   * @param bool $ifXpath
   */
  public function addCssFile ($filePath, $media = false, $priority = self::DEFAULT_PRIORITY, $user = true, $ieIfComment = null, $ifXpath = false) {
    if (isset($this->cssFiles[$filePath])) {
      return;
    }
    Cms::addVariableItem("styles", $filePath);
    #if(findFile($filePath, $user) === false) throw new Exception();
    $this->cssFiles[$filePath] = [
      "priority" => $priority,
      "file" => $filePath,
      "media" => $media,
      "user" => $user,
      "ifXpath" => $ifXpath,
      "if" => $ieIfComment,
    ];
    $this->cssFilesPriority[$filePath] = $priority;
  }

  /**
   * Create HTML 5 output from HTML+ content and own registers (JS/CSS)
   * @param HTMLPlus $content
   * @return string
   */
  public function getOutput (HTMLPlus $content) {
    stableSort($this->cssFilesPriority);
    stableSort($this->jsFilesPriority);
    $h1 = $content->documentElement->firstElement;
    $lang = $content->documentElement->getAttribute("xml:lang");

    // apply transformations
    $content = $this->applyTransformations($content);
    $contentPlus = new DOMDocumentPlus();
    $contentPlus->loadXML($content->saveXML());

    // final plugin modification
    global $plugins;
    foreach ($plugins->getIsInterface("IGCMS\\Core\\FinalContentStrategyInterface") as $fcs) {
      $contentPlus = $fcs->getContent($contentPlus);
    }

    // create output DOM with doctype
    $doc = $this->createDoc();
    $html = $this->addRoot($doc, $lang);

    // final validation
    $contentPlus->processFunctions(Cms::getAllFunctions());
    $xPath = new DOMXPath($contentPlus);
    $this->addHead($doc, $html, $h1, $xPath);

    /** @var DOMElementPlus $a */
    foreach ($xPath->query("//*[@var]") as $a) $a->stripAttr("var");
    foreach ($xPath->query("//*[@fn]") as $a) $a->stripAttr("fn");
    foreach ($xPath->query("//select[@pattern]") as $a) $a->stripAttr("pattern");
    foreach ($contentPlus->getElementsByTagName("a") as $e) {
      $this->processElement($e, "href");
    }
    foreach ($contentPlus->getElementsByTagName("form") as $e) {
      $this->processElement($e, "action");
    }
    $ids = [];
    foreach ($xPath->query("//*/@id") as $e) {
      $ids[$e->nodeValue] = null;
    }
    /** @var DOMElementPlus $img */
    foreach ($contentPlus->getElementsByTagName("img") as $img) {
      $this->processImage($img, $ids);
    }
    foreach ($xPath->query("//*[@xml:lang]") as $a) {
      if (!$a->hasAttribute("lang")) {
        $a->setAttribute("lang", $a->getAttribute("xml:lang"));
      }
      $a->removeAttribute("xml:lang");
    }
    $this->consolidateLang($contentPlus->documentElement, $lang);

    // import into html and save
    /** @var DOMElement $content */
    $content = $doc->importNode($contentPlus->documentElement, true);
    $html->appendChild($content);
    $this->appendJsFiles($html->getElementsByTagName("head")->item(0), self::APPEND_HEAD, $xPath);
    $this->appendJsFiles($content, self::APPEND_BODY, $xPath);

    $this->validateEmptyContent($doc);
    $html = $doc->saveXML();
    return substr($html, strpos($html, "\n") + 1);
  }

  /**
   * @param DOMDocumentPlus $content
   * @return DOMDocument|DOMDocumentPlus
   */
  private function applyTransformations (DOMDocumentPlus $content) {
    $proc = new XSLTProcessor();
    /** @noinspection PhpParamsInspection */
    $proc->setParameter('', $this->getProcParams());
    stableSort($this->transformationsPriority);
    foreach ($this->transformationsPriority as $xslt => $priority) {
      try {
        $newContent = $this->transform($content, $xslt, $proc);
        $newContent->encoding = "utf-8";
        $xml = $newContent->saveXML();
        if (!@$newContent->loadXML($xml)) {
          throw new Exception(sprintf(_("Invalid transformation or parameter in '%s'"), $xslt));
        }
        #todo: validate HTML5 validity
        $content = $newContent;
      } catch (Exception $e) {
        Logger::user_error($e->getMessage());
      }
    }
    return $content;
  }

  /**
   * @return array
   */
  private function getProcParams () {
    $o = [];
    foreach (Cms::getAllVariables() as $k => $v) {
      if ($v instanceof \Closure) {
        continue;
      } elseif ($v instanceof DOMDocumentPlus) {
        $s = $v->saveXML($v->documentElement);
      } elseif ($v instanceof DOMElement) {
        $s = "";
        foreach ($v->childNodes as $n) $s .= $v->ownerDocument->saveXML($n);
      } elseif (is_array($v)) {
        $s = implode(", ", $v);
      } elseif (is_object($v) && !method_exists($v, '__toString')) {
        Logger::critical(sprintf(_("Unable to convert variable '%s' to string"), $k));
        continue;
      } else {
        $s = (string) $v;
      }
      if (false) {
        if ($k != "globalmenu") {
          continue;
        }
        #$v = "&copy;2014 &amp; <a href='http://www.internetguru.cz'>InternetGuru</a>";
        echo ($v)."\n";
        echo html_entity_decode($v)."\n";
        echo htmlentities($v)."\n";
        echo html_entity_decode($v)."\n";
        echo utf8_decode(html_entity_decode($v))."\n";
        echo htmlentities(utf8_decode(html_entity_decode($v)), ENT_XHTML)."\n";
        echo translateUtf8Entities($v)."\n";
        die();
      }
      $o[$k] = str_replace("'", '"', translateUtf8Entities($s));
    }
    return $o;
  }

  /**
   * @param DOMDocument $content
   * @param string $fileName
   * @param XSLTProcessor $proc
   * @return DOMDocument
   * @throws Exception
   */
  private function transform (DOMDocument $content, $fileName, XSLTProcessor $proc) {
    #var_dump($fileName);
    $xsl = XMLBuilder::load($fileName);
    if (!@$proc->importStylesheet($xsl)) {
      throw new Exception(sprintf(_("XSLT '%s' compilation error"), $fileName));
    }
    if (($doc = @$proc->transformToDoc($content)) === false) {
      throw new Exception(sprintf(_("XSLT '%s' transformation fail"), $fileName));
    }
    #echo $x->saveXML();
    return $doc;
  }

  /**
   * @return DOMDocument
   */
  private function createDoc () {
    $imp = new DOMImplementation();
    $dtd = $imp->createDocumentType('html');
    #$dtd = $imp->createDocumentType('html',
    #    '-//W3C//DTD XHTML 1.1//EN',
    #    'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd');
    $doc = $imp->createDocument(null, null, $dtd);
    $doc->encoding = "utf-8";
    return $doc;
  }

  /**
   * @param DOMDocument $doc
   * @param string $lang
   * @return DOMElement
   */
  private function addRoot (DOMDocument $doc, $lang) {
    $html = $doc->createElement("html");
    $html->setAttribute("xmlns", "http://www.w3.org/1999/xhtml");
    #$html->setAttribute("xml:lang", $lang);
    $html->setAttribute("lang", $lang);
    $doc->appendChild($html);
    return $html;
  }

  /**
   * @param DOMDocument $doc
   * @param DOMElement $html
   * @param DOMElementPlus $h1
   * @param DOMXPath $xPath
   * @return DOMElement
   */
  private function addHead (DOMDocument $doc, DOMElement $html, DOMElementPlus $h1, DOMXPath $xPath) {
    $head = $doc->createElement("head");
    $head->appendChild($doc->createElement("title", $this->getTitle($h1)));
    $this->appendMeta($head, "charset", "utf-8", false, true);
    $this->appendMeta($head, "viewport", "initial-scale=1");
    $this->appendMeta($head, "generator", Cms::getVariable("cms-name"));
    $this->appendMeta($head, "author", $h1->getAttribute("author"));
    $this->appendMeta($head, "description", $h1->nextElement->nodeValue);
    $this->appendMeta($head, "keywords", $h1->nextElement->getAttribute("kw"));
    $this->appendMeta($head, "robots", $this->getMetaRobots());
    update_file($this->favIcon, self::FAVICON); // hash?
    $this->appendLinkElement($head, $this->getFavIcon(), "shortcut icon", false, false);
    update_file(findFile($this->pluginDir."/robots.txt"), "robots.txt"); // hash?
    $this->appendCssFiles($head, $xPath);
    $html->appendChild($head);
    return $head;
  }

  private function getMetaRobots () {
    $mrs = $this->cfg->getElementsByTagName("metarobots");
    $lastMatch = null;
    foreach ($mrs as $mr) {
      if ($mr->hasAttribute("domain")) {
        $d = $mr->getAttribute("domain");
        if (!preg_match("/^[a-z.*]+$/", $d)) {
          Logger::user_error(sprintf(_("Invalid attribute domain value '%s'"), $d));
          continue;
        }
        $pattern = str_replace([".", "*"], ["\.", "[a-z]+"], $d);
        if (!preg_match("/^$pattern$/",DOMAIN)) {
          continue;
        }
      }
      $lastMatch = $mr;
    }
    if (is_null($lastMatch)) {
      throw new Exception("Element 'metarobots' not found");
    }
    if (!$lastMatch->hasAttribute("domain")) {
      Logger::user_warning(_("Using meta robots value with no domain match"));
    }
    return $lastMatch->nodeValue;
  }

  /**
   * @param DOMElementPlus $h1
   * @return string|null
   */
  private function getTitle (DOMElementPlus $h1) {
    $title = null;
    foreach ($this->subject->getIsInterface("IGCMS\\Core\\TitleStrategyInterface") as $clsName => $cls) {
      $title = $cls->getTitle();
      if (!is_null($title)) {
        return $title;
      }
    }
    return $h1->hasAttribute("short") ? $h1->getAttribute("short") : $h1->nodeValue;
  }

  /**
   * Append meta element to an element (supposed head)
   * @param  DOMElement $e Element to which meta is to be appended
   * @param  string $nameValue Value of attribute name/http-equiv
   * @param  string $contentValue Value of attribute content
   * @param  boolean $httpEquiv Use attr.http-equiv instead of name
   * @param  bool $short
   * @return void
   */
  private function appendMeta (DOMElement $e, $nameValue, $contentValue, $httpEquiv = false, $short = false) {
    $meta = $e->ownerDocument->createElement("meta");
    if ($short) {
      $meta->setAttribute($nameValue, $contentValue);
    } else {
      $meta->setAttribute(($httpEquiv ? "http-equiv" : "name"), $nameValue);
      $meta->setAttribute("content", $contentValue);
    }
    $e->appendChild($meta);
  }

  /**
   * @param DOMElement $parent
   * @param string $filePath
   * @param string $rel
   * @param bool $type
   * @param bool $media
   * @param string|null $ieIfComment
   */
  private function appendLinkElement (DOMElement $parent, $filePath, $rel, $type = false, $media = false, $ieIfComment = null) {
    $e = $parent->ownerDocument->createElement("link");
    if ($type) {
      $e->setAttribute("type", $type);
    }
    if ($rel) {
      $e->setAttribute("rel", $rel);
    }
    if ($media) {
      $e->setAttribute("media", $media);
    }
    $e->setAttribute("href", $filePath);
    if (!is_null($ieIfComment)) {
      $parent->appendChild(
        $parent->ownerDocument->createComment("[if $ieIfComment]>".$e->ownerDocument->saveXML($e)."<![endif]")
      );
      return;
    }
    $parent->appendChild($e);
  }

  /**
   * @return string
   */
  private function getFavIcon () {
    if (Cms::hasErrorMessage()) {
      return $this->cfg->getElementById("error")->nodeValue;
    }
    if (Cms::hasWarningMessage()) {
      return $this->cfg->getElementById("warning")->nodeValue;
    }
    if (Cms::hasNoticeMessage()) {
      return $this->cfg->getElementById("notice")->nodeValue;
    }
    if (Cms::hasSuccessMessage()) {
      return $this->cfg->getElementById("success")->nodeValue;
    }
    return ROOT_URL.self::FAVICON;
  }

  /**
   * Append all registered CSS files into a parent (usually head)
   * @param  DOMElement $parent Element to append JS files to
   * @param  DOMXPath $xPath
   * @return void
   */
  private function appendCssFiles (DOMElement $parent, DOMXPath $xPath) {
    foreach ($this->cssFilesPriority as $k => $v) {
      $ifXpath = isset($this->cssFiles[$k]["ifXpath"]) ? $this->cssFiles[$k]["ifXpath"] : false;
      if ($ifXpath !== false) {
        $r = @$xPath->query($ifXpath);
        if ($r === false) {
          Logger::user_warning(sprintf(_("Invalid xPath query '%s'"), $ifXpath));
          continue;
        }
        if ($r->length === 0) {
          continue;
        }
      }
      $ieIfComment = isset($this->cssFiles[$k]["if"]) ? $this->cssFiles[$k]["if"] : null;
      $filePath = ROOT_URL.getResDir($this->cssFiles[$k]["file"]);
      $this->appendLinkElement(
        $parent,
        $filePath,
        "stylesheet",
        "text/css",
        $this->cssFiles[$k]["media"],
        $ieIfComment
      );
    }
  }

  /**
   * @param DOMElementPlus $e
   * @param string $aName
   */
  private function processElement (DOMElementPlus $e, $aName) {
    // no target, no check, no title manipulation
    if (!$e->hasAttribute($aName)) {
      return;
    }
    $target = trim($e->getAttribute($aName));
    try {
      // link is empty
      if (!strlen($target)) {
        throw new Exception(_("Empty value"));
      }
      // throws unable to parse exception
      $pLink = parseLocalLink($target);
      // link is external
      if (is_null($pLink)) {
        return;
      }
      // build local url iff local url
      $this->processLink($e, $aName, $pLink);
      if ($e->nodeName != "a") {
        return;
      }
      if (!array_key_exists("id", $pLink)) {
        return;
      }
      $e->setAttribute("lang", HTMLPlusBuilder::getIdToLang($pLink["id"]));
      // generate title if not exists
      if ($e->hasAttribute("title")) {
        return;
      }
      if (array_key_exists("query", $pLink)) {
        return;
      }
      $this->insertTitle($e, $pLink["id"]);
    } catch (Exception $ex) {
      $message = sprintf(_("Attribute %s='%s' removed: %s"), $aName, $target, $ex->getMessage());
      $e->stripAttr($aName, $message);
      if (is_null(Cms::getLoggedUser())) {
        return;
      }
      $e->setAttribute("title", $message);
      $e->addClass("stripped");
      $e->addClass(REQUEST_TOKEN); // make it always show in webdiff
    }
  }

  /**
   * @param DOMElementPlus $e
   * @param String $aName
   * @param array $pLink
   * @throws Exception
   */
  private function processLink (DOMElementPlus $e, $aName, Array &$pLink) {
    $isLink = true;
    if (array_key_exists("path", $pLink)) {
      // link to supported file
      if (FileHandler::isSupportedRequest($pLink["path"])) {
        $isLink = false;
      }
      $ext = pathinfo($pLink["path"], PATHINFO_EXTENSION);
      $isFile = is_file($pLink["path"]);
      // link to image
      if (!$isLink && FileHandler::isImage($ext)) {
        list($targetWidth, $targetHeight) = self::getImageDimensions($pLink['path']);
        $e->setAttribute("data-target-width", $targetWidth);
        $e->setAttribute("data-target-height", $targetHeight);
      }
      // link to existing file
      if ($isLink && $isFile) {
        if ($ext == "php") {
          return;
        }
        $isLink = false;
      }
    }
    $localFragment = $this->isLocalFragment($pLink);
    if ($isLink) {
      $rootId = HTMLPlusBuilder::getFileToId(HTMLPlusBuilder::getCurFile());
      $pLink = $this->getLink($pLink, $rootId);
    }
    if (empty($pLink)) {
      if ($localFragment) {
        return;
      }
      throw new Exception(_("Target not found"));
    }
    $ignoreCyclic = $e->nodeName != "a";
    $link = buildLocalUrl($pLink, $ignoreCyclic, $isLink);
    $e->setAttribute($aName, $link);
  }

  /**
   * @param array $pHref
   * @param string $rootId
   * @return array
   */
  private function getLink ($pHref, $rootId) {
    if (!array_key_exists("path", $pHref) && !array_key_exists("fragment", $pHref)) {
      return $pHref;
    }
    $pHref["id"] = array_key_exists("path", $pHref) ? $pHref["path"] : $rootId;
    if (!strlen($pHref["id"])) {
      $pHref["id"] = HTMLPlusBuilder::getRootId();
    }
    if (array_key_exists("fragment", $pHref)) {
      $pHref["id"] .= "/".$pHref["fragment"];
    }
    // href is link
    $id = HTMLPlusBuilder::getLinkToId($pHref["id"]);
    if (!is_null($id)) {
      $pHref["id"] = $id;
      return $pHref;
    }
    // href is heading id
    $link = HTMLPlusBuilder::getIdToLink($pHref["id"]);
    // href is non-heading id
    if (is_null($link)) {
      $id = HTMLPlusBuilder::getIdToParentId($pHref["id"]);
      // link not found
      if (is_null($id)) {
        return [];
      }
      $link = HTMLPlusBuilder::getIdToLink($id);
    }
    $linkArray = explode("#", $link);
    $pHref["path"] = $linkArray[0];
    // update fragment iff not non-heading id
    if (isset($linkArray[1]) && !isset($pHref["fragment"])) {
      $pHref["fragment"] = $linkArray[1];
    }
    return $pHref;
  }

  /**
   * @param array $pLink
   * @return bool
   */
  private function isLocalFragment (Array $pLink) {
    if (array_key_exists("path", $pLink)) {
      return false;
    }
    return array_key_exists("fragment", $pLink);
  }

  /**
   * @param DOMElementPlus $a
   * @param string $id
   */
  private function insertTitle (DOMElementPlus $a, $id) {
    $title = HTMLPlusBuilder::getIdToTitle($id);
    if (!strlen($title) || $title == $a->nodeValue) {
      $title = HTMLPlusBuilder::getIdToHeading($id);
      if (!strlen($title) || $title == $a->nodeValue) {
        $title = getShortString(HTMLPlusBuilder::getIdToDesc($id));
      }
    }
    $a->setAttribute("title", $title);
  }

  /**
   * @param string $text
   * @param array $register
   * @param int $attempt
   * @return mixed
   */
  private function getUniqueHash ($text, Array &$register, $attempt = 0) {
    $suffix = $attempt > 0 ? "-$attempt" : "";
    $hash = substr(hash("sha256", $text), 0, 4).$suffix;
    if (array_key_exists($hash, $register)) {
      return $this->getUniqueHash($text, $register, ++$attempt);
    }
    $register[$hash] = null;
    return $hash;
  }

  /**
   * @param DOMElementPlus $img
   * @param array $ids
   */
  private function processImage (DOMElementPlus $img, Array &$ids) {
    if ($img->hasAttribute("width") && $img->hasAttribute("height")) {
      return;
    }
    $src = $img->getAttribute("src");
    // external
    if (is_null(parseLocalLink($src))) {
      return;
    }
    try {
      list($targetWidth, $targetHeight) = $this->getImageDimensions($src);
    } catch (Exception $ex) {
      if (is_null(Cms::getLoggedUser())) {
        $img->stripElement();
        return;
      }
      $message = sprintf(_("Attribute src=%s removed: %s"), $src, $ex->getMessage());
      $img->setAttribute('src', "/".LIB_DIR."/".NOT_FOUND_IMG_FILENAME);
      list($targetWidth, $targetHeight) = getimagesize(LIB_FOLDER."/".NOT_FOUND_IMG_FILENAME);
      $img->setAttribute("title", $message);
      $img->addClass("stripped");
      $img->addClass(REQUEST_TOKEN); // make it always show in webdiff
    }
    $img->setAttribute("width", $targetWidth);
    $img->setAttribute("height", $targetHeight);
    if ($img->hasAttribute("id")) {
      return;
    }
    $img->setAttribute("id", "img".self::getUniqueHash($img->getAttribute("src"), $ids));
  }

  /**
   * @param string $src
   * @return array
   */
  private function getImageDimensions ($src) {
    if (stream_resolve_include_path($src)) {
      return getimagesize(realpath($src));
    } else {
      return FileHandler::calculateImageSize($src);
    }
  }

  /**
   * @param DOMElementPlus $parent
   * @param string $lang
   */
  private function consolidateLang (DOMElementPlus $parent, $lang) {
    if ($parent->getAttribute("lang") == $lang) {
      $parent->removeAttribute("lang");
    }
    foreach ($parent->childElementsArray as $e) {
      $this->consolidateLang($e, $lang);
    }
  }

  /**
   * @param DOMElement $parent
   * @param string $append
   * @param DOMXPath $xPath
   */
  private function appendJsFiles (DOMElement $parent, $append = self::APPEND_HEAD, DOMXPath $xPath) {
    foreach ($this->jsFilesPriority as $k => $v) {
      if ($append != $this->jsFiles[$k]["append"]) {
        continue;
      }
      $ifXpath = isset($this->jsFiles[$k]["ifXpath"]) ? $this->jsFiles[$k]["ifXpath"] : false;
      if ($ifXpath !== false) {
        $r = $xPath->query($ifXpath);
        if ($r === false || $r->length === 0) {
          continue;
        }
      }
      $e = $parent->ownerDocument->createElement("script");
      $this->appendCdata($e, $this->jsFiles[$k]["content"]);
      $e->setAttribute("type", "text/javascript");
      $filePath = ROOT_URL.getResDir($this->jsFiles[$k]["file"]);
      if (!is_null($this->jsFiles[$k]["file"])) {
        $e->setAttribute("src", $filePath);
      }
      $ieIfComment = isset($this->jsFiles[$k]["if"]) ? $this->jsFiles[$k]["if"] : null;
      if (!is_null($ieIfComment)) {
        #$e->nodeValue = "�";
        $parent->appendChild(
          $parent->ownerDocument->createComment("[if $ieIfComment]>".$e->ownerDocument->saveXML($e)."<![endif]")
        );
        continue;
      }
      $parent->appendChild($e);
    }
  }

  /**
   * @param DOMElement $appendTo
   * @param string $text
   */
  private function appendCdata (DOMElement $appendTo, $text) {
    if (!strlen($text)) {
      return;
    }
    $cm = $appendTo->ownerDocument->createTextNode("//");
    if (strpos($text, "\n") !== 0) {
      $text = "\n$text";
    }
    $ct = $appendTo->ownerDocument->createCDATASection("$text\n//");
    $appendTo->appendChild($cm);
    $appendTo->appendChild($ct);
  }

  /**
   * @param DOMDocument $doc
   */
  private function validateEmptyContent (DOMDocument $doc) {
    $emptyShort = ["input", "br", "hr", "meta", "link", "param", "img", "source"]; // allowed empty in short format
    $emptyLong = ["script", "textarea", "object"]; // allowed empty in long format only
    $xpath = new DOMXPath($doc);
    $toExpand = [];
    $toDelete = [];
    foreach ($xpath->query("//*[not(node()) and not(normalize-space())]") as $e) {
      if (in_array($e->nodeName, $emptyShort)) {
        continue;
      }
      if (in_array($e->nodeName, $emptyLong)) {
        $toExpand[] = $e;
        continue;
      }
      $toDelete[] = $e;
    }
    foreach ($toExpand as $e) $e->appendChild($doc->createTextNode(""));
    /** @var DOMElement $e */
    foreach ($toDelete as $e) {
      if (!property_exists($e, "ownerDocument")) {
        continue;
      } // already deleted
      $eInfo = $e->nodeName;
      foreach ($e->attributes as $a) $eInfo .= ".".$a->nodeName."=".$a->nodeValue;
      $this->removePrevDt($e);
      $this->removeEmptyElement($e, sprintf(_("Removed empty element %s"), $eInfo));
    }
  }

  /**
   * @param DOMElement $e
   */
  private function removePrevDt (DOMElement $e) {
    if ($e->nodeName != "dd") {
      return;
    }
    $prev = $this->previousElementSibling($e);
    $next = $this->nextElementSibling($e);
    if ($prev->nodeName != "dt") {
      return;
    }
    if (!is_null($next) && $next->nodeName == "dd") {
      return;
    }
    $e->parentNode->removeChild($prev);
  }

  /**
   * @param DOMElement $e
   * @return DOMElement
   */
  private function previousElementSibling (DOMElement $e) {
    while ($e && ($e = $e->previousSibling)) {
      if ($e instanceof DOMElement) {
        break;
      }
    }
    return $e;
  }

  /**
   * @param DOMElement $e
   * @return DOMElement
   */
  private function nextElementSibling (DOMElement $e) {
    while ($e && ($e = $e->nextSibling)) {
      if ($e instanceof DOMElement) {
        break;
      }
    }
    return $e;
  }

  /**
   * @param DOMElement $e
   * @param string $comment
   */
  private function removeEmptyElement (DOMElement $e, $comment) {
    $parent = $e->parentNode;
    if (strlen(trim($parent->nodeValue))) {
      if (Cms::isSuperUser()) {
        $cmt = $e->ownerDocument->createComment(" $comment ");
        $parent->insertBefore($cmt, $e);
      }
      $parent->removeChild($e);
      return;
    }
    $this->removeEmptyElement($parent, $comment);
  }

  /**
   * @param string $content
   * @param int $priority
   * @param string $append
   */
  public function addJs ($content, $priority = self::DEFAULT_PRIORITY, $append = self::APPEND_BODY) {
    $key = "k".count($this->jsFiles);
    $this->jsFiles[$key] = [
      "file" => null,
      "append" => $append,
      "content" => $content,
    ];
    $this->jsFilesPriority[$key] = $priority;
  }

}

?>
