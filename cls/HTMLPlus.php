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

  public function fragToLinks(HTMLPlus $src) {
    $toStrip = array();
    foreach($this->getElementsByTagName("a") as $a) {
      if(!$a->hasAttribute("href")) continue;
      if(strpos($a->getAttribute("href"),"#") !== 0) continue;
      $frag = substr($a->getAttribute("href"),1);
      $h = $this->getElementById($frag);
      if(!is_null($h)) {
        if($this->getElementsByTagName("h")->item(0)->isSameNode($h)) {
          $toStrip[] = $a;
        }
        continue; // ignore visible headings
      }
      $h = $src->getElementById($frag);
      if(is_null($h) || $h->nodeName != "h") continue;
      if($h->hasAttribute("link")) {
        $a->setAttribute("href",getLocalLink($h->getAttribute("link")));
        continue;
      }
      $link = $src->getAncestorValue($h,"link");
      if(is_null($link)) continue;
      $a->setAttribute("href",getLocalLink($link)."#".$frag);
    }
    foreach($toStrip as $e) $e->stripTag("cyclic fragment found");
  }

  public function validatePlus($repair=false) {
    $this->headings = $this->getElementsByTagName("h");
    $this->validateRoot();
    $this->validateLang($repair);
    $this->validateId();
    $this->validateId("link");
    $this->validateLink("a","href",$repair);
    $this->validateLink("form","action",$repair);
    $this->cyclicLinks("a","href");
    $this->validateHId($repair);
    $this->validateDesc($repair);
    $this->validateHLink($repair);
    $this->validateDates($repair);
    $this->validateAuthor($repair);
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
        $this->setUniqueId($h);
        $this->autocorrected = true;
        continue;
      }
      $id = $h->getAttribute("id");
      if(!$this->isValidId($id)) {
        if(!$repair || trim($id) != "")
          throw new Exception ("Invalid ID value '$id'");
        $this->setUniqueId($h);
        $this->autocorrected = true;
        continue;
      }
    }
  }

  private function validateLink($elName,$attName,$repair) {
    $toStrip = array();
    foreach($this->getElementsByTagName($elName) as $e) {
      if(!$e->hasAttribute($attName)) continue;
      $force = true;
      $attVal = $e->getAttribute($attName);
      $curUrl = $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"];
      if(strpos($attVal,$curUrl) !== 0) $force = false;
      try {
        $link = getLocalLink($e->getAttribute($attName),$force);
        if($link === true) continue;
        $e->setAttribute($attName,$link);
      } catch(Exception $ex) {
        $toStrip[] = array($e,$ex->getMessage());
      }
    }
    foreach($toStrip as $a) $a[0]->stripTag($a[1]);
  }

  private function cyclicLinks($eName,$aName) {
    $toStrip = array();
    foreach($this->getElementsByTagName($eName) as $e) {
      if(!$e->hasAttribute($aName)) continue;
      $parsedVal = parse_url($e->getAttribute($aName));
      if(isset($parsedVal["scheme"])) continue;
      if(!isset($parsedVal["path"])) continue;
      if($_SERVER["REQUEST_URI"] != $parsedVal["path"] . "/") continue;
      if(!isset($parsedVal["fragment"])) {
        $toStrip[] = $e;
        continue;
      }
      $e->setAttribute($aName,"#".$parsedVal["fragment"]);
    }
    foreach($toStrip as $e) $e->stripTag("cyclic path found");
  }

  private function validateDesc($repair) {
    if($repair) $this->repairDesc();
    foreach($this->headings as $h) {
      if(is_null($h->nextElement) || $h->nextElement->nodeName != "desc") {
        if(!$repair) throw new Exception ("Missing element 'desc'");
        $desc = $h->ownerDocument->createElement("desc");
        $h->parentNode->insertBefore($desc,$h->nextElement);
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

  private function validateAuthor($repair) {
    foreach($this->headings as $h) {
      if(!$h->hasAttribute("author")) continue;
      if(strlen(trim($h->getAttribute("author")))) continue;
      if(!$repair) throw new Exception("Attr 'author' cannot be empty");
      $h->parentNode->insertBefore(new DOMComment(" empty attr 'author' removed "),$h);
      $h->removeAttribute("author");
      $this->autocorrected = true;
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
        $this->autocorrected = true;
      }
      $ctime_date = $this->createDate($ctime);
      if(is_null($ctime_date)) {
        if(!$repair) throw new Exception("Invalid 'ctime' attribute format");
        $h->parentNode->insertBefore(new DOMComment(" invalid ctime='$ctime' "),$h);
        $h->removeAttribute("ctime");
        $this->autocorrected = true;
      }
      if(is_null($mtime)) return;
      $mtime_date = $this->createDate($mtime);
      if(is_null($mtime_date)) {
        if(!$repair) throw new Exception("Invalid 'mtime' attribute format");
        $h->parentNode->insertBefore(new DOMComment(" invalid mtime='$mtime' "),$h);
        $h->removeAttribute("mtime");
        $this->autocorrected = true;
      }
      if($mtime_date < $ctime_date) {
        if(!$repair) throw new Exception("'mtime' cannot be lower than 'ctime'");
        $h->parentNode->insertBefore(new DOMComment(" invalid mtime='$mtime' "),$h);
        $h->removeAttribute("mtime");
        $this->autocorrected = true;
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

}
?>