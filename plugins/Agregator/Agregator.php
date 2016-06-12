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
  const APC = false;
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

  private function insertDocInfo(HTMLPlus $doc, Array $info) {
    $vars = array();
    foreach($this->cfg->getElementsByTagName("var") as $var) {
      $vars[$var->getAttribute("id")] = $var;
    }
    foreach($doc->getElementsByTagName("h") as $h) {
      $ul = $this->createDocInfo($h, $vars, $info);
      if(!$ul->childNodes->length) continue;
      $ul->processVariables($info, array(), true);
      if($h->parentNode->nodeName == "body") {
        $wrapper = $doc->createElement("var");
        $wrapper->appendChild($ul);
        Cms::setVariable("docinfo", $wrapper);
        continue;
      }
      $e = $h->nextElement;
      while(!is_null($e)) {
        if($e->nodeName == "h") break;
        $e = $e->nextElement;
      }
      if(is_null($e)) $h->parentNode->appendChild($ul);
      else $h->parentNode->insertBefore($ul, $e);
    }
  }

  private function createDocInfo(DOMElementPlus $h, Array $vars, Array $info) {
    $doc = $h->ownerDocument;
    $ul = $doc->createElement("ul");
    if($h->parentNode->nodeName == "body") {
      $ul->setAttribute("class", "docinfo nomultiple global");
      $li = $ul->appendChild($doc->createElement("li"));
      // global author & creation
      $li->setAttribute("class", "creation");
      foreach($vars["creation"]->childNodes as $n) {
        $li->appendChild($doc->importNode($n, true));
      }
      // global modification
      if(substr($info["ctime"], 0, 10) != substr($info["mtime"], 0, 10)) {
        $li = $ul->appendChild($doc->createElement("li"));
        $li->setAttribute("class", "modified");
        foreach($vars["modified"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      // global responsibility
      if($h->hasAttribute("resp")) {
        $li = $ul->appendChild($doc->createElement("li"));
        $li->setAttribute("class", "responsible");
        foreach($vars["responsible"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      // edit link
      if(Cms::isSuperUser()) {
        $li = $ul->appendChild($doc->createElement("li"));
        $li->setAttribute("class", "edit noprint");
        $a = $li->appendChild($doc->createElement("a", $this->edit));
        $a->setAttribute("href", "?Admin=".$this->currentFilepath);
        $a->setAttribute("title", $this->currentFilepath);
      }
    } else {
      $ul->setAttribute("class", "docinfo nomultiple partial");
      $partinfo = array();
      // local author (?)
      // local responsibility (?)
      // local creation
      if($h->hasAttribute("ctime") && substr($this->docinfo["ctime"], 0, 10) != substr($h->getAttribute("ctime"), 0, 10)) {
        $partinfo["ctime"] = $h->getAttribute("ctime");
        $li = $ul->appendChild($doc->createElement("li"));
        foreach($vars["part_created"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      // local modification
      if($h->hasAttribute("mtime") && substr($this->docinfo["mtime"], 0, 10) != substr($h->getAttribute("mtime"), 0, 10)) {
        $partinfo["mtime"] = $h->getAttribute("mtime");
        $li = $ul->appendChild($doc->createElement("li"));
        foreach($vars["part_modified"]->childNodes as $n) {
          $li->appendChild($doc->importNode($n, true));
        }
      }
      $ul->processVariables($partinfo, array(), true);
    }
    return $ul;
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
    $useCache = self::APC;
    if(is_file($inotify)) $checkSum = filemtime($inotify);
    else $checkSum = count($files);
    if(!apc_is_valid_cache($cacheKey, $checkSum)) {
      apc_store_cache($cacheKey, $checkSum, $subDir);
      $useCache = false;
    }
    $alts = $this->buildImgAlts();
    $vars = $this->buildImgVars($files, $alts, $subDir);
    if(empty($vars)) return;
    foreach($this->cfg->documentElement->childElementsArray as $image) {
      if($image->nodeName != "image") continue;
      try {
        $id = $image->getRequiredAttribute("id");
      } catch(Exception $e) {
        Logger::user_warning($e->getMessage());
        continue;
      }
      $vName = $id.($subDir == "" ? "" : "_".str_replace("/", "_", $subDir));
      $cacheKey = apc_get_key($vName);
      if($useCache && apc_exists($cacheKey)) {
        $doc = new DOMDocumentPlus();
        $doc->loadXML(apc_fetch($cacheKey));
        Cms::setVariable($vName, $doc->documentElement);
        continue;
      }
      $this->sort($vars, $image, "name", false);
      $vValue = $this->getDOM($vars, $image);
      apc_store_cache($cacheKey, $vValue->saveXML(), $vName);
      Cms::setVariable($vName, $vValue->documentElement);
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
        $id = HTMLPlusBuilder::register($filePath, $subDir, $subDir);
        $vars[$filePath] = HTMLPlusBuilder::getIdToAll($id);
        $vars[$filePath]["filemtime"] = HTMLPlusBuilder::getFileMtime($filePath);
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
      if(apc_is_valid_cache($cacheKey, $vars[$filePath]["filemtime"])) continue;
      #var_dump($vars[$filePath]);
      apc_store_cache($cacheKey, $vars[$filePath]["filemtime"], $filePath);
      $useCache = false;
    }
    return $vars;
  }

  private function createCmsVars($subDir, $files) {
    $useCache = self::APC;
    $vars = $this->getFileVars($subDir, $files, $useCache);
    if(!count($vars)) return;
    $className = (new \ReflectionClass($this))->getShortName();
    $filePath = findFile($this->pluginDir."/".$className.".xml");
    $cacheKey = apc_get_key($filePath);
    if(!apc_is_valid_cache($cacheKey, filemtime($filePath))) {
      apc_store_cache($cacheKey, filemtime($filePath), $this->pluginDir."/".$className.".xml");
      $useCache = false;
    }
    foreach($this->cfg->documentElement->childElementsArray as $template) {
      if($template->nodeName != "html") continue;
      try {
        $id = $template->getRequiredAttribute("id");
      } catch(Exception $e) {
        Logger::user_warning($e->getMessage());
        continue;
      }
      $vName = $id.($subDir == "" ? "" : "_".str_replace("/", "_", $subDir));
      $cacheKey = apc_get_key($vName);
      // use cache
      if($useCache) {
        $sCache = $this->getSubDirCache($cacheKey);
        if(!is_null($sCache)) {
          $doc = new DOMDocumentPlus();
          $doc->loadXML($sCache["value"]);
          Cms::setVariable($sCache["name"], $doc->documentElement);
          continue;
        }
      }
      $this->sort($vars, $template, "ctime", true);
      try {
        $vValue = $this->getDOM($vars, $template);
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

  private function getDOM(Array $vars, DOMElementPlus $html) {
    $items = $html->childElementsArray;
    $id = $html->getAttribute("id");
    $class = $html->getAttribute("class");
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    if(strlen($html->getAttribute("wrapper")))
      $root = $root->appendChild($doc->createElement($html->getAttribute("wrapper")));
    if(strlen($class)) $root->setAttribute("class", $class);
    $nonItemElement = false;
    $patterns = array();
    foreach($items as $item) {
      if($item->nodeName != "item") {
        $nonItemElement = true;
        continue;
      }
      if($item->hasAttribute("since"))
        $patterns[$item->getAttribute("since")-1] = $item;
      else $patterns[] = $item;
    }
    if($nonItemElement) Logger::user_warning(sprintf(_("Redundant element(s) found in %s"), $id));
    if(empty($patterns)) throw new Exception(_("No item element found"));
    $i = -1;
    $pattern = null;
    foreach($vars as $k => $v) {
      $i++;
      if(isset($patterns[$i])) $pattern = $patterns[$i];
      if(is_null($pattern) || !$pattern->childNodes->length) continue;
      $item = $root->appendChild($doc->importNode($pattern, true));
      $item->processVariables($v, array(), true);
      $item->stripTag();
    }
    return $doc;
  }

}

?>
