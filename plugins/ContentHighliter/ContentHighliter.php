<?php

class ContentHighliter implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "preinit") return;
    $this->subject = $subject;
    $subject->setPriority($this,100);
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    $os = $cms->getOutputStrategy();

    $os->addCssFile("lib/codemirror/lib/codemirror.css");
    $os->addCssFile("lib/codemirror/theme/monokai.css");

    $os->addJsFile("lib/codemirror/lib/codemirror.js");
    $os->addJsFile("lib/codemirror/mode/xml/xml.js");
    $os->addJsFile("lib/codemirror/keymap/sublime.js");

    $os->addJsFile("lib/codemirror/addon/search/searchcursor.js");
    $os->addJsFile("lib/codemirror/addon/comment/comment.js");
    $os->addJsFile("lib/codemirror/addon/wrap/hardwrap.js");
    $os->addJsFile("lib/codemirror/addon/fold/foldcode.js");

    $os->addJs('var editor = CodeMirror.fromTextArea(document.getElementById("adminForm"),{keyMap:"sublime",theme:"monokai",lineNumbers: true,mode:"xml", width:"100%",  lineWrapping: true});', 10, "body");

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
