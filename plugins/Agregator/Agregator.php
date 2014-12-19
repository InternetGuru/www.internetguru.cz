<?php


class Agregator extends Plugin implements SplObserver {
  private $links = array();  // link => filePath
  private $html = array();  // filePath => HTMLPlus
  private $files = array();  // filePath => fileInfo(?)
  private $cfg;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 2);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $this->cfg = $this->getDOMPlus();
    $this->createList($this->html, USER_FOLDER."/".$this->getDir());
    $this->createHtmlVar(USER_FOLDER."/".$this->getDir());
    $this->createList($this->files, FILES_FOLDER);
    $this->createFilesVar(FILES_FOLDER);
    $this->createImgVar(FILES_FOLDER);
    $this->insertContent();
  }

  private function insertContent() {
    if(!array_key_exists(getCurLink(), $this->links)) return;
    $subDir = dirname(substr($this->links[getCurLink()]
      , strlen(USER_FOLDER."/".$this->getDir()) + 1));
    if($subDir == ".") $subDir = "";
    $fName = basename($this->links[getCurLink()]);
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
  }

  private function createList(&$list, $rootDir, $subDir=null) {
    if(!is_dir($rootDir)) {
      if(!mkdir($rootDir, 0775, true))
        new Logger(_("Unable to create Agregator temporary folder"), Logger::LOGGER_WARNING);
      return;
    }
    $workingDir = is_null($subDir) ? $rootDir : "$rootDir/$subDir";
    foreach(scandir($workingDir, SCANDIR_SORT_ASCENDING) as $f) {
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
        $a->setAttribute("href", $href);
        $o = $a->appendChild($doc->createElement("object"));
        $o->setAttribute("data", "$href?thumb");
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
        $a->setAttribute("href", $href);
        $a->nodeValue = $href;
      }
      Cms::setVariable("files".($subDir == "" ? "" : "_".str_replace("/", "_", $subDir)), $root);
    }
  }

  private function createHtmlVar($rootDir) {
    foreach($this->html as $subDir => $null) {
      $workingDir = $subDir == "" ? $rootDir : "$rootDir/$subDir";
      $ctime = array();
      foreach($this->html[$subDir] as $f => $null) {
        if(pathinfo($f, PATHINFO_EXTENSION) != "html") continue;
        try {
          $doc = DOMBuilder::buildHTMLPlus("$workingDir/$f", true, $subDir == "" ? null : $subDir);
          $this->html[$subDir][$f] = $doc;
          $ctime["$workingDir/$f"] = strtotime($doc->documentElement->firstElement->getAttribute("ctime"));
          foreach($doc->getElementsByTagName("h") as $h) {
            if(!$h->hasAttribute("link")) continue;
            $this->links[$h->getAttribute("link")] = "$workingDir/$f";
          }
        } catch(Exception $e) {
          new Logger(sprintf(_("Agregator skipped file '%s'"), "$subDir/$f"), Logger::LOGGER_WARNING);
        }
      }
      if(empty($ctime)) return;
      stableSort($ctime);
      foreach($this->cfg->documentElement->childElements as $html) {
        if($html->nodeName != "html") continue;
        if(!$html->hasAttribute("id")) {
          new Logger(_("Configuration element html missing attribute id"));
          continue;
        }
        if(!$html->hasAttribute("wrapper")) {
          new Logger(_("Configuration element html missing attribute wrapper"));
          continue;
        }
        try {
          Cms::setVariable($html->getAttribute("id")
            .($subDir == "" ? "" : "_".str_replace("/", "_", $subDir)),
            $this->getDOM($html->childElements, $html->getAttribute("wrapper"), $subDir));
        } catch(Exception $e) {
          new Logger($e->getMessage());
          continue;
        }
      }
    }
  }

  private function getDOM(DOMNodeList $items, $wrapper, $subDir) {
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    $list = $root->appendChild($doc->createElement($wrapper));

    $patterns = array();
    foreach($items as $item) {
      if($item->nodeName != "item") continue;
      if($item->hasAttribute("since"))
        $patterns[$item->getAttribute("since")-1] = $item->childElements;
      else $patterns[] = $item->childElements;
    }
    if(empty($patterns)) throw new Exception(_("No item element found"));
    $i = -1;
    $pattern = null;
    foreach($this->html[$subDir] as $k => $htmlPlus) {
      if(is_null($htmlPlus)) continue;
      $i++;
      if(isset($patterns[$i])) $pattern = $patterns[$i];
      if(is_null($pattern) || !$pattern->length) continue;
      foreach($pattern as $p) {
        $vars = $this->getHTMLVariables($htmlPlus);
        $p = $this->replaceVariables($p, $vars);
        $item = $list->appendChild($doc->importNode($p, true));
      }
    }
    $doc = Cms::processVariables($doc);
    return $doc->documentElement;
  }

  private function replaceVariables(DOMElementPlus $element, Array $vars) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($element, true));
    $parts = explode('\$', $doc->saveXML());
    foreach($parts as $k => $p) {
      $parts[$k] = str_replace(array_keys($vars), $vars, $p);
    }
    $doc->loadXML(implode('$', $parts));
    return $doc->documentElement;
  }

  private function getHTMLVariables(HTMLPlus $doc) {
    $vars = array();
    $h = $doc->documentElement->firstElement;
    $desc = $h->nextElement;
    $vars['$heading'] = $h->nodeValue;
    $vars['$link'] = $h->getAttribute("link");
    $vars['$ns'] = $h->getAttribute("ns");
    $vars['$author'] = $h->getAttribute("author");
    $vars['$ctime'] = $h->getAttribute("ctime");
    $vars['$mtime'] = $h->getAttribute("mtime");
    $vars['$short'] = $h->hasAttribute("short") ? $h->getAttribute("short") : null;
    $vars['$desc'] = $desc->nodeValue;
    $vars['$kw'] = $desc->getAttribute("kw");
    return $vars;
  }

}

?>