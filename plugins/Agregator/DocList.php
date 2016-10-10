<?php

namespace IGCMS\Plugins\Agregator;
use DateTime;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;

/**
 * Class DocList
 * @package IGCMS\Plugins\Agregator
 */
class DocList extends AgregatorList {
  /**
   * @var string
   */
  private $kw;
  /**
   * @var string
   */
  const DEFAULT_SORTBY = "ctime";
  /**
   * @var bool
   */
  const DEFAULT_RSORT = true;

  /**
   * DocList constructor.
   * @param DOMElementPlus $doclist
   * @param DOMElementPlus|null $pattern
   */
  public function __construct(DOMElementPlus $doclist, DOMElementPlus $pattern=null) {
    parent::__construct($doclist, self::DEFAULT_SORTBY, self::DEFAULT_RSORT);
    $this->kw = $doclist->getAttribute("kw");
    $vars = $this->createVars();
    if(is_null($pattern)) $pattern = $doclist;
    $list = $this->createList($pattern, $vars);
    Cms::setVariable($this->id, $list);
  }

  /**
   * @return array
   * @throws Exception
   */
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
        $date->setTimestamp($vars[$file]["fileToMtime"]);
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