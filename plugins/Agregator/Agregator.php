<?php

class Agregator extends Plugin implements SplObserver {
  private $files = array();  // filePath => fileInfo(?)
  private $currentDoc = null;
  private $currentSubdir = null;
  private $currentFilepath = null;
  private $currentMTime = null;
  private $currentCTime = null;
  private $edit;
  private $cfg;
  private static $sortKey;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 2);
    $this->edit = _("Edit");
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $this->cfg = $this->getDOMPlus();
    $htmlDir = USER_FOLDER."/".$this->pluginDir;
    $curLink = getCurLink();
    foreach($this->createList($htmlDir) as $subDir => $files) {
      $this->createHtmlVar($subDir, $files);
    }
    #$filesList = $this->createList(FILES_FOLDER);
    #$this->createFilesVar(FILES_FOLDER);
    #$this->createImgVar(FILES_FOLDER);
    if(is_null($this->currentDoc)) return;
    $this->insertDocInfo($this->currentDoc);
    $this->insertContent($this->currentDoc, $this->currentSubdir);
  }

  private function insertDocInfo(HTMLPlus $doc) {
    foreach($doc->getElementsByTagName("h") as $h) {
      $ul = $this->createDocInfo($h);
      if(!$ul->childNodes->length) continue;
      $e = $h->nextElement;
      while(!is_null($e)) {
        if($e->nodeName == "h") break;
        $e = $e->nextElement;
      }
      if(is_null($e)) $h->parentNode->appendChild($ul);
      else $h->parentNode->insertBefore($ul, $e);
    }
  }

  private function createDocInfo(DOMElementPlus $h) {
    $ul = $h->ownerDocument->createElement("ul");
    if($h->parentNode->nodeName == "body") {
      $ul->setAttribute("class", "docinfo nomultiple global");
      // global author & creation
      $li = $ul->appendChild($h->ownerDocument->createElement("li"));
      $li->setAttribute("class", "created");
      $authorLink = $h->getAttribute("author");
      if($h->hasAttribute("authorid")) {
        $authorLink = "<a href='".$h->getAttribute("authorid")."'>$authorLink</a>";
      }
      $cDate = "<em fn='inputvar-dateformat'>".$this->currentCTime."</em>";
      $li->insertVariable(sprintf(_("Created by %s on %s"), $authorLink, $cDate));
      // global modification
      if(substr($this->currentCTime, 0, 10) != substr($this->currentMTime, 0, 10)) {
        $li = $ul->appendChild($h->ownerDocument->createElement("li"));
        $li->setAttribute("class", "modified");
        $mDate = "<em fn='inputvar-dateformat'>".$this->currentMTime."</em>";
        $li->insertVariable(sprintf(_("Document content last modified on %s"), $mDate));
      }
      // global responsibility
      if($h->hasAttribute("resp")) {
        $li = $ul->appendChild($h->ownerDocument->createElement("li"));
        $li->setAttribute("class", "responsible");
        $respLink = $h->getAttribute("resp");
        if($h->hasAttribute("respid")) {
          $respLink = "<a href='".$h->getAttribute("respid")."'>$respLink</a>";
        }
        $li->insertVariable(sprintf(_("Person responsible for this content is %s"), $respLink));
      }
      // edit link
      if(Cms::isSuperUser()) {
        $li = $ul->appendChild($h->ownerDocument->createElement("li"));
        $li->setAttribute("class", "edit");
        $a = $li->appendChild($h->ownerDocument->createElement("a", $this->edit));
        $a->setAttribute("href", "?Admin=".$this->currentFilepath);
        $a->setAttribute("title", $this->currentFilepath);
      }
    } else {
      $ul->setAttribute("class", "docinfo nomultiple partial");
      // local author (?)
      // local responsibility (?)
      // local creation
      if($h->hasAttribute("ctime") && substr($this->currentCTime, 0, 10) != substr($h->getAttribute("ctime"), 0, 10)) {
        $li = $ul->appendChild($h->ownerDocument->createElement("li"));
        $cDate = "<em fn='inputvar-dateformat'>".$h->getAttribute("ctime")."</em>";
        $li->insertVariable(sprintf(_("This section has been created on %s"), $cDate));
      }
      // local modification
      if($h->hasAttribute("mtime") && substr($this->currentMTime, 0, 10) != substr($h->getAttribute("mtime"), 0, 10)) {
        $li = $ul->appendChild($h->ownerDocument->createElement("li"));
        $mDate = "<em fn='inputvar-dateformat'>".$h->getAttribute("mtime")."</em>";
        $li->insertVariable(sprintf(_("This section has been modified on %s"), $mDate));
      }
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
    foreach($doc->documentElement->childElements as $e) {
      $dest->appendChild($dest->ownerDocument->importNode($e, true));
    }
  }

  private function createList($rootDir, $subDir=null) {
    try {
      mkdir_plus($rootDir);
    } catch(Exception $e) {
      new Logger(_("Unable to create Agregator temporary folder"), Logger::LOGGER_WARNING);
      return;
    }
    $list = array();
    $workingDir = is_null($subDir) ? $rootDir : "$rootDir/$subDir";
    foreach(scandir($workingDir) as $f) {
      if(strpos($f, ".") === 0) continue;
      if(is_dir("$workingDir/$f")) {
        $list = array_merge($list, $this->createList($rootDir, is_null($subDir) ? $f : "$subDir/$f"));
        continue;
      }
      if(is_file("$workingDir/.$f")) continue;
      $list[$subDir][] = $f;
    }
    return $list;
  }

  private function createImgVar($rootDir) {
    foreach($this->files as $subDir => $null) {
      $workingDir = $subDir == "" ? $rootDir : "$rootDir/$subDir";
      $doc = new DOMDocumentPlus();
      $root = $doc->appendChild($doc->createElement("root"));
      $ol = $root->appendChild($doc->createElement("ol"));
      $found = false;
      foreach($this->files[$subDir] as $f => $null) {
        $mime = getFileMime("$workingDir/$f");
        if(strpos($mime, "image/") !== 0) continue;
        $li = $ol->appendChild($doc->createElement("li"));
        $a = $li->appendChild($doc->createElement("a"));
        $href = $subDir == "" ? $f : "$subDir/$f";
        $a->setAttribute("href", "/$href");
        $o = $a->appendChild($doc->createElement("object"));
        $o->setAttribute("data", "/$href?thumb");
        $o->setAttribute("type", $mime);
        $o->nodeValue = $href;
        $found = true;
      }
      if(!$found) continue;
      Cms::setVariable("img".($subDir == "" ? "" : "_".str_replace("/", "_", $subDir)), $root);
    }
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

  private function createHtmlVar($subDir, Array $files) {
    $vars = array();
    $useCache = true;
    foreach($files as $fileName) {
      if(pathinfo($fileName, PATHINFO_EXTENSION) != "html") continue;
      if(strlen($subDir)) $fileName = "$subDir/$fileName";
      $file = $this->pluginDir."/$fileName";
      $filePath = USER_FOLDER."/".$file;
      try {
        $doc = DOMBuilder::buildHTMLPlus($filePath);
      } catch(Exception $e) {
        new Logger($e->getMessage(), Logger::LOGGER_WARNING);
        continue;
      }
      $vars[$filePath] = $this->getHTMLVariables($doc, $file);
      foreach($doc->getElementsByTagName("h") as $h) {
        if(!$h->hasAttribute("link")) continue;
        if($h->getAttribute("link") != getCurLink()) continue;
        $this->currentDoc = $doc;
        $this->currentSubdir = $subDir;
        $this->currentFilepath = $file;
        $this->currentCTime = $h->getAttribute("ctime");
        $this->currentMTime = $h->getAttribute("mtime");
      }
      $cacheKey = get_class($this).$filePath;
      if(!$this->isValidCached($cacheKey, $filePath)) {
        $stored = apc_store($cacheKey, filemtime($filePath), rand(3600*24*30*3, 3600*24*30*6));
        if(!$stored) new Logger(sprintf(_("Unable to cache variable %s"), $file), Logger::LOGGER_WARNING);
        $useCache = false;
      }
    }
    if(empty($vars)) return;
    foreach($this->cfg->documentElement->childElements as $html) {
      if($html->nodeName != "html") continue;
      if(!$html->hasAttribute("id")) {
        new Logger(_("Configuration element html missing attribute id"), Logger::LOGGER_WARNING);
        continue;
      }
      $vName = $html->getAttribute("id").($subDir == "" ? "" : "_".str_replace("/", "_", $subDir));
      // use cache
      if($useCache) {
        $sCache = $this->getSubDirCache($vName);
        if(!is_null($sCache)) {
          $doc = new DOMDocumentPlus();
          $doc->loadXML($sCache["value"]);
          Cms::setVariable($sCache["name"], $doc->documentElement);
          continue;
        }
      }
      self::$sortKey = "ctime";
      $reverse = true;
      if($html->hasAttribute("sort") || $html->hasAttribute("rsort")) {
        $reverse = $html->hasAttribute("rsort");
        $userKey = $html->hasAttribute("sort") ? $html->getAttribute("sort") : $html->getAttribute("rsort");
        if(!array_key_exists($userKey, current($vars))) {
          new Logger(sprintf(_("Sort variable %s not found; using default"), $userKey), Logger::LOGGER_WARNING);
        } else {
          self::$sortKey = $userKey;
        }
      }
      uasort($vars, array("Agregator", "cmp"));
      if($reverse) $vars = array_reverse($vars);
      try {
        $vValue = $this->getDOM($vars, $html->childElements, $html->getAttribute("id"));
        Cms::setVariable($vName, $vValue->documentElement);
        $var = array(
          "name" => $vName,
          "value" => $vValue->saveXML(),
        );
        $stored = apc_store(HOST.get_class($this)."_subdir_$vName", $var, rand(3600*24*30*3, 3600*24*30*6));
        if(!$stored) new Logger(sprintf(_("Unable to cache variable %s"), $vName), Logger::LOGGER_WARNING);
      } catch(Exception $e) {
        new Logger($e->getMessage(), Logger::LOGGER_WARNING);
        continue;
      }
    }
  }

  private function isValidCached($key, $filePath) {
    if(!apc_exists($key)) return false;
    if(apc_fetch($key) != filemtime($filePath)) return false;
    return true;
  }

  private function getSubDirCache($vName) {
    $cacheKey = HOST.get_class($this)."_subdir_$vName";
    if(!apc_exists($cacheKey)) return null;
    return apc_fetch($cacheKey);
  }


  private static function cmp($a, $b) {
    if($a[self::$sortKey] == $b[self::$sortKey]) return 0;
    return ($a[self::$sortKey] < $b[self::$sortKey]) ? -1 : 1;
  }

  private function getDOM(Array $vars, DOMNodeList $items, $id) {
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
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
    if($nonItemElement) new Logger(sprintf(_("Redundant element(s) found in %s"), $id), Logger::LOGGER_WARNING);
    if(empty($patterns)) throw new Exception(_("No item element found"));
    $i = -1;
    $pattern = null;
    foreach($vars as $k => $v) {
      $i++;
      if(isset($patterns[$i])) $pattern = $patterns[$i];
      if(is_null($pattern) || !$pattern->childNodes->length) continue;
      $item = $this->replaceVariables($pattern, $v);
      $item = $root->appendChild($doc->importNode($item, true));
      $item->stripTag();
    }
    return $doc;
  }

  private function replaceVariables(DOMElementPlus $element, Array $vars) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($element, true));
    $doc->processVariables($vars);
    #$doc->processFunctions(array(), $vars);
    return $doc->documentElement;
  }

  private function getHTMLVariables(HTMLPlus $doc, $filePath) {
    $vars = array();
    $h = $doc->documentElement->firstElement;
    $desc = $h->nextElement;
    $vars['editlink'] = "";
    if(Cms::isSuperUser()) {
      $vars['editlink'] = "<a href='?Admin=$filePath' title='$filePath' class='flaticon-drawing3'>".$this->edit."</a>";
    }
    $vars['heading'] = $h->nodeValue;
    $vars['link'] = $h->getAttribute("link");
    $vars['author'] = $h->getAttribute("author");
    $vars['authorid'] = $h->hasAttribute("authorid") ? $h->getAttribute("authorid") : null;
    $vars['resp'] = $h->hasAttribute("resp") ? $h->getAttribute("resp") : null;
    $vars['respid'] = $h->hasAttribute("respid") ? $h->getAttribute("respid") : null;
    $vars['ctime'] = $h->getAttribute("ctime");
    $vars['mtime'] = $h->getAttribute("mtime");
    $vars['short'] = $h->hasAttribute("short") ? $h->getAttribute("short") : null;
    $vars['desc'] = $desc->nodeValue;
    $vars['kw'] = $desc->getAttribute("kw");
    return $vars;
  }

}

?>