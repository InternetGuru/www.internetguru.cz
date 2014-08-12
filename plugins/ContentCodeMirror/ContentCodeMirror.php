<?php

class ContentCodeMirror implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "preinit") return;
    $this->subject = $subject;
    $subject->setPriority($this,100);
  }

  public function getContent(HTMLPlus $content) {

    // detach and return if no textarea or blockcode
    $ta = $content->getElementsByTagName("textarea");
    if($ta->length == 0) {
      $this->subject->detach($this);
      return $content;
    }

    // supported syntax only
    $detach = true;
    foreach($ta as $t) {
      if($t->hasAttribute("class") && in_array("xml", explode(" ",$t->getAttribute("class")))) $detach = false;
    }
    if($detach) {
      $this->subject->detach($this);
      return $content;
    }

    $cms = $this->subject->getCms();
    $os = $cms->getOutputStrategy();

    $os->addCssFile("lib/codemirror/lib/codemirror.css");
    $os->addCssFile("lib/codemirror/theme/monokai.css");
    $os->addCssFile('ContentCodeMirror.css','ContentCodeMirror');

    $os->addJsFile("lib/codemirror/lib/codemirror.js");
    $os->addJsFile("lib/codemirror/mode/xml/xml.js");
    $os->addJsFile("lib/codemirror/keymap/sublime.js");

    $os->addJsFile("lib/codemirror/addon/search/searchcursor.js");
    $os->addJsFile("lib/codemirror/addon/comment/comment.js");
    $os->addJsFile("lib/codemirror/addon/wrap/hardwrap.js");
    $os->addJsFile("lib/codemirror/addon/fold/foldcode.js");

    $os->addJsFile('ContentCodeMirror.js','ContentCodeMirror', 10, "body");

    return $content;
  }

  public function getTitle(Array $q) {
    return $q;
  }

  public function getDescription($q) {
    return $q;
  }

}

?>
