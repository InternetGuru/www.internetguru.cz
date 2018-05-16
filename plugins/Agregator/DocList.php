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
  const DEFAULT_SORTBY = "ctime";
  /**
   * @var bool
   */
  const DEFAULT_RSORT = true;
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
    $this->keyword = $doclist->getAttribute("kw");
    $vars = $this->createVars();
    if (is_null($pattern)) {
      $pattern = $doclist;
    }
    $list = $this->createList($pattern, $vars);
    $linkParts = explode("/", get_link());
    $curFile = HTMLPlusBuilder::getIdToFile(end($linkParts));
    if (array_key_exists($curFile, $vars)) {
      foreach ($vars[$curFile] as $name => $value) {
        Cms::setVariable($name, $value, "");
      }
    }
    Cms::setVariable($this->listId, $list);
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
      }
    }
    return $vars;
  }

}