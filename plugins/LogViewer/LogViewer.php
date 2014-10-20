<?php

class LogViewer extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;

  public function __construct() {
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    if(isset($_GET["log"])) redirTo(getRoot() . getCurLink() . "?" . get_class($this)
      . (strlen($_GET["log"]) ? "=".$_GET["log"] : "")); // shortcut log to LogViewer
    if(!isset($_GET[get_class($this)])) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() == "preinit") {
      $subject->setPriority($this,3);
    }
    if($subject->getStatus() != "init") return;
    $this->subject = $subject;
  }

  public function getContent(HTMLPlus $content) {
    $cms = $this->subject->getCms();
    #$cms->getOutputStrategy()->addCssFile($this->getDir() . '/LogViewer.css');
    #$cms->getOutputStrategy()->addJsFile($this->getDir() . '/LogViewer.js', 100, "body");

    $newContent = $this->getHTMLPlus();

    return $newContent;
  }

}

?>
