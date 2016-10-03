<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\GetContentStrategyInterface;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use SplObserver;
use SplSubject;

class LogViewer extends Plugin implements SplObserver, GetContentStrategyInterface {
  private $usrFiles;
  private $sysFiles;
  private $emlFiles;
  private $curFilePath;
  private $curFileName;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 5);
  }

  public function update(SplSubject $subject) {
    if(!Cms::isSuperUser() || !isset($_GET[$this->className])) $subject->detach($this);
    if($subject->getStatus() != STATUS_INIT) return;
    $this->requireActiveCms();
    $this->usrFiles = $this->getFiles(LOG_FOLDER, 15, "usr.log");
    $this->sysFiles = $this->getFiles(LOG_FOLDER, 15, "sys.log");
    $this->emlFiles = $this->getFiles(LOG_FOLDER, 15, "eml.log");
    $this->histFiles = array(CMS_CHANGELOG_FILENAME => CMS_FOLDER."/".CMS_CHANGELOG_FILENAME);
  }

  public function getContent() {
    $fName = $_GET[$this->className];
    try {
      $fPath = $this->getCurFilePath($fName);
      $vars["content"] = htmlspecialchars($this->file_get_contents($fPath));
    } catch(Exception $e) {
      Logger::user_warning($e->getMessage());
    }
    $content = $this->getHTMLPlus();
    $vars["cur_file"] = $fName;
    $usrFiles = $this->makeLink($this->usrFiles);
    $vars["usr_files"] = empty($usrFiles) ? null : $usrFiles;
    $sysFiles = $this->makeLink($this->sysFiles);
    $vars["sys_files"] = empty($sysFiles) ? null : $sysFiles;
    $emlFiles = $this->makeLink($this->emlFiles);
    $vars["eml_files"] = empty($emlFiles) ? null : $emlFiles;
    $vars["history_file"] = $this->makeLink($this->histFiles);
    $content->processVariables($vars);
    return $content;
  }

  private function getCurFilePath($fName) {
    switch($fName) {
      case CMS_CHANGELOG_FILENAME:
      return $this->histFiles[$fName];
      case "":
      case "usr":
      if(empty($this->usrFiles)) $this->redirTo(CMS_CHANGELOG_FILENAME);
      reset($this->usrFiles);
      $this->redirTo(key($this->usrFiles));
      case "sys":
      if(empty($this->sysFiles)) $this->redirTo(CMS_CHANGELOG_FILENAME);
      reset($this->sysFiles);
      $this->redirTo(key($this->sysFiles));
      case "eml":
      if(empty($this->emlFiles)) $this->redirTo(CMS_CHANGELOG_FILENAME);
      reset($this->emlFiles);
      $this->redirTo(key($this->emlFiles));
      default:
      if(is_file(LOG_FOLDER."/$fName")) return LOG_FOLDER."/$fName";
      if(is_file(LOG_FOLDER."/$fName.zip")) return LOG_FOLDER."/$fName.zip";
      throw new Exception (sprintf(_("File or extension '%s' not found"), $fName));
    }
  }

  private function redirTo($fName) {
    redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => $this->className."=$fName")));
  }

  private function makeLink(Array $array) {
    $links = array();
    foreach($array as $name => $path) {
      $links[] = "<a href='".getCurLink()."?".$this->className."=$name'>$name</a>";
    }
    return $links;
  }

  private function file_get_contents($file) {
    if(substr($file, -4) != ".zip") return file_get_contents($file);
    else return readZippedFile($file, substr(pathinfo($file, PATHINFO_BASENAME), 0, -4));
  }

  private function getFiles($dir, $limit=0, $ext=null) {
    $files = array();
    foreach(scandir($dir, SCANDIR_SORT_DESCENDING) as $f) {
      if(!is_file("$dir/$f")) continue;
      $id = (substr($f, -4) == ".zip") ? substr($f, 0, -4) : $f;
      if(!is_null($ext) && substr($id, strpos($id, ".") + 1) != $ext) continue;
      $files[$id] = "$dir/$f";
      if(count($files) == $limit) break;
    }
    return $files;
  }

}

?>
