<?php

class Xhtml11 extends Plugin implements SplObserver, OutputStrategyInterface {
  private $jsFiles = array(); // String filename => Int priority
  private $jsFilesPriority = array(); // String filename => Int priority
  private $jsContent = array();
  private $jsContentBody = array();
  private $cssFiles = array();
  private $cssFilesPriority = array();
  private $transformationsPriority = array();
  private $transformations = array();
  private $favIcon = null;
  private $selectable = false;
  const APPEND_HEAD = "head";
  const APPEND_BODY = "body";
  const DTD_FILE = 'lib/xhtml11-flat.dtd';
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_INIT) {
      Cms::setOutputStrategy($this);
    }
    if($subject->getStatus() == STATUS_PROCESS) {
      $cfg = $this->getDOMPlus();
      $this->registerThemes($cfg);
      if(!$this->selectable) return;
      $selectTitle = _("Select all");
      $this->addJs("Selectable.init({selectTitle: \"$selectTitle\"});", 6);
    }
  }

  /**
   * Create XHTML 1.1 output from HTML+ content and own registers (JS/CSS)
   * @return void
   */
  public function getOutput(HTMLPlus $content) {
    stableSort($this->cssFilesPriority);
    stableSort($this->jsFilesPriority);

    // create output DOM with doctype
    $imp = new DOMImplementation();
    $dtd = $imp->createDocumentType('html',
        '-//W3C//DTD XHTML 1.1//EN',
        'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd');
    $doc = $imp->createDocument(null, null, $dtd);
    $doc->encoding="utf-8";
    $h1 = $content->documentElement->firstElement;
    $lang = $content->documentElement->getAttribute("xml:lang");

    // add root element
    $html = $doc->createElement("html");
    $html->setAttribute("xmlns", "http://www.w3.org/1999/xhtml");
    $html->setAttribute("xml:lang", $lang);
    $html->setAttribute("lang", $lang);
    $doc->appendChild($html);

    // add head element
    $head = $doc->createElement("head");
    $head->appendChild($doc->createElement("title", $this->getTitle($h1)));
    $this->appendMeta($head, "Content-Type", "text/html; charset=utf-8");
    $this->appendMeta($head, "viewport", "initial-scale=1");
    $this->appendMeta($head, "Content-Language", $lang);
    $this->appendMeta($head, "generator", Cms::getVariable("cms-name"));
    $this->appendMeta($head, "author", $h1->getAttribute("author"));
    $this->appendMeta($head, "description", $h1->nextElement->nodeValue);
    $this->appendMeta($head, "keywords", $h1->nextElement->getAttribute("kw"));
    if(!is_null($this->favIcon)) $this->appendLinkElement($head, $this->favIcon, "shortcut icon");
    #if(!is_null($this->favIcon)) $this->appendLinkElement($head, $this->favIcon, "shortcut icon", false, false, false);
    $this->appendJsFiles($head);
    $this->appendCssFiles($head);
    $html->appendChild($head);

    // no more direct system messages
    Cms::setForceFlash();

    // apply transformations
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
        $content = $newContent;
      } catch(Exception $e) {
        new Logger($e->getMessage(), Logger::LOGGER_ERROR);
      }
    }

    // correct links
    $contentPlus = new DOMDocumentPlus();
    $contentPlus->loadXML($content->saveXML());
    $contentPlus->validateLinks("a", "href", true);
    $contentPlus->validateLinks("form", "action", true);
    $contentPlus->validateLinks("object", "data", true);
    $this->fragToLinks($contentPlus, Cms::getContentFull(), "a", "href");
    $this->fragToLinks($contentPlus, Cms::getContentFull(), "form", "action");
    $this->fragToLinks($contentPlus, Cms::getContentFull(), "object", "data");

    // check object.data mime/size
    $this->validateImages($contentPlus);

    // import into html and save
    $content = $doc->importNode($contentPlus->documentElement, true);
    $html->appendChild($content);
    $this->appendJsFiles($content, self::APPEND_BODY);

    #var_dump($doc->schemaValidate(CMS_FOLDER."/".self::DTD_FILE));
    $this->validateEmptyContent($doc);
    return $doc->saveXML();
  }

  private function fragToLinks(DOMDocumentPlus $doc, HTMLPlus $src, $eName="a", $aName="href") {
    $toStrip = array();
    foreach($doc->getElementsByTagName($eName) as $a) {
      if(!$a->hasAttribute($aName)) continue; // no link found
      $link = $a->getAttribute($aName);
      $pLink = parse_url($link);
      if(trimLink($link) == $link && isset($pLink["host"])) continue;
      $query = isset($pLink["query"]) ? $pLink["query"] : "";
      $queryUrl = strlen($query) ? "?$query" : "";
      if(isset($pLink["path"]) || isset($pLink["query"])) { // link is by path/query
        $path = isset($pLink["path"]) ? $pLink["path"] : getCurLink();
        if($path == "/") $path = "";
        if(strlen($path) && !DOMBuilder::isLink($path)) {
          if(is_file(FILES_FOLDER.$path) || is_file(FILES_FOLDER."/".$path)) {
            if(strpos($path, "/") === 0) $path = substr($path, 1);
            $a->setAttribute($aName, getRoot().$path.$queryUrl); // add root
            continue; // link to file
          }
          $toStrip[] = array($a, sprintf(_("Link '%s' not found within existing links or files"), $path));
          continue; // link not exists
        }
        if($eName != "form" && getCurLink(true) == $path.$queryUrl) {
          $toStrip[] = array($a, _("Cyclic link found"));
          continue; // link is cyclic (except form@action)
        }
        $a->setAttribute($aName, getRoot().$path.$queryUrl);
        continue; // localize link
      }
      $frag = $pLink["fragment"];
      $linkedElement = $doc->getElementById($frag);
      if(!is_null($linkedElement)) {
        $h1id = $doc->getElementsByTagName("h1")->item(0)->getAttribute("id");
        if(getCurLink(true) == getCurLink().$queryUrl && $h1id == $frag) {
          $toStrip[] = array($a, _("Cyclic fragment found"));
        }
        continue; // ignore visible headings
      }
      $link = DOMBuilder::getLink($frag);
      if(is_null($link)) {
        $toStrip[] = array($a, sprintf(_("Identifier '%s' not found"), $frag));
        continue; // id not exists
      }
      $a->setAttribute($aName, $link);
    }
    foreach($toStrip as $a) $a[0]->stripAttr($aName, $a[1]);
  }

  private function validateImages(DOMDocumentPlus $dom) {
    $toStrip = array();
    foreach($dom->getElementsByTagName("object") as $o) {
      if(!$o->hasAttribute("data")) continue;
      $dataFile = $o->getAttribute("data");
      $pUrl = parse_url($dataFile);
      if(!is_array($pUrl)) {
        $toStrip[] = array($o, sprintf(_("Invalid object data '%s' format"), $dataFile));
        continue;
      }
      if(array_key_exists("scheme", $pUrl)) {
        #todo: $this->asynchronousExternalImageCheck($dataFile);
        continue;
      }
      $filePath = FILES_FOLDER."/".$pUrl["path"];
      if(!is_file($filePath)) {
        $toStrip[] = array($o, sprintf(_("Object data '%s' not found"), $pUrl["path"]));
        continue;
      }
      try {
        $mime = getFileMime($filePath);
        if(strpos($mime, "image/") !== 0)
          throw new Exception(sprintf(_("Invalid object '%s' MIME type '%s'"), $dataFile, $mime));
        if(!$o->hasAttribute("type") || $o->getAttribute("type") != $mime) {
          $o->setAttribute("type", $mime);
          new Logger(sprintf(_("Object '%s' attribute type set to '%s'"), $dataFile, $mime), Logger::LOGGER_WARNING);
        }
        $query = isset($pUrl["query"]) ? explode("&", $pUrl["query"]) : array();
        $fullRemoved = false;
        foreach($query as $k => $q) {
          if($q != "full" && strpos($q, "full=") !== 0) continue;
          unset($query[$k]);
          $fullRemoved = true;
        }
        if($fullRemoved) {
          $o->setAttribute("data", $pUrl["path"].(count($query) ? "?".implode("&", $query) : ""));
          new Logger(sprintf(_("Parameter 'full' removed from data attribute '%s'"), $dataFile), Logger::LOGGER_WARNING);
        }
      } catch(Exception $e) {
        new Logger($e->getMessage(), Logger::LOGGER_ERROR);
        $toStrip[] = array($o, $e->getMessage());
      }
    }
    foreach($toStrip as $o) $o[0]->stripTag($o[1]);
  }

  /*
  private function asynchronousExternalImageCheck($file) {
    $headers = @get_headers(absoluteLink(getRoot().$filePath), 1);
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
      new Logger("Object '$filePath' attr type set to '".$mime."'", "warning");
    }
    $size = (int) $headers["Content-Length"];
    if(!$size || $size > 350*1024) {
      new Logger("Object '$filePath' too big or invalid size ".fileSizeConvert($size), "warning");
    }
  }
  */

  private function getTitle(DOMElementPlus $h1) {
    $title = $h1->hasAttribute("short") ? $h1->getAttribute("short") : $h1->nodeValue;
    foreach($this->subject->getIsInterface("ContentStrategyInterface") as $clsName => $cls) {
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
        $v = $d->saveHTML();
      } elseif($v instanceof DOMElement) {
        $d = new DOMDocumentPlus();
        foreach($v->childNodes as $n) $d->appendChild($d->importNode($n, true));
        $v = $d->saveHTML();
      } elseif(is_array($v)) {
        $v = implode(", ", $v);
      } elseif(is_object($v) && !method_exists($v, '__toString')) {
        new Logger(sprintf(_("Unable to convert variable '%s' to string"), $k), Logger::LOGGER_ERROR);
        continue;
      } else {
        $v = (string) $v;
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
      $o[$k] = str_replace("'", '"', translateUtf8Entities($v));
    }
    return $o;
  }

  private function registerThemes(DOMDocumentPlus $cfg) {

    // add default xsl
    $this->addTransformation($this->pluginDir."/Xhtml11.xsl", 0, false);
    $this->addTransformation($this->pluginDir."/Xhtml11final.xsl", 100, false);

    // add template files
    $theme = $cfg->getElementById("theme");
    if(!is_null($theme)) {
      $themeId = $theme->nodeValue;
      $t = $cfg->getElementById($themeId);
      if(is_null($t)) new Logger(sprintf(_("Theme '%s' not found"), $themeId), Logger::LOGGER_ERROR);
      else $this->addThemeFiles($t);
    }

    // add root template files
    $this->addThemeFiles($cfg->documentElement);
  }

  private function createRootFavicon($target) {
    if(IS_LOCALHOST) return;
    $link = "favicon.ico";
    if(is_link($link) && readlink($link) == $target) return;
    if(symlink($target, "$link~") && rename("$link~", $link)) return;
    new Logger(sprintf(_("Unable to create root '%s' link"), $link), Logger::LOGGER_ERROR);
  }

  private function addThemeFiles(DOMElement $e) {
    foreach($e->childElements as $n) {
      if($n->nodeValue == "") continue;
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
        if($n->nodeValue == "themes/selectable.js") $this->selectable = true;
        $this->addJsFile($n->nodeValue, $priority, $append, $user);
        break;
        case "stylesheet":
        $media = ($n->hasAttribute("media") ? $n->getAttribute("media") : false);
        $this->addCssFile($n->nodeValue, $media);
        break;
        case "favicon":
        $this->favIcon = $n->nodeValue;
        break;
      }
    }
  }

  private function transform(DOMDocument $content, $fileName, $user, XSLTProcessor $proc) {
    #var_dump($fileName);
    $xsl = DOMBuilder::buildDOMPlus($fileName, true, $user);
    if(!@$proc->importStylesheet($xsl))
      throw new Exception(sprintf(_("XSLT '%s' compilation error"), $fileName));
    if(($x = @$proc->transformToDoc($content) ) === false)
      throw new Exception(sprintf(_("XSLT '%s' transformation fail"), $fileName));
    #echo $x->saveXML();
    return $x;
  }

  /**
   * Append meta element to an element (supposed head)
   * @param  DOMElement $e            Element to which meta is to be appended
   * @param  string     $nameValue    Value of attribute name/http-equiv
   * @param  string     $contentValue Value of attribute content
   * @param  boolean    $httpEquiv    Use attr.http-equiv instead of name
   * @return void
   */
  private function appendMeta(DOMElement $e, $nameValue, $contentValue, $httpEquiv=false) {
    $meta = $e->ownerDocument->createElement("meta");
    $meta->setAttribute(($httpEquiv ? "http-equiv" : "name"), $nameValue);
    $meta->setAttribute("content", $contentValue);
    $e->appendChild($meta);
  }

  private function appendMetaCharset(DOMElement $e, $charset) {
    $meta = $e->ownerDocument->createElement("meta");
    $meta->setAttribute("charset", $charset);
    $e->appendChild($meta);
  }

  private function appendLinkElement(DOMElement $parent, $file, $rel, $type=false, $media=false, $user=true) {
    try {
      $f = findFile($file, $user, true, true);
    } catch (Exception $e) {
      $f = false;
    }
    if(!strlen($f)) {
      $comment = sprintf(_("Link [%s] file '%s' not found"), $rel, $file);
      $parent->appendChild(new DOMComment(" $comment "));
      new Logger($comment, Logger::LOGGER_ERROR);
      return;
    }
    if($rel == "shortcut icon") $this->createRootFavicon($f);
    $e = $parent->ownerDocument->createElement("link");
    if($type) $e->setAttribute("type", $type);
    if($rel) $e->setAttribute("rel", $rel);
    if($media) $e->setAttribute("media", $media);
    $e->setAttribute("href", getRoot().$f);
    $parent->appendChild($e);
  }

  /**
   * Add unique JS file into register with priority
   * @param string  $filePath JS file to be registered
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJsFile($filePath, $priority = 10, $append = self::APPEND_HEAD, $user=false) {
    Cms::addVariableItem("javascripts", $filePath);
    $this->jsFiles[$filePath] = array(
      "file" => $filePath,
      "append" => $append,
      "content" => "",
      "user" => $user);
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
  public function addCssFile($filePath, $media = false, $priority = 10, $user = true) {
    Cms::addVariableItem("styles", $filePath);
    $this->cssFiles[$filePath] = array(
      "priority" => $priority,
      "file" => $filePath,
      "media" => $media,
      "user" => $user);
    $this->cssFilesPriority[$filePath] = $priority;
  }


  public function addTransformation($filePath, $priority = 10, $user = true) {
    Cms::addVariableItem("transformations", $filePath);
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
  private function appendJsFiles(DOMElement $parent, $append = self::APPEND_HEAD) {
    foreach($this->jsFilesPriority as $k => $v) {
      if($append != $this->jsFiles[$k]["append"]) continue;
      $f = false;
      if(!is_null($this->jsFiles[$k]["file"])) {
        try {
          $f = findFile($this->jsFiles[$k]["file"], $this->jsFiles[$k]["user"], true, true);
        } catch (Exception $ex) {}
        if($f === false) {
          $comment = sprintf(_("Javascript file '%s' not found"), $k);
          $parent->appendChild(new DOMComment(" $comment "));
          new Logger($comment, Logger::LOGGER_ERROR);
          continue;
        }
      }
      $e = $parent->ownerDocument->createElement("script");
      $this->appendCdata($e, $this->jsFiles[$k]["content"]);
      $e->setAttribute("type", "text/javascript");
      if($f !== false) $e->setAttribute("src", getRoot().$f);
      $parent->appendChild($e);
    }
  }

  private function validateEmptyContent(DOMDocument $doc) {
    $xpath = new DOMXPath($doc);
    $emptyElOk = array("input", "br", "hr");
    $emptyEl = array();
    foreach($xpath->query("//*[not(node()) or text()='']") as $e) {
      if(in_array($e->nodeName, $emptyElOk)) continue;
      $emptyEl[] = $e;
    }
    if(!count($emptyEl)) return;
    foreach($emptyEl as $e) {
      $e->appendChild($doc->createTextNode(""));
    }
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
      $this->appendLinkElement($parent, $this->cssFiles[$k]["file"], "stylesheet",
        "text/css", $this->cssFiles[$k]["media"], $this->cssFiles[$k]["user"]);
    }
  }

}

?>
