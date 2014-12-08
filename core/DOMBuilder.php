<?php

/**
 * Create DOM from XML file and update elements from adm/usr directories.
 * Add by default; empty element to delete all elements with same nodeName.
 * Preserve values with readonly attribute.
 * Elements with attribute domain will be applied only when matched.
 */
class DOMBuilder {

  const DEBUG = false;
  #const USE_CACHE = true;
  private static $included = array();
  private static $identifiers = array(); // id => closest or self link
  private static $links = array(); // link => self id

  public static function buildHTMLPlus($filePath, $user=true) {
    $doc = new HTMLPlus();
    self::build($doc, $filePath, true, $user);
    return $doc;
  }

  public static function buildDOMPlus($filePath, $replace=false, $user=true) {
    $doc = new DOMDocumentPlus();
    self::build($doc, $filePath, $replace, $user);
    if($replace) return $doc;
    self::globalReadonly($doc);
    return $doc;
  }

  public static function getLink($frag) {
    if(!array_key_exists($frag, self::$identifiers)) return null;
    reset(self::$identifiers);
    if($frag == key(self::$identifiers)) return getRoot();
    $link = self::$identifiers[$frag];
    if($link == current(self::$links)) return getRoot()."#$frag";
    if(self::$links[$link] != $frag) $link .= "#$frag";
    return $link;
  }

  public static function getLinks() {
    return array_keys(self::$links);
  }

  public static function isLink($link) {
    return array_key_exists($link, self::$links);
  }

  private static function globalReadonly(DOMDocumentPlus $doc) {
    $nodes = array();
    foreach($doc->documentElement->childElements as $n) {
      if($n->nodeValue == "" && $n->hasAttribute("readonly")) $nodes[] = $n;
    }
    foreach($nodes as $n) {
      foreach($n->ownerDocument->getElementsByTagName($n->nodeName) as $e) {
        $e->setAttribute("readonly", "readonly");
      }
      $n->parentNode->removeChild($n);
    }
  }

  private static function build(DOMDocumentPlus $doc, $fileName, $replace, $user) {
    /*
    $dc = new DOMCache(hash(FILE_HASH_ALGO, "$fileName, $replace, $user"));
    if($dc->isValid()) return $dc->getCache();
    $dc->addSurceFile($fileName);
    $dc->addSurceFile(ADMIN_FOLDER."/$fileName");
    $dc->addSurceFile(USER_FOLDER."/$fileName");
    */
    if(self::DEBUG) $doc->formatOutput = true;

    if($replace) {
      self::safeLoadDOM($fileName, $doc, $user, true);
      if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";
      return;
    }

    $f = findFile($fileName, false, false);
    if($f) {
      self::loadDOM($f, $doc);
      if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";
    }

    $f = ADMIN_FOLDER."/$fileName";
    try {
      if(is_file($f)) self::updateDOM($doc, $f, true);
    } catch(Exception $e) {
      new Logger(sprintf(_("Unable to admin-update XML: %s"), $e->getMessage()), Logger::LOGGER_ERROR);
    }
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    if(!$user) return;

    $f = USER_FOLDER."/$fileName";
    try {
      if(is_file($f)) self::updateDOM($doc, $f);
    } catch(Exception $e) {
      new Logger(sprintf(_("Unable to user-update XML: %s"), $e->getMessage()), Logger::LOGGER_ERROR);
    }
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";
  }

  private static function findFile($fileName, $user=true, $admin=true) {
    $f = findFile($fileName, $user, $admin);
    if($f === false) throw new Exception(sprintf(_("File '%s' not found"), $fileName));
    return $f;
  }

  private static function safeLoadDOM($filePath, DOMDocumentPlus $doc, $user, $admin) {
    $files = array();
    try {
      $files[self::findFile($filePath, $user, $admin)] = null;
      $files[self::findFile($filePath, false, true)] = null;
      $files[self::findFile($filePath, false, false)] = null;
    } catch(Exception $e) {
      if(empty($files)) throw $e;
    }
    $success = false;
    $e = null;
    foreach($files as $f => $void) {
      try {
        self::loadDOM($f, $doc);
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

  private static function loadDOM($filePath, DOMDocumentPlus $doc, $author=null) {
    if($doc instanceof HTMLPlus) {
      $remove = array("?".USER_FOLDER."/", "?".ADMIN_FOLDER."/", "?".CMS_FOLDER."/");
      $fShort = str_replace($remove, array(), "?$filePath");
      if(array_key_exists(realpath($filePath), self::$included))
        throw new Exception(sprintf(_("File '%s' already included"), $fShort));
      self::$included[realpath($filePath)] = null;
      Cms::addVariableItem("html", $fShort);
    }
    if(is_file(dirname($filePath)."/.".basename($filePath)))
      throw new Exception(sprintf(_("File disabled")));

    // load
    if(!@$doc->load($filePath))
      throw new Exception(sprintf(_("Invalid XML file")));
    // validate, save if repaired
    if($doc instanceof HTMLPlus) {
      $c = new DateTime();
      $c->setTimeStamp(filectime($filePath));
      $doc->defaultCtime = $c->format(DateTime::W3C);
      $doc->defaultAuthor = is_null($author) ? Cms::getVariable("cms-author") : $author;
    }
    try {
      $doc->validatePlus();
    } catch(Exception $e) {
      $doc->validatePlus(true);
      if(!safeRewrite($doc->saveXML(), $filePath))
        throw new Exception(sprintf(_("Unable to save autocorrected file: %s"), $e->getMessage()));
      new Logger(sprintf(_("HTML+ autocorrected: %s"), $e->getMessage()), Logger::LOGGER_INFO);
    }
    if(!($doc instanceof HTMLPlus)) return;
    // generate ctime/mtime from file if not set
    self::setMtime($doc, $filePath);
    // register ids; repair if duplicit
    self::registerIds($doc);
    // HTML+ include
    self::insertIncludes($doc, $filePath);
    #print_r(self::$identifiers);
    #print_r(self::$links);
  }

  private static function registerIds(HTMLPlus $doc) {
    $doc->validateId("id", true, self::$identifiers);
    $doc->validateId("link", true, self::$links);
    foreach(self::$identifiers as $id => $null) {
      if(!is_null($null)) continue;
      $e = $doc->getElementById($id);
      if($e->hasAttribute("link")) {
        self::$identifiers[$id] = $e->getAttribute("link");
        continue;
      }
      $h = $e->getPreviousElement("h");
      while(!is_null($h) && !$h->hasAttribute("link")) {
        $h = $h->parentNode->getPreviousElement("h");
      }
      self::$identifiers[$id] = $h->getAttribute("link");
    }
  }

  private static function setMtime(HTMLPlus $doc, $filePath) {
    $h = $doc->documentElement->firstElement;
    if($h->hasAttribute("mtime")) return;
    if(!$h->hasAttribute("ctime")) return;
    $m = new DateTime();
    $m->setTimeStamp(filemtime($filePath));
    $c = new DateTime($h->getAttribute("ctime"));
    if($c > $m) return;
    $h->setAttribute("mtime", $m->format(DateTime::W3C));
  }

  private static function insertIncludes(HTMLPlus $doc, $filePath) {
    $includes = array();
    foreach($doc->getElementsByTagName("include") as $include) $includes[] = $include;
    if(!count($includes)) return;
    $start_time = microtime(true);
    $toStripElement = array();
    $toStripTag = array();
    foreach($includes as $include) {
      try {
        $author = $include->getAncestorValue("author", "h");
        self::insertHtmlPlus($include, dirname($filePath), $author);
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

  private static function insertHtmlPlus(DOMElement $include, $homeDir, $author) {
    $val = $include->getAttribute("src");
    $file = realpath("$homeDir/$val");
    if($file === false) {
      Cms::addVariableItem("html", $val);
      throw new Exception(sprintf(_("Included file '%s' not found"), $val));
    }
    if(pathinfo($val, PATHINFO_EXTENSION) != "html")
      throw new Exception(sprintf(_("Included file extension '%s' must be .html"), $val));
    if(strpos($file, realpath("$homeDir/")) !== 0)
      throw new Exception(sprintf(_("Included file '%s' is out of working directory"), $val));
    try {
      $doc = new HTMLPlus();
      self::loadDOM("$homeDir/$val", $doc, $author);
    } catch(Exception $e) {
      $msg = sprintf(_("Unable to import '%s': %s"), $val, $e->getMessage());
      $c = new DOMComment(" $msg ");
      $include->parentNode->insertBefore($c, $include);
      throw new Exception($msg);
    }
    $sectLang = self::getSectionLang($include->parentNode);
    $impLang = $doc->documentElement->getAttribute("xml:lang");
    if($impLang != $sectLang)
      new Logger(sprintf(_("Imported file language '%s' does not match section language '%s' in '%s'"), $impLang, $sectLang, $val), Logger::LOGGER_WARNING);
    foreach($doc->documentElement->childElements as $n) {
      $include->parentNode->insertBefore($include->ownerDocument->importNode($n, true), $include);
    }
    try {
      $include->ownerDocument->validatePlus();
    } catch(Exception $e) {
      echo $include->ownerDocument->saveXML();
      die();
      $include->ownerDocument->validatePlus(true);
      new Logger(sprintf(_("HTML+ autocorrected after inserting '%s': %s"), $val, $e->getMessage()), Logger::LOGGER_WARNING);
    }
  }

  private static function getSectionLang($s) {
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
   * @throws Exception   if unable to load XML file incl.backup file
   */
  private static function updateDOM(DOMDocumentPlus $doc, $filePath, $ignoreReadonly=false) {
    $newDoc = new DOMDocumentPlus();
    self::loadDOM($filePath, $newDoc);
    // create root element if not exists
    if(is_null($doc->documentElement)) {
      $doc->appendChild($doc->importNode($newDoc->documentElement));
    }
    foreach($newDoc->documentElement->childElements as $n) {
      // if empty && readonly => user cannot modify
      foreach($doc->getElementsByTagName($n->nodeName) as $d) {
        if(!$ignoreReadonly && $d->hasAttribute("readonly") && $d->nodeValue == "") return;
      }
      if(!$n instanceof DOMElement) continue;
      if(self::doRemove($n)) {
        $remove = array();
        foreach($doc->documentElement->childElements as $d) {
          if($d->nodeName != $n->nodeName) continue;
          if($ignoreReadonly || !$d->hasAttribute("readonly")) $remove[] = $d;
        }
        #if(!count($remove)) {
        #  $doc->documentElement->appendChild($doc->importNode($n, true));
        #  continue;
        #}
        foreach($remove as $d) $d->parentNode->removeChild($d);
      } elseif($n->hasAttribute("id")) {
        $sameIdElement = $doc->getElementById($n->getAttribute("id"));
        if(is_null($sameIdElement)) {
          $doc->documentElement->appendChild($doc->importNode($n, true));
          continue;
        }
        if($sameIdElement->nodeName != $n->nodeName)
          throw new Exception(sprintf(_("ID '%s' conflicts with element '%s'"), $n->getAttribute("id"), $n->nodeName));
        if(!$ignoreReadonly && $sameIdElement->hasAttribute("readonly")) continue;
        $doc->documentElement->replaceChild($doc->importNode($n, true), $sameIdElement);
      } else {
        $doc->documentElement->appendChild($doc->importNode($n, true));
      }
    }
  }

  private static function doRemove(DOMElement $n) {
    if($n->nodeValue != "") return false;
    if($n->attributes->length > 1) return false;
    if($n->attributes->length == 1 && !$n->hasAttribute("readonly")) return false;
    return true;
  }

}

?>