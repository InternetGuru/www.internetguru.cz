<?php

class SyntaxCodeMirror extends Plugin implements SplObserver, ContentStrategyInterface {

  const CM_DIR = "CodeMirror";

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 100);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_INIT) {
      $this->detachIfNotAttached("HtmlOutput");
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
      "xml" => LIB_DIR."/".self::CM_DIR."/mode/xml/xml.js",
      "css" => LIB_DIR."/".self::CM_DIR."/mode/css/css.js",
      "javascript" => LIB_DIR."/".self::CM_DIR."/mode/javascript/javascript.js",
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

    $os->addCssFile(LIB_DIR."/".self::CM_DIR."/lib/codemirror.css");
    $os->addCssFile(LIB_DIR."/".self::CM_DIR."/theme/tomorrow-night-eighties.css");
    $os->addCssFile($this->pluginDir.'/SyntaxCodeMirror.css');

    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/lib/codemirror.js");
    foreach($libs as $l) $os->addJsFile($l);
    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/keymap/sublime.js");

    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/addon/search/searchcursor.js");
    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/addon/search/search.js");
    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/addon/dialog/dialog.js");
    $os->addCssFile(LIB_DIR."/".self::CM_DIR."/addon/dialog/dialog.css");

    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/addon/selection/active-line.js");
    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/addon/selection/mark-selection.js");
    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/addon/comment/comment.js");
    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/addon/edit/closetag.js");
    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/addon/wrap/hardwrap.js");
    $os->addJsFile(LIB_DIR."/".self::CM_DIR."/addon/fold/foldcode.js");

    $os->addJsFile($this->pluginDir.'/SyntaxCodeMirror.js', 10, "body");

    return $content;
  }

}

?>
