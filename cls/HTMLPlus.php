<?php

class HTMLPlus extends DOMDocumentPlus {
  private $headings = array();
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

  protected function validateHTMLPlus($repair) {
    $this->headings = $this->getElementsByTagName("h");
    $this->validateRoot($repair);
    $this->validateSections($repair);
    $this->validateLang($repair);
    $this->validateId("id",$repair);
    $this->validateId("link",$repair);
    $this->validateHId($repair);
    $this->validateDesc($repair);
    $this->validateHLink($repair);
    $this->validateLinks("a","href",$repair);
    $this->validateLinks("form","action",$repair);
    $this->validateDates($repair);
    $this->validateAuthor($repair);
    $this->relaxNGValidatePlus();
  }

  public function relaxNGValidatePlus() {
    return parent::relaxNGValidatePlus(CMS_FOLDER . "/" . self::RNG_FILE);
  }

  private function validateRoot($repair) {
    if(is_null($this->documentElement))
      throw new Exception("Root element not found");
    if($this->documentElement->nodeName != "body") {
      if(!$repair) throw new Exception("Root element must be 'body'",1);
      $this->documentElement->rename("body");
    }
    if(!$this->documentElement->hasAttribute("lang")
      && !$this->documentElement->hasAttribute("xml:lang")) {
      if(!$repair) throw new Exception("Root element missing attribute 'xml:lang'");
      $this->documentElement->setAttribute("xml:lang", "en");
    }
    if($this->documentElement->childElements->length == 1
      && $this->documentElement->childElements->item(0)->nodeName == "section") {
      if(!$repair) throw new Exception("Root element missing attribute 'xml:lang'");
      $this->addTitleElements();
      return;
    }
    $hRoot = 0;
    foreach($this->documentElement->childNodes as $e) {
      if($e->nodeType != XML_ELEMENT_NODE) continue;
      if($e->nodeName != "h") continue;
      if($hRoot++ == 0) continue;
      if(!$repair) throw new Exception("Multiple root headings found");
      break;
    }
    if($hRoot == 1) return;
    if($hRoot == 0) throw new Exception("No root heading found");
    $children = array();
    foreach($this->documentElement->childNodes as $e) $children[] = $e;
    $s = $this->createElement("section");
    foreach($children as $e) $s->appendChild($e);
    $s->appendChild($this->createTextNode("  "));
    $this->documentElement->appendChild($s);
    $this->documentElement->appendChild($this->createTextNode("\n"));
    $this->addTitleElements($s);
  }

  private function addTitleElements() {
    $b = $this->documentElement->firstElement;
    $b->parentNode->insertBefore($this->createTextNode("\n  "), $b);
    $b->parentNode->insertBefore($this->createElement("h","Web title"), $b);
    $b->parentNode->insertBefore($this->createTextNode("\n  "), $b);
    $b->parentNode->insertBefore($this->createElement("desc","Web description"), $b);
    $b->parentNode->insertBefore($this->createTextNode("\n  "), $b);
  }

  private function validateSections($repair) {
    $emptySect = array();
    foreach($this->getElementsByTagName("section") as $s) {
      if($s->childElements->length === 0) $emptySect[] = $s;
    }
    if(!$repair && count($emptySect)) throw new Exception("Empty section(s) found");
    if(!count($emptySect)) return;
    foreach($emptySect as $s) $s->stripTag("Empty section deleted");
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
    }
  }

  private function validateHId($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("id")) {
        if(!$repair) throw new Exception ("Missing id attribute in element h");
        $this->setUniqueId($h);
        continue;
      }
      $id = $h->getAttribute("id");
      if(!$this->isValidId($id)) {
        if(!$repair || trim($id) != "")
          throw new Exception ("Invalid ID value '$id'");
        $this->setUniqueId($h);
        continue;
      }
    }
  }

  private function validateDesc($repair) {
    if($repair) $this->repairDesc();
    foreach($this->headings as $h) {
      if(is_null($h->nextElement) || $h->nextElement->nodeName != "desc") {
        if(!$repair) throw new Exception ("Missing element 'desc'");
        $desc = $h->ownerDocument->createElement("desc");
        $h->parentNode->insertBefore($desc,$h->nextElement);
      }
    }
  }

  private function repairDesc() {
    $desc = array();
    foreach($this->getElementsByTagName("description") as $d) $desc[] = $d;
    foreach($desc as $d) {
      $d->rename("desc");
    }
  }

  private function validateHLink($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("link")) continue;
      #$this->getElementById($h->getAttribute("link"),"link");
      $link = normalize($h->getAttribute("link"));
      while(preg_match("/^[^a-z]/",$link)) $link = substr($link,1); // must start with a-z
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
      }
    }
  }

  private function validateAuthor($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("author")) continue;
      if(strlen(trim($h->getAttribute("author")))) continue;
      if(!$repair) throw new Exception("Attr 'author' cannot be empty");
      $h->parentNode->insertBefore(new DOMComment(" empty attr 'author' removed "),$h);
      $h->removeAttribute("author");
    }
  }

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
    $date = new DateTime();
    $date->setTimestamp(strtotime($d));
    $date_errors = DateTime::getLastErrors();
    if($date_errors['warning_count'] + $date_errors['error_count'] > 0) {
      return null;
    }
    return $date;
  }

}
?>