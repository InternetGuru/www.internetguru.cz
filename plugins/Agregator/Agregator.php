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
use IGCMS\Plugins\Agregator\AgregatorList;
use IGCMS\Plugins\Agregator\DocList;
use SplObserver;
use SplSubject;

/**
 * Class Agregator
 * @package IGCMS\Plugins
 */
class Agregator extends Plugin implements SplObserver, GetContentStrategyInterface {
  /**
   * @var array
   */
  private $registered = array();  // filePath => fileInfo(?)
  /**
   * @var DOMElementPlus[]
   */
  private $imgLists = array();
  /**
   * @var DOMElementPlus[]
   */
  private $docLists = array();
  /**
   * @var DOMElementPlus[]
   */
  private $filters = array();
  /**
   * @var array
   */
  private $lists = array();
  /**
   * @var string
   */
  const DOCLIST_CLASS = "IGCMS\\Plugins\\Agregator\\DocList";
  /**
   * @var string
   */
  const IMGLIST_CLASS = "IGCMS\\Plugins\\Agregator\\ImgList";

  /**
   * Agregator constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 2);
    $this->lists[self::DOCLIST_CLASS] = array();
    $this->lists[self::IMGLIST_CLASS] = array();
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
    $this->setVars();
    foreach($this->docLists as $id => $docList) {
      $this->filterDoclist($id);
    }
    foreach($this->docLists as $id => $docList) {
      $this->createList(self::DOCLIST_CLASS, $id, $docList);
    }
    foreach($this->imgLists as $id => $imgList) {
      $this->createList(self::IMGLIST_CLASS, $id, $imgList);
    }
  }

  /**
   * @param string $id
   */
  private function filterDoclist($id) {
    if(!array_key_exists($id, $_GET)) return;
    if(!array_key_exists($_GET[$id], $this->filters)) return;
    $filter = $this->filters[$_GET[$id]];
    $doclist = $filter->getAttribute("doclist");
    $for = $filter->getAttribute("for");
    if(!strlen($for)) $for = $doclist;
    if(!strlen($doclist)) $doclist = $for;
    if(!strlen($for) || $for != $id) return;
    if(!array_key_exists($for, $this->docLists)) return;
    /** @var DOMElementPlus $ref */
    $ref = $this->docLists[$for];
    if($doclist != $for) {
      $ref->removeChildNodes();
      $toAppend = array();
      foreach($this->docLists[$doclist]->childNodes as $childNode) { $toAppend[] = $childNode; }
      foreach($toAppend as $childNode) { $ref->appendChild($childNode); }
    }
    foreach($filter->attributes as $attrName => $attrNode) {
      if(in_array($attrName, array("id", "doclist", "for"))) continue;
      $ref->setAttribute($attrName, $attrNode->nodeValue);
    }
  }

  private function setVars() {
    /** @var DOMElementPlus $child */
    foreach($this->getXML()->documentElement->childNodes as $child) {
      if($child->nodeType != XML_ELEMENT_NODE) continue;
      try {
        $id = $child->getRequiredAttribute("id");
      } catch(Exception $e) {
        Logger::user_warning($e->getMessage());
        continue;
      }
      switch($child->nodeName) {
        case "imglist":
          $this->imgLists[$id] = $child;
          break;
        case "doclist":
          $this->docLists[$id] = $child;
          break;
        case "filter":
          $this->filters[$id] = $child;
      }
    }
  }

  /**
   * @param string $listClass
   * @param string $templateId
   * @param DOMElementPlus $template
   * @throws Exception
   */
  private function createList($listClass, $templateId, DOMElementPlus $template) {
    try {
      $listRef = $template->getAttribute($template->nodeName);
      if(!strlen($listRef)) {
        $this->lists[$listClass][$templateId] = $template;
        new $listClass($template);
        return;
      }
      if(!array_key_exists($listRef, $this->lists[$listClass])) {
        throw new Exception(sprintf(_("Reference id '%s' not found"), $listRef));
      }
      new $listClass($template, $this->lists[$listClass][$listRef]);
    } catch(Exception $e) {
      Logger::user_warning(sprintf(_("List '%s' not created: %s"), $templateId, $e->getMessage()));
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
