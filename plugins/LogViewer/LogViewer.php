<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\GetContentStrategyInterface;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class LogViewer
 * @package IGCMS\Plugins
 */
class LogViewer extends Plugin implements SplObserver, GetContentStrategyInterface {
  /**
   * @var array
   */
  private $usrFiles;
  /**
   * @var array
   */
  private $sysFiles;
  /**
   * @var array
   */
  private $emlFiles;
  /**
   * @var array
   */
  private $histFiles;

  /**
   * LogViewer constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 70);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    if (!Cms::isSuperUser() || !isset($_GET[$this->className])) {
      $subject->detach($this);
    }
    if ($subject->getStatus() != STATUS_INIT) {
      return;
    }
    $this->requireActiveCms();
    $this->usrFiles = $this->getFiles(LOG_FOLDER, 15, "usr.log");
    $this->sysFiles = $this->getFiles(LOG_FOLDER, 15, "sys.log");
    $this->emlFiles = $this->getFiles(LOG_FOLDER, 15, "eml.log");
    $this->histFiles = $this->getFiles(CMS_FOLDER, 15, "md");
  }

  /**
   * @param string $dir
   * @param int $limit
   * @param string|null $ext
   * @return array
   */
  private function getFiles ($dir, $limit = 0, $ext = null) {
    $files = [];
    foreach (scandir($dir, SCANDIR_SORT_DESCENDING) as $file) {
      if (!is_file("$dir/$file")) {
        continue;
      }
      $fileId = (substr($file, -4) == ".zip") ? substr($file, 0, -4) : $file;
      if (!is_null($ext)
        && substr($fileId, strpos($fileId, ".") + 1) != $ext
        && pathinfo($fileId, PATHINFO_EXTENSION) != $ext
      ) {
        continue;
      }
      $files[$fileId] = "$dir/$file";
      if (count($files) == $limit) {
        break;
      }
    }
    return $files;
  }

  /**
   * @return HTMLPlus
   * @throws Exception
   */
  public function getContent () {
    $fName = $_GET[$this->className];
    try {
      $fPath = $this->getCurFilePath($fName);
      $vars["content"] = htmlspecialchars($this->fileGetContents($fPath));
    } catch (Exception $exc) {
      Logger::user_warning($exc->getMessage());
    }
    $content = self::getHTMLPlus();
    $vars["cur_file"] = $fName;
    $usrFiles = $this->makeLink($this->usrFiles);
    $vars["usr_files"] = empty($usrFiles) ? null : $usrFiles;
    $sysFiles = $this->makeLink($this->sysFiles);
    $vars["sys_files"] = empty($sysFiles) ? null : $sysFiles;
    $emlFiles = $this->makeLink($this->emlFiles);
    $vars["eml_files"] = empty($emlFiles) ? null : $emlFiles;
    $histFiles = $this->makeLink($this->histFiles);
    $vars["history_file"] = empty($histFiles) ? null : $histFiles;
    $vars["type"] = strpos($fName, "CHANGELOG") === 0 ? "markdown" : "accesslog";
    $content->processVariables($vars);
    return $content;
  }

  /**
   * @param string $fName
   * @return string
   * @throws Exception
   */
  private function getCurFilePath ($fName) {
    switch ($fName) {
      case "CHANGELOG":
        $this->redirTo(CMS_CHANGELOG_FILENAME);
      case "":
      case "usr":
        if (empty($this->usrFiles)) {
          $this->redirTo(CMS_CHANGELOG_FILENAME);
        }
        reset($this->usrFiles);
        $this->redirTo(key($this->usrFiles));
      case "sys":
        if (empty($this->sysFiles)) {
          $this->redirTo(CMS_CHANGELOG_FILENAME);
        }
        reset($this->sysFiles);
        $this->redirTo(key($this->sysFiles));
      case "eml":
        if (empty($this->emlFiles)) {
          $this->redirTo(CMS_CHANGELOG_FILENAME);
        }
        reset($this->emlFiles);
        $this->redirTo(key($this->emlFiles));
      default:
        if (is_file(LOG_FOLDER."/$fName")) {
          return LOG_FOLDER."/$fName";
        }
        if (is_file(LOG_FOLDER."/$fName.zip")) {
          return LOG_FOLDER."/$fName.zip";
        }
        if (array_key_exists($fName, $this->histFiles)) {
          return $this->histFiles[$fName];
        }
        throw new Exception (sprintf(_("File or extension '%s' not found"), $fName));
    }
  }

  /**
   * @param string $fName
   * @throws Exception
   */
  private function redirTo ($fName) {
    redir_to(build_local_url(["path" => get_link(), "query" => $this->className."=$fName"]));
  }

  /**
   * @param string $file
   * @return bool|string
   * @throws Exception
   */
  private function fileGetContents ($file) {
    if (substr($file, -4) != ".zip") {
      return file_get_contents($file);
    } else {
      return read_zip($file, substr(pathinfo($file, PATHINFO_BASENAME), 0, -4));
    }
  }

  /**
   * @param array $array
   * @return array
   */
  private function makeLink (Array $array) {
    $links = [];
    foreach ($array as $name => $path) {
      $links[] = "<a href='".get_link()."?".$this->className."=$name'>$name</a>";
    }
    return $links;
  }

}
