<?php

#TODO: meta keywords

class Xhtml11 extends Plugin implements SplObserver, OutputStrategyInterface {
  private $jsFiles = array(); // String filename => Int priority
  private $jsFilesPriority = array(); // String filename => Int priority
  private $jsContent = array();
  private $jsContentBody = array();
  private $cssFiles = array(); // String filename => Int priority
  private $cssFilesPriority = array();
  private $transformations = array();
  const APPEND_HEAD = "head";
  const APPEND_BODY = "body";
  const DTD_FILE = 'lib/xhtml11-flat.dtd';
  const DEBUG = false;

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "preinit") {
      $this->subject = $subject;
      $subject->getCms()->setOutputStrategy($this);
    }
  }

  /**
   * Create XHTML 1.1 output from HTML+ content and own registers (JS/CSS)
   * @return void
   */
  public function getOutput(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $cfg = $this->getDOMPlus();
    $this->registerThemes($cfg);
    stableSort($this->cssFilesPriority);
    stableSort($this->jsFilesPriority);

    // create output DOM with doctype
    $imp = new DOMImplementation();
    $dtd = $imp->createDocumentType('html',
        '-//W3C//DTD XHTML 1.1//EN',
        'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd');
    $doc = $imp->createDocument(null, null, $dtd);
    $doc->encoding="utf-8";

    // add root element
    $html = $doc->createElement("html");
    $html->setAttribute("xmlns","http://www.w3.org/1999/xhtml");
    $html->setAttribute("xml:lang",$cms->getVariable("cms-lang"));
    $html->setAttribute("lang",$cms->getVariable("cms-lang"));
    $doc->appendChild($html);

    // add head element
    $head = $doc->createElement("head");
    $head->appendChild($doc->createElement("title",$this->getTitle($cms)));
    $this->appendMeta($head,"Content-Type","text/html; charset=utf-8");
    $this->appendMeta($head,"viewport","initial-scale=1");
    $this->appendMeta($head,"Content-Language", $cms->getVariable("cms-lang"));
    $author = $cms->getVariable("cms-author");
    if(strlen($author)) $this->appendMeta($head, "author", $author);
    $desc = $cms->getVariable("cms-desc");
    if(strlen($desc)) $this->appendMeta($head, "description", $desc);
    $kw = $cms->getVariable("cms-kw");
    if(strlen($kw)) $this->appendMeta($head, "keywords", $kw);
    $fav = $this->getFavicon($cfg);
    if($fav) $this->appendLinkElement($head,$fav,"shortcut icon");
    $this->appendJsFiles($head);
    $this->appendCssFiles($head);
    $html->appendChild($head);

    // apply transformations
    $proc = new XSLTProcessor();
    $proc->setParameter('',$this->getProcParams($cms));
    foreach($this->transformations as $xslt => $user) {
      $newContent = $this->transform($content,$xslt,$user,$proc);
      $newContent->encoding="utf-8";
      $xml = $newContent->saveXML();
      if(!@$newContent->loadXML($xml)) {
        new Logger("Invalid transformation (or parameter) in '$xslt'","error");
        if(self::DEBUG) {
          echo $xml;
          die();
        }
        continue;
      }
      $content = $newContent;
    }
    $content = $doc->importNode($content->documentElement,true);
    $html->appendChild($content);
    $this->appendJsFiles($content,self::APPEND_BODY);
    #var_dump($doc->schemaValidate(CMS_FOLDER . "/" . self::DTD_FILE));

    // and that's it
    return $doc->saveXML();
  }

  private function getTitle(Cms $cms) {
    $title = $cms->getVariable("cms-title");
    foreach($this->subject->getIsInterface("ContentStrategyInterface") as $clsName => $cls) {
      $tmp = $cms->getVariable(strtolower($clsName)."-cms-title");
      if(!is_null($tmp)) $title = $tmp;
    }
    return $title;
  }

  private function getProcParams(Cms $cms) {
    $o = array();
    foreach($cms->getAllVariables() as $k => $v) {
      if($v instanceof DOMDocument) {
        $o[$k] = html_entity_decode($v->saveHTML());
        continue;
      }
      if(is_object($v) && !method_exists($v, '__toString')) {
        new Logger("Unable to convert variable '$k' to string","error");
        continue;
      }
      $v = html_entity_decode((string) $v);
      if(!$this->isValidInlineHTML($v)) {
        new Logger("Input variable '$k' is not HTML valid","error");
        continue;
      }
      $o[$k] = $v;
    }
    return $o;
  }

  private function isValidInlineHTML($v) {
    $doc = new HTMLPlus();
    $html = '<body xml:lang="en"><h id="x">x</h><desc>'.utf8_encode($v).'</desc></body>';
    if(!@$doc->loadXML($html)) return false;
    try {
      $doc->validatePlus();
    } catch(Exception $ex) {
      return false;
    }
    return true;
  }

  private function registerThemes(DOMDocumentPlus $cfg) {

    // add default xsl
    $this->transformations[$this->getDir() ."/Xhtml11.xsl"] = false;

    // add template files
    $xpath = new DOMXPath($cfg);
    $theme = $xpath->query("theme[last()]");
    if($theme->length) {
      $themeId = $theme->item(0)->nodeValue;
      $t = $cfg->getElementById($themeId);
      if(is_null($t)) throw new Exception("Theme '$themeId' not found");
      $this->addThemeFiles($t);
    }

    // add root template files
    $this->addThemeFiles($cfg->documentElement);

    // add final xsl
    #$this->transformations[$this->getDir() ."/Xhtml11final.xsl"] = false;
  }

  private function addThemeFiles(DOMElement $e) {
    foreach($e->childElements as $n) {
      if($n->nodeValue == "") continue;
      switch ($n->nodeName) {
        case "xslt":
        $user = !$n->hasAttribute("readonly");
        $this->transformations[$n->nodeValue] = $user;
        break;
        case "jsFile":
        $user = !$n->hasAttribute("readonly");
        $append = self::APPEND_HEAD;
        if($n->hasAttribute("append")) $append = $n->getAttribute("append");
        $this->addJsFile($n->nodeValue,10,$append,$user);
        break;
        case "stylesheet":
        $media = ($n->hasAttribute("media") ? $n->getAttribute("media") : false);
        $this->addCssFile($n->nodeValue,$media);
        break;
      }
    }
  }

  private function getFavicon(DOMDocumentPlus $cfg) {
    $xpath = new DOMXPath($cfg);
    $theme = $xpath->query("favicon[last()]");
    if(!$theme->length) return false;
    return $theme->item(0)->nodeValue;
  }

  private function transform(DOMDocument $content,$fileName,$user, XSLTProcessor $proc) {
    $db = $this->subject->getCms()->getDomBuilder();
    $xsl = $db->buildDOMPlus($fileName,true,$user);
    $proc->importStylesheet($xsl);
    return $proc->transformToDoc($content);
  }

  /**
   * Append meta element to an element (supposed head)
   * @param  DOMElement $e            Element to which meta is to be appended
   * @param  string     $nameValue    Value of attribute name/http-equiv
   * @param  string     $contentValue Value of attribute content
   * @param  boolean    $httpEquiv    Use attr. http-equiv instead of name
   * @return void
   */
  private function appendMeta(DOMElement $e,$nameValue,$contentValue,$httpEquiv=false) {
    $meta = $e->ownerDocument->createElement("meta");
    $meta->setAttribute(($httpEquiv ? "http-equiv" : "name"),$nameValue);
    $meta->setAttribute("content",$contentValue);
    $e->appendChild($meta);
  }

  private function appendMetaCharset(DOMElement $e,$charset) {
    $meta = $e->ownerDocument->createElement("meta");
    $meta->setAttribute("charset",$charset);
    $e->appendChild($meta);
  }

  private function appendLinkElement(DOMElement $parent,$file,$rel,$type=false,$media=false,$user=true) {
    try {
      $f = findFile($file,$user,true,true);
    } catch (Exception $ex) {
      $f = false;
    }
    if($f === false) {
      $parent->appendChild(new DOMComment(" [$rel] file '$file' not found "));
      return;
    }
    $e = $parent->ownerDocument->createElement("link");
    if($type) $e->setAttribute("type",$type);
    if($rel) $e->setAttribute("rel",$rel);
    if($media) $e->setAttribute("media",$media);
    $e->setAttribute("href", getLocalLink($f));
    $parent->appendChild($e);
  }

  /**
   * Add unique JS file into register with priority
   * @param string  $filePath JS file to be registered
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJsFile($filePath,$priority = 10,$append = self::APPEND_HEAD,$user=false) {
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
  public function addJs($content,$priority = 10,$append = self::APPEND_BODY) {
    $key = "k" . count($this->jsFiles);
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
    $this->cssFiles[$filePath] = array(
      "priority" => $priority,
      "file" => $filePath,
      "media" => $media,
      "user" => $user);
    $this->cssFilesPriority[$filePath] = $priority;
  }

  /**
   * Append all registered JS files into a parent (usually head)
   * @param  DOMElement $parent Element to append JS files to
   * @return void
   */
  private function appendJsFiles(DOMElement $parent,$append = self::APPEND_HEAD) {
    foreach($this->jsFilesPriority as $k => $v) {
      if($append != $this->jsFiles[$k]["append"]) continue;
      $f = false;
      if(!is_null($this->jsFiles[$k]["file"])) {
        try {
          $f = findFile($this->jsFiles[$k]["file"],$this->jsFiles[$k]["user"],true,true);
        } catch (Exception $ex) {}
        if($f === false) {
          $parent->appendChild(new DOMComment(" JS file '$k' not found "));
          continue;
        }
      }
      $content = $this->jsFiles[$k]["content"];
      $e = $parent->ownerDocument->createElement("script");
      $e->appendChild($parent->ownerDocument->createTextNode($content));
      $e->setAttribute("type","text/javascript");
      if($f !== false) $e->setAttribute("src", getLocalLink($f));
      $parent->appendChild($e);
    }
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
