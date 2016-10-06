<?php

namespace IGCMS\Plugins;
use Exception;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\GetContentStrategyInterface;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class Agregator
 * @package IGCMS\Plugins
 */
class Agregator extends Plugin implements SplObserver, GetContentStrategyInterface {
  private $registered = array();  // filePath => fileInfo(?)

  /**
   * Agregator constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 2);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    if($this->detachIfNotAttached("HtmlOutput")) return;
    $this->registerFiles(CMS_FOLDER);
    $this->registerFiles(ADMIN_FOLDER);
    $this->registerFiles(USER_FOLDER);
    $this->createLists();
    #var_dump(HTMLPlusBuilder::getIdToParentId());
    #die();
  }

  private function createLists() {
    $docListClass = "IGCMS\\Plugins\\Agregator\\DocList";
    $imgListClass = "IGCMS\\Plugins\\Agregator\\ImgList";
    $listElements[$docListClass] = array();
    $listElements[$imgListClass] = array();
    foreach($this->getXML()->documentElement->childNodes as $child) {
      if($child->nodeType != XML_ELEMENT_NODE) continue;
      /** @var DOMElementPlus $child */
      $id = "n/a";
      $listClass = $docListClass;
      try {
        switch($child->nodeName) {
          case "imglist":
          $listClass = $imgListClass;
          case "doclist":
          $id = $child->getRequiredAttribute("id");
          $listRef = $child->getAttribute($child->nodeName);
          if(!strlen($listRef)) {
            $listElements[$listClass][$id] = $child;
            $listId[$id] = new $listClass($child);
            continue;
          }
          if(!array_key_exists($listRef, $listElements[$listClass])) {
            throw new Exception(sprintf(_("Reference id '%s' not found"), $listRef));
          }
          $listId[$id] = new $listClass($child, $listElements[$listClass][$listRef]);
        }
      } catch(Exception $e) {
        Logger::user_warning(sprintf(_("List '%s' not created: %s"), $id, $e->getMessage()));
      }
    }
  }

  /**
   * @return HTMLPlus|null
   */
  public function getContent() {
    $file = HTMLPlusBuilder::getCurFile();
    if(is_null($file) || !array_key_exists($file, $this->registered)) return null;
    $content =  HTMLPlusBuilder::getFileToDoc($file);
    $content->documentElement->addClass(strtolower($this->className));
    return $content;
  }

  /**
   * @param string $workingDir
   * @param string|null $folder
   */
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
      try {
        HTMLPlusBuilder::register($this->pluginDir."/$filePath", $folder);
        $this->registered[$this->pluginDir."/$filePath"] = null;
      } catch(Exception $e) {
        Logger::user_warning(sprintf(_("Unable to register '%s': %s"), $filePath, $e->getMessage()));
      }
    }
  }

}

?>
