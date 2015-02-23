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
  private static $idToLink = array(); // id => closest or self link
  private static $idToDesc = array(); // id => shorted description
  private static $idToTitle = array(); // id => self title or shorted content
  private static $linkToNull = array(); // link => null

  public static function buildHTMLPlus($filePath, $user=true, $linkPrefix=null) {
    $doc = new HTMLPlus();
    self::build($doc, $filePath, true, $user, $linkPrefix);
    return $doc;
  }

  public static function buildDOMPlus($filePath, $replace=false, $user=true) {
    $doc = new DOMDocumentPlus();
    self::build($doc, $filePath, $replace, $user, null);
    if($replace) return $doc;
    self::globalReadonly($doc);
    return $doc;
  }

  public static function getId($link="") {
    throw new Exception(METHOD_NA);
  }

  public static function getLink(Array $pUrl) {
    reset(self::$idToLink);
    if(array_key_exists("fragment", $pUrl)) {
      $ids = current(self::$idToLink);
      if(array_key_exists("path", $pUrl) && array_key_exists($pUrl["path"], self::$idToLink))
        $ids = self::$idToLink[$pUrl["path"]];
      if(array_key_exists($pUrl["fragment"], $ids)) {
        $pUrl = array_merge($pUrl, $ids[$pUrl["fragment"]]);
        if(!isset($ids[$pUrl["fragment"]]["fragment"])) unset($pUrl["fragment"]);
        if($pUrl["path"] == key(self::$idToLink)) unset($pUrl["path"]);
        return $pUrl;
      }
    }
    if(array_key_exists("path", $pUrl) && array_key_exists($pUrl["path"], self::$linkToNull)) {
      if($pUrl["path"] == key(self::$idToLink)) unset($pUrl["path"]);
      return $pUrl;
    }
    if(!array_key_exists("path", $pUrl) && !array_key_exists("fragment", $pUrl)) return $pUrl;
    throw new Exception(sprintf(_("Link %s not found"), buildUrl($pUrl)));
  }

  public static function getDesc($id) {
    if(array_key_exists($id, self::$idToDesc)) return self::$idToDesc[$id];
    try {
      return self::getDesc(self::getId($id));
    } catch(Exception $e) {
      return null;
    }
  }

  public static function getTitle($id) {
    if(array_key_exists($id, self::$idToTitle)) return self::$idToTitle[$id];
    try {
      return self::getTitle(self::getId($id));
    } catch(Exception $e) {
      return null;
    }
  }

  public static function getRootHeadingId() {
    reset(self::$idToLink);
    return key(self::$idToLink);
  }

  public static function getLinks() {
    return array_keys(self::$linkToNull);
  }

  public static function isLink($link) {
    return array_key_exists($link, self::$linkToNull);
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

  private static function build(DOMDocumentPlus $doc, $fileName, $replace, $user, $linkPrefix) {
    /*
    $dc = new DOMCache(hash(FILE_HASH_ALGO, "$fileName, $replace, $user"));
    if($dc->isValid()) return $dc->getCache();
    $dc->addSurceFile($fileName);
    $dc->addSurceFile(ADMIN_FOLDER."/$fileName");
    $dc->addSurceFile(USER_FOLDER."/$fileName");
    */
    if(self::DEBUG) $doc->formatOutput = true;

    if($replace) {
      self::safeLoadDOM($fileName, $doc, $user, true, $linkPrefix);
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
      if(!file_exists(dirname($f)."/.".basename($f)) && is_file($f))
        self::updateDOM($doc, $f, true);
    } catch(Exception $e) {
      new Logger(sprintf(_("Unable to admin-update XML: %s"), $e->getMessage()), Logger::LOGGER_ERROR);
    }
    if(self::DEBUG) echo "<pre>".htmlspecialchars($doc->saveXML())."</pre>";

    if(!$user) return;

    $f = USER_FOLDER."/$fileName";
    try {
      if(!file_exists(dirname($f)."/.".basename($f)) && is_file($f))
        self::updateDOM($doc, $f);
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

  private static function safeLoadDOM($filePath, DOMDocumentPlus $doc, $user, $admin, $linkPrefix) {
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
      if(file_exists(dirname($f)."/.".basename($f))) continue;
      try {
        self::loadDOM($f, $doc, null, $linkPrefix);
      } catch(Exception $e) {
        new Logger(sprintf(_("Unable to load '%s': %s"), basename($filePath), $e->getMessage()), Logger::LOGGER_ERROR);
        continue;
      }
      $success = true;
      break;
    }
    if($success) return;
    $doc = null;
    if(!is_null($e))
      throw new Exception(sprintf(_("Failed to load user/admin/default file %s"), basename($filePath)));
  }

  private static function loadDOM($filePath, DOMDocumentPlus $doc, $author=null, $linkPrefix=null) {
    $remove = array("?".USER_FOLDER."/", "?".ADMIN_FOLDER."/", "?".CMS_FOLDER."/");
    $fShort = str_replace($remove, array(), "?$filePath");
    if($doc instanceof HTMLPlus) {
      if(array_key_exists(realpath($filePath), self::$included))
        throw new Exception(sprintf(_("File '%s' already included"), $fShort));
      self::$included[realpath($filePath)] = null;
      Cms::addVariableItem("html", $fShort);
    }
    if(is_file(dirname($filePath)."/.".basename($filePath)))
      throw new Exception(sprintf(_("File disabled")));

    // load
    if(!@$doc->load($filePath))
      throw new Exception(sprintf(_("Invalid XML file %s"), $fShort));
    if(!($doc instanceof HTMLPlus)) return;

    // validate, save if repaired
    $c = new DateTime();
    $c->setTimeStamp(filectime($filePath));
    $doc->defaultCtime = $c->format(DateTime::W3C);
    $doc->defaultLink = strtolower(pathinfo($filePath, PATHINFO_FILENAME));
    $doc->defaultAuthor = is_null($author) ? Cms::getVariable("cms-author") : $author;
    if(!is_null($linkPrefix)) self::prefixLinks($linkPrefix, $doc);
    try {
      $doc->validatePlus();
    } catch(Exception $e) {
      $doc->validatePlus(true);
      if(strpos($filePath, CMS_FOLDER) !== 0) {
        #if(!safeRewrite($doc->saveXML(), $filePath))
        #  throw new Exception(sprintf(_("Unable to save autocorrected file: %s"), $e->getMessage()));
        new Logger(sprintf(_("HTML+ file %s autocorrected: %s"), $fShort, $e->getMessage()), Logger::LOGGER_WARNING);
      }
    }
    // generate ctime/mtime from file if not set
    self::setMtime($doc, $filePath);
    // HTML+ include
    self::insertIncludes($doc, $filePath);
    // register links/ids; repair if duplicit
    self::setIdentifiers($doc, $fShort);
    #var_dump(self::$idToLink);
    #print_r(self::$linkToNull);
  }

  private static function prefixLinks($prefix, HTMLPlus $doc) {
    foreach($doc->getElementsByTagName("h") as $h) {
      if(!$h->hasAttribute("link")) continue;
      $h->setAttribute("link", "$prefix/".$h->getAttribute("link"));
    }
  }

  private static function setIdentifiers(HTMLPlus $doc, $fShort) {
    $duplicit = array();
    $h1 = $doc->documentElement->firstElement;
    $prefix = trim($h1->getAttribute("link"), "/");
    #if(empty(self::$idToLink)) $prefix = "";
    if(array_key_exists($prefix, self::$idToLink)) {
      $prefix = self::generateUniqueVal($prefix, self::$idToLink);
      new Logger(sprintf(_("Duplicit prefix %s in %s renamed to %s"), $h1->getAttribute("link"),
        $fShort, $prefix), Logger::LOGGER_WARNING);
      $h1->setAttribute("link", $prefix);
    }
    self::$idToLink[$prefix] = array();
    self::registerKeys($doc, $prefix);
    self::addLocalPrefix($doc, $prefix, "a", "href");
    self::addLocalPrefix($doc, $prefix, "form", "action");
    #print_r(self::$idToLink);
    #var_dump(self::$linkToNull);
  }

  private static function addLocalPrefix(HTMLPlus $doc, $prefix, $eName, $aName) {
    foreach($doc->getElementsByTagName($eName) as $e) {
      if(!$e->hasAttribute($aName)) continue;
      $pLink = parseLocalLink($e->getAttribute($aName));
      if(is_null($pLink)) continue; // link is external

      if(array_key_exists("path", $pLink)) {
        if(!array_key_exists($pLink["path"], self::$idToLink[$prefix])) {
          foreach(self::$idToLink[$prefix] as $link) {
            if("$prefix/".$link["path"] != $pLink["path"]) continue;
            new Logger(sprintf(_("Found local link with prefix %s"), $e->getAttribute($aName)),
              Logger::LOGGER_WARNING);
            break;
          }
          continue;
        }
        $pLink["path"] = "$prefix/".$pLink["path"];
      }
      if(array_key_exists("fragment", $pLink)) {
        if(array_key_exists("path", $pLink)) {
          if($pLink["path"] == $prefix) new Logger(sprintf(_("Found local fragment with prefix %s"),
            $e->getAttribute($aName)), Logger::LOGGER_WARNING);
          continue;
        }
        $pLink["path"] = $prefix;
      }
      $e->setAttribute($aName, buildUrl($pLink));
    }
  }

  private static function registerKeys(HTMLPlus $doc, $prefix) {
    $newLinks = array();
    $prefixLink = "$prefix/";
    if(empty(self::$linkToNull)) $prefixLink = "";
    $xpath = new DOMXPath($doc);
    foreach($xpath->query("//*[@id]") as $e) {
      $id = $e->getAttribute("id");
      if($e->hasAttribute("link")) {
        $link = $e->getAttribute("link");
        if($link != $prefix) $link = $prefixLink.$link;
        #if(!strlen($prefixLink) && $link == $prefix) $link = "";
        if(array_key_exists($link, self::$linkToNull)) {
          new Logger(sprintf(_("Duplicit link %s skipped"), $link), Logger::LOGGER_WARNING);
          continue;
        }
        self::$linkToNull[$link] = null;
        $newLinks[$link] = $e;
        $pLink = array("path" => $link);
      } else {
        $link = $e->getAncestorValue("link", "h");
        if($link != $prefix) $link = $prefixLink.$link;
        #if(!strlen($prefixLink) && $link == $prefix) $link = "";
        $pLink = array("path" => $link, "fragment" => $id);
        $link = buildUrl($pLink);
      }
      self::$idToLink[$prefix][$id] = $pLink;
      if($e->nodeName == "h") {
        $desc = getShortString($e->nextElement->nodeValue);
        if(strlen($desc)) self::$idToDesc[$link] = $desc;
      }
      if(strlen($e->getAttribute("title")))
        self::$idToTitle[$link] = $e->getAttribute("title");
      else
        self::$idToTitle[$link] = getShortString($e->nodeValue);
    }
    foreach($newLinks as $link => $e) $e->setAttribute("link", $link);
  }

  private static function generateUniqueVal($val, Array $reg) {
    $i = 1;
    while(array_key_exists($val.$i, $reg)) $i++;
    return $val.$i;
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
        self::insertHtmlPlus($include, dirname($filePath));
        $toStripElement[] = $include;
      } catch(Exception $e) {
        $toStripTag[] = $include;
        new Logger($e->getMessage(), Logger::LOGGER_ERROR);
      }
    }
    foreach($toStripTag as $include) $include->stripTag();
    foreach($toStripElement as $include) $include->stripElement();
    new Logger(sprintf(_("Inserted %s of %s HTML+ file(s)"),
      count($toStripElement), count($includes)), null, $start_time, false);
  }

  private static function insertHtmlPlus(DOMElement $include, $homeDir) {
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
      $author = $include->getAncestorValue("author", "h");
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
      new Logger(sprintf(_("Imported file language '%s' does not match section language '%s' in '%s'"),
        $impLang, $sectLang, $val), Logger::LOGGER_WARNING);
    $impAuthor = $doc->documentElement->firstElement->getAttribute("author");
    if($impAuthor == $author)
      $doc->documentElement->firstElement->removeAttribute("author"); // prevent "creation" info
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
          if($d->hasAttribute("modifyonly")) continue;
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