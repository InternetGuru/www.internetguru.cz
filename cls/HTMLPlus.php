<?php

class HTMLPlus extends DOMDocumentPlus {
  private $hid = array();
  private $hnoid = array();
  private $hnodesc = array();
  const RNG_FILE = "lib/HTMLPlus.rng";

  function __construct($version="1.0",$encoding="utf-8") {
    parent::__construct($version,$encoding);
    $this->preserveWhiteSpace = false;
  }

  public function cloneNode($deep=false) {
    if(!$deep) return parent::cloneNode(false);
    $doc = new HTMLPlus();
    $root = $doc->importNode($this->documentElement,true);
    $doc->appendChild($root);
    return $doc;
  }

  public function validate($repair=false,$i=0) {
    if($i>3) throw new Exception ("Maximum repair cycles exceeded");
    try {
      $this->doValidate();
      if(!($f = findFilePath(self::RNG_FILE,"",false)))
        throw new Exception ("Unable to find HTMLPlus RNG schema");

      try {
        libxml_use_internal_errors(true);
        if(!$this->relaxNGValidate($f))
          throw new Exception("relaxNGValidate internal error occured");
      } catch (Exception $e) {
        $internal_errors = libxml_get_errors();
        if(count($internal_errors)) {
          #print_r($e);
          $e = new Exception(current($internal_errors)->message);
        }
      }
      libxml_clear_errors();
      libxml_use_internal_errors(false);
      if(isset($e)) throw $e;

      return $i;
    } catch (Exception $e) {
      if(!$repair) throw $e;
      switch ($e->getCode()) {
        #case 1:
        #fix error 1
        #break;
        case 2:
        $this->addHeadingIds();
        $this->addDescriptions();
        break;
        default:
        throw $e;
      }
      return $this->validate($repair,++$i);
    }
  }

  private function addHeadingIds() {
    foreach($this->hnoid as $h) {
      $h->setAttribute("id",$this->generateUniqueId());
    }
    $this->hnoid = array();
  }

  private function addDescriptions() {
    foreach($this->hnodesc as $h) {
      if($h->nextSibling->nodeName == "p") {
        $h->ownerDocument->renameElement($h->nextSibling,"description");
      } else {
        $h->parentNode->insertBefore($h->ownerDocument->createElement("description"),$h->nextSibling);
      }
    }
    $this->hnodesc = array();
  }

  private function generateUniqueId() {
    $id = "h." . substr(md5(microtime()),0,3);
    if(!$this->isValidId($id)) return $this->generateUniqueId();
    if(array_key_exists($id,$this->hid)) return $this->generateUniqueId();
    return $id;
  }

  private function isValidId($id) {
    return (bool) preg_match("/^[A-Za-z][A-Za-z0-9_:\.-]*$/",$id);
  }

  private function doValidate() {
    if($this->documentElement->nodeName != "body")
      throw new Exception("Root element must be 'body'",1);
    $this->hid = array();
    $this->hnoid = array();
    $this->hnodesc = array();
    foreach($this->getElementsByTagName("h") as $h) {
      if($h->nextSibling->nodeName != "description")
        $this->hnodesc[] = $h;
      if(!$h->hasAttribute("id")) {
        $this->hnoid[] = $h;
        continue;
      }
      $id = $h->getAttribute("id");
      if(!$this->isValidId($id)) {
        if(trim($id) != "")
          throw new Exception ("Invalid ID value '$id'");
        $this->hnoid[] = $h;
      }
      if(array_key_exists($id,$this->hid))
        throw new Exception ("Duplicit id found, value '$id'");
      $this->hid[$id] = null;
    }
    if(count($this->hnoid) || count($this->hnodesc)) {
      throw new Exception ("Missing element h ID or description",2);
    }
    $xpath = new DOMXPath($this);
    if($xpath->query("/body/*[1]")->item(0)->nodeName != "h")
      throw new Exception ("Missing main heading (/body/h)");
  }

}
?>