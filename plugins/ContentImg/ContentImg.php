<?php

class ContentImg extends Plugin implements SplObserver, ContentStrategyInterface {
  private $content = null;
  private $mime = array("image/jpeg","image/png","image/gif","image/svg+xml");

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->setPriority($this,110);
    }
  }

  public function getContent(HTMLPlus $content) {
    foreach($this->getDOMPlus()->getElementsByTagName("var") as $var) {
      if(!$var->hasAttribute("id")) throw new Exception ("Missing id attr in element var");
      if(!$var->hasAttribute("pattern")) throw new Exception ("Missing pattern attr in element var");
      $images = matchFiles($var->getAttribute("pattern"),FILES_FOLDER . "/thumbs");
      if(!count($images)) continue;
      $id = $var->getAttribute("id");
      $dom = new DOMDocumentPlus();
      $ul = $dom->createElement("ul");
      $limit = 100;
      if($var->hasAttribute("limit"))
        $limit = (int) $var->getAttribute("limit");
      $descFor = array();
      $desc = array();
      foreach($var->childNodes as $d) {
        if(!$d->hasAttribute("for")) {
          $desc[] = $d->nodeValue;
          continue;
        }
        $descFor[FILES_FOLDER . "/thumbs/" . $d->getAttribute("for")] = $d->nodeValue;
      }
      foreach($images as $i) {
        if(--$limit < 0) break;
        $imgInfo = getimagesize($i);
        if(!$this->isSupportedImg($i,$imgInfo["mime"])) continue;
        #todo: check dimensions

        $title = pathinfo($i,PATHINFO_FILENAME);
        if(array_key_exists($i, $descFor)) $title = $descFor[$i];
        elseif(count($desc)) $title = array_shift($desc);

        $li = $dom->createElement("li");
        $ul->appendChild($li);
        $obj = $dom->createElement("object",$title);
        $obj->setAttribute("data",$i);
        $obj->setAttribute("width",$imgInfo[0]);
        $obj->setAttribute("height",$imgInfo[1]);
        $obj->setAttribute("type",$imgInfo["mime"]);
        $obj->setAttribute("title",$title);

        $pic = FILES_FOLDER . "/pictures/" . pathinfo($i,PATHINFO_BASENAME);
        if(file_exists($pic) && $this->isSupportedImg($pic)) {
          $a = $dom->createElement("a");
          $a->appendChild($obj);
          $a->setAttribute("href",$pic);
          $li->appendChild($a);
          continue;
        }
        $li->appendChild($obj);
      }
      if(!$ul->childNodes->length)
        $ul->appendChild($dom->createElement("li","Image(s) not found"));
      $content->insertVar($id,$ul,"ContentImg");
    }
    return $content;
  }

  private function isSupportedImg($img,$mime=null) {
    if(is_null($mime)) {
      $i = getimagesize($img);
      $mime = $i["mime"];
    }
    if(!in_array($mime, $this->mime)) {
      $l = new Logger("Unsupported mime type '$mime' of '$i'","warning");
      return false;
    }
    return true;
  }

  public function getTitle(Array $queries) {
    return $queries;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>