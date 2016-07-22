<?php

namespace IGCMS\Plugins;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMElementPlus;

class DocList {
  private $id;
  private $class;
  private $path;
  private $kw;
  private $wrapper;
  private $sortby;
  private $rsort;
  private $skip;
  private $limit;
  const DEFAULT_SORTBY = "ctime";

  public function __construct(DOMElementPlus $doclist, DOMElementPlus $pattern = null) {
    $this->id = $doclist->getRequiredAttribute("id");
    $this->class = "agregator ".$this->id;
    $this->path = $doclist->getAttribute("path");
    $this->kw = $doclist->getAttribute("kw");
    $this->wrapper = $doclist->getAttribute("wrapper");
    $this->rsort = $doclist->hasAttribute("rsort");
    if($this->rsort) {
      $this->sortby = $doclist->getAttribute("rsort");
    } else {
      $this->sortby = $doclist->getAttribute("sort");
    }
    $this->skip = $doclist->hasAttribute("skip");
    if(!is_numeric($this->skip)) $this->skip = 0;
    $this->limit = $doclist->hasAttribute("limit");
    if(!is_numeric($this->limit)) $this->limit = 0;
    if(is_null($pattern)) $pattern = $doclist;
    $this->createVars();
  }

  private function createVars() {
    $fileIds = array();
    $userKw = preg_split("/ *, */", $this->kw);
    $userKw = array_filter($userKw, function($value) { return $value !== ''; });
    foreach(HTMLPlusBuilder::getFileToId() as $file => $id) {
      if(strpos($file, PLUGINS_DIR."/".basename(__DIR__)."/".$this->path."/") !== 0) continue;
      if(count($userKw)) {
        $docKw = preg_split("/ *, */", HTMLPlusBuilder::getIdToKw($id));
        if(array_diff($userKw, $docKw)) continue;
      }
      $fileIds[$file] = $id;
    }
    var_dump($this->id);
    var_dump($fileIds);
    return;
    $vars = array();
    foreach(scandir($dir) as $file) {
      if(pathinfo($file, PATHINFO_EXTENSION) != "html") continue;
      # todo: kw
      $filePath = "$dir/$file";
      try {
        #$id = HTMLPlusBuilder::register($filePath, null, $subDir);
        $vars[$filePath] = HTMLPlusBuilder::getIdToAll($id);
        $vars[$filePath]["fileToMtime"] = HTMLPlusBuilder::getFileToMtime($filePath);
        $vars[$filePath]["parentid"] = $subDir;
        $vars[$filePath]["prefixid"] = $subDir;
        $vars[$filePath]["file"] = $filePath;
        $vars[$filePath]["link"] = $id;
        $vars[$filePath]['editlink'] = "";
        if(Cms::isSuperUser()) {
          $vars[$filePath]['editlink'] = "<a href='?Admin=$filePath' title='$filePath' class='flaticon-drawing3'>".$this->edit."</a>";
        }
        $this->vars[$filePath] = $vars[$filePath];
      } catch(Exception $e) {
        Logger::critical($e->getMessage());
        continue;
      }
    }
    return $vars;
  }



}