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
  private static $linkToDesc = array(); // id => shorted description
  private static $linkToTitle = array(); // id => self title or shorted content
  private static $linkToId = array(); // link => null
  private static $defaultPrefix = null;

  public static function buildHTMLPlus($filePath, $user=true) {
    $doc = new HTMLPlus();
    self::build($doc, $filePath, true, $user);
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
    throw new Exception(sprintf(METHOD_NA, __CLASS__.".".__FUNCTION__));
  }

  public static function normalizeLink(Array $pUrl) {
    #var_dump($pUrl);
    #if(empty($pUrl)) return "";
    if(empty($pUrl)) return array("path" => "");
    #if(count($pUrl) == 1 && isset($pUrl["path"]) && $pUrl["path"] == self::$defaultPrefix) return "";
    if(count($pUrl) == 1 && isset($pUrl["path"]) && $pUrl["path"] == self::$defaultPrefix) return array("path" => "");
    #if(!isset($pUrl["path"])) return null; // no prefix
    if(!isset($pUrl["path"])) {
      if(isset($pUrl["fragment"]) && isset(self::$idToLink[self::$defaultPrefix][$pUrl["fragment"]]))
        $pUrl["path"] = self::$defaultPrefix;
      else return $pUrl; // no prefix
    }
    #var_dump($pUrl);
    #var_dump(self::$linkToId);
    #var_dump(self::$idToLink);
    #if(!isset($pUrl["fragment"])) $pUrl["fragment"] = $pUrl["path"];
    if(isset($pUrl["fragment"]) && isset(self::$idToLink[$pUrl["path"]][$pUrl["fragment"]])) {
      if($pUrl["path"] == self::$defaultPrefix) $pUrl["path"] = self::$idToLink[$pUrl["path"]][$pUrl["fragment"]];
      elseif(strlen(self::$idToLink[$pUrl["path"]][$pUrl["fragment"]])) $pUrl["path"] = $pUrl["path"]."/".self::$idToLink[$pUrl["path"]][$pUrl["fragment"]];
      if(!isset(self::$linkToId[implodeLink($pUrl)])) unset($pUrl["fragment"]);
      #return implodeLink($pUrl);
      return $pUrl;
    }
    #if($pUrl["path"] == "") $pUrl["path"] = self::$defaultPrefix;
    #if(isset(self::$linkToId[implodeLink($pUrl, false)])) return implodeLink($pUrl);
    if(isset(self::$linkToId[implodeLink($pUrl, false)])) return $pUrl;
    #if(isset(self::$linkToId[$pUrl["path"]]) && !isset($pUrl["fragment"])) {
      #if($pUrl["path"] == self::$defaultPrefix) $pUrl["path"] = "";
      #return implodeLink($pUrl);
    #}
    throw new Exception(sprintf(_("Link %s not found"), implodeLink($pUrl)));
  }

  public static function getDesc($link) {
    if(array_key_exists($link, self::$linkToDesc)) return self::$linkToDesc[$link];
    reset(self::$linkToDesc);
    $link = key(self::$linkToDesc).$link;
    if(array_key_exists($link, self::$linkToDesc)) return self::$linkToDesc[$link];
    return null;
  }

  public static function getTitle($link) {
    #var_dump(self::$linkToTitle); die();
    if(array_key_exists($link, self::$linkToTitle)) return self::$linkToTitle[$link];
    reset(self::$linkToTitle);
    $link = key(self::$linkToTitle).$link;
    if(array_key_exists($link, self::$linkToTitle)) return self::$linkToTitle[$link];
    return null;
  }

  public static function getRootHeadingId() {
    reset(self::$idToLink);
    return key(self::$idToLink);
  }

  public static function getLinks() {
    return array_keys(self::$linkToId);
  }

  public static function isLink($link) {
    return array_key_exists($link, self::$linkToId);
  }

  private static function globalReadonly(DOMDocumentPlus $doc) {
    $nodes = array();
    foreach($doc->documentElement->childElementsArray as $n) {
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
      if(file_exists(dirname($f)."/.".basename($f))) continue;
      try {
        self::loadDOM($f, $doc, null);
      } catch(Exception $e) {
        new Logger(sprintf(_("Unable to load '%s': %s"), basename($filePath), $e->getMessage()), Logger::LOGGER_ERROR);
        continue;
      }
      $success = true;
      break;
    }
    if($success) return;
    $doc = null;
    if(!is_null($e)) throw new Exception(sprintf(_("Failed to load user/admin/default file %s: %s"),
      basename($filePath), $e->getMessage()));
  }

  private static function loadDOM($filePath, DOMDocumentPlus $doc, $author=null, $included=false) {
    $fShort = stripDataFolder($filePath);
    if($doc instanceof HTMLPlus) {
      if(array_key_exists($filePath, self::$included))
        throw new Exception(sprintf(_("File '%s' already included"), $fShort));
      self::$included[$filePath] = null;
      Cms::addVariableItem("html", $fShort);
    }
    if(is_file(dirname($filePath)."/.".basename($filePath)))
      throw new Exception(sprintf(_("File disabled")));

    $fInfo = self::getCache($filePath);
    if(!is_null($fInfo)) {
      if(is_null(self::$defaultPrefix)) self::$defaultPrefix = $fInfo["prefix"];
      foreach($fInfo["includes"] as $file => $mtime) {
        self::$included[$file] = null;
        Cms::addVariableItem("html", stripDataFolder($file));
      }
      $doc->loadXML($fInfo["xml"]);
      self::$idToLink[$fInfo["prefix"]] = $fInfo["idtolink"];
      self::$linkToId = array_merge(self::$linkToId, $fInfo["linktoid"]);
      self::$linkToDesc = array_merge(self::$linkToDesc, $fInfo["linktodesc"]);
      self::$linkToTitle = array_merge(self::$linkToTitle, $fInfo["linktotitle"]);
      return;
    }

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
    $storeCache = true;
    try {
      $doc->validatePlus();
    } catch(Exception $e) {
      $doc->validatePlus(true);
      $storeCache = false;
      if(strpos($filePath, CMS_FOLDER) !== 0) {
        new Logger(sprintf(_("HTML+ file %s autocorrected: %s"), $fShort, $e->getMessage()), Logger::LOGGER_WARNING);
      }
    }
    // generate ctime/mtime from file if not set
    self::setMtime($doc, $filePath);
    // HTML+ include
    $inclDom = array();
    $inclSrc = array();
    foreach($doc->getElementsByTagName("include") as $include) {
      $inclDom[] = $include;
      $inclPath = dirname($filePath)."/".$include->getAttribute("src");
      $inclSrc[$inclPath] = filemtime($inclPath);
    }
    $offId = count(self::$linkToId);
    $offDesc = count(self::$linkToDesc);
    $offTitle = count(self::$linkToTitle);
    if(!self::insertIncludes($inclDom, dirname($filePath))) $storeCache = false;
    // register links/ids; repair if duplicit
    if($included) return;
    if(!self::setIdentifiers($doc, $fShort)) $storeCache = false;
    #var_dump(self::$idToLink);
    #var_dump(self::$linkToId);
    #var_dump(self::$linkToDesc);
    #var_dump(self::$linkToTitle);
    if(!$storeCache) return;
    $fInfo = array(
      "mtime" => filemtime($filePath),
      "includes" => $inclSrc,
      "idtolink" => end(self::$idToLink),
      "prefix" => key(self::$idToLink),
      "linktoid" => array_slice(self::$linkToId, $offId, null, true),
      "linktodesc" => array_slice(self::$linkToDesc, $offDesc, null, true),
      "linktotitle" => array_slice(self::$linkToTitle, $offTitle, null, true),
      "xml" => $doc->saveXML()
    );
    $stored = apc_store($filePath, $fInfo, rand(3600*24*30*3, 3600*24*30*6));
    if(!$stored) new Logger(sprintf(_("Unable to cache file %s"), $fShort), Logger::LOGGER_WARNING);
  }

  private static function getCache($filePath) {
    if(!apc_exists($filePath)) return null;
    $fInfo = apc_fetch($filePath);
    if($fInfo["mtime"] != filemtime($filePath)) return null;
    foreach($fInfo["includes"] as $file => $mtime) {
      if($mtime != filemtime($file)) return null;
      if(isset(self::$included[$file])) return null;
    }
    return $fInfo;
  }

  private static function setIdentifiers(HTMLPlus $doc, $fShort) {
    $storeCache = true;
    $h1 = $doc->documentElement->firstElement;
    $prefix = trim($h1->getAttribute("link"), "/");
    #if(empty(self::$idToLink)) $prefix = "";
    if(array_key_exists($prefix, self::$idToLink)) {
      $prefix = self::generateUniqueVal($prefix, self::$idToLink);
      new Logger(sprintf(_("Duplicit prefix %s in %s renamed to %s"), $h1->getAttribute("link"),
        $fShort, $prefix), Logger::LOGGER_WARNING);
      $h1->setAttribute("link", $prefix);
      $storeCache = false;
    }
    self::$idToLink[$prefix] = array();
    #self::$linkToId[$prefix] = array();
    if(!self::registerKeys($doc, $prefix)) $storeCache = false;
    self::addLocalPrefix($doc, $prefix, "a", "href");
    self::addLocalPrefix($doc, $prefix, "form", "action");
    #var_dump(self::$idToLink); var_dump(self::$linkToId);
    return $storeCache;
  }

  private static function addLocalPrefix(HTMLPlus $doc, $prefix, $eName, $aName) {
    foreach($doc->getElementsByTagName($eName) as $e) {
      if(!$e->hasAttribute($aName)) continue;
      $pLink = parseLocalLink($e->getAttribute($aName));
      if(is_null($pLink)) continue; // link is external
      if(isset($pLink["path"]) && strlen($pLink["path"])) {
        if(!isset(self::$linkToId[$prefix."/".$pLink["path"]])) continue;
        $pLink["path"] = $prefix."/".$pLink["path"];
      } elseif(isset($pLink["fragment"])) {
        if(!isset(self::$linkToId[$prefix."#".$pLink["fragment"]])) continue;
        $pLink["path"] = $prefix;
      } else continue;
      $e->setAttribute($aName, implodeLink($pLink));
      #var_dump(implodeLink($pLink));
    }
  }

  private static function registerKeys(HTMLPlus $doc, $prefix) {
    $newLinks = array();
    $storeCache = true;
    if(empty(self::$linkToId)) self::$defaultPrefix = $prefix;
    $xpath = new DOMXPath($doc);
    foreach($xpath->query("//*[@id]") as $e) {
      $id = $e->getAttribute("id");
      if($e->hasAttribute("link")) {
        $link = ($e->getAttribute("link") != $prefix ? $e->getAttribute("link") : "");
        #if($link != $prefix) $link = .$link;
        #if(!strlen($prefix."/") && $link == $prefix) $link = "";
        #$pLink = array("path" => $link);
        $linkId = self::getLinkFull($prefix, $e->getAttribute("link"), null);
        $newLinks[$linkId] = $e;
      } else {
        $link = $e->getAncestorValue("link", "h");
        if($link == $prefix) $link = "";
        $linkId = self::getLinkFull($prefix, $link, $id);
      }
      if(empty(self::$linkToId)) $linkId = "";
      if(array_key_exists($linkId, self::$linkToId)) {
        new Logger(sprintf(_("Duplicit link %s skipped"), $link), Logger::LOGGER_WARNING);
        $storeCache = false;
        continue;
      }
      self::$idToLink[$prefix][$id] = $link;
      self::$linkToId[$linkId] = $id;
      #self::$idToLink[$prefix][$id] = $pLink;
      if($e->nodeName == "h") {
        $desc = getShortString($e->nextElement->nodeValue);
        if(strlen($desc)) self::$linkToDesc[$linkId] = $desc;
      }
      if(strlen($e->getAttribute("title")))
        self::$linkToTitle[$linkId] = $e->getAttribute("title");
      else
        self::$linkToTitle[$linkId] = getShortString($e->nodeValue);
    }
    foreach($newLinks as $link => $e) {
      #if(!strlen($link)) $link = self::$defaultPrefix;
      $e->setAttribute("link", $link);
    }
    return $storeCache;
    #var_dump($link);
  }

  private static function getLinkFull($prefix, $link, $frag) {
    $linkId = array();
    if($prefix != self::$defaultPrefix) $linkId[] = $prefix;
    if($link != $prefix && strlen($link)) $linkId[] = $link;
    if(empty($linkId) && !strlen($frag)) $linkId[] = self::$defaultPrefix;
    return implode("/", $linkId).(strlen($frag) ? "#$frag" : "");
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

  private static function insertIncludes(Array $includes, $homeDir) {
    $toStripElement = array();
    $toStripTag = array();
    foreach($includes as $include) {
      try {
        self::insertHtmlPlus($include, $homeDir);
        $toStripElement[] = $include;
      } catch(Exception $e) {
        $toStripTag[] = $include;
        new Logger($e->getMessage(), Logger::LOGGER_ERROR);
      }
    }
    foreach($toStripTag as $include) $include->stripTag();
    foreach($toStripElement as $include) $include->stripElement();
    return empty($toStripTag);
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
      self::loadDOM("$homeDir/$val", $doc, $author, true);
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
    foreach($doc->documentElement->childElementsArray as $n) {
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
    foreach($newDoc->documentElement->childElementsArray as $n) {
      // if empty && readonly => user cannot modify
      foreach($doc->getElementsByTagName($n->nodeName) as $d) {
        if(!$ignoreReadonly && $d->hasAttribute("readonly") && $d->nodeValue == "") return;
      }
      if(!$n instanceof DOMElement) continue;
      if(self::doRemove($n)) {
        $remove = array();
        foreach($doc->documentElement->childElementsArray as $d) {
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