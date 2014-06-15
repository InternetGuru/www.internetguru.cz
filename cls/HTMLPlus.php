<?php

class HTMLPlus extends DOMDocument {

  function __construct(DOMDocument $doc) {
    $this->HTMLPlusValidate($doc);
    parent::__construct($doc->version,$doc->encoding);
    $root = parent::importNode($doc->documentElement,true);
    parent::appendChild($root);
  }

  private function HTMLPlusValidate(DOMDocument $doc) {
    if($doc->documentElement->nodeName != "body")
      throw new Exception ("Root element must be body");
  }

}
?>