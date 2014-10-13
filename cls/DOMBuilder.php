<?php

#TODO: cache

/**
 * Create DOM from XML file and update elements from adm/usr directories.
 * Add by default; empty element to delete all elements with same nodeName.
 * Respect readonly attribute when applying user file.
 * Default XML file is required (plugins do not have to use Config at all).
 * Elements with attribute subdom will be applied only when matched.
 *
 * @param: String plugin (optional)
 * @return: DOMDocument
 * @throws: Exception when files don't exist or are corrupted/empty
 */
class DOMBuilder {

  const DEBUG = false;
  const USE_CACHE = true;
  private $doc; // DOMDocument (HTMLPlus)
  private $plugin;
  private $replace; // bool
  private $imported = array();

  public function __construct() {}

  public function buildDOMPlus($filePath,$replace=false,$user=true) {
    $this->doc = new DOMDocumentPlus();
    $this->build($filePath,$replace,$user);
    if($replace) return $this->doc;
    $this->globalReadonly();
    return $this->doc;
  }

  private function globalReadonly() {
    $nodes = array();
    foreach($this->doc->documentElement->childElements as $n) {
      if($n->nodeValue == "" && $n->hasAttribute("readonly")) $nodes[] = $n;
    }
    foreach($nodes as $n) {
      foreach($n->ownerDocument->getElementsByTagName($n->nodeName) as $e) {
        $e->setAttribute("readonly","readonly");
      }
      $n->parentNode->removeChild($n);
    }
  }

  private function validateLink($elName,$attName) {
    $toStrip = array();
    foreach($this->doc->getElementsByTagName($elName) as $e) {
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
    foreach($this->doc->getElementsByTagName($eName) as $e) {
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

  public function buildHTMLPlus($filePath,$user=true) {
    $this->doc = new HTMLPlus();
    $this->build($filePath,true,$user);
    $this->validateLink("a","href");
    $this->validateLink("form","action");
    $this->cyclicLinks("a","href");
    return $this->doc;
  }

  private function build($filePath,$replace,$user) {
    /*
    $dc = new DOMCache(hash(FILE_HASH_ALGO,"$filePath,$replace,$user"));
    if($dc->isValid()) return $dc->getCache();
    $dc->addSurceFile($filePath);
    $dc->addSurceFile(ADMIN_FOLDER . "/$filePath");
    $dc->addSurceFile(USER_FOLDER . "/$filePath");
    */

    if(self::DEBUG) $this->doc->formatOutput = true;

    if($replace) {
      $this->loadDOM($this->findFile($filePath,$user),$this->doc);
      if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";
      return;
    }

    $this->loadDOM($this->findFile($filePath,false,false),$this->doc);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";

    $f = ADMIN_FOLDER . "/$filePath";
    if(is_file($f)) $this->updateDOM($f,true);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";

    if(!$user) return;

    $f = USER_FOLDER . "/$filePath";
    if(is_file($f)) $this->updateDOM($f);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";
  }

  private function findFile($filePath,$user=true,$admin=true) {
    $f = findFile($filePath,$user,$admin);
    if($f === false) throw new Exception("File '$filePath' not found");
    return $f;
  }

  private function loadDOM($filePath, DOMDocumentPlus $doc) {
    // load
    if(!@$doc->load($filePath))
      throw new Exception("Unable to load DOM from file '$filePath'");
    // validate if htmlplus
    try {
      $doc->validatePlus();
    } catch(Exception $e) {
      if(!($doc instanceof HTMLPlus)) throw $e;
      $doc->validatePlus(true);
      $doc->formatOutput = true;
      saveRewrite($filePath, $doc->saveXML());
    }
    // HTMLPlus import
    if(!($doc instanceof HTMLPlus)) return;
    $this->insertImports($doc,$filePath);
  }

  private function insertImports(HTMLPlus $doc,$filePath) {
    $this->imported[] = $filePath;
    $headings = array();
    $dir = pathinfo($filePath, PATHINFO_DIRNAME);
    foreach($doc->getElementsByTagName("h") as $h) {
      if($h->hasAttribute("import")) $headings[] = $h;
    }
    if(!count($headings)) return;
    $l = new Logger("Importing HTML+",null,false);
    foreach($headings as $h) {
      $files = matchFiles($h->getAttribute("import"),$dir);
      $h->removeAttribute("import");
      if(!count($files)) continue;
      $before = count($this->imported);
      foreach($files as $f) $this->insertHtmlPlus($h,$f);
      if($before < count($this->imported)) {
        $h->ownerDocument->removeUntilSame($h);
      }
    }
    $l->finished();
  }

  private function insertHtmlPlus(DOMElement $h, $file) {
    if(in_array($file, $this->imported))
      throw new Exception(sprintf("Cyclic import '%s' found in '%s'",$file,$h->getAttribute("import")));
    $doc = new HTMLPlus();
    try {
      $this->loadDOM($file, $doc);
    } catch(Exception $e) {
      $c = new DOMComment(" invalid import file '$file' ");
      $h->parentNode->insertBefore($c,$h);
      return;
    }
    #todo: validate imported file language
    foreach($doc->documentElement->childElements as $n) {
      $h->parentNode->insertBefore($h->ownerDocument->importNode($n,true),$h);
    }
    $h->ownerDocument->validateId("id",true);
    $h->ownerDocument->validateId("link",true);
    $h->ownerDocument->validatePlus(true);
    $this->imported[] = $file;
  }

  /**
   * Load XML file into DOMDocument using backup/restore
   * Respect subdom attribute
   * @param  string      $filePath File to be loaded into document
   * @return void
   * @throws Exception   if unable to load XML file incl. backup file
   */
  private function updateDOM($filePath,$ignoreReadonly=false) {
    $doc = new DOMDocumentPlus();
    $this->loadDOM($filePath,$doc);
    // create root element if not exists
    if(is_null($this->doc->documentElement)) {
      $this->doc->appendChild($this->doc->importNode($doc->documentElement));
    }
    foreach($doc->documentElement->childElements as $n) {
      // if empty && readonly => user cannot modify
      foreach($this->doc->getElementsByTagName($n->nodeName) as $d) {
        if(!$ignoreReadonly && $d->hasAttribute("readonly") && $d->nodeValue == "") return;
      }
      if(!$n instanceof DOMElement) continue;
      if($this->doRemove($n)) {
        $remove = array();
        foreach($this->doc->documentElement->childElements as $d) {
          if($d->nodeName != $n->nodeName) continue;
          if($ignoreReadonly || !$d->hasAttribute("readonly")) $remove[] = $d;
        }
        #if(!count($remove)) {
        #  $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
        #  continue;
        #}
        foreach($remove as $d) $d->parentNode->removeChild($d);
      } elseif($n->hasAttribute("id")) {
        $sameIdElement = $this->doc->getElementById($n->getAttribute("id"));
        if(is_null($sameIdElement)) {
          $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
          continue;
        }
        if($sameIdElement->nodeName != $n->nodeName)
          throw new Exception ("Id conflict with " . $n->nodeName);
        if(!$ignoreReadonly && $sameIdElement->hasAttribute("readonly")) continue;
        $this->doc->documentElement->replaceChild($this->doc->importNode($n,true),$sameIdElement);
      } else {
        $this->doc->documentElement->appendChild($this->doc->importNode($n,true));
      }
    }
  }

  private function doRemove(DOMElement $n) {
    if($n->nodeValue != "") return false;
    if($n->attributes->length > 1) return false;
    if($n->attributes->length == 1 && !$n->hasAttribute("readonly")) return false;
    return true;
  }

}

?>