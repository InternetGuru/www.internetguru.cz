<?php

namespace IGCMS\Plugins\Agregator;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;

/**
 * Class ImgList
 * @package IGCMS\Plugins\Agregator
 */
class ImgList extends AgregatorList {
  /**
   * @var string
   */
  const DEFAULT_SORTBY = "name";
  /**
   * @var bool
   */
  const DEFAULT_RSORT = false;

  /**
   * ImgList constructor.
   * @param DOMElementPlus $doclist
   * @param DOMElementPlus|null $pattern
   * @throws Exception
   */
  public function __construct (DOMElementPlus $doclist, DOMElementPlus $pattern = null) {
    parent::__construct($doclist, self::DEFAULT_SORTBY, self::DEFAULT_RSORT);
    $cacheKey = apc_get_key(__FUNCTION__."/".$this->listId);
    $cacheExists = apc_exists($cacheKey);
    $cache = null;
    if ($cacheExists) {
      $cache = apc_fetch($cacheKey);
      $doc = new DOMDocumentPlus();
      $doc->loadXML($cache);
      $listDoc = $doc;
    } else {
      $vars = $this->createVars();
      if (is_null($pattern)) {
        $pattern = $doclist;
      }
      $listDoc = $this->createList($pattern, $vars);
    }
    if (!$cacheExists || !$this->isFilesUpToDate()) {
      $cache = $listDoc->saveXML($listDoc);
      apc_store_cache($cacheKey, $cache, __FUNCTION__);
    }
    Cms::setVariable($this->listId, $listDoc);
  }

  /**
   * @return bool
   */
  private function isFilesUpToDate () {
    $userFilesInotifyPath = FILES_FOLDER."/".INOTIFY;
    $domainFilesInotifyPath = FILES_DIR."/".INOTIFY;
    return filemtime($userFilesInotifyPath) === filemtime($domainFilesInotifyPath);
  }

  /**
   * @return array
   * @throws Exception
   */
  private function createVars () {
    $path = strlen($this->path) ? "/".$this->path : "";
    $fileDir = FILES_DIR.$path;
    $fileFolder = USER_FOLDER."/".$fileDir;
    $vars = [];
    if (!is_dir($fileFolder)) {
      throw new Exception(sprintf(_("Path '%s' not found"), $fileDir));
    }
    foreach (scandir($fileFolder) as $file) {
      if (strpos($file, ".") === 0) {
        continue;
      }
      $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
      if (!in_array($ext, ["jpg", "jpeg", "png", "gif"])) {
        continue;
      }
      $filePath = "$fileDir/$file";
      $fullFilePath = "$fileFolder/$file";
      if (is_dir($fullFilePath)) {
        continue;
      }
      $mimeType = get_mime($fullFilePath);
      if ($mimeType != "image/svg+xml" && strpos($mimeType, "image/") !== 0) {
        continue;
      }
      $variable = [];
      $variable["name"] = $file;
      $variable["type"] = $mimeType;
      $variable["mtime"] = filemtime($fullFilePath);
      $variable["url"] = ROOT_URL.$filePath;
      $variable["url-images"] = $variable["url"]; // alias for $v["url"]
      $variable["url-thumbs"] = ROOT_URL.FILES_DIR."/thumbs$path/$file";
      $variable["url-preview"] = ROOT_URL.FILES_DIR."/preview$path/$file";
      $variable["url-big"] = ROOT_URL.FILES_DIR."/big$path/$file";
      $variable["url-full"] = ROOT_URL.FILES_DIR."/full$path/$file";
      $altPath = ltrim("$path/".pathinfo($file, PATHINFO_FILENAME), "/");
      $variable["alt"] = preg_replace(
        [
          "~(\d)([a-z])~", // "1.9tdi" to "1.9 tdi"
          "~([a-z])(\d)~", // "file01" to "file 01"
          "~([a-z])-([a-z]|\d)~",
          "~([a-z]|\d)-([a-z])~",
          "~/~",
          "~_~",
          "~ +~",
        ],
        [
          "\\1 \\2",
          "\\1 \\2",
          "\\1 - \\2",
          "\\1 - \\2",
          " / ",
          " ",
          " ",
        ],
        $altPath
      );
      foreach ($variable as $name => $value) {
        $variable[$name] = [
          "value" => $value,
          "cacheable" => true,
        ];
      }
      $vars[$filePath] = $variable;
    }
    if (empty($vars)) {
      throw new Exception(sprintf(_("No images found in '%s'"), $fileDir));
    }
    return $vars;
  }

}
