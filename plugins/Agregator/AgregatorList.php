<?php

namespace IGCMS\Plugins\Agregator;

use Exception;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\Logger;

/**
 * Class AgregatorList
 * @package IGCMS\Plugins\Agregator
 */
class AgregatorList {
  /**
   * @var string
   */
  private static $sortby;
  /**
   * @var bool
   */
  private static $rsort;
  /**
   * @var string
   */
  protected $listId;
  /**
   * @var string
   */
  protected $path;
  /**
   * @var string
   */
  private $wrapper;
  /**
   * @var string
   */
  private $defaultSortby;
  /**
   * @var bool
   */
  private $defaultRsort;
  /**
   * @var int
   */
  private $skip;
  /**
   * @var int
   */
  private $limit;

  /**
   * AgregatorList constructor.
   * @param DOMElementPlus $doclist
   * @param string $defaultSortby
   * @param bool $defaultRsort
   * @throws Exception
   */
  public function __construct (DOMElementPlus $doclist, $defaultSortby, $defaultRsort) {
    $this->listId = $doclist->getRequiredAttribute("id");
    $this->path = $doclist->getAttribute("path");
    $this->wrapper = $doclist->getAttribute("wrapper");
    $this->defaultSortby = $defaultSortby;
    $this->defaultRsort = $defaultRsort;
    self::$rsort = $doclist->hasAttribute("rsort");
    if (self::$rsort) {
      self::$sortby = $doclist->getAttribute("rsort");
    } else {
      self::$sortby = $doclist->getAttribute("sort");
    }
    $this->skip = $doclist->getAttribute("skip");
    if (!is_numeric($this->skip)) {
      $this->skip = 0;
    }
    $this->limit = $doclist->getAttribute("limit");
    if (!is_numeric($this->limit)) {
      $this->limit = 0;
    }
  }

  /**
   * @param array $a
   * @param array $b
   * @return int
   */
  private static function compare ($a, $b) {
    if ($a[self::$sortby] == $b[self::$sortby]) {
      return 0;
    }
    $val = ($a[self::$sortby] < $b[self::$sortby]) ? -1 : 1;
    if (self::$rsort) {
      return -$val;
    }
    return $val;
  }

  /**
   * @param DOMElementPlus $pattern
   * @param array $vars
   * @return DOMDocumentPlus
   * @throws Exception
   */
  protected function createList (DOMElementPlus $pattern, Array $vars) {
    $this->sort($vars);
    return $this->getDOM($pattern, $vars);
  }

  /**
   * @param array $vars
   */
  private function sort (Array &$vars) {
    if (!array_key_exists(self::$sortby, current($vars))) {
      if (strlen(self::$sortby)) {
        Logger::user_warning(sprintf(_("Sort variable %s not found; using default"), self::$sortby));
      } else {
        self::$rsort = $this->defaultRsort;
      }
      self::$sortby = $this->defaultSortby;
    }
    uasort($vars, ["IGCMS\\Plugins\\Agregator\\AgregatorList", "compare"]);
  }

  /**
   * @param DOMElementPlus $pattern
   * @param array $vars
   * @return DOMDocumentPlus
   * @throws Exception
   */
  private function getDOM (DOMElementPlus $pattern, Array $vars) {
    $doc = new DOMDocumentPlus();
    $root = $doc->appendChild($doc->createElement("root"));
    if (strlen($this->wrapper)) {
      /** @var DOMElementPlus $root */
      $root = $root->appendChild($doc->createElement($this->wrapper));
      $root->setAttribute("class", "agregator ".strtolower(get_caller_class(2))." ".$this->listId);
    }
    $index = 0;
    foreach ($vars as $key => $varValue) {
      if ($index++ < $this->skip) {
        continue;
      }
      if ($this->limit > 0 && $index > $this->skip + $this->limit) {
        break;
      }
      /** @var DOMElementPlus $list */
      $list = $root->appendChild($doc->importNode($pattern, true));
      $list->processVariables($varValue, [], true);
      $list->stripTag();
    }
    return $doc;
  }

}
