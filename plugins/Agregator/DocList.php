<?php

namespace IGCMS\Plugins\Agregator;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Cms;
use DateTime;
use Exception;

class DocList extends AgregatorList {
  private $kw;
  const DEFAULT_SORTBY = "ctime";
  const DEFAULT_RSORT = false;

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
    foreach(HTMLPlusBuilder::getFileToId() as $file => $id) {
      if(strpos($file, PLUGINS_DIR."/".basename(__DIR__)."/".$this->path."/") !== 0) continue;
      $somethingFound = true;
      if(count($userKw)) {
        $docKw = preg_split("/ *, */", HTMLPlusBuilder::getIdToKw($id));
        if(array_diff($userKw, $docKw)) continue;
      }
      $fileIds[$file] = $id;
    }
    if(empty($fileIds)) {
      if(!$somethingFound) throw new Exception(sprintf(_("Path '%s' not found or empty"), $this->path));
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