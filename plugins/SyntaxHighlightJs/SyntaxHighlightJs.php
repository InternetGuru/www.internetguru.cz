<?php
/**
 * How to use highlight.js
 * http://highlightjs.org/usage/
 */
class SyntaxHighlightJs extends Plugin implements SplObserver, ContentStrategyInterface {

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,100);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() == STATUS_INIT) {
      $this->detachIfNotAttached("Xhtml11");
      return;
    }
  }

  public function getContent(HTMLPlus $content) {

    // detach and return if no textarea or blockcode
    $ta = $content->getElementsByTagName("blockcode");
    $co = $content->getElementsByTagName("code");
    if($ta->length + $co->length == 0) {
      $this->subject->detach($this);
      return $content;
    }

    global $cms;
    $os = $cms->getOutputStrategy();

    $os->addCssFile(LIB_DIR."/highlight/styles/tomorrow.css");
    $os->addCssFile($this->getDir() ."/SyntaxHighlightJs.css");
    $os->addJsFile(LIB_DIR."/highlight/highlight.pack.js");
    $os->addJsFile($this->getDir() .'/SyntaxHighlightJs.js', 10, "body");

    return $content;
  }

}

?>
