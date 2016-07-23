<?php

namespace IGCMS\Plugins\Agregator;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Cms;
use DateTime;
use Exception;

class DocList {
  private $id;
  private $class;
  private $path;
  private $kw;
  private $wrapper;
  private $skip;
  private $limit;
  private static $sortby;
  private static $rsort;

  const DEFAULT_SORTBY = "ctime";

  public function __construct(DOMElementPlus $doclist, DOMElementPlus $pattern = null) {
    $this->id = $doclist->getRequiredAttribute("id");
    $this->class = "agregator ".$this->id;
    $this->path = $doclist->getAttribute("path");
    $this->kw = $doclist->getAttribute("kw");
    $this->wrapper = $doclist->getAttribute("wrapper");
    self::$rsort = $doclist->hasAttribute("rsort");
    if(self::$rsort) {
      self::$sortby = $doclist->getAttribute("rsort");
    } else {
      self::$sortby = $doclist->getAttribute("sort");
    }
    if(!strlen(self::$sortby)) self::$sortby = self::DEFAULT_SORTBY;
    $this->skip = $doclist->hasAttribute("skip");
    if(!is_numeric($this->skip)) $this->skip = 0;
    $this->limit = $doclist->hasAttribute("limit");
    if(!is_numeric($this->limit)) $this->limit = 0;
    if(is_null($pattern)) $pattern = $doclist;
    try {
      $vars = $this->createVars();
    } catch(Exception $e) {
      Logger::user_warning(sprintf(_("Doclist '%s' not created: %s"), $this->id, $e->getMessage()));
      return;
    }
    $this->sort($vars);
    $list = $this->getDOM($pattern, $vars);
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

  private function getDOM(DOMElementPlus $pattern, Array $vars) {
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    if(strlen($this->wrapper))
      $root = $root->appendChild($doc->createElement($this->wrapper));
    if(strlen($this->class)) $root->setAttribute("class", $this->class);
    $i = 0;
    foreach($vars as $k => $v) {
      if($i++ < $this->skip) continue;
      if($this->limit > 0 && $i > $this->skip + $this->limit) break;
      $list = $root->appendChild($doc->importNode($pattern, true));
      $list->processVariables($v, array(), true);
      $list->stripTag();
    }
    return $doc;
  }

  private function sort(Array &$vars) {
    if(strlen(self::$sortby) && !array_key_exists(self::$sortby, current($vars))) {
      Logger::user_warning(sprintf(_("Sort variable %s not found; using default"), self::$sortby));
      self::$sortby = self::DEFAULT_SORTBY;
    }
    uasort($vars, array("IGCMS\Plugins\Agregator\DocList", "cmp"));
  }

  private static function cmp($a, $b) {
    if($a[self::$sortby] == $b[self::$sortby]) return 0;
    $val = ($a[self::$sortby] < $b[self::$sortby]) ? -1 : 1;
    if(self::$rsort) return -$val;
    return $val;
  }


}