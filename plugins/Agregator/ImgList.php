<?php

namespace IGCMS\Plugins\Agregator;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Cms;
use DateTime;
use Exception;

class ImgList extends AgregatorList {
  const DEFAULT_SORTBY = "name";
  const DEFAULT_RSORT = false;

  public function __construct(DOMElementPlus $doclist, DOMElementPlus $pattern = null) {
    parent::__construct($doclist, self::DEFAULT_SORTBY, self::DEFAULT_RSORT);
    $vars = $this->createVars();
    if(is_null($pattern)) $pattern = $doclist;
    $list = $this->createList($pattern, $vars);
    Cms::setVariable($this->id, $list);
  }

  private function createVars() {
    $path = strlen($this->path) ? "/".$this->path : "";
    $fileDir = FILES_DIR.$path;
    $fileFolder = USER_FOLDER."/".$fileDir;
    $vars = array();
    if(!is_dir($fileFolder)) {
      throw new Exception(sprintf(_("Path '%s' not found"), $fileDir));
    }
    foreach(scandir($fileFolder) as $file) {
      $filePath = "$fileDir/$file";
      $fullFilePath = "$fileFolder/$file";
      if(is_dir($fullFilePath)) continue;
      $mimeType = getFileMime($fullFilePath);
      if($mimeType != "image/svg+xml" && strpos($mimeType, "image/") !== 0) continue;
      $v = array();
      $v["name"] = $file;
      $v["type"] = $mimeType;
      $v["mtime"] = filemtime($fullFilePath);
      $v["url"] = $filePath;
      $v["url-images"] = $v["url"]; // alias for $v["url"]
      $v["url-thumbs"] = FILES_DIR."/thumbs$path/$file";
      $v["url-preview"] = FILES_DIR."/preview$path/$file";
      $v["url-big"] = FILES_DIR."/big$path/$file";
      $v["url-full"] = FILES_DIR."/full$path/$file";
      $vars[$filePath] = $v;
    }
    if(empty($vars)) {
      throw new Exception(sprintf(_("No images found in '%s'"), $fileDir));
    }
    return $vars;
  }

}