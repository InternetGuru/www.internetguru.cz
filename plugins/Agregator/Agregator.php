<?php


class Agregator extends Plugin implements SplObserver {
  private $links = array();  // link => filePath
  private $html = array();  // filePath => HTMLPlus
  private $files = array();  // filePath => fileInfo(?)
  private $cfg;
  private static $sortKey;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 2);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $this->cfg = $this->getDOMPlus();
    $htmlDir = USER_FOLDER."/".$this->pluginDir;
    $this->createList($this->html, $htmlDir);
    $this->createHtmlVar($htmlDir);
    $this->createList($this->files, FILES_FOLDER);
    $this->createFilesVar(FILES_FOLDER);
    $this->createImgVar(FILES_FOLDER);
    $this->insertContent($htmlDir);
  }

  private function insertContent() {
    if(!array_key_exists(getCurLink(), $this->links)) return;
    $subDir = $this->links[getCurLink()][0];
    $fName = $this->links[getCurLink()][1];
    $doc = $this->html[$subDir][$fName];
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
    foreach($doc->documentElement->childElements as $e) {
      $dest->appendChild($dest->ownerDocument->importNode($e, true));
    }
    Cms::setVariable("filepath", $this->pluginDir.(strlen($subDir) ? "/$subDir" : "")."/$fName");
  }

  private function createList(&$list, $rootDir, $subDir=null) {
    if(!is_dir($rootDir)) {
      if(!mkdir($rootDir, 0775, true))
        new Logger(_("Unable to create Agregator temporary folder"), Logger::LOGGER_WARNING);
      return;
    }
    $workingDir = is_null($subDir) ? $rootDir : "$rootDir/$subDir";
    foreach(scandir($workingDir) as $f) {
      if(strpos($f, ".") === 0) continue;
      if(is_dir("$workingDir/$f")) {
        $this->createList($list, $rootDir, is_null($subDir) ? $f : "$subDir/$f");
        continue;
      }
      if(is_file("$workingDir/.$f")) continue;
      $list[$subDir][$f] = null;
    }
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

  private function createHtmlVar($rootDir) {
    $vars = array();
    foreach($this->html as $subDir => $null) {
      $workingDir = ($subDir == "" ? $rootDir : "$rootDir/$subDir");
      foreach($this->html[$subDir] as $f => $null) {
        if(pathinfo($f, PATHINFO_EXTENSION) != "html") continue;
        try {
          $doc = DOMBuilder::buildHTMLPlus("$workingDir/$f");
          #$doc = DOMBuilder::buildHTMLPlus("$workingDir/$f", true, $subDir == "" ? null : $subDir);
        } catch(Exception $e) {
          continue;
          #new Logger(sprintf(_("Agregator skipped file '%s'"), "$subDir/$f"), Logger::LOGGER_WARNING);
        }
        $this->html[$subDir][$f] = $doc;
        $vars[$subDir][$f] = $this->getHTMLVariables($doc, "$workingDir/$f");
        foreach($doc->getElementsByTagName("h") as $h) {
          if(!$h->hasAttribute("link")) continue;
          $this->links[$h->getAttribute("link")] = array($subDir, $f);
        }
      }
      if(!array_key_exists($subDir, $vars)) continue;
      foreach($this->cfg->documentElement->childElements as $html) {
        if($html->nodeName != "html") continue;
        if(!$html->hasAttribute("id")) {
          new Logger(_("Configuration element html missing attribute id"), Logger::LOGGER_WARNING);
          continue;
        }
        self::$sortKey = "ctime";
        if($html->hasAttribute("sort") || $html->hasAttribute("rsort")) {
          $reverse = $html->hasAttribute("rsort");
          $userKey = $html->hasAttribute("sort") ? $html->getAttribute("sort") : $html->getAttribute("rsort");
          if(!array_key_exists($userKey, current($vars[$subDir]))) {
            new Logger(sprintf(_("Sort variable %s not found; using default ctime"), $userKey), Logger::LOGGER_WARNING);
          } else {
            self::$sortKey = $userKey;
          }
        } else $reverse = true;
        uasort($vars[$subDir], array("Agregator", "cmp"));
        if($reverse) $vars[$subDir] = array_reverse($vars[$subDir]);
        try {
          $vName = $html->getAttribute("id").($subDir == "" ? "" : "_".str_replace("/", "_", $subDir));
          $vValue = $this->getDOM($vars[$subDir], $html->childElements, $subDir);
          Cms::setVariable($vName, $vValue);
        } catch(Exception $e) {
          new Logger($e->getMessage(), Logger::LOGGER_WARNING);
          continue;
        }
      }
    }
  }

  private static function cmp($a, $b) {
    if($a[self::$sortKey] == $b[self::$sortKey]) return 0;
    return ($a[self::$sortKey] < $b[self::$sortKey]) ? -1 : 1;
  }

  private function getDOM(Array $vars, DOMNodeList $items, $subDir) {
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));

    $patterns = array();
    foreach($items as $item) {
      if($item->nodeName != "item") continue;
      if($item->hasAttribute("since"))
        $patterns[$item->getAttribute("since")-1] = $item;
      else $patterns[] = $item;
    }
    if(empty($patterns)) throw new Exception(_("No item element found"));
    $i = -1;
    $pattern = null;
    foreach($vars as $k => $v) {
      $htmlPlus = $this->html[$subDir][$k];
      if(is_null($htmlPlus)) continue;
      $i++;
      if(isset($patterns[$i])) $pattern = $patterns[$i];
      if(is_null($pattern) || !$pattern->childNodes->length) continue;
      $item = $this->replaceVariables($pattern, $v);
      $item = $root->appendChild($doc->importNode($item, true));
      $item->stripTag();
    }
    return $doc->documentElement;
  }

  private function replaceVariables(DOMElementPlus $element, Array $vars) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($element, true));
    $doc->processVariables($vars);
    $doc->processFunctions(array(), $vars);
    return $doc->documentElement;
  }

  private function getHTMLVariables(HTMLPlus $doc, $filePath) {
    $vars = array();
    $h = $doc->documentElement->firstElement;
    $desc = $h->nextElement;
    $vars['filepath'] = $filePath;
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