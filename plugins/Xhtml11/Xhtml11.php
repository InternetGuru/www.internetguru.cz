<?php

#TODO: links getRoot()
#TODO: css and js (instead of csm.xml)
#TODO: themes definition and selection

class Xhtml11 implements SplObserver, OutputStrategyInterface {
  private $subject; // SplSubject
  private $jsFiles = array(); // String filename => Int priority
  private $jsFilesPriority = array(); // String filename => Int priority
  private $jsContent = array();
  private $jsContentBody = array();
  private $cssFiles = array(); // String filename => Int priority
  private $cssFilesPriority = array();
  const APPEND_HEAD = "head";
  const APPEND_BODY = "body";

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
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
    $cfg = $cms->buildDOM("Xhtml11");
    $lang = $cms->getLanguage();
    $title = $cms->getTitle();
    stableSort($this->cssFilesPriority);
    stableSort($this->jsFilesPriority);

    // add css from cfg
    foreach($cfg->getElementsByTagName("jsFile") as $jsFile) {
      if($jsFile->nodeValue == "") continue;
      $this->addJsFile($jsFile->nodeValue);
    }

    // add js from cfg
    foreach($cfg->getElementsByTagName("stylesheet") as $css) {
      $media = ($css->hasAttribute("media") ? $css->getAttribute("media") : false);
      $this->addCssFile($css->nodeValue,$media);
    }

    // create output DOM with doctype
    $imp = new DOMImplementation();
    $dtd = $imp->createDocumentType('html',
        '-//W3C//DTD XHTML 1.1//EN',
        'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd');
    $doc = $imp->createDocument(null, null, $dtd);
    $doc->formatOutput = true;
    $doc->encoding="utf-8";

    // add root element
    $html = $doc->createElement("html");
    $html->setAttribute("xmlns","http://www.w3.org/1999/xhtml");
    $html->setAttribute("xml:lang",$lang);
    $html->setAttribute("lang",$lang);
    $doc->appendChild($html);

    // add head element
    $head = $doc->createElement("head");
    $head->appendChild($doc->createElement("title",$title));
    $this->appendMetaElement($head,"Content-Type","text/html; charset=utf-8");
    $this->appendMetaElement($head,"Content-Language", $lang);
    $this->appendMetaElement($head,"description", $cms->getDescription());
    $this->appendLinkElement($head,$this->getFavicon($cfg),"shortcut icon");
    $this->appendJsFiles($head);
    $this->appendCssFiles($head);
    $html->appendChild($head);

    // transform content and add as body element
    $defaultXsl = PLUGIN_FOLDER ."/". get_class($this) ."/Xhtml11.xsl";
    $content = $this->transform($content,$defaultXsl,false);
    foreach($cfg->getElementsByTagName("xslt") as $xslt) {
      $user = !$xslt->hasAttribute("readonly");
      $content = $this->transform($content,$xslt->nodeValue,$user);
    }
    $content->encoding="utf-8";
    $content = $doc->importNode($content->documentElement,true);
    $html->appendChild($content);
    $this->appendJsFiles($content,self::APPEND_BODY);

    // and that's it
    return $doc->saveXML();
  }

  private function getFavicon(DOMDocumentPlus $cfg) {
    $icons = array(PLUGIN_FOLDER ."/". get_class($this) ."/favicon.ico");
    foreach($cfg->getElementsByTagName("favicon") as $n) {
      $icons[] = $n->nodeValue;
    }
    foreach(array_reverse($icons) as $f) {
      $f = findFile($f);
      if(!$f) continue;
      return $f;
    }
    return false;
  }

  private function transform(DOMDocument $content,$fileName,$user) {
    $db = $this->subject->getCms()->getDomBuilder();
    $xsl = $db->buildDOMPlus($fileName,true,$user);
    $proc = new XSLTProcessor();
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
  private function appendMetaElement(DOMElement $e,$nameValue,$contentValue,$httpEquiv=false) {
    $meta = $e->ownerDocument->createElement("meta");
    $meta->setAttribute(($httpEquiv ? "http-equiv" : "name"),$nameValue);
    $meta->setAttribute("content",$contentValue);
    $e->appendChild($meta);
  }

  private function appendLinkElement(DOMElement $parent,$file,$rel,$type=false,$media=false) {
    if($file === false || !file_exists($file)) {
      $parent->appendChild(new DOMComment(" [$rel] file '$file' not found "));
      return;
    }
    $e = $parent->ownerDocument->createElement("link");
    if($type) $e->setAttribute("type",$type);
    if($rel) $e->setAttribute("rel",$rel);
    if($media) $e->setAttribute("media",$media);
    $e->setAttribute("href", getRoot() . $file);
    $parent->appendChild($e);
  }

  /**
   * Add unique JS file into register with priority
   * @param string  $filePath JS file to be registered
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJsFile($filePath,$priority = 10,$append = self::APPEND_HEAD,$user=false) {
    $this->jsFiles[$filePath] = array(
      "file" => findFile($filePath,$user),
      "append" => $append,
      "content" => "" );
    $this->jsFilesPriority[$filePath] = $priority;
  }

  /**
   * Add JS (as a string) into register with priority
   * @param string  $content  JS to be added
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJs($content,$priority = 10,$append = self::APPEND_HEAD) {
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
      "file" => findFile($filePath,$user),
      "media" => $media);
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
      if($this->jsFiles[$k]["file"] === false) {
        $parent->appendChild(new DOMComment(" JS file '$k' not found "));
        continue;
      }
      $content = $this->jsFiles[$k]["content"];
      $e = $parent->ownerDocument->createElement("script");
      $e->appendChild($parent->ownerDocument->createTextNode($content));
      $e->setAttribute("type","text/javascript");
      if(!is_null($this->jsFiles[$k]["file"])) {
        $e->setAttribute("src", getRoot() . $this->jsFiles[$k]["file"]);
      }
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
        "text/css", $this->cssFiles[$k]["media"]);
    }
  }

}

?>
