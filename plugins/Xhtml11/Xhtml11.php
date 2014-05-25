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
    $lang = $cms->getBodyLang();

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
    $this->head = $doc->createElement("head");
    $html->appendChild($this->head);
    $this->head->appendChild($doc->createElement("title",$cms->getTitle()));
    $this->addMeta("Content-Type","text/html; charset=utf-8");
    $this->addMeta("Content-Language", "cs");
    $this->addJsFiles();
    $this->addCssFiles();

    // add body element
    $body = $doc->importNode($body->documentElement,true);
    $html->appendChild($body);

    // and that's it
    return $doc->saveXML();
  }

  private function addMeta($nameValue,$contentValue,$httpEquiv=false) {
    $meta = $this->head->ownerDocument->createElement("meta");
    $meta->setAttribute(($httpEquiv ? "http-equiv" : "name"),$nameValue);
    $meta->setAttribute("content",$contentValue);
    $this->head->appendChild($meta);
  }

  public function addJsFile($fileName,$plugin = "",$priority = 10) {
    $this->jsFiles[($plugin == "" ? "" : basename(PLUGIN_FOLDER) . "/$plugin/" ) . "$fileName"] = $priority;
  }

  private function addJsFiles() {
    #todo: existence souboru
    foreach($this->jsFiles as $k => $p) {
      $content = "";
      if(is_numeric($k)) $content = $this->jsContent[$k];
      $e = $this->head->ownerDocument->createElement("script",$content);
      $e->setAttribute("type","text/javascript");
      if(!is_numeric($k)) $e->setAttribute("src",$k);
      $this->head->appendChild($e);
    }
  }

  public function addCssFile($fileName,$plugin, $priority = 10) {
    $this->cssFiles[($plugin == "" ? "" : basename(PLUGIN_FOLDER) . "/$plugin/" ) . "$fileName"] = $priority;
  }

  private function addCssFiles() {
    #todo: existence souboru
    foreach($this->cssFiles as $f => $p) {
      $e = $this->head->ownerDocument->createElement("link");
      $e->setAttribute("type","text/css");
      $e->setAttribute("rel","stylesheet");
      $e->setAttribute("href",$f);
      $this->head->appendChild($e);
    }
  }

  public function addJs($content,$priority = 10) {
    $this->jsFiles[] = $priority;
    end($this->jsFiles);
    $this->jsContent[key($this->jsFiles)] = $content;
  }

  private function transformBody(DOMDocument $dom) {
    $xsl = $this->subject->getCms()->getDOMBuilder()->build("Xhtml11","xsl");
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $output = $proc->transformToDoc($dom);
    $output->encoding="utf-8";
    return $output;
  }

}

?>
