<?php

#todo: files list, next/prev

class LogViewer extends Plugin implements SplObserver, ContentStrategyInterface {
  const DEBUG = false;
  private $logFiles;
  private $verFiles;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 5);
    if(self::DEBUG) new Logger("DEBUG");
  }

  public function update(SplSubject $subject) {
    if(!isset($_GET[get_class($this)])) {
      $subject->detach($this);
    }
    if($subject->getStatus() != STATUS_INIT) return;
    $this->logFiles = $this->getFiles(LOG_FOLDER, 15);
    $this->verFiles = $this->getFiles(VER_FOLDER);
  }

  public function getContent(HTMLPlus $content) {
    $fPath = null;
    $fName = strlen($_GET[get_class($this)]) ? $_GET[get_class($this)] : "log";
    switch($fName) {
      case "ver":
      $fPath = current($this->verFiles);
      $fName = key($this->verFiles);
      break;
      case "log":
      $fPath = current($this->logFiles);
      $fName = key($this->logFiles);
      break;
      default:
      $fPath = $this->getFilePath($fName);
      if(!is_null($fPath)) break;
      Cms::addMessage(sprintf(_("File or extension '%s' not found"), $fName), Cms::MSG_ERROR);
      $fPath = current($this->logFiles);
      $fName = key($this->logFiles);
    }

    $newContent = $this->getHTMLPlus();
    $vars["cur_file"] = $fName;
    $vars["log_files"] = $this->makeLink($this->logFiles);
    $vars["ver_files"] = $this->makeLink($this->verFiles);

    if(!is_null($fPath)) {
      $vars["content"] = htmlspecialchars($this->file_get_contents($fPath));
    }
    $newContent->processVariables($vars);
    return $newContent;
  }

  private function makeLink(Array $array) {
    $links = array();
    foreach($array as $name => $path) {
      $links[] = "<a href='".getCurLink()."?".get_class($this)."=$name'>$name</a>";
    }
    return $links;
  }

  private function file_get_contents($file) {
    if(substr($file, -4) != ".zip") return file_get_contents($file);
    return readZippedFile($file, substr(pathinfo($file, PATHINFO_BASENAME), 0, -4));
  }

  private function getFilePath($fName) {
    if(is_file(LOG_FOLDER."/$fName")) return LOG_FOLDER."/$fName";
    if(is_file(VER_FOLDER."/$fName")) return VER_FOLDER."/$fName";
    if(is_file(LOG_FOLDER."/$fName.zip")) return LOG_FOLDER."/$fName.zip";
    if(is_file(VER_FOLDER."/$fName.zip")) return VER_FOLDER."/$fName.zip";
    return null;
  }

  private function getFiles($dir, $limit=0) {
    $files = array();
    foreach(scandir($dir, SCANDIR_SORT_DESCENDING) as $f) {
      if(!is_file("$dir/$f")) continue;
      $id = (substr($f, -4) == ".zip") ? substr($f, 0, -4) : $f;
      $files[$id] = "$dir/$f";
      if(count($files) == $limit) break;
    }
    return $files;
  }

}

?>
