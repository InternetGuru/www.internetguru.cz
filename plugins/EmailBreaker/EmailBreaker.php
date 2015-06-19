<?php

class EmailBreaker extends Plugin implements SplObserver, ContentStrategyInterface {

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
  }

  public function getContent(HTMLPlus $content) {
    $cfg = $this->getDOMPlus();
    $contentStr = $content->saveXML();
    $at = $cfg->getElementById("at")->nodeValue;
    $dot = $cfg->getElementById("dot")->nodeValue;
    $contentStr = preg_replace("/\b".EMAIL_PATTERN."\b/", "$1$at$2$dot$3", $contentStr);
    if(!$contentStr) return $content;
    Cms::getOutputStrategy()->addJsFile($this->pluginDir."/".get_class($this).".js");
    Cms::getOutputStrategy()->addJs("EmailBreaker.init({
      at: '$at',
      dot: '$dot'
    });");
    $newContent = new HTMLPlus();
    $newContent->loadXml($contentStr);
    return $newContent;
  }

}

?>