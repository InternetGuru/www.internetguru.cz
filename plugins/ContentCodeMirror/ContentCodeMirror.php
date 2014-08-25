<?php

#TODO: more textarea support (js)

class ContentCodeMirror extends Plugin implements SplObserver, ContentStrategyInterface {

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
    $modes = array(
      "xml" => "lib/codemirror/mode/xml/xml.js",
      "xsl" => "lib/codemirror/mode/xml/xml.js",
      "css" => "lib/codemirror/mode/css/css.js",
      );
    $libs = array();
    foreach($ta as $t) {
      if(!$t->hasAttribute("class")) continue;
      foreach(explode(" ",$t->getAttribute("class")) as $c) {
        if(array_key_exists($c, $modes)) {
          $libs[] = $modes[$c];
          break;
        }
      }
    }
    if(empty($libs)) {
      $this->subject->detach($this);
      return $content;
    }

    $cms = $this->subject->getCms();
    $os = $cms->getOutputStrategy();

    $os->addCssFile("lib/codemirror/lib/codemirror.css");
    $os->addCssFile("lib/codemirror/theme/tomorrow-night-eighties.css");
    $os->addCssFile($this->getDir() .'/ContentCodeMirror.css');

    $os->addJsFile("lib/codemirror/lib/codemirror.js");
    foreach($libs as $l) $os->addJsFile($l);
    $os->addJsFile("lib/codemirror/keymap/sublime.js");

    $os->addJsFile("lib/codemirror/addon/search/searchcursor.js");
    $os->addJsFile("lib/codemirror/addon/selection/active-line.js");
    $os->addJsFile("lib/codemirror/addon/comment/comment.js");
    $os->addJsFile("lib/codemirror/addon/edit/closetag.js");
    $os->addJsFile("lib/codemirror/addon/wrap/hardwrap.js");
    $os->addJsFile("lib/codemirror/addon/fold/foldcode.js");

    $os->addJsFile($this->getDir() .'/ContentCodeMirror.js', 10, "body");

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
