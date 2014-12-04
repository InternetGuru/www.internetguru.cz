<?php

/**
 * Create DOM from XML file and update elements from adm/usr directories.
 * Add by default; empty element to delete all elements with same nodeName.
 * Preserve values with readonly attribute.
 * Elements with attribute domain will be applied only when matched.
 */
class DOMBuilder {

  const DEBUG = false;
  const USE_CACHE = true;
  private $doc; // DOMDocument (HTMLPlus)
  private $replace; // bool
  private $included = array();

  public function __construct() {
    if(self::DEBUG) new Logger("DEBUG");
  }

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

  public function buildHTMLPlus($filePath,$user=true) {
    $this->doc = new HTMLPlus();
    $this->build($filePath,true,$user);
    return $this->doc;
  }

  private function build($fileName,$replace,$user) {
    /*
    $dc = new DOMCache(hash(FILE_HASH_ALGO,"$fileName,$replace,$user"));
    if($dc->isValid()) return $dc->getCache();
    $dc->addSurceFile($fileName);
    $dc->addSurceFile(ADMIN_FOLDER . "/$fileName");
    $dc->addSurceFile(USER_FOLDER . "/$fileName");
    */

    if(self::DEBUG) $this->doc->formatOutput = true;

    if($replace) {
      $this->safeLoadDOM($fileName,$this->doc,$user,true);
      if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";
      return;
    }

    $this->loadDOM($this->findFile($fileName,false,false),$this->doc);
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";

    $f = ADMIN_FOLDER . "/$fileName";
    try {
      if(is_file($f)) $this->updateDOM($f,true);
    } catch(Exception $e) {
      new Logger(sprintf(_("Unable to admin-update XML: %s"), $e->getMessage()), Logger::LOGGER_ERROR);
    }
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";

    if(!$user) return;

    $f = USER_FOLDER . "/$fileName";
    try {
      if(is_file($f)) $this->updateDOM($f);
    } catch(Exception $e) {
      new Logger(sprintf(_("Unable to user-update XML: %s"), $e->getMessage()), Logger::LOGGER_ERROR);
    }
    if(self::DEBUG) echo "<pre>".htmlspecialchars($this->doc->saveXML())."</pre>";
  }

  private function findFile($fileName,$user=true,$admin=true) {
    $f = findFile($fileName,$user,$admin);
    if($f === false) throw new Exception(sprintf(_("File '%s' not found"), $fileName));
    return $f;
  }

  private function safeLoadDOM($filePath,DOMDocumentPlus $doc,$user,$admin) {
    $files = array();
    try {
      $files[$this->findFile($filePath,$user,$admin)] = null;
      $files[$this->findFile($filePath,false,true)] = null;
      $files[$this->findFile($filePath,false,false)] = null;
    } catch(Exception $e) {
      if(empty($files)) throw $e;
    }
    $success = false;
    $e = null;
    foreach($files as $f => $void) {
      try {
        $this->loadDOM($f, $doc);
      } catch(Exception $e) {
        new Logger(sprintf(_("Unable to load '%s': %s"), $filePath, $e->getMessage()), Logger::LOGGER_ERROR);
        continue;
      }
      $success = true;
      break;
    }
    if($success) return;
    $doc = null;
    if(!is_null($e)) throw $e;
  }

  private function loadDOM($filePath, DOMDocumentPlus $doc, $author=null) {
    if($doc instanceof HTMLPlus) {
      $remove = array("?".USER_FOLDER."/","?".ADMIN_FOLDER."/","?".CMS_FOLDER."/");
      Cms::addVariableItem("html", str_replace($remove, array(), "?$filePath"));
    }
    if(is_file(dirname($filePath)."/.".basename($filePath)))
      throw new Exception(sprintf(_("File disabled")));
    // load
    if(!@$doc->load($filePath))
      throw new Exception(sprintf(_("Invalid XML file")));
    // validate, save if repaired
    try {
      $doc->validatePlus();
    } catch(Exception $e) {
      if(!is_null($author)) {
        $h = $doc->documentElement->firstElement;
        if($h->nodeName == "h" && !$h->hasAttribute("author")) {
          $h->setAttribute("author", $author);
        }
      }
      $doc->validatePlus(true);
      saveRewrite($filePath, $doc->saveXML());
      new Logger(sprintf(_("HTML+ autocorrected: %s"), $e->getMessage()), Logger::LOGGER_INFO);
    }
    if(!($doc instanceof HTMLPlus)) return;
    // generate ctime/mtime from file if not set
    $this->generateDates($doc, $filePath);
    // HTML+ include
    $this->insertIncludes($doc, $filePath);
  }

  private function generateDates(HTMLPlus $doc, $filePath) {
    $h = $doc->documentElement->firstElement;
    if(!$h->hasAttribute("ctime")) {
      $c = new DateTime();
      $c->setTimeStamp(filectime($filePath));
      $h->setAttribute("ctime", $c->format(DateTime::W3C));
    }
    if(!$h->hasAttribute("mtime")) {
      $m = new DateTime();
      $m->setTimeStamp(filemtime($filePath));
      $h->setAttribute("mtime", $m->format(DateTime::W3C));
    }
  }

  private function insertIncludes(HTMLPlus $doc, $filePath) {
    $this->included[realpath($filePath)] = null;
    $includes = array();
    foreach($doc->getElementsByTagName("include") as $include) $includes[] = $include;
    if(!count($includes)) return;
    $start_time = microtime(true);
    $toStripElement = array();
    $toStripTag = array();
    foreach($includes as $include) {
      try {
        $include->setAncestorValue("author", "h");
        $this->insertHtmlPlus($include, dirname($filePath));
        $toStripElement[] = $include;
      } catch(Exception $e) {
        $toStripTag[] = $include;
        new Logger($e->getMessage(), Logger::LOGGER_ERROR);
      }
    }
    foreach($toStripTag as $include) $include->stripTag();
    foreach($toStripElement as $include) $include->stripElement();
    new Logger(sprintf(_("Inserted %s of %s HTML+ file(s)"),
      count($toStripElement), count($includes)), null, $start_time);
  }

  private function insertHtmlPlus(DOMElement $include, $homeDir) {
    $val = $include->getAttribute("src");
    $file = realpath("$homeDir/$val");
    if($file === false) {
      Cms::addVariableItem("html", $val);
      throw new Exception(sprintf(_("Included file '%s' not found"), $val));
    }
    if(array_key_exists($file, $this->included))
      throw new Exception(sprintf(_("File '%s' already included"), $val));
    $this->included[$file] = null;
    if(pathinfo($val, PATHINFO_EXTENSION) != "html")
      throw new Exception(sprintf(_("Included file extension '%s' must be .html"), $val));
    if(strpos($file, realpath("$homeDir/")) !== 0)
      throw new Exception(sprintf(_("Included file '%s' is out of working directory"), $val));
    try {
      $doc = new HTMLPlus();
      $this->loadDOM("$homeDir/$val", $doc, $include->getAttribute("author"));
    } catch(Exception $e) {
      $msg = sprintf(_("Unable to import '%s': %s"), $val, $e->getMessage());
      $c = new DOMComment(" $msg ");
      $include->parentNode->insertBefore($c, $include);
      throw new Exception($msg);
    }
    $sectLang = $this->getSectionLang($include->parentNode);
    $impLang = $doc->documentElement->getAttribute("xml:lang");
    if($impLang != $sectLang)
      new Logger(sprintf(_("Imported file language '%s' does not match section language '%s' in '%s'"), $impLang, $sectLang, $val), Logger::LOGGER_WARNING);
    foreach($doc->documentElement->childElements as $n) {
      $include->parentNode->insertBefore($include->ownerDocument->importNode($n, true), $include);
    }
    try {
      $include->ownerDocument->validatePlus();
    } catch(Exception $e) {
      $include->ownerDocument->validatePlus(true);
      new Logger(sprintf(_("HTML+ autocorrected after inserting '%s': %s"), $val, $e->getMessage()), Logger::LOGGER_WARNING);
    }
  }

  private function getSectionLang($s) {
    while(!is_null($s)) {
      if($s->hasAttribute("xml:lang")) return $s->getAttribute("xml:lang");
      $s = $s->parentNode;
    }
    return null;
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
          throw new Exception(sprintf(_("ID '%s' conflicts with element '%s'"), $n->getAttribute("id"), $n->nodeName));
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