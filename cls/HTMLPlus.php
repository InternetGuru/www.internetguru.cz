<?php

class HTMLPlus extends DOMDocumentPlus {

  function __construct($version="1.0",$encoding="utf-8") {
    parent::__construct($version,$encoding);
  }

  private function HTMLPlusValidate(DOMDocument $doc) {
    if($doc->documentElement->nodeName != "body")
      throw new Exception ("Root element must be body");
  }

  public function cloneNode($deep=false) {
    if(!$deep) return parent::cloneNode(false);
    $doc = new HTMLPlus();
    $root = $doc->importNode($this->documentElement,true);
    $doc->appendChild($root);
    return $doc;
  }

}
?>