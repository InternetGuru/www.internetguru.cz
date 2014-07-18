<?php

class Xhtml11 implements SplObserver, OutputStrategyInterface {
  private $subject; // SplSubject
  private $jsFiles = array(); // String filename => Int priority
  private $jsFilesBody = array(); // String filename => Int priority
  private $jsContent = array();
  private $jsContentBody = array();
  private $cssFiles = array(); // String filename => Int priority
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
    $lang = $cms->getLanguage();
    $title = $cms->getTitle();

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
    $this->appendJsFiles($head);
    $this->appendCssFiles($head);
    $html->appendChild($head);

    // transform content and add as body element
    $cfg = $cms->buildDOM("Xhtml11");
    $body = $content;
    foreach($cfg->getElementsByTagName("xslt") as $xslt) {
      $body = $this->transform($body,$xslt->nodeValue,$xslt->hasAttribute("absolute"));
    }
    $body->encoding="utf-8";
    $body = $doc->importNode($body->documentElement,true);
    $html->appendChild($body);
    $this->appendJsFiles($body,self::APPEND_BODY);

    // and that's it
    return $doc->saveXML();
  }

  private function transform(DOMDocument $content,$fileName,$absolute=false) {
    $xsl = $this->subject->getCms()->buildDOM(($absolute ? "Cms" : "Xhtml11"),true,$fileName);
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

  /**
   * Add unique JS file into register with priority
   * @param string  $fileName JS file to be registered
   * @param string  $plugin   Plugin name (no plugin by default)
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJsFile($fileName,$plugin = "",$priority = 10,$append = self::APPEND_HEAD) {
    if(($f = findFilePath($fileName,$plugin,false)) !== false) {
      if($append == self::APPEND_HEAD) $this->jsFiles[$f] = $priority;
      else $this->jsFilesBody[$f] = $priority;
      return;
    }
    if($append == self::APPEND_HEAD) $this->jsFiles[$fileName] = null;
    else $this->jsFilesBody[$fileName] = null;
  }

  /**
   * Add JS (as a string) into register with priority
   * @param string  $content  JS to be added
   * @param integer $priority The higher priority the lower appearance
   */
  public function addJs($content,$priority = 10,$append = self::APPEND_HEAD) {
    if($append == self::APPEND_HEAD) {
      $this->jsFiles[] = $priority;
      end($this->jsFiles);
      $this->jsContent[key($this->jsFiles)] = $content;
    } else {
      $this->jsFilesBody[] = $priority;
      end($this->jsFilesBody);
      $this->jsContentBody[key($this->jsFilesBody)] = $content;
    }
  }

  /**
   * Add unique CSS file into register with priority
   * @param string  $fileName JS file to be registered
   * @param string  $plugin   Plugin name (no plugin by default)
   * @param integer $priority The higher priority the lower appearance
   */
  public function addCssFile($fileName,$plugin = "", $priority = 10) {
    if(($f = findFilePath($fileName,$plugin)) !== false) {
      $this->cssFiles[$f] = $priority;
      return;
    }
    $this->cssFiles[$fileName] = null;
  }

  /**
   * Set media attribute for a specific CSS file
   * @param string $fileName CSS file name (eg. style.css)
   * @param string $media    Attribute media value (eg. print, all, screen)
   */
  public function setCssMedia($fileName,$media) {
    if(!is_file($fileName)) $fileName = "../" . CMS_FOLDER . "/$fileName";
    $this->cssMedia[$fileName] = $media;
  }

  /**
   * Append all registered JS files into a parent (usually head)
   * @param  DOMElement $parent Element to append JS files to
   * @return void
   */
  private function appendJsFiles(DOMElement $parent,$append = self::APPEND_HEAD) {
    if($append == self::APPEND_HEAD) {
      $jsFiles = $this->jsFiles;
      $jsContent = $this->jsContent;
    } else {
      $jsFiles = $this->jsFilesBody;
      $jsContent = $this->jsContentBody;
    }
    stableSort($jsFiles);
    foreach($jsFiles as $f => $p) {
      if(is_null($p)) {
        $parent->appendChild(new DOMComment(" JS file '$f' not found "));
        continue;
      }
      $content = "";
      if(is_numeric($f)) $content = $jsContent[$f];
      $e = $parent->ownerDocument->createElement("script");
      $e->appendChild($parent->ownerDocument->createTextNode($content));
      $e->setAttribute("type","text/javascript");
      if(!is_numeric($f)) $e->setAttribute("src",$this->getSubdom() ."/". $f);
      $parent->appendChild($e);
    }
  }

  /**
   * Append all registered CSS files into a parent (usually head)
   * @param  DOMElement $parent Element to append JS files to
   * @return void
   */
  private function appendCssFiles(DOMElement $parent) {
    stableSort($this->cssFiles);
    foreach($this->cssFiles as $f => $p) {
      if(is_null($p)) {
        $parent->appendChild(new DOMComment(" CSS file '$f' not found "));
        continue;
      }
      $e = $parent->ownerDocument->createElement("link");
      $e->setAttribute("type","text/css");
      $e->setAttribute("rel","stylesheet");
      $e->setAttribute("href",$this->getSubdom() ."/". $f);
      if(isset($this->cssMedia[$f])) $e->setAttribute("media",$this->cssMedia[$f]);
      $parent->appendChild($e);
    }
  }

  private function getSubdom() {
    if(!isAtLocalhost()) return;
    $d = explode("/", $_SERVER["SCRIPT_NAME"]);
    return "/" . $d[1];
  }

}

?>
