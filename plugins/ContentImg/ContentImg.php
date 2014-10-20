<?php

#todo: limit as {x,y}

class ContentImg extends Plugin implements SplObserver, ContentStrategyInterface {
  private $content = null;
  private $mime = array("image/jpeg","image/png","image/gif","image/svg+xml");
  const PREFIX = "contentimg-";

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "init") {
      $this->subject = $subject;
      $subject->setPriority($this,110);
    }
  }

  public function getContent(HTMLPlus $content) {
    $cfg = $this->getDomPlus();
    $xpath = new DOMXPath($content);
    foreach($xpath->query("//*[self::ul or self::ol][@var and contains(@var,'".self::PREFIX."')]") as $ul) {
      $dom = new DOMDocumentPlus();
      $list = $dom->appendChild($dom->createElement("list"));
      foreach(explode(" ", $ul->getAttribute("var")) as $val) {
        if(strpos($val,self::PREFIX) !== 0) continue;
        $pattern = substr($val,11);
        $this->getImages($list, $pattern, $cfg);
        if($list->childElements->length == 0) continue;
        $content->insertVar(self::PREFIX.$pattern,$list);
      }
    }
    return $content;
  }

  public function getImages(DOMElement $ul, $pattern, DOMDocumentPlus $cfg) {
    $images = matchFiles($pattern,THUMBS_FOLDER);
    if(!count($images)) return;
    $desc = array();
    foreach($cfg->getElementsByTagName("desc") as $d) {
      if(!$d->hasAttribute("id")) continue;
      $desc[THUMBS_FOLDER . "/" . $d->getAttribute("id")] = $d->nodeValue;
    }
    $limit = 100;
    foreach($images as $i) {
      if(--$limit < 0) break;
      $imgInfo = getimagesize($i);
      if(!$this->isSupportedImg($i,$imgInfo["mime"])) continue;
      #todo: check dimensions

      #todo: sophisticated default desc
      $title = pathinfo($i,PATHINFO_FILENAME);
      if(array_key_exists($i, $desc)) $title = $desc[$i];

      $li = $ul->ownerDocument->createElement("li");
      $ul->appendChild($li);
      $obj = $ul->ownerDocument->createElement("object",$title);
      $obj->setAttribute("data",$i);
      $obj->setAttribute("width",$imgInfo[0]);
      $obj->setAttribute("height",$imgInfo[1]);
      $obj->setAttribute("type",$imgInfo["mime"]);
      $obj->setAttribute("title",$title);

      $pic = PICTURES_FOLDER . "/" . pathinfo($i,PATHINFO_BASENAME);
      if(file_exists($pic) && $this->isSupportedImg($pic)) {
        $a = $ul->ownerDocument->createElement("a");
        $a->appendChild($obj);
        $a->setAttribute("href",$pic);
        $li->appendChild($a);
        continue;
      }
      $li->appendChild($obj);
    }
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

}

?>