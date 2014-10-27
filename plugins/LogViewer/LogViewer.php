<?php

class LogViewer extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $err = array();

  public function __construct() {
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    #if(isset($_GET["log"])) redirTo(getRoot() . getCurLink() . "?" . get_class($this)
    #  . (strlen($_GET["log"]) ? "=".$_GET["log"] : "")); // shortcut log to LogViewer
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
    #global $cms;
    #$cms->getOutputStrategy()->addCssFile($this->getDir() . '/LogViewer.css');
    #$cms->getOutputStrategy()->addJsFile($this->getDir() . '/LogViewer.js', 100, "body");

    $f = strlen($_GET[get_class($this)]) ? $_GET[get_class($this)] : "log";
    $fArr = explode(".",$f);
    $ext = array_pop($fArr);
    switch($ext) {
      case "ver":
      $f = $this->getVersionFile(implode(".",$fArr));
      break;
      case "log":
      $f = $this->getLogFile(implode(".",$fArr));
      break;
      default:
      $this->getLogFile($f);
      if(empty($this->err)) redirTo("?".get_class($this)."=$f.log");
      $this->err = array();
      $this->err[] = "Unsupported LogViewer extension '$ext'";
      $f = $this->getLogFile();
    }

    $newContent = $this->getHTMLPlus();
    $newContent->insertVar("logviewer-errors", $this->err);
    if(!is_null($f)) $newContent->insertVar("logviewer-content", file_get_contents($f));
    return $newContent;
  }

  private function getLogFile($fileName=null) {
    return $this->getFile($fileName,LOG_FOLDER,date('Ymd'),"log");
  }

  private function getVersionFile($fileName=null) {
    $v = explode(".",CMS_VERSION);
    return $this->getFile($fileName,CMS_FOLDER ."/". VER_FOLDER,$v[0].".".$v[1],"ver");
  }

  private function getFile($fileName,$dir,$defaultName,$ext) {
    $f = "$fileName.$ext";
    if(is_file("$dir/$f")) return "$dir/$f";
    if(strlen($fileName)) $this->err[] = "File '$f' not found";
    $f = "$defaultName.$ext";
    if(is_file("$dir/$f")) {
      $this->err[] = "Showing current file '$f'";
      return "$dir/$f";
    }
    $this->err[] = "Current file '$f' not found";
    return null;
  }

}

?>
