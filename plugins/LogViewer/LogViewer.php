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
    global $cms;
    #$cms->getOutputStrategy()->addCssFile($this->getDir() . '/LogViewer.css');
    #$cms->getOutputStrategy()->addJsFile($this->getDir() . '/LogViewer.js', 100, "body");
    $f = LOG_FOLDER ."/". $_GET[get_class($this)] . ".log";
    if(!is_file($f)) $f = LOG_FOLDER ."/". date('Ymd') . ".log";
    if(!is_file($f)) new LoggerException("Unable to find '$f'");
    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("logviewer-content", file_get_contents($f));
    return $newContent;
  }

}

?>
