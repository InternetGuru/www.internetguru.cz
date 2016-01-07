<?php

class Agregator extends Plugin implements SplObserver {
  private $files = array();  // filePath => fileInfo(?)
  private $docinfo = array();
  private $currentDoc = null;
  private $currentSubdir = null;
  private $currentFilepath = null;
  private $useCache = true;
  private $edit;
  private $cfg;
  private static $sortKey;
  const INOTIFY = ".inotify";
  const APC_PREFIX = "1";
  const DEBUG = false;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    if(self::DEBUG) Logger::log("DEBUG");
    $s->setPriority($this, 2);
    $this->edit = _("Edit");
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
    $this->cfg = $this->getDOMPlus();
    $curLink = getCurLink();
    try {
      mkdir_plus(ADMIN_FOLDER."/".$this->pluginDir);
      mkdir_plus(USER_FOLDER."/".$this->pluginDir);
      $list = array();
      $this->createList(USER_FOLDER, $list, "html");
      $this->createList(ADMIN_FOLDER, $list, "html");
      foreach($list as $subDir => $files) {
        $vars = $this->getFileVars($subDir, $files);
        #if(!count($vars)) continue;
        $this->createCmsVars($subDir, $vars);
      }
      $list = array();
      $this->createList(FILES_FOLDER, $list);
      #$this->createFilesVar(FILES_FOLDER);
      foreach($list as $subDir => $files) {
        $this->createImgVar(FILES_DIR, $subDir, $files);
      }
    } catch(Exception $e) {
      Logger::log($e->getMessage(), Logger::LOGGER_WARNING);
      return;
    }
    if(is_null($this->currentDoc)) return;
    Cms::getOutputStrategy()->addTransformation($this->pluginDir."/Agregator.xsl");
    $this->insertDocInfo($this->currentDoc);
    $this->insertContent($this->currentDoc, $this->currentSubdir);
  }

  private function insertDocInfo(HTMLPlus $doc) {
    $vars = array();
    foreach($this->cfg->getElementsByTagName("var") as $var) {
      $vars[$var->getAttribute("id")] = $var;
    }
    foreach($doc->getElementsByTagName("h") as $h) {
      $ul = $this->createDocInfo($h, $vars);
      if(!$ul->childNodes->length) continue;
      $ul->processVariables($this->docinfo, array(), true);
      if($h->parentNode->nodeName == "body") {
        $wrapper = $doc->createElement("var");
        $wrapper->appendChild($ul);
        Cms::setVariable("docinfo", $wrapper);
        continue;
      }
      $e = $h->nextElement;
      while(!is_null($e)) {
        if($e->nodeName == "h") break;
        $e = $e->nextElement;
      }
      if(is_null($e)) $h->parentNode->appendChild($ul);
      else $h->parentNode->insertBefore($ul, $e);
    }
  }

  private function createDocInfo(DOMElementPlus $h, Array $vars) {
    $doc = $h->ownerDocument;
    $ul = $doc->createElement("ul");
    if($h->parentNode->nodeName == "body") {
      $ul->setAttribute("class", "docinfo nomultiple global");
      $li = $ul->appendChild($doc->createElement("li"));
      // global author & creation
      $li->setAttribute("class", "creation");
      foreach($vars["creation"]->childNodes as $n) {
        $li->appendChild($doc->importNode($n, true));
      }
      // global modification
      if(substr($this->docinfo["ctime"], 0, 10) != substr($this->docinfo["mtime"], 0, 10)) {
        $li = $ul->appendChild($doc->createElement("li"));
        $li->setAttribute("class", "modified");
        foreach($vars["modified"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      // global responsibility
      if($h->hasAttribute("resp")) {
        $li = $ul->appendChild($doc->createElement("li"));
        $li->setAttribute("class", "responsible");
        foreach($vars["responsible"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      // edit link
      if(Cms::isSuperUser()) {
        $li = $ul->appendChild($doc->createElement("li"));
        $li->setAttribute("class", "edit");
        $a = $li->appendChild($doc->createElement("a", $this->edit));
        $a->setAttribute("href", "?Admin=".$this->currentFilepath);
        $a->setAttribute("title", $this->currentFilepath);
      }
    } else {
      $ul->setAttribute("class", "docinfo nomultiple partial");
      $partinfo = array();
      // local author (?)
      // local responsibility (?)
      // local creation
      if($h->hasAttribute("ctime") && substr($this->docinfo["ctime"], 0, 10) != substr($h->getAttribute("ctime"), 0, 10)) {
        $partinfo["ctime"] = $h->getAttribute("ctime");
        $li = $ul->appendChild($doc->createElement("li"));
        foreach($vars["part_created"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      // local modification
      if($h->hasAttribute("mtime") && substr($this->docinfo["mtime"], 0, 10) != substr($h->getAttribute("mtime"), 0, 10)) {
        $partinfo["mtime"] = $h->getAttribute("mtime");
        $li = $ul->appendChild($doc->createElement("li"));
        foreach($vars["part_modified"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      $ul->processVariables($partinfo, array(), true);
    }
    return $ul;
  }

  private function insertContent(HTMLPlus $doc, $subDir) {
    $dest = Cms::getContentFull()->getElementById($subDir, "link");
    if(is_null($dest)) $dest = Cms::getContentFull()->documentElement->firstElement->nextElement;
    while($dest->nodeName != "section") {
      if(is_null($dest->nextElement)) {
        $dest = $dest->parentNode->appendChild($dest->ownerDocument->createElement("section"));
        break;
      }
      if($dest->nextElement->nodeName == "h") {
        $dest = $dest->parentNode->insertBefore($dest->ownerDocument->createElement("section"), $dest->nextElement);
        break;
      }
      $dest = $dest->nextElement;
    }
    foreach($doc->documentElement->attributes as $a) {
      if($a->nodeName == "ns") continue;
      $dest->setAttribute($a->nodeName, $a->nodeValue);
    }
    foreach($doc->documentElement->childElementsArray as $e) {
      $dest->appendChild($dest->ownerDocument->importNode($e, true));
    }
  }

  private function createList($rootDir, Array &$list, $ext=null, $subDir=null) {
    if(isset($list[$subDir])) return; // user dir (with at least one file) beats admin dir
    $workingDir = "$rootDir/".$this->pluginDir.(strlen($subDir) ? "/$subDir" : "");
    if(!is_dir($workingDir)) return;
    foreach(scandir($workingDir) as $f) {
      if(strpos($f, ".") === 0) continue;
      if(is_dir("$workingDir/$f")) {
        $this->createList($rootDir, $list, $ext, is_null($subDir) ? $f : "$subDir/$f");
        continue;
      }
      if(!is_null($ext) && pathinfo($f, PATHINFO_EXTENSION) != $ext) continue;
      if(is_file("$workingDir/.$f")) continue;
      $list[$subDir][$f] = $rootDir;
    }
  }

  private function createImgVar($root, $subDir, Array $files) {
    $cacheKey = apc_get_key($subDir);
    $useCache = true;
    if(!apc_is_valid_cache($cacheKey, count($files))) {
      apc_store_cache($cacheKey, count($files), $subDir);
      $useCache = false;
    }
    $alts = $this->buildImgAlts();
    $vars = $this->buildImgVars($files, $alts, $root, $subDir);
    if(empty($vars)) return;
    foreach($this->cfg->documentElement->childElementsArray as $image) {
      if($image->nodeName != "image") continue;
      if(!$image->hasAttribute("id")) {
        Logger::log(_("Configuration element image missing attribute id"), Logger::LOGGER_WARNING);
        continue;
      }
      $vName = $image->getAttribute("id").($subDir == "" ? "" : "_".str_replace("/", "_", $subDir));
      self::$sortKey = "name";
      $cacheKey = apc_get_key($vName);
      if($useCache && apc_exists($cacheKey)) {
        $doc = new DOMDocumentPlus();
        $doc->loadXML(apc_fetch($cacheKey));
        Cms::setVariable($vName, $doc->documentElement);
        continue;
      }
      $vars = $this->sort($vars, $image);
      $vValue = $this->getDOM($vars, $image);
      apc_store_cache($cacheKey, $vValue->saveXML(), $vName);
      Cms::setVariable($vName, $vValue->documentElement);
    }
  }

  private function buildImgAlts() {
    $alts = array();
    foreach($this->cfg->documentElement->childElementsArray as $alt) {
      if($alt->nodeName != "alt") continue;
      if(!$alt->hasAttribute("for")) {
        Logger::log(_("Configuration element alt missing attribute for"), Logger::LOGGER_WARNING);
        continue;
      }
      $alts[$alt->getAttribute("for")] = $alt->nodeValue;
    }
    return $alts;
  }

  private function buildImgVars(Array $files, Array $alts, $root, $subDir) {
    $vars = array();
    foreach($files as $fileName) {
      $sd = $subDir;
      if(strlen($subDir)) $sd.= "/";
      $filePath = USER_FOLDER."/$root/$sd$fileName";
      $mimeType = getFileMime($filePath);
      if($mimeType != "image/svg+xml" && strpos($mimeType, "image/") !== 0) continue;
      $v = array();
      $v["name"] = $fileName;
      $v["type"] = $mimeType;
      $v["mtime"] = filemtime($filePath);
      $v["url"] = $filePath;
      $v["url-images"] = $filePath;
      $v["url-thumbs"] = "$root/thumbs/$sd$fileName";
      $v["url-preview"] = "$root/preview/$sd$fileName";
      $v["url-big"] = "$root/big/$sd$fileName";
      $v["url-full"] = "$root/full/$sd$fileName";
      if(isset($alts[$fileName])) $v["alt"] = $alts[$fileName];
      $vars[$filePath] = $v;
    }
    return $vars;
  }

  private function createFilesVar($rootDir) {
    foreach($this->files as $subDir => $null) {
      $workingDir = $subDir == "" ? $rootDir : "$rootDir/$subDir";
      $doc = new DOMDocumentPlus();
      $root = $doc->appendChild($doc->createElement("root"));
      $ol = $root->appendChild($doc->createElement("ol"));
      foreach($this->files[$subDir] as $f => $null) {
        $li = $ol->appendChild($doc->createElement("li"));
        $a = $li->appendChild($doc->createElement("a"));
        $href = $subDir == "" ? $f : "$subDir/$f";
        $a->setAttribute("href", "/$href");
        $a->nodeValue = $href;
      }
      Cms::setVariable("files".($subDir == "" ? "" : "_".str_replace("/", "_", $subDir)), $root);
    }
  }

  private function getFileVars($subDir, Array $files) {
    $vars = array();
    $cacheKey = apc_get_key($subDir);
    $path = $this->pluginDir.(strlen($subDir) ? "/$subDir" : "");
    $inotify = current($files)."/$path/".self::INOTIFY;
    if(is_file($inotify)) $checkSum = filemtime($inotify);
    else $checkSum = count($files);
    if(!apc_is_valid_cache($cacheKey, $checkSum)) {
      apc_store_cache($cacheKey, $checkSum, $subDir);
      $this->useCache = false;
    }
    foreach($files as $fileName => $rootDir) {
      $file = "$path/$fileName";
      $filePath = "$rootDir/$file";
      try {
        $doc = DOMBuilder::buildHTMLPlus($file);
        $vars[$filePath] = $this->getHTMLVariables($doc, $filePath, $file);
      } catch(Exception $e) {
        Logger::log($e->getMessage(), Logger::LOGGER_WARNING);
        continue;
      }
      if(is_null($this->currentDoc) && $this->isCurrentDoc($vars[$filePath]["links"])) {
        $this->docinfo = $vars[$filePath];
        $this->currentSubdir = $subDir;
        $this->currentFilepath = $file;
        $this->currentDoc = $doc;
      }
      if(is_file($inotify)) continue;
      $cacheKey = apc_get_key($filePath);
      if(apc_is_valid_cache($cacheKey, filemtime($filePath))) continue;
      apc_store_cache($cacheKey, filemtime($filePath), $file);
      $this->useCache = false;
    }
    return $vars;
  }

  private function createCmsVars($subDir, Array $vars) {
    $filePath = findFile($this->pluginDir."/".get_class($this).".xml");
    $cacheKey = apc_get_key($filePath);
    if(!apc_is_valid_cache($cacheKey, filemtime($filePath))) {
      apc_store_cache($cacheKey, filemtime($filePath), $this->pluginDir."/".get_class($this).".xml");
      $this->useCache = false;
    }
    foreach($this->cfg->documentElement->childElementsArray as $html) {
      if($html->nodeName != "html") continue;
      if(!$html->hasAttribute("id")) {
        Logger::log(_("Configuration element html missing attribute id"), Logger::LOGGER_WARNING);
        continue;
      }
      $vName = $html->getAttribute("id").($subDir == "" ? "" : "_".str_replace("/", "_", $subDir));
      $cacheKey = apc_get_key($vName);
      // use cache
      if($this->useCache && !self::DEBUG) {
        $sCache = $this->getSubDirCache($cacheKey);
        if(!is_null($sCache)) {
          $doc = new DOMDocumentPlus();
          $doc->loadXML($sCache["value"]);
          Cms::setVariable($sCache["name"], $doc->documentElement);
          continue;
        }
      }
      self::$sortKey = "ctime";
      $vars = $this->sort($vars, $html);
      try {
        $vValue = $this->getDOM($vars, $html);
        Cms::setVariable($vName, $vValue->documentElement);
        $var = array(
          "name" => $vName,
          "value" => $vValue->saveXML(),
        );
        apc_store_cache($cacheKey, $var, $vName);
      } catch(Exception $e) {
        Logger::log($e->getMessage(), Logger::LOGGER_WARNING);
        continue;
      }
    }
  }

  private function sort($vars, $e) {
    $reverse = true;
    if($e->hasAttribute("sort") || $e->hasAttribute("rsort")) {
      $reverse = $e->hasAttribute("rsort");
      $userKey = $e->hasAttribute("sort") ? $e->getAttribute("sort") : $e->getAttribute("rsort");
      if(!array_key_exists($userKey, current($vars))) {
        Logger::log(sprintf(_("Sort variable %s not found; using default"), $userKey), Logger::LOGGER_WARNING);
      } else {
        self::$sortKey = $userKey;
      }
    }
    uasort($vars, array("Agregator", "cmp"));
    if($reverse) $vars = array_reverse($vars);
    return $vars;
  }

  private function getSubDirCache($cacheKey) {
    if(!apc_exists($cacheKey)) return null;
    return apc_fetch($cacheKey);
  }

  private static function cmp($a, $b) {
    if($a[self::$sortKey] == $b[self::$sortKey]) return 0;
    return ($a[self::$sortKey] < $b[self::$sortKey]) ? -1 : 1;
  }

  private function getDOM(Array $vars, DOMElementPlus $html) {
    $items = $html->childElementsArray;
    $id = $html->getAttribute("id");
    $class = $html->getAttribute("class");
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    if(strlen($html->getAttribute("wrapper")))
      $root = $root->appendChild($doc->createElement($html->getAttribute("wrapper")));
    if(strlen($class)) $root->setAttribute("class", $class);
    $nonItemElement = false;
    $patterns = array();
    foreach($items as $item) {
      if($item->nodeName != "item") {
        $nonItemElement = true;
        continue;
      }
      if($item->hasAttribute("since"))
        $patterns[$item->getAttribute("since")-1] = $item;
      else $patterns[] = $item;
    }
    if($nonItemElement) Logger::log(sprintf(_("Redundant element(s) found in %s"), $id), Logger::LOGGER_WARNING);
    if(empty($patterns)) throw new Exception(_("No item element found"));
    $i = -1;
    $pattern = null;
    foreach($vars as $k => $v) {
      $i++;
      if(isset($patterns[$i])) $pattern = $patterns[$i];
      if(is_null($pattern) || !$pattern->childNodes->length) continue;
      $item = $root->appendChild($doc->importNode($pattern, true));
      $item->processVariables($v, array(), true);
      $item->stripTag();
    }
    return $doc;
  }

  private function getHTMLVariables(HTMLPlus $doc, $filePath, $file) {
    $cacheKey = apc_get_key("vars/$filePath");
    $mTime = filemtime($filePath);
    if($this->useCache && apc_exists($cacheKey)) {
      $vars = apc_fetch($cacheKey);
      if($vars["filemtime"] == $mTime) return $vars;
    }
    $vars = array();
    $h = $doc->documentElement->firstElement;
    $desc = $h->nextElement;
    $vars['editlink'] = "";
    if(Cms::isSuperUser()) {
      $vars['editlink'] = "<a href='?Admin=$file' title='$file' class='flaticon-drawing3'>".$this->edit."</a>";
    }
    $vars['filemtime'] = $mTime;
    $vars['heading'] = $h->nodeValue;
    $vars['link'] = $h->getAttribute("link");
    $vars['author'] = $h->getAttribute("author");
    $vars['authorid'] = $h->hasAttribute("authorid") ? $h->getAttribute("authorid") : "";
    $vars['resp'] = $h->hasAttribute("resp") ? $h->getAttribute("resp") : null;
    $vars['respid'] = $h->hasAttribute("respid") ? $h->getAttribute("respid") : "";
    $vars['ctime'] = $h->getAttribute("ctime");
    $vars['mtime'] = $h->getAttribute("mtime");
    $vars['short'] = $h->hasAttribute("short") ? $h->getAttribute("short") : null;
    $vars['desc'] = $desc->nodeValue;
    $vars['kw'] = $desc->getAttribute("kw");
    $vars['links'] = array();
    foreach($doc->getElementsByTagName("h") as $h) {
      if(!$h->hasAttribute("link")) continue;
      $vars['links'][] = $h->getAttribute("link");
    }
    apc_store_cache($cacheKey, $vars, $file);
    return $vars;
  }

  private function isCurrentDoc(Array $links) {
    foreach($links as $link) {
      if($link == getCurLink()) return true;
    }
    return false;
  }

}

?>
