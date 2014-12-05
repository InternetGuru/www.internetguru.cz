<?php

#TODO: more textarea support (js)
#TODO: alt+.to end an element
#TODO: search result jump to first occurence
#TODO: search zero match found message!
#TODO: replace (ctrl+h) buttons color

#fixme: moving up/down changes word wrapping (issue 13)
#https://bitbucket.org/igwr/cms/issue/13/codemirror-changing-word-wrap

class SyntaxCodeMirror extends Plugin implements SplObserver, ContentStrategyInterface {

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 100);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_INIT) {
      $this->detachIfNotAttached("Xhtml11");
      return;
    }
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
      "xml" => LIB_DIR."/codemirror/mode/xml/xml.js",
      "css" => LIB_DIR."/codemirror/mode/css/css.js",
      "javascript" => LIB_DIR."/codemirror/mode/javascript/javascript.js",
      );
    $libs = array();
    foreach($ta as $t) {
      if(!$t->hasAttribute("class")) continue;
      foreach(explode(" ", $t->getAttribute("class")) as $c) {
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
    $os = Cms::getOutputStrategy();

    $os->addCssFile(LIB_DIR."/codemirror/lib/codemirror.css");
    $os->addCssFile(LIB_DIR."/codemirror/theme/tomorrow-night-eighties.css");
    $os->addCssFile($this->getDir().'/SyntaxCodeMirror.css');

    $os->addJsFile(LIB_DIR."/codemirror/lib/codemirror.js");
    foreach($libs as $l) $os->addJsFile($l);
    $os->addJsFile(LIB_DIR."/codemirror/keymap/sublime.js");

    $os->addJsFile(LIB_DIR."/codemirror/addon/search/searchcursor.js");
    $os->addJsFile(LIB_DIR."/codemirror/addon/search/search.js");
    $os->addJsFile(LIB_DIR."/codemirror/addon/dialog/dialog.js");
    $os->addCssFile(LIB_DIR."/codemirror/addon/dialog/dialog.css");

    $os->addJsFile(LIB_DIR."/codemirror/addon/selection/active-line.js");
    $os->addJsFile(LIB_DIR."/codemirror/addon/selection/mark-selection.js");
    $os->addJsFile(LIB_DIR."/codemirror/addon/comment/comment.js");
    $os->addJsFile(LIB_DIR."/codemirror/addon/edit/closetag.js");
    $os->addJsFile(LIB_DIR."/codemirror/addon/wrap/hardwrap.js");
    $os->addJsFile(LIB_DIR."/codemirror/addon/fold/foldcode.js");

    $os->addJsFile($this->getDir().'/SyntaxCodeMirror.js', 10, "body");

    return $content;
  }

}

?>
