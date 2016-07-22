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
# register recursive from root...

class Agregator extends Plugin implements SplObserver, GetContentStrategyInterface {
  private $vars = array();  // filePath => fileInfo(?)
  private $docinfo = array();
  private $currentSubdir = null;
  private $currentFilepath = null;
  private $edit;
  private $cfg;
  private static $sortKey;
  private static $reverse;
  const DEBUG = true;
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
    $this->registerFiles(CMS_FOLDER);
    $this->registerFiles(ADMIN_FOLDER);
    $this->registerFiles(USER_FOLDER);
    $this->createLists();
  }

  private function createLists() {
    $docLists = array();
    $docListElements = array();
    foreach($this->cfg->documentElement->childNodes as $child) {
      if($child->nodeType != XML_ELEMENT_NODE) continue;
      try {
        switch($child->nodeName) {
          case "doclist":
          $id = $child->getRequiredAttribute("id");
          $docListRef = $child->getAttribute("doclist");
          if(!strlen($docListRef)) {
            $docLists[$id] = new DocList($child);
            $docListElements[$id] = $child;
            continue;
          }
          if(!array_key_exists($docListRef, $docListElements)) {
            throw new Exception(sprintf(_("Reference id '%s' not found"), $docListRef));
          }
          $docLists[$id] = new DocList($child, $docListElements[$docListRef]);
          #break;
          #case "imglist":
        }
      } catch(Exception $e) {
        Logger::user_warning($e->getMessage());
      }
    }
  }

  public function getContent() {
    $file = HTMLPlusBuilder::getCurFile();
    if(is_null($file) || !array_key_exists($file, $this->vars)) return null;
    Cms::getOutputStrategy()->addTransformation($this->pluginDir."/Agregator.xsl");
    return HTMLPlusBuilder::getFileToDoc($file);
  }

  private function registerFiles($workingDir, $folder=null) {
    $cwd = "$workingDir/".$this->pluginDir."/$folder";
    if(!is_dir($cwd)) return;
    switch($workingDir) {
      case CMS_FOLDER:
      if(is_dir(ADMIN_FOLDER."/".$this->pluginDir."/$folder")
        && !file_exists(ADMIN_FOLDER."/".$this->pluginDir."/.$folder")) return;
      case ADMIN_FOLDER:
      if(is_dir(USER_FOLDER."/".$this->pluginDir."/$folder")
        && !file_exists(USER_FOLDER."/".$this->pluginDir."/.$folder")) return;
    }
    foreach(scandir($cwd) as $file) {
      if(strpos($file, ".") === 0) continue;
      if(file_exists("$cwd/.$file")) continue;
      $filePath = is_null($folder) ? $file : "$folder/$file";
      if(is_dir("$cwd/$file")) {
        $this->registerFiles($workingDir, $filePath);
        continue;
      }
      if(pathinfo($file, PATHINFO_EXTENSION) != "html") continue;
      HTMLPlusBuilder::register($this->pluginDir."/$filePath", $folder);
    }
  }

  private function createImgList($subDir, Array $files) {
    // $cacheKey = apc_get_key($subDir);
    // $inotify = current($files)."/".(strlen($subDir) ? "$subDir/" : "").INOTIFY;
    // if(is_file($inotify)) $checkSum = filemtime($inotify);
    // else $checkSum = count($files);
    // if(!apc_is_valid_cache($cacheKey, $checkSum)) {
    //   apc_store_cache($cacheKey, $checkSum, $subDir);
    //   $useCache = false;
    // }
    // $alts = $this->buildImgAlts();
    // $vars = $this->buildImgVars($files, $alts, $subDir);
    // if(empty($vars)) return;
    $this->createVars($subDir, $files, $vars, $cacheKey, "imglist", "name", $useCache);
  }

  private function createDocList($subDir, Array $files) {
    // $filePath = findFile($this->pluginDir."/".$this->className.".xml");
    // $cacheKey = apc_get_key($filePath);
    // if(!apc_is_valid_cache($cacheKey, filemtime($filePath))) {
    //   apc_store_cache($cacheKey, filemtime($filePath), $this->pluginDir."/".$this->className.".xml");
    //   $useCache = false;
    // }
    // $vars = $this->getFileVars($subDir, $files, $useCache);
    // if(!count($vars)) return;
    $this->createVars($subDir, $files, $vars, $cacheKey, "doclist", "mtime", $useCache);
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
