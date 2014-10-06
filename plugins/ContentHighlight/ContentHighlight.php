<?php
/**
 * How to use highlight.js
 * http://highlightjs.org/usage/
 */
class ContentHighlight extends Plugin implements SplObserver, ContentStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() == "preinit") {
      $this->subject = $subject;
      $subject->setPriority($this,100);
    }
    if($subject->getStatus() == "init") {
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

    $cms = $this->subject->getCms();
    $os = $cms->getOutputStrategy();

    $os->addCssFile("lib/highlight/styles/tomorrow.css");
    $os->addCssFile($this->getDir() ."/ContentHighlight.css");
    $os->addJsFile("lib/highlight/highlight.pack.js");
    $os->addJsFile($this->getDir() .'/ContentHighlight.js', 10, "body");

    return $content;
  }

}

?>
