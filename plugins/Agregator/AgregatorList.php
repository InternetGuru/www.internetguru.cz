<?php

namespace IGCMS\Plugins\Agregator;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Cms;
use DateTime;
use Exception;

class AgregatorList {
  protected $id;
  protected $path;
  private $class;
  private $wrapper;
  private $defaultSortby;
  private $skip;
  private $limit;
  private static $sortby;
  private static $rsort;

  public function __construct(DOMElementPlus $doclist, $defaultSortby, $defaultRsort) {
    $this->id = $doclist->getRequiredAttribute("id");
    $this->class = "agregator ".$this->id;
    $this->path = $doclist->getAttribute("path");
    $this->wrapper = $doclist->getAttribute("wrapper");
    $this->defaultSortby = $defaultSortby;
    $this->defaultRsort = $defaultRsort;
    self::$rsort = $doclist->hasAttribute("rsort");
    if(self::$rsort) {
      self::$sortby = $doclist->getAttribute("rsort");
    } else {
      self::$sortby = $doclist->getAttribute("sort");
    }
    $this->skip = $doclist->hasAttribute("skip");
    if(!is_numeric($this->skip)) $this->skip = 0;
    $this->limit = $doclist->hasAttribute("limit");
    if(!is_numeric($this->limit)) $this->limit = 0;
  }

  protected function createList(DOMElementPlus $pattern, Array $vars) {
    $this->sort($vars);
    return $this->getDOM($pattern, $vars);
  }

  private function getDOM(DOMElementPlus $pattern, Array $vars) {
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    if(strlen($this->wrapper))
      $root = $root->appendChild($doc->createElement($this->wrapper));
    if(strlen($this->class)) $root->setAttribute("class", $this->class);
    $i = 0;
    foreach($vars as $k => $v) {
      if($i++ < $this->skip) continue;
      if($this->limit > 0 && $i > $this->skip + $this->limit) break;
      $list = $root->appendChild($doc->importNode($pattern, true));
      $list->processVariables($v, array(), true);
      $list->stripTag();
    }
    return $doc;
  }

  private function sort(Array &$vars) {
    if(!array_key_exists(self::$sortby, current($vars))) {
      if(strlen(self::$sortby)) {
        Logger::user_warning(sprintf(_("Sort variable %s not found; using default"), self::$sortby));
      } else {
        self::$rsort = $this->defaultRsort;
      }
      self::$sortby = $this->defaultSortby;
    }
    uasort($vars, array("IGCMS\Plugins\Agregator\AgregatorList", "cmp"));
  }

  private static function cmp($a, $b) {
    if($a[self::$sortby] == $b[self::$sortby]) return 0;
    $val = ($a[self::$sortby] < $b[self::$sortby]) ? -1 : 1;
    if(self::$rsort) return -$val;
    return $val;
  }


}