<?php
/**
 * How to use highlight.js
 * http://highlightjs.org/usage/
 */
class ContentHighlight implements SplObserver, ContentStrategyInterface {
  private $subject; // SplSubject

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "preinit") return;
    $this->subject = $subject;
    $subject->setPriority($this,100);
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

    $os->addCssFile("lib/highlight/styles/default.css");
    $os->addCssFile(PLUGIN_FOLDER ."/". get_class($this) ."ContentHighlight.css");
    $os->addJsFile("lib/highlight/highlight.pack.js");
    $os->addJsFile(PLUGIN_FOLDER ."/". get_class($this) .'ContentHighlight.js', 10, "body");

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
