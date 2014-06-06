<?php

class Xhtml11 implements SplObserver, OutputStrategyInterface {
  private $head;
  private $subject; // SplSubject
  private $jsFiles = array(); // String filename => Int priority
  private $cssFiles = array(); // String filename => Int priority

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->getCms()->setOutputStrategy($this);
    }
  }

  public function output(Cms $cms) {
    $body = $this->transformBody($cms->getContent());
    $lang = $cms->getLanguage();

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
    $head->appendChild($doc->createElement("title",$cms->getTitle()));
    $this->appendMetaElement($head,"Content-Type","text/html; charset=utf-8");
    $this->appendMetaElement($head,"Content-Language", $lang);
    $this->appendMetaElement($head,"description", $cms->getDescription());
    $this->appendJsFiles($head);
    $this->appendCssFiles($head);
    $html->appendChild($head);

    // add body element
    $body = $doc->importNode($body->documentElement,true);
    $html->appendChild($body);

    // and that's it
    return $doc->saveXML();
  }

  private function appendMetaElement(DOMElement $e,$nameValue,$contentValue,$httpEquiv=false) {
    $meta = $e->ownerDocument->createElement("meta");
    $meta->setAttribute(($httpEquiv ? "http-equiv" : "name"),$nameValue);
    $meta->setAttribute("content",$contentValue);
    $e->appendChild($meta);
  }

  public function addJsFile($fileName,$plugin = "",$priority = 10) {
    $fileName = ($plugin == "" ? "" : PLUGIN_FOLDER . "/$plugin/" ) . "$fileName";
    if(!is_file($fileName)) $fileName = "../" . CMS_FOLDER . "/$fileName";
    $this->jsFiles[$fileName] = $priority;
  }

  public function addCssFile($fileName,$plugin = "", $priority = 10) {
    $fileName = ($plugin == "" ? "" : PLUGIN_FOLDER . "/$plugin/" ) . "$fileName";
    if(!is_file($fileName)) $fileName = "../" . CMS_FOLDER . "/$fileName";
    $this->cssFiles[$fileName] = $priority;
  }

  public function setCssMedia($fileName,$media) {
    if(!is_file($fileName)) $fileName = "../" . CMS_FOLDER . "/$fileName";
    $this->cssMedia[$fileName] = $media;
  }

  private function appendJsFiles(DOMElement $parent) {
    #todo: sort by priority
    foreach($this->jsFiles as $k => $p) {
      $content = "";
      if(is_numeric($k)) $content = $this->jsContent[$k];
      $e = $parent->ownerDocument->createElement("script",$content);
      $e->setAttribute("type","text/javascript");
      if(!is_numeric($k)) $e->setAttribute("src",$k);
      $parent->appendChild($e);
    }
  }

  private function appendCssFiles(DOMElement $parent) {
    #todo: sort by priority
    foreach($this->cssFiles as $f => $p) {
      $e = $parent->ownerDocument->createElement("link");
      $e->setAttribute("type","text/css");
      $e->setAttribute("rel","stylesheet");
      $e->setAttribute("href",$f);
      if(isset($this->cssMedia[$f])) $e->setAttribute("media",$this->cssMedia[$f]);
      $parent->appendChild($e);
      #$parent->appendChild(new DOMComment("test"));
    }
  }

  public function addJs($content,$priority = 10) {
    $this->jsFiles[] = $priority;
    end($this->jsFiles);
    $this->jsContent[key($this->jsFiles)] = $content;
  }

  private function transformBody(DOMDocument $dom) {
    $xsl = $this->subject->getCms()->getDOM("Xhtml11","xsl");
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $output = $proc->transformToDoc($dom);
    $output->encoding="utf-8";
    return $output;
  }

}

?>
