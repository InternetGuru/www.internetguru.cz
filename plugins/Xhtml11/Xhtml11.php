<?php

class Xhtml11 implements SplObserver, OutputStrategyInterface {
  private $head;

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $subject->getCms()->setOutputStrategy($this);
    }
  }

  public function output(Cms $cms) {

    $body = $this->transformBody($cms->getBody());
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

  private function transformBody(DOMElement $dom) {
    // transform body
    $xsl = DomBuilder::build("Xhtml11","xsl");
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $output = $proc->transformToDoc($dom);
    $output->encoding="utf-8";
    return $output;
  }

}

?>
