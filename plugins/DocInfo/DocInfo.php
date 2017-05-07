<?php

namespace IGCMS\Plugins;

use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class DocInfo
 * @package IGCMS\Plugins
 */
class DocInfo extends Plugin implements SplObserver, ModifyContentStrategyInterface {
  /**
   * @var array
   */
  private $vars = [];

  /**
   * DocInfo constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 210);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
  }

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent (HTMLPlus $content) {
    if (!$content->documentElement->hasClass(strtolower($this->className))) {
      return;
    }
    $filePath = HTMLPlusBuilder::getCurFile();
    $id = HTMLPlusBuilder::getFileToId($filePath);
    $globalInfo = [];
    $globalInfo["ctime"] = HTMLPlusBuilder::getIdToCtime($id);
    $globalInfo["mtime"] = HTMLPlusBuilder::getIdToMtime($id);
    $globalInfo["author"] = HTMLPlusBuilder::getIdToAuthor($id);
    $globalInfo["authorid"] = HTMLPlusBuilder::getIdToAuthorId($id);
    $globalInfo["resp"] = HTMLPlusBuilder::getIdToResp($id);
    $globalInfo["respid"] = HTMLPlusBuilder::getIdToRespId($id);
    $this->insertDocInfo($content, $globalInfo, $filePath);
  }

  /**
   * @param HTMLPlus $doc
   * @param array $globalInfo
   * @param $filePath
   */
  private function insertDocInfo (HTMLPlus $doc, Array $globalInfo, $filePath) {
    $this->vars = [];
    /** @var DOMElementPlus $var */
    foreach ($this->getXML()->getElementsByTagName("var") as $var) {
      $this->vars[$var->getAttribute("id")] = $var;
    }
    foreach ($doc->getElementsByTagName("h") as $h) {
      $before = null;
      if ($h->parentNode->nodeName == "body") {
        if ($filePath == INDEX_HTML) {
          continue;
        }
        $info = $this->createGlobalDocInfo($globalInfo, $filePath);
      } else {
        $info = $this->createLocalDocInfo($h, $globalInfo);
        $before = $h->nextElement;
        while (!is_null($before)) {
          if ($before->nodeName == "h") {
            break;
          }
          $before = $before->nextElement;
        }
      }
      if (is_null($info)) {
        continue;
      }
      /** @var DOMElementPlus $info */
      $info = $doc->importNode($info, true);
      foreach ($info->childElementsArray as $child) {
        $h->parentNode->insertBefore($child, $before);
      }
    }
  }

  /**
   * @param array $globalInfo
   * @param string $filePath
   * @return DOMElementPlus
   */
  private function createGlobalDocInfo (Array $globalInfo, $filePath) {
    $lists = [
      "created" => $this->vars["created"],
      "edit" => "",
      "modified" => "",
      "responsible" => "",
    ];
    if (strlen($globalInfo["mtime"]) && substr($globalInfo["ctime"], 0, 10) != substr($globalInfo["mtime"], 0, 10)) {
      $lists["modified"] = $this->vars["modified"];
    }
    if (strlen($globalInfo["resp"])) {
      $lists["responsible"] = $this->vars["responsible"];
    }
    if (Cms::isSuperUser()) {
      $globalInfo["editurl"] = "?Admin=".$filePath;
      $lists["edit"] = $this->vars["edit"];
    }
    $doc = $this->createDOM($this->vars["docinfo"]);
    $doc->processVariables($lists);
    $doc->processVariables($globalInfo);
    return $doc->documentElement;
  }

  /**
   * @param DOMElementPlus $set
   * @return DOMDocumentPlus
   */
  private function createDOM (DOMElementPlus $set) {
    $doc = new DOMDocumentPlus();
    $doc->appendChild($doc->importNode($set, true));
    return $doc;
  }

  /**
   * @param DOMElementPlus $h
   * @param array $globalInfo
   * @return DOMElementPlus|null
   */
  private function createLocalDocInfo (DOMElementPlus $h, Array $globalInfo) {
    $doc = $this->createDOM($this->vars["partinfo"]);
    $vars = [];
    $lists = [
      "part_created" => "",
      "part_modified" => "",
    ];
    if ($h->hasAttribute("ctime") && substr($globalInfo["ctime"], 0, 10) != substr($h->getAttribute("ctime"), 0, 10)) {
      $vars["ctime"] = $h->getAttribute("ctime");
      $lists["part_created"] = $this->vars["part_created"];
    }
    if ($h->hasAttribute("mtime")
      && (!strlen($globalInfo["mtime"])
        || substr($globalInfo["mtime"], 0, 10) != substr(
          $h->getAttribute("mtime"),
          0,
          10
        ))
    ) {
      $vars["mtime"] = $h->getAttribute("mtime");
      $lists["part_modified"] = $this->vars["part_modified"];
    }
    if (empty($vars)) {
      return null;
    }
    $doc->processVariables($lists);
    $doc->processVariables($vars);
    return $doc->documentElement;
  }
}

?>
