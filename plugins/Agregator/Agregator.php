<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\GetContentStrategyInterface;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use Exception;
use SplObserver;
use SplSubject;
use DateTime;

# TODO:
# registr souborů: v id celá cesta, v hodnotě pole štítků

class Agregator extends Plugin implements SplObserver, GetContentStrategyInterface {
  private $vars = array();  // filePath => fileInfo(?)
  private $docinfo = array();
  private $currentSubdir = null;
  private $currentFilepath = null;
  private $edit;
  private $cfg;
  private static $sortKey;
  private static $reverse;
  const DEBUG = false;
  const APC_PREFIX = "1";

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 2);
    $this->edit = _("Edit");
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
    $this->cfg = $this->getXML();
    $curLink = getCurLink();
    try {
      mkdir_plus(USER_FOLDER."/".$this->pluginDir);
      mkdir_plus(ADMIN_FOLDER."/".$this->pluginDir);
      $list = array();
      $this->createList(USER_FOLDER, $this->pluginDir, $list, "html");
      $this->createList(ADMIN_FOLDER, $this->pluginDir, $list, "html");
      foreach($list as $subDir => $files) {
        $this->createCmsVars($subDir, $files);
      }
      $list = array();
      $this->createList("", FILES_FOLDER, $list);
      #$this->createFilesVar(FILES_FOLDER);
      foreach($list as $subDir => $files) {
        $this->createImgVar($subDir, $files);
      }
    } catch(Exception $e) {
      Logger::critical($e->getMessage());
      return;
    }
  }

  public function getContent() {
    $file = HTMLPlusBuilder::getCurFile();
    if(is_null($file) || !array_key_exists($file, $this->vars)) return null;
    Cms::getOutputStrategy()->addTransformation($this->pluginDir."/Agregator.xsl");
    return HTMLPlusBuilder::build($file, $this->vars[$file]["parentid"], $this->vars[$file]["prefixid"]);
  }

  private function createList($prefixDir, $rootDir, Array &$list, $ext=null, $subDir=null) {
    if(isset($list[$subDir])) return; // user dir (with at least one file) beats admin dir
    $workingDir = "$prefixDir/$rootDir".(strlen($subDir) ? "/$subDir" : "");
    if(!is_dir($workingDir)) return;
    foreach(scandir($workingDir) as $f) {
      if(strpos($f, ".") === 0) continue;
      if(is_dir("$workingDir/$f")) {
        $this->createList($prefixDir, $rootDir, $list, $ext, is_null($subDir) ? $f : "$subDir/$f");
        continue;
      }
      if(!is_null($ext) && pathinfo($f, PATHINFO_EXTENSION) != $ext) continue;
      if(is_file("$workingDir/.$f")) continue;
      $list[$subDir][$f] = $rootDir;
    }
  }

  private function createImgVar($subDir, Array $files) {
    $cacheKey = apc_get_key($subDir);
    $inotify = current($files)."/".(strlen($subDir) ? "$subDir/" : "").INOTIFY;
    if(is_file($inotify)) $checkSum = filemtime($inotify);
    else $checkSum = count($files);
    if(!apc_is_valid_cache($cacheKey, $checkSum)) {
      apc_store_cache($cacheKey, $checkSum, $subDir);
      $useCache = false;
    }
    $alts = $this->buildImgAlts();
    $vars = $this->buildImgVars($files, $alts, $subDir);
    if(empty($vars)) return;
    $this->createVars($subDir, $files, $vars, $cacheKey, "imglist", "name", $useCache);
  }

  private function createCmsVars($subDir, Array $files) {
    $filePath = findFile($this->pluginDir."/".$this->className.".xml");
    $cacheKey = apc_get_key($filePath);
    if(!apc_is_valid_cache($cacheKey, filemtime($filePath))) {
      apc_store_cache($cacheKey, filemtime($filePath), $this->pluginDir."/".$this->className.".xml");
      $useCache = false;
    }
    $vars = $this->getFileVars($subDir, $files, $useCache);
    if(!count($vars)) return;
    $this->createVars($subDir, $files, $vars, $cacheKey, "doclist", "mtime", $useCache);
  }

  private function createVars($subDir, Array $files, Array $vars, $cacheKey, $cfgElName, $sort, $useCache) {
    $useCache = !self::DEBUG;
    foreach($this->cfg->documentElement->childElementsArray as $child) {
      if($child->nodeName != $cfgElName) continue;
      try {
        $id = $child->getRequiredAttribute("id");
      } catch(Exception $e) {
        Logger::user_warning($e->getMessage());
        continue;
      }
      $vName = $id.($subDir == "" ? "" : "_".str_replace("/", "_", $subDir));
      $cacheKey = apc_get_key($vName);
      if($useCache && apc_exists($cacheKey)) {
        $sCache = apc_fetch($cacheKey);
        if(!is_null($sCache)) {
          $doc = new DOMDocumentPlus();
          $doc->loadXML($sCache["value"]);
          Cms::setVariable($sCache["name"], $doc->documentElement);
          continue;
        }
      }
      $this->sort($vars, $child, $sort, false);
      try {
        $vValue = $this->getDOM($vars, $child);
        Cms::setVariable($vName, $vValue->documentElement);
        $var = array(
          "name" => $vName,
          "value" => $vValue->saveXML(),
        );
        apc_store_cache($cacheKey, $var, $vName);
      } catch(Exception $e) {
        Logger::critical($e->getMessage());
      }
    }
  }

  private function buildImgAlts() {
    $alts = array();
    foreach($this->cfg->documentElement->childElementsArray as $alt) {
      if($alt->nodeName != "alt") continue;
      try {
        $for = $alt->getRequiredAttribute("for");
      } catch(Exception $e) {
        Logger::user_warning($e->getMessage());
        continue;
      }
      $alts[$for] = $alt->nodeValue;
    }
    return $alts;
  }

  private function buildImgVars(Array $files, Array $alts, $subDir) {
    $vars = array();
    foreach($files as $fileName => $rootDir) {
      if(strlen($subDir)) $fileName = "$subDir/$fileName";
      $filePath = "$rootDir/$fileName";
      $mimeType = getFileMime($filePath);
      if($mimeType != "image/svg+xml" && strpos($mimeType, "image/") !== 0) continue;
      $v = array();
      $v["name"] = $fileName;
      $v["type"] = $mimeType;
      $v["mtime"] = filemtime($filePath);
      $v["url"] = FILES_DIR."/$fileName"; // $filePath ???
      $v["url-images"] = $v["url"]; // alias for $v["url"]
      $v["url-thumbs"] = FILES_DIR."/thumbs/$fileName";
      $v["url-preview"] = FILES_DIR."/preview/$fileName";
      $v["url-big"] = FILES_DIR."/big/$fileName";
      $v["url-full"] = FILES_DIR."/full/$fileName";
      if(isset($alts[$fileName])) $v["alt"] = $alts[$fileName];
      $vars[$filePath] = $v;
    }
    return $vars;
  }

  private function createFilesVar($rootDir) {
    foreach($this->files as $subDir => $null) {
      $workingDir = $subDir == "" ? $rootDir : "$rootDir/$subDir";
      $doc = new DOMDocumentPlus();
      $root = $doc->appendChild($doc->createElement("root"));
      $ol = $root->appendChild($doc->createElement("ol"));
      foreach($this->files[$subDir] as $f => $null) {
        $li = $ol->appendChild($doc->createElement("li"));
        $a = $li->appendChild($doc->createElement("a"));
        $href = $subDir == "" ? $f : "$subDir/$f";
        $a->setAttribute("href", "/$href");
        $a->nodeValue = $href;
      }
      Cms::setVariable("files".($subDir == "" ? "" : "_".str_replace("/", "_", $subDir)), $root);
    }
  }

  private function getFileVars($subDir, Array $files, &$useCache) {
    $vars = array();
    $cacheKey = apc_get_key($subDir);
    $inotify = current($files)."/".(strlen($subDir) ? "$subDir/" : "").INOTIFY;
    if(!IS_LOCALHOST && is_file($inotify)) $checkSum = filemtime($inotify);
    else $checkSum = count($files); // invalidate cache with different files count
    if(!apc_is_valid_cache($cacheKey, $checkSum)) {
      apc_store_cache($cacheKey, $checkSum, $subDir);
      $useCache = false;
    }
    $date = new DateTime();
    foreach($files as $fileName => $rootDir) {
      $filePath = $rootDir."/".(strlen($subDir) ? "$subDir/" : "").$fileName;
      try {
        $id = HTMLPlusBuilder::register($filePath, null, $subDir);
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
      if(!IS_LOCALHOST && is_file($inotify)) continue;
      $cacheKey = apc_get_key($filePath);
      if(apc_is_valid_cache($cacheKey, $vars[$filePath]["fileToMtime"])) continue;
      #var_dump($vars[$filePath]);
      apc_store_cache($cacheKey, $vars[$filePath]["fileToMtime"], $filePath);
      $useCache = false;
    }
    return $vars;
  }

  private function sort(Array &$vars, DOMElementPlus $template, $defaultSortKey, $defaultReverse) {
    self::$reverse = $defaultReverse;
    self::$sortKey = $defaultSortKey;
    if($template->hasAttribute("sort") || $template->hasAttribute("rsort")) {
      self::$reverse = $template->hasAttribute("rsort");
      $userKey = $template->hasAttribute("sort") ? $template->getAttribute("sort") : $template->getAttribute("rsort");
      if(!array_key_exists($userKey, current($vars))) {
        Logger::user_warning(sprintf(_("Sort variable %s not found; using default"), $userKey));
      } else {
        self::$sortKey = $userKey;
      }
    }
    uasort($vars, array("IGCMS\Plugins\Agregator", "cmp"));
  }

  private function getSubDirCache($cacheKey) {
    if(!apc_exists($cacheKey)) return null;
    return apc_fetch($cacheKey);
  }

  private static function cmp($a, $b) {
    if($a[self::$sortKey] == $b[self::$sortKey]) return 0;
    $val = ($a[self::$sortKey] < $b[self::$sortKey]) ? -1 : 1;
    if(self::$reverse) return -$val;
    return $val;
  }

  private function getDOM(Array $vars, DOMElementPlus $doclist) {
    $id = $doclist->getAttribute("id");
    $class = $doclist->getAttribute("class");
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    if(strlen($doclist->getAttribute("wrapper")))
      $root = $root->appendChild($doc->createElement($doclist->getAttribute("wrapper")));
    if(strlen($class)) $root->setAttribute("class", $class);
    $skip = $doclist->getAttribute("skip");
    if(!is_numeric($skip)) $skip = 0;
    $limit = $doclist->getAttribute("limit");
    if(!is_numeric($limit)) $limit = 0;
    $i = 0;
    foreach($vars as $k => $v) {
      if($i++ < $skip) continue;
      if($limit > 0 && $i > $skip + $limit) break;
      $list = $root->appendChild($doc->importNode($doclist, true));
      $list->processVariables($v, array(), true);
      $list->stripTag();
    }
    return $doc;
  }

}

?>
