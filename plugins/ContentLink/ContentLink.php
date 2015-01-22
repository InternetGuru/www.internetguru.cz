<?php

class ContentLink extends Plugin implements SplObserver, ContentStrategyInterface {
  private $lang = null;
  private $isRoot;
  private $hPath;
  private $vars = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 4);
  }

  public function update(SplSubject $subject) {
    $this->isRoot = getCurLink() == "";
    if($this->isRoot) return;
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("Xhtml11")) return;
  }

  public function getContent(HTMLPlus $c) {
    $cf = Cms::getContentFull();
    $link = getCurLink();
    if($this->isRoot) $h1 = $cf->documentElement->firstElement;
    else {
      $h1 = $cf->getElementById($link, "link", "h");
      if(is_null($h1)) new ErrorPage(sprintf(_("Page '%s' not found"), $link), 404);
    }
    $cfg = $this->getDOMPlus();
    foreach($cfg->documentElement->childElements as $e) {
      if($e->nodeName != "var" || !$e->hasAttribute("id")) continue;
      $this->vars[$e->getAttribute("id")] = $e;
    }

    $this->setPath($h1);
    $this->generateBc($c);
    if($this->isRoot) return $c;

    $desc = $h1->nextElement;
    if(!$h1->hasAttribute("ns")) $h1->setAttribute("ns", $h1->getAncestorValue("ns"));
    if(!strlen($desc->nodeValue)) $desc->nodeValue = $desc->getAncestorValue();
    if(!$desc->hasAttribute("kw")) $desc->setAttribute("kw", $desc->getAncestorValue("kw"));

    $this->handleAttribute($h1, "ctime");
    $this->handleAttribute($h1, "mtime");
    $this->handleAttribute($h1, "author");
    $this->handleAttribute($h1, "authorid");
    $this->handleAttribute($h1, "resp");
    $this->handleAttribute($h1, "respid");
    $this->handleAttribute($h1->parentNode, "xml:lang", "lang", Cms::getVariable("cms-lang"));

    $content = new HTMLPlus();
    $content->formatOutput = true;
    $body = $content->appendChild($content->createElement("body"));
    foreach($h1->parentNode->attributes as $attName => $attNode) {
      $body->setAttributeNode($content->importNode($attNode));
    }
    $this->appendUntilSame($h1, $body);

    return $content;
  }

  private function handleAttribute(DOMElement $e, $aName, $vName=null, $def=null) {
    if(is_null($vName)) $vName = $aName;
    if($e->hasAttribute($aName)) {
      Cms::setVariable($vName, $e->getAttribute($aName));
      return;
    }
    $value = $e->getAncestorValue($aName);
    if(is_null($value)) {
      if(is_null($def)) return;
      $value = $def;
    }
    $e->setAttribute($aName, $value);
    if($value == Cms::getVariable("cms-$vName")) return;
    Cms::setVariable($vName, $value);
  }

  private function setPath(DOMElement $h) {
    while(!is_null($h)) {
      if($h->hasAttribute("link")) $this->hPath[$h->getAttribute("id")] = $h;
      $h = $h->parentNode->getPreviousElement("h");
    }
  }

  private function generateBc(HTMLPlus $src) {
    $bc = new DOMDocumentPlus();
    $root = $bc->appendChild($bc->createElement("root"));
    $ol = $root->appendChild($bc->createElement("ol"));
    $ol->setAttribute("class", "contentlink-bc");
    $subtitles = array();
    $a = null;
    $li = null;
    $h = null;
    foreach(array_reverse($this->hPath) as $h) {
      #$h->processVariables(Cms::getAllVariables());
      $li = $ol->appendChild($bc->createElement("li"));
      $a = $li->appendChild($bc->createElement("a"));
      $textContainer = $a;
      $a->setAttribute("href", "#".$h->getAttribute("id"));
      if($h->hasAttribute("title")) $a->setAttribute("title", $h->getAttribute("title"));
      if(empty($subtitles)) {
        if(array_key_exists("logo", $this->vars)) {
          if(!is_file(FILES_FOLDER."/".$this->vars["logo"]->nodeValue)) {
            new Logger(sprintf(_("Logo file %s not found"), $this->vars["logo"]), Logger::LOGGER_WARNING);
          } else {
            $o = $a->appendChild($bc->createElement("object"));
            $o->setAttribute("data", $this->vars["logo"]->nodeValue);
            $o->setAttribute("type", $this->vars["logo"]->getAttribute("type"));
            $textContainer = $o;
            $a->appendChild($o);
          }
        }
        $subtitles[] = $h->nodeValue;
        if(!$this->isRoot && !$h->hasAttribute("title") && $h->hasAttribute("short"))
          $a->setAttribute("title", $h->getAttribute("short"));

      } else {
        $subtitles[] = $h->hasAttribute("short") ? $h->getAttribute("short") : $h->nodeValue;
      }
    }
    if($h->hasAttribute("short")) {
      if($textContainer->nodeName == "a") $a->nodeValue = $h->getAttribute("short");
      else $li->appendChild($bc->createElement("span", $h->getAttribute("short")));
    } else $textContainer->nodeValue = $h->nodeValue;
    Cms::setVariable("bc", $bc->documentElement);
    Cms::setVariable("title", implode(" - ", array_reverse($subtitles)));
  }

  private function appendUntilSame(DOMElement $e, DOMElement $into) {
    $doc = $into->ownerDocument;
    $into->appendChild($doc->importNode($e, true));
    $untilName = $e->nodeName;
    while(($e = $e->nextElement) !== null) {
      if($e->nodeName == $untilName) break;
      $into->appendChild($doc->importNode($e, true));
    }
  }

}

?>