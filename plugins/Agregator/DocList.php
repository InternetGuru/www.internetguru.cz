<?php

namespace IGCMS\Plugins\Agregator;
use DateTime;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;

class DocList extends AgregatorList {
  private $kw;
  const DEFAULT_SORTBY = "ctime";
  const DEFAULT_RSORT = true;

  public function __construct(DOMElementPlus $doclist, DOMElementPlus $pattern = null) {
    parent::__construct($doclist, self::DEFAULT_SORTBY, self::DEFAULT_RSORT);
    $this->kw = $doclist->getAttribute("kw");
    $vars = $this->createVars();
    if(is_null($pattern)) $pattern = $doclist;
    $list = $this->createList($pattern, $vars);
    Cms::setVariable($this->id, $list);
  }

  private function createVars() {
    $fileIds = array();
    $somethingFound = false;
    $userKw = preg_split("/ *, */", $this->kw);
    $userKw = array_filter($userKw, function($value) { return $value !== ''; });
    $dirPrefix = PLUGINS_DIR."/".basename(__DIR__)."/".(strlen($this->path) ? $this->path."/" : "");
    foreach(HTMLPlusBuilder::getFileToId() as $file => $id) {
      if(strpos($file, $dirPrefix) !== 0) continue;
      $somethingFound = true;
      if(count($userKw)) {
        $docKw = preg_split("/ *, */", HTMLPlusBuilder::getIdToKw($id));
        if(array_diff($userKw, $docKw)) continue;
      }
      $fileIds[$file] = $id;
    }
    if(empty($fileIds)) {
      if(!$somethingFound) throw new Exception(sprintf(_("No documents registered in '%s'"), $dirPrefix));
      throw new Exception(sprintf(_("No files matching attribute kw '%s'"), $this->kw));
    }
    $vars = array();
    $date = new DateTime();
    foreach($fileIds as $file => $id) {
      try {
        $vars[$file] = HTMLPlusBuilder::getIdToAll($id);
        $vars[$file]["fileToMtime"] = HTMLPlusBuilder::getFileToMtime($file);
        $date->setTimeStamp($vars[$file]["fileToMtime"]);
        $vars[$file]["mtime"] = $date->format(DateTime::W3C);
        $vars[$file]["file"] = $file;
        $vars[$file]["link"] = $id;
        $vars[$file]["editlink"] = "";
        if(Cms::isSuperUser()) {
          $vars[$file]["editlink"] = "<a href='?Admin=$file' title='$file' class='flaticon-drawing3'>"._("Edit")."</a>";
        }
      } catch(Exception $e) {
        Logger::critical($e->getMessage());
        continue;
      }
    }
    return $vars;
  }

}