<?php


class Agregator extends Plugin implements SplObserver {
  private $links = array();  // link => filePath
  private $html = array();  // filePath => HTMLPlus
  private $files = array();  // filePath => fileInfo(?)

  public function __construct(SplSubject $s) {
    $s->setPriority($this, 2);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $this->createList($this->html, USER_FOLDER."/".$this->getDir());
    $this->createHtmlVar(USER_FOLDER."/".$this->getDir());
    $this->createList($this->files, FILES_FOLDER);
    $this->createFilesVar(FILES_FOLDER);
    $this->createImgVar(FILES_FOLDER);
    $this->insertContent();
  }

  private function insertContent() {
    if(!array_key_exists(getCurLink(), $this->links)) return;
    $doc = $this->html[$this->links[getCurLink()]];
    $dest = Cms::getContentFull()->getElementById("dokumenty", "link");
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
      $list[is_null($subDir) ? "." : $subDir][$f] = null;
    }
  }

  private function createImgVar($rootDir) {
    foreach($this->files as $subDir => $null) {
      $workingDir = $subDir == "." ? $rootDir : "$rootDir/$subDir";
      $doc = new DOMDocumentPlus();
      $root = $doc->appendChild($doc->createElement("root"));
      $ol = $root->appendChild($doc->createElement("ol"));
      $found = false;
      foreach($this->files[$subDir] as $f => $null) {
        $mime = getFileMime("$workingDir/$f");
        if(strpos($mime, "image/") !== 0) continue;
        $li = $ol->appendChild($doc->createElement("li"));
        $a = $li->appendChild($doc->createElement("a"));
        $href = $subDir == "." ? $f : "$subDir/$f";
        $a->setAttribute("href", $href);
        $o = $a->appendChild($doc->createElement("object"));
        $o->setAttribute("data", "$href?thumb");
        $o->setAttribute("type", $mime);
        $o->nodeValue = $href;
        $found = true;
      }
      if(!$found) continue;
      Cms::setVariable("img".($subDir == "." ? "" : "_".str_replace("/", "_", $subDir)), $root);
    }
  }

  private function createFilesVar($rootDir) {
    foreach($this->files as $subDir => $null) {
      $workingDir = $subDir == "." ? $rootDir : "$rootDir/$subDir";
      $doc = new DOMDocumentPlus();
      $root = $doc->appendChild($doc->createElement("root"));
      $ol = $root->appendChild($doc->createElement("ol"));
      foreach($this->files[$subDir] as $f => $null) {
        $li = $ol->appendChild($doc->createElement("li"));
        $a = $li->appendChild($doc->createElement("a"));
        $href = $subDir == "." ? $f : "$subDir/$f";
        $a->setAttribute("href", $href);
        $a->nodeValue = $href;
      }
      Cms::setVariable("files".($subDir == "." ? "" : "_".str_replace("/", "_", $subDir)), $root);
    }
  }

  private function createHtmlVar($rootDir) {
    foreach($this->html as $subDir => $null) {
      $workingDir = $subDir == "." ? $rootDir : "$rootDir/$subDir";
      $ctime = array();
      foreach($this->html[$subDir] as $f => $null) {
        if(pathinfo($f, PATHINFO_EXTENSION) != "html") continue;
        try {
          $doc = DOMBuilder::buildHTMLPlus("$workingDir/$f", true, $subDir == "." ? null : $subDir);
          $this->html["$workingDir/$f"] = $doc;
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

      $doc = new DOMDocumentPlus();
      $root = $doc->appendChild($doc->createElement("root"));
      $ol = $root->appendChild($doc->createElement("ol"));

      $docTop = new DOMDocumentPlus();
      $rootTop = $docTop->appendChild($docTop->createElement("root"));
      $dl = $rootTop->appendChild($docTop->createElement("dl"));
      $i = 0;
      foreach($ctime as $k => $null) {
        $i++;
        $li = $ol->appendChild($doc->createElement("li"));
        $a = $li->appendChild($doc->createElement("a"));
        $href = $this->html[$k]->documentElement->firstElement->getAttribute("link");
        $a->setAttribute("href", $href);
        $hContent = $this->html[$k]->documentElement->firstElement->nodeValue;
        $a->nodeValue = $hContent;
        if($i > 3) continue;
        $dt = $dl->appendChild($docTop->createElement("dt"));
        $a = $dt->appendChild($docTop->createElement("a"));
        $a->setAttribute("href", $href);
        $a->nodeValue = $hContent;
        if($i > 1) continue;
        $dd = $dl->appendChild($docTop->createElement("dd"));
        $dd->nodeValue = $this->html[$k]->documentElement->firstElement->nextElement->nodeValue;
      }
      Cms::setVariable("htmltop".($subDir == "." ? "" : "_".str_replace("/", "_", $subDir)), $rootTop);
      Cms::setVariable("html".($subDir == "." ? "" : "_".str_replace("/", "_", $subDir)), $root);
    }
  }

}

?>