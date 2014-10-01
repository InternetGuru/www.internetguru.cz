<?php

#TODO: kw attribute do rng
#TODO: attribute style rng?

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

  public function validatePlus($repair=false) {
    $this->headings = $this->getElementsByTagName("h");
    $this->validateRoot();
    $this->validateLang($repair);
    $this->validateId();
    $this->validateId("link");
    $this->validateHId($repair);
    $this->validateDesc($repair);
    $this->validateHLink($repair);
    $this->validateDates($repair);
    $this->relaxNGValidatePlus();
    return true;
  }

  public function relaxNGValidatePlus() {
    return parent::relaxNGValidatePlus(CMS_FOLDER . "/" . self::RNG_FILE);
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

  private function validateDesc($repair) {
    if($repair) $this->repairDesc();
    foreach($this->headings as $h) {
      if(is_null($h->nextSibling) || $h->nextSibling->nodeName != "desc") {
        if(!$repair) throw new Exception ("Missing element 'desc'");
        $desc = $h->ownerDocument->createElement("desc");
        $h->parentNode->insertBefore($desc,$h->nextSibling);
        $this->autocorrected = true;
      }
    }
  }

  private function repairDesc() {
    $desc = array();
    foreach($this->getElementsByTagName("description") as $d) $desc[] = $d;
    foreach($desc as $d) {
      $this->renameElement($d,"desc");
      $this->autocorrected = true;
    }
  }

  private function validateHLink($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("link")) continue;
      #$this->getElementById($h->getAttribute("link"),"link");
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

  // mtime > ctime
  private function validateDates($repair) {
    foreach($this->headings as $h) {
      $ctime = null;
      $mtime = null;
      if($h->hasAttribute("ctime")) $ctime = $h->getAttribute("ctime");
      if($h->hasAttribute("mtime")) $mtime = $h->getAttribute("mtime");
      if(is_null($ctime) && is_null($mtime)) continue;
      if(is_null($ctime)) {
        if(!$repair) throw new Exception("Attribute 'mtime' requires 'ctime'");
        $ctime = $mtime;
        $h->setAttribute("ctime",$ctime);
      }
      $ctime_date = $this->createDate($ctime);
      if(is_null($ctime_date)) {
        if(!$repair) throw new Exception("Invalid 'ctime' attribute format");
        $h->parentNode->insertBefore(new DOMComment(" invalid ctime='$ctime' "),$h);
        $h->removeAttribute("ctime");
      }
      if(is_null($mtime)) return;
      $mtime_date = $this->createDate($mtime);
      if(is_null($mtime_date)) {
        if(!$repair) throw new Exception("Invalid 'mtime' attribute format");
        $h->parentNode->insertBefore(new DOMComment(" invalid mtime='$mtime' "),$h);
        $h->removeAttribute("mtime");
      }
      if($mtime_date < $ctime_date) {
        if(!$repair) throw new Exception("'mtime' cannot be lower than 'ctime'");
        $h->parentNode->insertBefore(new DOMComment(" invalid mtime='$mtime' "),$h);
        $h->removeAttribute("mtime");
      }
    }
  }

  private function createDate($d) {
    $date = DateTime::createFromFormat(DateTime::W3C, $d);
    $date_errors = DateTime::getLastErrors();
    if($date_errors['warning_count'] + $date_errors['error_count'] > 0) {
      return null;
    }
    return $date;
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