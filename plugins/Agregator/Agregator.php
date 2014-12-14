<?php


class Agregator extends Plugin implements SplObserver {
  private $links = array();  // link => filePath
  private $html = array();  // filePath => HTMLPlus

  public function __construct(SplSubject $s) {
    $s->setPriority($this, 2);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
    $this->buildLists();
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

  private function buildLists() {
    $dir = USER_FOLDER."/".$this->getDir();
    if(!is_dir($dir)) return;
    $this->createVarList($dir);
  }

  private function createVarList($rootDir, $subDir=null) {
    $ctime = array();
    $workingDir = is_null($subDir) ? $rootDir : "$rootDir/$subDir";
    foreach(scandir($workingDir) as $f) {
      if(strpos($f, ".") === 0) continue;
      if(is_dir("$workingDir/$f")) {
        $this->createVarList($rootDir, is_null($subDir) ? $f : "$subDir/$f");
        continue;
      }
      if(is_file("$workingDir/.$f")) continue;
      if(pathinfo($f, PATHINFO_EXTENSION) != "html") continue;
      try {
        $doc = DOMBuilder::buildHTMLPlus("$workingDir/$f", true, $subDir);
        $this->html["$workingDir/$f"] = $doc;
        $ctime["$workingDir/$f"] = strtotime($doc->documentElement->firstElement->getAttribute("ctime"));
        foreach($doc->getElementsByTagName("h") as $h) {
          if(!$h->hasAttribute("link")) continue;
          $this->links[$h->getAttribute("link")] = "$workingDir/$f";
        }
      } catch(Exception $e) {
        new Logger(sprintf(_("Agregator skipped file '%s'"), $f), Logger::LOGGER_WARNING);
      }
    }
    if(empty($ctime)) return;
    stableSort($ctime);
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    $ol = $root->appendChild($doc->createElement("ol"));
    foreach($ctime as $k => $null) {
      $li = $ol->appendChild($doc->createElement("li"));
      $a = $li->appendChild($doc->createElement("a"));
      $a->setAttribute("href", $this->html[$k]->documentElement->firstElement->getAttribute("link"));
      $a->nodeValue = $this->html[$k]->documentElement->firstElement->nodeValue;
    }

    Cms::setVariable("list".(is_null($subDir) ? "" : "_".str_replace("/", "_", $subDir)), $root);
  }

}

?>