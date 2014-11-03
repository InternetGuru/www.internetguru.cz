<?php

class LogViewer extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $err = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this,3);
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    if(!isset($_GET[get_class($this)])) {
      $subject->detach($this);
    }
  }

  public function getContent(HTMLPlus $content) {
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
    $newContent->insertVar("errors", $this->err);
    if(!is_null($f)) $newContent->insertVar("content", $this->file_get_contents($f));
    return $newContent;
  }

  private function file_get_contents($file) {
    if(substr($file,-4) != ".zip") return file_get_contents($file);
    return readZippedFile($file,substr(pathinfo($file,PATHINFO_BASENAME),0,-4));
  }

  private function getLogFile($fileName=null) {
    return $this->getFile($fileName,LOG_FOLDER,date('Ymd'),"log");
  }

  private function getVersionFile($fileName=null) {
    $v = explode(".",CMS_VERSION);
    return $this->getFile($fileName,CMS_FOLDER ."/". VER_FOLDER,$v[0],"ver");
  }

  private function getFile($fileName,$dir,$defaultName,$ext) {
    $f = "$fileName.$ext";
    if(is_file("$dir/$f")) return "$dir/$f";
    if(is_file("$dir/$f.zip")) return "$dir/$f.zip";
    if(strlen($fileName)) $this->err[] = "File '$f' not found";
    $f = "$defaultName.$ext";
    $zip = "";
    if(!is_file("$dir/$f")) $zip = ".zip";
    if(is_file("$dir/$f$zip")) {
      $this->err[] = "Showing default file '$f'";
      return "$dir/$f$zip";
    }
    $this->err[] = "Default file '$f' not found";
    return null;
  }

}

?>
