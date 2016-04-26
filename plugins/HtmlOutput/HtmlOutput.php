<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\OutputStrategyInterface;
use IGCMS\Core\Plugin;
use Exception;
use DOMImplementation;
use DOMDocument;
use DOMElement;
use DOMXPath;
use XSLTProcessor;
use SplObserver;
use SplSubject;


class HtmlOutput extends Plugin implements SplObserver, OutputStrategyInterface {
  private $jsFiles = array(); // String filename => Int priority
  private $jsFilesPriority = array(); // String filename => Int priority
  private $jsContent = array();
  private $jsContentBody = array();
  private $cssFiles = array();
  private $cssFilesPriority = array();
  private $transformationsPriority = array();
  private $transformations = array();
  private $favIcon = null;
  private $cfg = null;
  const APPEND_HEAD = "head";
  const APPEND_BODY = "body";
  const FAVICON = "favicon.ico";

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1000);
    Cms::setOutputStrategy($this);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    $this->cfg = $this->getDOMPlus();
    $this->registerThemes($this->cfg);
    if(is_null($this->favIcon)) $this->favIcon = findfile($this->pluginDir."/".self::FAVICON);
  }

  /**
   * Create HTML 5 output from HTML+ content and own registers (JS/CSS)
   * @return void
   */
  public function getOutput(HTMLPlus $content) {
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
    foreach($plugins->getIsInterface("IGCMS\Core\FinalContentStrategyInterface") as $fcs) {
      $contentPlus = $fcs->getContent($contentPlus);
    }

    // create output DOM with doctype
    $doc = $this->createDoc();
    $html = $this->addRoot($doc, $lang);
    $head = $this->addHead($doc, $html, $h1);

    // final validation
    $contentPlus->processFunctions(Cms::getAllFunctions(), Cms::getAllVariables());
    $xPath = new DOMXPath($contentPlus);
    foreach($xPath->query("//*[@var]") as $a) $a->stripAttr("var");
    foreach($xPath->query("//*[@fn]") as $a) $a->stripAttr("fn");
    foreach($xPath->query("//select[@pattern]") as $a) $a->stripAttr("pattern");
    foreach($xPath->query("//*[@xml:lang]") as $a) {
      if(!$a->hasAttribute("lang")) $a->setAttribute("lang", $a->getAttribute("xml:lang"));
      $a->removeAttribute("xml:lang");
    }
    $this->consolidateLang($contentPlus->documentElement, $lang);

    # TODO
    #$ids = $this->getIds($xPath);
    #$this->fragToLinks($contentPlus, $ids, "a", "href");
    #$this->fragToLinks($contentPlus, $ids, "form", "action");
    #$this->fragToLinks($contentPlus, $ids, "object", "data");

    // import into html and save
    $content = $doc->importNode($contentPlus->documentElement, true);
    $html->appendChild($content);
    $cXpath = new DOMXPath($html->ownerDocument);
    $this->addJs("window.Base = '".ROOT_URL."';", 1, self::APPEND_HEAD);
    $this->appendJsFiles($html->getElementsByTagName("head")->item(0), self::APPEND_HEAD, $cXpath);
    $this->appendJsFiles($content, self::APPEND_BODY, $cXpath);

    $this->validateEmptyContent($doc);
    $html = $doc->saveXML();
    return substr($html, strpos($html, "\n")+1);
  }

  private function consolidateLang(DOMElementPlus $parent, $lang) {
    if($parent->getAttribute("lang") == $lang) $parent->removeAttribute("lang");
    foreach($parent->childElementsArray as $e) {
      $this->consolidateLang($e, $lang);
    }
  }

  private function createDoc() {
    $imp = new DOMImplementation();
    $dtd = $imp->createDocumentType('html');
    #$dtd = $imp->createDocumentType('html',
    #    '-//W3C//DTD XHTML 1.1//EN',
    #    'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd');
    $doc = $imp->createDocument(null, null, $dtd);
    $doc->encoding="utf-8";
    return $doc;
  }

  private function addRoot(DOMDocument $doc, $lang) {
    $html = $doc->createElement("html");
    $html->setAttribute("xmlns", "http://www.w3.org/1999/xhtml");
    #$html->setAttribute("xml:lang", $lang);
    $html->setAttribute("lang", $lang);
    $doc->appendChild($html);
    return $html;
  }

  private function addHead(DOMDocument $doc, DOMElement $html, DOMElementPlus $h1) {
    $head = $doc->createElement("head");
    $head->appendChild($doc->createElement("title", $this->getTitle($h1)));
    $this->appendMeta($head, "charset", "utf-8", false, true);
    $this->appendMeta($head, "viewport", "initial-scale=1");
    $this->appendMeta($head, "generator", Cms::getVariable("cms-name"));
    $this->appendMeta($head, "author", $h1->getAttribute("author"));
    $this->appendMeta($head, "description", $h1->nextElement->nodeValue);
    $this->appendMeta($head, "keywords", $h1->nextElement->getAttribute("kw"));
    $robots = $this->cfg->getElementById("robots");
    $this->appendMeta($head, "robots", $robots->nodeValue);
    update_file($this->favIcon, self::FAVICON); // hash?
    $this->appendLinkElement($head, $this->getFavIcon(), "shortcut icon", false, false, true);
    update_file(findFile($this->pluginDir."/robots.txt"), "robots.txt"); // hash?
    $this->appendCssFiles($head);
    $html->appendChild($head);
    return $head;
  }

  private function applyTransformations(DOMDocumentPlus $content) {
    $proc = new XSLTProcessor();
    $proc->setParameter('', $this->getProcParams());
    stableSort($this->transformationsPriority);
    foreach($this->transformationsPriority as $xslt => $priority) {
      try {
        $newContent = $this->transform($content, $xslt, $this->transformations[$xslt]['user'], $proc);
        $newContent->encoding="utf-8";
        $xml = $newContent->saveXML();
        if(!@$newContent->loadXML($xml))
          throw new Exception(sprintf(_("Invalid transformation or parameter in '%s'"), $xslt));
        #todo: validate HTML5 validity
        $content = $newContent;
      } catch(Exception $e) {
        Logger::user_error($e->getMessage());
      }
    }
    return $content;
  }

  private function getFavIcon() {
    if(Cms::hasErrorMessage()) return $this->cfg->getElementById("error")->nodeValue;
    if(Cms::hasWarningMessage()) return $this->cfg->getElementById("warning")->nodeValue;
    if(Cms::hasNoticeMessage()) return $this->cfg->getElementById("notice")->nodeValue;
    if(Cms::hasSuccessMessage()) return $this->cfg->getElementById("success")->nodeValue;
    return ROOT_URL.self::FAVICON;
  }

  private function getIds(DOMXPath $xPath) {
    $ids = array();
    $toStrip = array();
    foreach($xPath->query("//*[@id]") as $e) {
      $id = $e->getAttribute("id");
      if(isset($ids[$id])) {
        $toStrip[] = $e;
        continue;
      }
      $ids[$id] = $e;
    }
    foreach($toStrip as $e) {
      $m = sprintf(_("Removed duplicit id %s"), $e->getAttribute("id"));
      $e->stripAttr("id", $m);
      Logger::user_warning($m);
    }
    return $ids;
  }

  private function fragToLinks(DOMDocumentPlus $doc, Array $ids, $eName, $aName) {
    $toStrip = array();
    foreach($doc->getElementsByTagName($eName) as $a) {
      #var_dump($a->nodeValue);
      if(!$a->hasAttribute($aName)) continue; // no link found
      try {
        #var_dump($a->getAttribute($aName));
        $pUrl = parseLocalLink($a->getAttribute($aName));
        if(is_null($pUrl)) continue; // link is external
        if(isset($pUrl["path"]) && preg_match("/".FILEPATH_PATTERN."/", $pUrl["path"])) { // link to file
          if(Cms::isSuperUser() && $eName == "object" && is_file($pUrl["path"])) {
            $mimeType = getFileMime($pUrl["path"]);
            if($a->getAttribute("type") != $mimeType)
              Logger::user_warning(sprintf(_("Object %s attribute type invalid or missing: %s"), $pUrl["path"], $mimeType));
          }
          $a->setAttribute($aName, ROOT_URL.$pUrl["path"]);
          continue;
        }
        $this->setupLink($a, $aName, $pUrl, $ids);
      } catch(Exception $e) {
        $toStrip[] = array($a, sprintf(_("Link %s removed: %s"), $a->getAttribute($aName), $e->getMessage()));
      }

    }
    foreach($toStrip as $a) {
      $a[0]->stripAttr($aName, $a[1]);
      $a[0]->stripAttr("title", "");
    }
  }

  private function setupLink(DOMElement $a, $aName, $pLink, Array $ids) {
    #var_dump("---------");
    #var_dump(implodeLink($pLink));
    #var_dump(getCurLink());
    #var_dump(getCurLink(true));
    #var_dump($pLink);
    $link = DOMBuilder::normalizeLink($pLink);
    #if(!is_null($linkId)) $link = $linkId; else $link = implodeLink($pLink);
    #var_dump($link);
    #var_dump(implodeLink($link));
    #if($a->nodeName != "form" && (!isset($link["path"]) ? getCurLink() : "").implodeLink($link) == getCurLink(true))
    #  throw new Exception(sprintf(_("Removed cyclic link %s"), $a->getAttribute($aName)));
    if($a->nodeName == "a" && !isset($pLink["query"])) $this->insertTitle($a, implodeLink($link));
    $localUrl = buildLocalUrl($link, $a->nodeName == "form");
    #var_dump($localUrl);
    if(strpos($localUrl, "#") === 0 && !array_key_exists($pLink["fragment"], $ids))
      throw new Exception(sprintf(_("Local fragment %s to undefined id"), $pLink["fragment"]));
    $a->setAttribute($aName, $localUrl);
  }

  private function insertTitle(DOMElement $a, $link) {
    #var_dump($link);
    if($a->hasAttribute("title")) {
      if(!strlen($a->getAttribute("title"))) $a->stripAttr("title");
      return;
    }
    $title = DOMBuilder::getTitle($link);
    if(normalize($title) == normalize($a->nodeValue)) $title = DOMBuilder::getDesc($link);
    if(is_null($title)) return;
    if(normalize($title) == normalize($a->nodeValue)) return;
    $a->setAttribute("title", $title);
  }

  private function validateImages(DOMDocumentPlus $dom) {
    $toStrip = array();
    foreach($dom->getElementsByTagName("object") as $o) {
      if(!$o->hasAttribute("data")) continue;
      if(strpos($dataFile, ROOT_URL) === 0) $dataFile = substr($o->getAttribute("data"), strlen(ROOT_URL));
      $dataFile = findFile($o->getAttribute("data"));
      if(is_null($dataFile)) continue;
      try {
        $pUrl = parseLocalLink($dataFile);
      } catch(Exception $e) {
        $toStrip[] = array($o, $e->getMessage());
        continue;
      }
      if(is_null($pUrl)) continue; // external
      $query = array();
      if(isset($pUrl["query"])) parse_str($pUrl["query"], $query);
      $filePath = USER_FOLDER."/".(array_key_exists("q", $query) ? $query["q"] : $pUrl["path"]);
      if(!is_file($filePath)) {
        $toStrip[] = array($o, sprintf(_("Object data '%s' not found"), $pUrl["path"]));
        continue;
      }
      try {
        $mime = getFileMime($filePath);
        if(strpos($mime, "image/") !== 0)
          throw new Exception(sprintf(_("Invalid object '%s' MIME type '%s'"), $dataFile, $mime), 1);
        if(!$o->hasAttribute("type") || $o->getAttribute("type") != $mime) {
          $o->setAttribute("type", $mime);
          Logger::user_warning(sprintf(_("Object '%s' attribute type set to '%s'"), $dataFile, $mime));
        }
        if(array_key_exists("full", $query)) {
          unset($query["full"]);
          $o->setAttribute("data", $pUrl["path"].buildQuery($query));
          Logger::user_warning(sprintf(_("Parameter 'full' removed from data attribute '%s'"), $dataFile));
        }
      } catch(Exception $e) {
        if($e->getCode() === 1)
          Logger::user_warning($e->getMessage());
        else
          Logger::critical($e->getMessage());
        $toStrip[] = array($o, $e->getMessage());
      }
    }
    foreach($toStrip as $o) $o[0]->stripTag($o[1]);
  }

  /*
  private function asynchronousExternalImageCheck($file) {
    $headers = @get_headers(absoluteLink(ROOT_URL.$filePath), 1);
    $invalid = !is_array($headers) || !array_key_exists("Content-Type", $headers)
      || !array_key_exists("Content-Length", $headers);
    if($invalid || strpos($headers[0], '200') === false) {
      $error = $invalid ? "bad response" : $headers[0];
      throw new Exception("object data '$filePath' not found ($error)");
    }
    $mime = $headers["Content-Type"];
    if(strpos($mime, "image/") !== 0)
      throw new Exception("invalid object '$filePath' mime type '$mime'");
    if(!$o->hasAttribute("type") || $o->getAttribute("type") != $mime) {
      $o->setAttribute("type", $mime);
      Logger::warning("Object '$filePath' attr type set to '".$mime."'");
    }
    $size = (int) $headers["Content-Length"];
    if(!$size || $size > 350*1024) {
      Logger::warning("Object '$filePath' too big or invalid size ".fileSizeConvert($size));
    }
  }
  */

  private function getTitle(DOMElementPlus $h1) {
    $title = $h1->hasAttribute("short") ? $h1->getAttribute("short") : $h1->nodeValue;
    foreach($this->subject->getIsInterface("IGCMS\Core\ContentStrategyInterface") as $clsName => $cls) {
      $tmp = Cms::getVariable(strtolower($clsName)."-title");
      if(!is_null($tmp)) $title = $tmp;
    }
    return $title;
  }

  private function getProcParams() {
    $o = array();
    foreach(Cms::getAllVariables() as $k => $v) {
      $valid = true;
      if($v instanceof Closure) continue;
      elseif($v instanceof DOMDocumentPlus) {
        $s = $v->saveXML($v->documentElement);
      } elseif($v instanceof DOMElement) {
        $s = "";
        foreach($v->childNodes as $n) $s .= $v->ownerDocument->saveXML($n);
      } elseif(is_array($v)) {
        $s = implode(", ", $v);
      } elseif(is_object($v) && !method_exists($v, '__toString')) {
        Logger::critical(sprintf(_("Unable to convert variable '%s' to string"), $k));
        continue;
      } else {
        $s = (string) $v;
      }
      if(false) {
        if($k != "globalmenu") continue;
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

  private function registerThemes(DOMDocumentPlus $cfg) {

    // add default xsl
    $this->addTransformation($this->pluginDir."/".(new \ReflectionClass($this))->getShortName().".xsl", 1, false);

    // add template files
    $theme = $cfg->getElementById("theme");
    if(!is_null($theme)) {
      $themeId = $theme->nodeValue;
      $t = $cfg->getElementById($themeId);
      if(is_null($t)) Logger::user_warning(sprintf(_("Theme '%s' not found"), $themeId));
      else $this->addThemeFiles($t);
    }

    // add root template files
    $this->addThemeFiles($cfg->documentElement);
  }

  private function addThemeFiles(DOMElement $e = null) {
    foreach($e->childElementsArray as $n) {
      if($n->nodeValue == "" || in_array($n->nodeName, array("var", "themes"))) continue;
      try {
        switch ($n->nodeName) {
          case "xslt":
          $user = !$n->hasAttribute("readonly");
          $this->addTransformation($n->nodeValue, 5, $user);
          break;
          case "jsFile":
          $user = !$n->hasAttribute("readonly");
          $append = self::APPEND_HEAD;
          $priority = 10;
          if($n->hasAttribute("append")) $append = $n->getAttribute("append");
          if($n->hasAttribute("priority")) $priority = $n->getAttribute("priority");
          $ieIfComment = ($n->hasAttribute("if") ? $n->getAttribute("if") : null);
          $ifXpath = ($n->hasAttribute("if-xpath") ? $n->getAttribute("if-xpath") : false);
          $this->addJsFile($n->nodeValue, $priority, $append, $user, $ieIfComment, $ifXpath);
          break;
          case "stylesheet":
          $media = ($n->hasAttribute("media") ? $n->getAttribute("media") : false);
          $ieIfComment = ($n->hasAttribute("if") ? $n->getAttribute("if") : null);
          $this->addCssFile($n->nodeValue, $media, 10, true, $ieIfComment);
          break;
          case "favicon":
          $this->favIcon = findFile($n->nodeValue);
        }
      } catch(Exception $e) {
        Logger::user_warning(sprintf(_("File %s of type %s not found"), $n->nodeValue, $n->nodeName));
      }
    }
  }

  private function transform(DOMDocument $content, $fileName, $user, XSLTProcessor $proc) {
    #var_dump($fileName);
    $xsl = DOMBuilder::buildDOMPlus($fileName, true, $user);
    if(!@$proc->importStylesheet($xsl))
      throw new Exception(sprintf(_("XSLT '%s' compilation error"), $fileName));
    if(($doc = @$proc->transformToDoc($content) ) === false)
      throw new Exception(sprintf(_("XSLT '%s' transformation fail"), $fileName));
    #echo $x->saveXML();
    return $doc;
  }

  /**
   * Append meta element to an element (supposed head)
   * @param  DOMElement $e            Element to which meta is to be appended
   * @param  string     $nameValue    Value of attribute name/http-equiv
   * @param  string     $contentValue Value of attribute content
   * @param  boolean    $httpEquiv    Use attr.http-equiv instead of name
   * @return void
   */
  private function appendMeta(DOMElement $e, $nameValue, $contentValue, $httpEquiv=false, $short=false) {
    $meta = $e->ownerDocument->createElement("meta");
    if($short) $meta->setAttribute($nameValue, $contentValue);
    else {
      $meta->setAttribute(($httpEquiv ? "http-equiv" : "name"), $nameValue);
      $meta->setAttribute("content", $contentValue);
    }
    $e->appendChild($meta);
  }

  private function appendMetaCharset(DOMElement $e, $charset) {
    $meta = $e->ownerDocument->createElement("meta");
    $meta->setAttribute("charset", $charset);
    $e->appendChild($meta);
  }

  private function appendLinkElement(DOMElement $parent, $filePath, $rel, $type=false, $media=false, $user=true, $ieIfComment=null) {
    $e = $parent->ownerDocument->createElement("link");
    if($type) $e->setAttribute("type", $type);
    if($rel) $e->setAttribute("rel", $rel);
    if($media) $e->setAttribute("media", $media);
    $e->setAttribute("href", $filePath);
    if(!is_null($ieIfComment)) {
      $parent->appendChild($parent->ownerDocument->createComment("[if $ieIfComment]>".$e->ownerDocument->saveXML($e)."<![endif]"));
      return;
    }
    $parent->appendChild($e);
  }

  /**
   * Add unique JS file into register with priority
   * @param string  $filePath JS file to be registered
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJsFile($filePath, $priority = 10, $append = self::APPEND_HEAD, $user=false, $ieIfComment=null, $ifXpath=false) {
    if(isset($this->jsFiles[$filePath])) return;
    Cms::addVariableItem("javascripts", $filePath);
    #if(findFile($filePath, $user) === false) throw new Exception();
    $this->jsFiles[$filePath] = array(
      "file" => $filePath,
      "append" => $append,
      "content" => "",
      "user" => $user,
      "ifXpath" => $ifXpath,
      "if" => $ieIfComment);
    $this->jsFilesPriority[$filePath] = $priority;
  }

  /**
   * Add JS (as a string) into register with priority
   * @param string  $content  JS to be added
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJs($content, $priority = 10, $append = self::APPEND_BODY) {
    $key = "k".count($this->jsFiles);
    $this->jsFiles[$key] = array(
      "file" => null,
      "append" => $append,
      "content" => $content);
    $this->jsFilesPriority[$key] = $priority;
  }

  /**
   * Add unique CSS file into register with priority
   * @param string  $fileName JS file to be registered
   * @param integer $priority The higher priority the lower appearance
   */
  public function addCssFile($filePath, $media = false, $priority = 10, $user = true, $ieIfComment=null) {
    if(isset($this->cssFiles[$filePath])) return;
    Cms::addVariableItem("styles", $filePath);
    #if(findFile($filePath, $user) === false) throw new Exception();
    $this->cssFiles[$filePath] = array(
      "priority" => $priority,
      "file" => $filePath,
      "media" => $media,
      "user" => $user,
      "if" => $ieIfComment);
    $this->cssFilesPriority[$filePath] = $priority;
  }


  public function addTransformation($filePath, $priority = 10, $user = true) {
    if(isset($this->transformations[$filePath])) return;
    Cms::addVariableItem("transformations", $filePath);
    #if(findFile($filePath, $user) === false) throw new Exception();
    if(!$user && is_file(USER_FOLDER."/".$filePath))
      Logger::user_warning(sprintf(_("File %s modification is disabled"), $filePath));
    $this->transformations[$filePath] = array(
      "priority" => $priority,
      "file" => $filePath,
      "user" => $user);
    $this->transformationsPriority[$filePath] = $priority;
  }

  /**
   * Append all registered JS files into a parent (usually head)
   * @param  DOMElement $parent Element to append JS files to
   * @return void
   */
  private function appendJsFiles(DOMElement $parent, $append = self::APPEND_HEAD, DOMXPath $xPath) {
    foreach($this->jsFilesPriority as $k => $v) {
      if($append != $this->jsFiles[$k]["append"]) continue;
      $ifXpath = isset($this->jsFiles[$k]["ifXpath"]) ? $this->jsFiles[$k]["ifXpath"] : false;
      if($ifXpath !== false) {
        $r = $xPath->query($ifXpath);
        if($r === false || $r->length === 0) continue;
      }
      $e = $parent->ownerDocument->createElement("script");
      $this->appendCdata($e, $this->jsFiles[$k]["content"]);
      $e->setAttribute("type", "text/javascript");
      $filePath = ROOT_URL.getResDir($this->jsFiles[$k]["file"]);
      if(!is_null($this->jsFiles[$k]["file"])) $e->setAttribute("src", $filePath);
      $ieIfComment = isset($this->jsFiles[$k]["if"]) ? $this->jsFiles[$k]["if"] : null;
      if(!is_null($ieIfComment)) {
        #$e->nodeValue = " ";
        $parent->appendChild($parent->ownerDocument->createComment("[if $ieIfComment]>".$e->ownerDocument->saveXML($e)."<![endif]"));
        continue;
      }
      $parent->appendChild($e);
    }
  }

  private function validateEmptyContent(DOMDocument $doc) {
    $emptyShort = array("input", "br", "hr", "meta", "link", "param"); // allowed empty in short format
    $emptyLong = array("script", "textarea", "object"); // allowed empty in long format only
    $xpath = new DOMXPath($doc);
    $toExpand = array();
    $toDelete = array();
    foreach($xpath->query("//*[not(node()) and not(normalize-space())]") as $e) {
      if(in_array($e->nodeName, $emptyShort)) continue;
      if(in_array($e->nodeName, $emptyLong)) {
        $toExpand[] = $e;
        continue;
      }
      $toDelete[] = $e;
    }
    foreach($toExpand as $e) $e->appendChild($doc->createTextNode(""));
    foreach($toDelete as $e) {
      if(!property_exists($e, "ownerDocument")) continue; // already deleted
      $eInfo = $e->nodeName;
      foreach($e->attributes as $a) $eInfo .= ".".$a->nodeName."=".$a->nodeValue;
      $this->removeEmptyElement($e, sprintf(_("Removed empty element %s"), $eInfo));
    }
  }

  private function removeEmptyElement(DOMElement $e, $comment) {
    $parent = $e->parentNode;
    if(strlen($parent->nodeValue)) {
      if(Cms::isSuperUser()) {
        $cmt = $e->ownerDocument->createComment(" $comment ");
        $parent->insertBefore($cmt, $e);
      }
      $parent->removeChild($e);
      return;
    }
    $this->removeEmptyElement($parent, $comment);
  }

  private function appendCdata(DOMElement $appendTo, $text) {
    if(!strlen($text)) return;
    $cm = $appendTo->ownerDocument->createTextNode("//");
    if(strpos($text, "\n") !== 0) $text = "\n$text";
    $ct = $appendTo->ownerDocument->createCDATASection("$text\n//");
    $appendTo->appendChild($cm);
    $appendTo->appendChild($ct);
  }

  /**
   * Append all registered CSS files into a parent (usually head)
   * @param  DOMElement $parent Element to append JS files to
   * @return void
   */
  private function appendCssFiles(DOMElement $parent) {
    foreach($this->cssFilesPriority as $k => $v) {
      $ieIfComment = isset($this->cssFiles[$k]["if"]) ? $this->cssFiles[$k]["if"] : null;
      $filePath = ROOT_URL.getResDir($this->cssFiles[$k]["file"]);
      $this->appendLinkElement($parent, $filePath, "stylesheet", "text/css",
        $this->cssFiles[$k]["media"], $this->cssFiles[$k]["user"], $ieIfComment);
    }
  }

}

?>
