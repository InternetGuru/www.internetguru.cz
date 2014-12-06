<?php


class Agregator extends Plugin implements SplObserver {
  private $links = array();  // link => filePath
  private $html = array();  // filePath => HTMLPlus

  public function __construct(SplSubject $s) {
    $s->setPriority($this, 2);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
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
    foreach(scandir($dir) as $f) {
      if(strpos($f, ".") === 0) continue;
      if(!is_dir("$dir/$f")) continue;
      if(!preg_match("/^[a-z]+$/", $f)) {
        new Logger(sprintf(_("Wrong folder format '%s'"), $f), Logger::LOGGER_WARNING);
        continue;
      }
      $this->createVarList("$dir/$f");
    }
  }

  private function createVarList($src) {
    $ctime = array();
    foreach(scandir($src) as $f) {
      if(!is_file("$src/$f")) continue;
      if(strpos($f, ".") === 0) continue;
      if(is_file("$src/.$f")) continue;
      if(pathinfo($f, PATHINFO_EXTENSION) != "html") continue;
      try {
        $doc = DOMBuilder::buildHTMLPlus("$src/$f");
        $this->html["$src/$f"] = $doc;
        $ctime["$src/$f"] = strtotime($doc->documentElement->firstElement->getAttribute("ctime"));
        foreach($doc->getElementsByTagName("h") as $h) {
          if(!$h->hasAttribute("link")) continue;
          $this->links[$h->getAttribute("link")] = "$src/$f";
        }
      } catch(Exception $e) {
        #new Logger(sprintf(_("Unable to load '%s': %s"), $f, $e->getMessage()), Logger::LOGGER_WARNING);
      }
    }
    if(empty($this->html)) return;
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
    Cms::setVariable("list_".basename($src), $root);
  }

}

?>