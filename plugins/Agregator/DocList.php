<?php

namespace IGCMS\Plugins\Agregator;

use DateTime;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMBuilder;
use IGCMS\Core\DOMDocumentPlus;
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
  const DEFAULT_SORTBY = "ctime";
  /**
   * @var bool
   */
  const DEFAULT_RSORT = true;
  /**
   * @var int
   */
  const APC_ID = 2;
  /**
   * @var string
   */
  private $keyword;

  /**
   * DocList constructor.
   * @param DOMElementPlus $doclist
   * @param DOMElementPlus|null $pattern
   * @throws Exception
   */
  public function __construct (DOMElementPlus $doclist, DOMElementPlus $pattern = null) {
    parent::__construct($doclist, self::DEFAULT_SORTBY, self::DEFAULT_RSORT);
    $newestCacheMtime = DOMBuilder::getNewestCacheMtime();
    $cacheKey = apc_get_key(__FUNCTION__."/".self::APC_ID."/".$this->listId);
    $cacheExists = apc_exists($cacheKey);
    $cacheUpTodate = false;
    $cache = null;
    $listDoc = null;
    if ($cacheExists) {
      $cache = apc_fetch($cacheKey);
      $cacheUpTodate = $cache["newestCacheMtime"] == $newestCacheMtime;
    }
    if ($cacheUpTodate) {
      $doc = new DOMDocumentPlus();
      $doc->loadXML($cache["data"]);
      $listDoc = $doc;
      $vars = unserialize($cache["vars"]);
    } else {
      $this->keyword = $doclist->getAttribute("kw");
      $vars = $this->createVars();
      if (is_null($pattern)) {
        $pattern = $doclist;
      }
      $listDoc = $this->createList($pattern, $vars);
    }
    if (!$cacheExists || !$cacheUpTodate) {
      $cache = [
        "data" => $listDoc->saveXML($listDoc),
        "vars" => serialize($vars),
        "newestCacheMtime" => $newestCacheMtime,
      ];
      apc_store_cache($cacheKey, $cache, __FUNCTION__);
    }
    $linkParts = explode("/", get_link());
    $curFile = HTMLPlusBuilder::getIdToFile(end($linkParts));
    if (array_key_exists($curFile, $vars)) {
      foreach ($vars[$curFile] as $name => $var) {
        Cms::setVariable($name, $var["value"], $var["cacheable"], "");
      }
    }
    Cms::setVariable($this->listId, $listDoc);
  }

  /**
   * @return array
   * @throws Exception
   */
  private function createVars () {
    $fileIds = [];
    $somethingFound = false;
    $userKw = preg_split("/ *, */", $this->keyword);
    $userKw = array_filter(
      $userKw,
      function($value) {
        return $value !== '';
      }
    );
    $dirPrefix = PLUGINS_DIR."/".basename(__DIR__)."/".(strlen($this->path) ? $this->path."/" : "");
    foreach (HTMLPlusBuilder::getFileToId() as $file => $fileId) {
      if (strpos($file, $dirPrefix) !== 0) {
        continue;
      }
      $somethingFound = true;
      if (count($userKw)) {
        $docKw = preg_split("/ *, */", HTMLPlusBuilder::getIdToKw($fileId));
        if (array_diff($userKw, $docKw)) {
          continue;
        }
      }
      $fileIds[$file] = $fileId;
    }
    if (empty($fileIds)) {
      if (!$somethingFound) {
        throw new Exception(sprintf(_("No documents registered in '%s'"), $dirPrefix));
      }
      throw new Exception(sprintf(_("No files matching attribute kw '%s'"), $this->keyword));
    }
    $vars = [];
    $date = new DateTime();
    foreach ($fileIds as $file => $fileId) {
      try {
        $vars[$file] = HTMLPlusBuilder::getIdToAll($fileId);
        $vars[$file]["fileToMtime"] = HTMLPlusBuilder::getFileToMtime($file);
        $date->setTimestamp($vars[$file]["fileToMtime"]);
        $vars[$file]["mtime"] = $date->format(DateTime::W3C);
        $vars[$file]["file"] = $file;
        $vars[$file]["link"] = $fileId;
        $vars[$file]["editlink"] = "";
        $data = HTMLPlusBuilder::getIdToData($fileId);
        if (!is_null($data)) {
          foreach ($data as $varName => $varValue) {
            $vars[$file][$varName] = $varValue;
          }
        }
        if (Cms::isSuperUser()) {
          $vars[$file]["editlink"] = "<a href='?Admin=$file' title='$file' class='fa fa-edit'>"._("Edit")."</a>";
        }
      } catch (Exception $exc) {
        Logger::critical($exc->getMessage());
        continue;
      } finally {
        foreach ($vars[$file] as $name => $value) {
          $vars[$file][$name] = [
            "value" => $value,
            "cacheable" => true,
          ];
        }
      }
    }
    return $vars;
  }

}
