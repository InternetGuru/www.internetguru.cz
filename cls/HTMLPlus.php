<?php

class HTMLPlus extends DOMDocumentPlus {
  private $headings = array();
  private $autocorrected = false;
  const RNG_FILE = "lib/HTMLPlus.rng";

  function __construct($version="1.0",$encoding="utf-8") {
    parent::__construct($version,$encoding);
  }

  public function __clone() {
    $doc = new HTMLPlus();
    $root = $doc->importNode($this->documentElement,true);
    $doc->appendChild($root);
    return $doc;
  }

  public function isAutocorrected() {
    return $this->autocorrected;
  }

  public function validate($repair=false) {
    $this->headings = $this->getElementsByTagName("h");
    $this->validateRoot();
    $this->validateLang($repair);
    $this->validateHId($repair);
    $this->validateHDesc($repair);
    $this->validateHLink($repair);
    $this->relaxNGValidatePlus();
    return true;
  }

  public function relaxNGValidatePlus() {
    return parent::relaxNGValidatePlus(self::RNG_FILE);
  }

  private function validateRoot() {
    if(is_null($this->documentElement) || $this->documentElement->nodeName != "body")
      throw new Exception("Root element must be 'body'",1);
  }

  private function validateLang($repair) {
    $xpath = new DOMXPath($this);
    $langs = $xpath->query("//*[@lang]");
    if($langs->length && !$repair)
      throw new Exception ("Lang attribute without xml namespace",3);
    foreach($langs as $n) {
      if(!$n->hasAttribute("xml:lang"))
        $n->setAttribute("xml:lang", $n->getAttribute("lang"));
      $n->removeAttribute("lang");
      $this->autocorrected = true;
    }
  }

  private function validateHId($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("id")) {
        if(!$repair) throw new Exception ("Missing id attribute in element h");
        $h->setAttribute("id",$this->generateUniqueId());
        $this->autocorrected = true;
        continue;
      }
      $id = $h->getAttribute("id");
      if(!$this->isValidId($id)) {
        if(!$repair || trim($id) != "")
          throw new Exception ("Invalid ID value '$id'");
        $h->setAttribute("id",$this->generateUniqueId());
        $this->autocorrected = true;
        continue;
      }
    }
  }

  private function validateHDesc($repair) {
    foreach($this->headings as $h) {
      if(is_null($h->nextSibling) || $h->nextSibling->nodeName != "description") {
        if(!$repair) throw new Exception ("Missing description element");
        $desc = $h->ownerDocument->createElement("description");
        $h->parentNode->insertBefore($desc,$h->nextSibling);
        $this->autocorrected = true;
      }
    }
  }

  private function validateHLink($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("link")) continue;
      $this->getElementById($h->getAttribute("link"),"link");
      $link = normalize($h->getAttribute("link"));
      if(trim($link) == "") {
        if($link != $h->getAttribute("link"))
          throw new Exception ("Normalize link leads to empty value '{$h->getAttribute("link")}'");
        throw new Exception ("Empty link found");
      }
      if($link != $h->getAttribute("link")) {
        if(!$repair) throw new Exception ("Invalid link value found '{$h->getAttribute("link")}'");
        if(!is_null($this->getElementById($link,"link"))) {
          throw new Exception ("Normalize link leads to duplicit value '{$h->getAttribute("link")}'");
        }
        $h->setAttribute("link",$link);
        $this->autocorrected = true;
      }
    }
  }

  private function generateUniqueId() {
    $id = "h." . substr(md5(microtime()),0,3);
    if(!$this->isValidId($id)) return $this->generateUniqueId();
    if(!is_null($this->getElementById($id)))
      return $this->generateUniqueId();
    return $id;
  }

  private function isValidId($id) {
    return (bool) preg_match("/^[A-Za-z][A-Za-z0-9_:\.-]*$/",$id);
  }

}
?>