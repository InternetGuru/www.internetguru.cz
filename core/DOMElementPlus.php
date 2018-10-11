<?php

namespace IGCMS\Core;

use DOMElement;
use Exception;

/**
 * Class DOMElementPlus
 * @package IGCMS\Core
 *
 * @property DOMDocumentPlus|HTMLPlus|null $ownerDocument
 * @property DOMElementPlus $nextElement
 * @property DOMElementPlus|null $nextSibling
 * @property DOMElementPlus $previousElement
 * @property DOMElementPlus|DOMElement|null $parentNode
 * @property DOMElementPlus $firstElement
 * @property DOMElementPlus|null $previousSibling
 * @property DOMElementPlus[] $childElementsArray
 * @property DOMElementPlus $lastElement
 */
class DOMElementPlus extends DOMElement implements \Serializable {
  /**
   * @var int
   */
  const MAX_VAR_RECURSION_LEVEL = 3;
  /**
   * @var array
   */
  const VAR_ATTRIBUTES = ["id", "fn", "var", "cacheable", "required", "modifyonly"];
  /**
   * @var int
   */
  public $varRecursionLvl = 0;

  /**
   * String representation of object
   * @link http://php.net/manual/en/serializable.serialize.php
   * @return string the string representation of the object or null
   * @since 5.1.0
   */
  public function serialize () {
    return $this->ownerDocument->saveXML($this);
  }

  /**
   * Constructs the object
   * @link http://php.net/manual/en/serializable.unserialize.php
   * @param string $serialized <p>
   * The string representation of the object.
   * </p>
   * @return void
   * @since 5.1.0
   */
  public function unserialize ($serialized) {
    // TODO: Implement unserialize() method.
  }

  /**
   * @param string $aName
   * @return string
   * @throws Exception
   */
  public function getRequiredAttribute ($aName) {
    if (!$this->hasAttribute($aName)) {
      throw new Exception(sprintf(_("Element %s missing attribute %s"), $this->nodeName, $aName));
    }
    return $this->getAttribute($aName);
  }

  /**
   * @param string $name
   * @return DOMElementPlus
   */
  public function rename ($name) {
    $newnode = $this->ownerDocument->createElement($name);
    $children = [];
    foreach ($this->childNodes as $child) {
      $children[] = $child;
    }
    foreach ($children as $child) {
      $child = $this->ownerDocument->importNode($child, true);
      $newnode->appendChild($child);
    }
    foreach ($this->attributes as $attrName => $attrNode) {
      $newnode->setAttribute($attrName, $attrNode->nodeValue);
    }
    $this->parentNode->replaceChild($newnode, $this);
    return $newnode;
  }

  /**
   * @param array $except
   */
  public function removeAllAttributes (Array $except = []) {
    $toRemove = [];
    foreach ($this->attributes as $attr) {
      if (in_array($attr->nodeName, $except)) {
        continue;
      }
      $toRemove[] = $attr;
    }
    foreach ($toRemove as $attr) {
      $this->removeAttribute($attr->nodeName);
    }
  }

  /**
   * TODO return?
   * @param array $variables
   * @param array $ignore
   * @param bool $deep
   * @return DOMDocumentPlus|DOMElementPlus|mixed|null
   */
  public function processVariables (Array $variables, $ignore = [], $deep = false) {
    return $this->ownerDocument->elementProcessVars($variables, $ignore, $this, $deep);
  }

  /**
   * @param array $functions
   * @param array $ignore
   */
  public function processFunctions (Array $functions, Array $ignore = []) {
    foreach ($this->getVariables("fn", $ignore) as list($vName, $aName, $fName)) {
      try {
        $func = array_key_exists($vName, $functions) ? $functions[$vName] : null;
        if (is_null($func)) {
          continue;
        }
        $this->removeAttrVal("fn", $fName);
        $value = call_user_func($func, is_null($aName) ? $this : $this->getAttributeNode($aName));
        $res = $this->ownerDocument->insertVariable($this, $value, $aName);
        if (!$res->isSameNode($this)) {
          $this->emptyRecursive();
        }
      } catch (Exception $exc) {
        Logger::user_error(sprintf(_("Unable to insert function %s: %s"), $vName, $exc->getMessage()));
      }
      if (is_null($aName)) {
        return;
      }
    }
  }

  /**
   * @param string $attr
   * @param array $ignore
   * @return array
   */
  public function getVariables ($attr, Array $ignore) {
    $variables = [];
    if (!$this->hasAttribute($attr)) {
      return $variables;
    }
    foreach (explode(" ", $this->getAttribute($attr)) as $var) {
      list($vName, $aName) = array_pad(explode("@", $var), 2, null);
      if (in_array($aName, $ignore)) {
        Logger::user_warning(sprintf(_("Cannot modify attribute %s in element %s"), $aName, $this->nodeName));
        continue;
      }
      if (is_null($aName)) {
        $variables[] = [$vName, $aName, $var];
      } else {
        array_unshift($variables, [$vName, $aName, $var]);
      }
    }
    return $variables;
  }

  /**
   * @param string $aName
   * @param string $aValue
   */
  public function removeAttrVal ($aName, $aValue) {
    if (!strlen($this->getAttribute($aName))) {
      return;
    }
    $attrs = explode(" ", $this->getAttribute($aName));
    foreach ($attrs as $key => $value) {
      if ($value == $aValue) {
        unset($attrs[$key]);
      }
    }
    if (empty($attrs)) {
      $this->removeAttribute($aName);
    } else {
      $this->setAttribute($aName, implode(" ", $attrs));
    }
  }

  public function emptyRecursive () {
    $pNode = $this->parentNode;
    if (is_null($pNode)) {
      return;
    }
    $pNode->removeChild($this);
    if ($pNode->nodeType != XML_ELEMENT_NODE) {
      return;
    }
    if ($pNode->childNodes->length) {
      return;
    }
    $pNode->emptyRecursive();
  }

  /**
   * TODO return?
   * @param array $value
   * @param string $aName
   * @return DOMElementPlus|mixed|null
   * @throws Exception
   */
  public function insertVarArray (Array $value, $aName) {
    if (!is_null($aName)) {
      return $this->insertVarString(implode(" ", $value), $aName);
    }
    $sep = null;
    switch ($this->nodeName) {
      case "li":
      case "dd":
        break;
      case "em":
      case "strong":
      case "samp":
      case "span":
      case "del":
      case "ins":
      case "sub":
      case "sup":
        $sep = ", ";
        break;
      case "ul":
      case "ol":
        $this->removeChildNodes();
        $lItem = $this->ownerDocument->createElement("li");
        $this->appendChild($lItem);
        return $lItem->insertInnerHTML($value, $sep);
      #case "body":
      #case "section":
      #case "dl":
      #case "form":
      #case "fieldset":
      default:
        throw new Exception(sprintf(_("Unable to insert array into '%s'"), $this->nodeName));
    }
    return $this->insertInnerHTML($value, $sep);
  }

  /**
   * TODO return?
   * @param string $value
   * @param string $aName
   * @return DOMElementPlus|mixed|null
   * @throws Exception
   */
  public function insertVarString ($value, $aName) {
    if (is_null($aName)) {
      return $this->insertInnerHTML($value, "");
    }
    if (!$this->hasAttribute($aName) || $this->getAttribute($aName) == "") {
      if (strlen($value)) {
        $this->setAttribute($aName, $value);
      } elseif ($this->hasAttribute($aName)) {
        $this->removeAttribute($aName);
      }
      return $this;
    }
    /** @noinspection PhpUsageOfSilenceOperatorInspection */
    $temp = @sprintf($this->getAttribute($aName), $value);
    if ($temp !== false && $temp != $this->getAttribute($aName)) {
      $this->setAttribute($aName, $temp);
      return $this;
    }
    if (!strlen($value)) {
      $this->removeAttribute($aName);
      return $this;
    }
    if ($aName == "class") {
      $this->addClass($value);
    } else {
      $this->setAttribute($aName, $value);
    }
    return $this;
  }

  /**
   * TODO $html type, return?
   * @param $html
   * @param string $sep
   * @return mixed|null
   * @throws Exception
   */
  private function insertInnerHTML ($html, $sep) {
    if (!is_array($html)) {
      $html = [$html];
    }
    $dom = new DOMDocumentPlus();
    $eNam = $this->nodeName;
    $xml = "<var><$eNam>".implode("</$eNam>$sep<$eNam>", $html)."</$eNam></var>";
    try {
      $dom->loadXML($xml);
    } catch (Exception $exc) {
      $var = $dom->appendChild($dom->createElement("var"));
      foreach ($html as $key => $value) {
        $exc = $var->appendChild($dom->createElement($eNam));
        $exc->nodeValue = htmlspecialchars($html[$key]);
      }
    }
    return $this->insertVarDOMElement($dom->documentElement, null);
  }

  /**
   * TODO return?
   * @param DOMElement $element
   * @param string $aName
   * @return DOMElementPlus|mixed|null
   * @throws Exception
   */
  public function insertVarDOMElement (DOMElement $element, $aName) {
    if (!is_null($aName)) {
      return $this->insertVarString($element->nodeValue, $aName);
    }
    $var = $this->ownerDocument->importNode(clone $element, true);
    $nodes = [];
    foreach ($var->childNodes as $node) {
      $nodes[] = $node;
    }
    $insertAttributes = [];
    /** @var \DOMAttr $attr */
    foreach ($element->attributes as $attr) {
      if (in_array($attr->nodeName, self::VAR_ATTRIBUTES)) {
        continue;
      }
      $insertAttributes[$attr->nodeName] = $attr->nodeValue;
    }
    if (count($insertAttributes)) {
      foreach ($insertAttributes as $attrName => $attrValue) {
        if ($attrName == "class" && $this->hasAttribute("class")) {
          $this->addClass($attrValue);
          continue;
        }
        $this->setAttribute($attrName, $attrValue);
      }
      return $this;
    }
    if ($this->toInsert($element)) {
      $this->removeChildNodes();
      foreach ($nodes as $node) $this->appendChild($node);
      return $this;
    }
    $res = null;
    $attributes = [];
    foreach ($this->attributes as $attr) {
      $attributes[$attr->nodeName] = $attr->nodeValue;
    }
    #todo: already checked?
    #if(is_null($this->parentNode)) return $element;
    foreach ($nodes as $node) {
      $res = $node;
      $this->parentNode->insertBefore($node, $this);
      if ($node->nodeType != XML_ELEMENT_NODE) {
        continue;
      }
      foreach ($attributes as $aName => $aValue) {
        if ($node->hasAttribute($aName)) {
          continue;
        }
        $node->setAttribute($aName, $aValue);
      }
    }
    return $res;
  }

  /**
   * @param DOMElement $element
   * @return bool
   */
  private function toInsert (DOMElement $element) {
    $first = $element->firstChild;
    if (is_null($first)) {
      return false;
    }
    if ($first->nodeType == XML_TEXT_NODE && trim($first->nodeValue) == "") {
      $first = $first->nextSibling;
    }
    if ($first->nodeType != XML_ELEMENT_NODE) {
      return true;
    }
    if ($this->nodeName == $first->nodeName) {
      return false;
    }
    $blockElements = ["p", "ul", "ol", "dl", "blockcode", "blockquote", "form"];
    if (in_array($first->nodeName, $blockElements)) {
      return false;
    }
    return true;
  }

  public function removeChildNodes () {
    $toRemove = [];
    foreach ($this->childNodes as $node) {
      $toRemove[] = $node;
    }
    foreach ($toRemove as $node) {
      $this->removeChild($node);
    }
  }

  /**
   * @param string $class
   * @throws Exception
   */
  public function addClass ($class) {
    if (!preg_match("/^[A-Za-z][A-Za-z0-9_-]*$/", $class)) {
      throw new Exception(sprintf(_("Invalid class name '%s'"), $class));
    }
    if ($this->hasClass($class)) {
      return;
    }
    if (!strlen(trim($this->getAttribute("class")))) {
      $this->setAttribute("class", $class);
    } else {
      $this->setAttribute("class", $this->getAttribute("class")." $class");
    }
  }

  /**
   * @param string $class
   * @return bool
   */
  public function hasClass ($class) {
    return in_array($class, explode(" ", $this->getAttribute("class")));
  }

  /**
   * @param string|null $comment
   */
  public function stripElement ($comment = null) {
    $this->stripTag($comment, false);
  }

  /**
   * @param string|null $comment
   * @param bool $keepContent
   */
  public function stripTag ($comment = null, $keepContent = true) {
    if (!is_null($comment) && (Cms::isSuperUser() || CMS_DEBUG)) {
      $cmt = $this->ownerDocument->createComment(" $comment ");
      $this->parentNode->insertBefore($cmt, $this);
    }
    if ($keepContent) {
      $children = [];
      foreach ($this->childNodes as $node) {
        $children[] = $node;
      }
      foreach ($children as $node) {
        $this->parentNode->insertBefore($node, $this);
      }
    }
    $this->parentNode->removeChild($this);
  }

  /**
   * @param string $attr
   * @param string|null $comment
   */
  public function stripAttr ($attr, $comment = null) {
    if (!$this->hasAttribute($attr)) {
      return;
    }
    $aVal = $this->getAttribute($attr);
    $this->removeAttribute($attr);
    if ($comment === "") {
      return;
    }
    if (is_null(Cms::getLoggedUser())) {
      return;
    }
    if (is_null($comment)) {
      $comment = sprintf(_("Attribute %s stripped"), "$attr='$aVal'");
    }
    $cmt = $this->ownerDocument->createComment(" $comment ");
    $this->parentNode->insertBefore($cmt, $this);
  }

  /**
   * @param string|null $attName
   * @param string|null $eName
   * @return string|null
   */
  public function getSelfOrParentValue ($attName = null, $eName = null) {
    if (!strlen($attName)) {
      if (strlen($this->nodeValue)) {
        return htmlspecialchars($this->nodeValue);
      } // TODO: remove specialchars?
    } else {
      if (strlen($this->getAttribute($attName))) {
        return $this->getAttribute($attName);
      }
    }
    return $this->getParentValue($attName, $eName);
  }

  /**
   * @param string|null $attName
   * @param string|null $eName
   * @return string|null
   */
  public function getParentValue ($attName = null, $eName = null) {
    $parent = $this;
    while (!is_null($parent)) {
      $parent = $parent->parentNode;
      if (is_null($parent)) {
        continue;
      }
      if (!is_null($eName) && $parent->nodeName != $eName) {
        continue;
      }
      if (!is_null($attName) && $parent->hasAttribute($attName)) {
        return $parent->getAttribute($attName);
      } elseif (is_null($attName) && strlen($parent->nodeValue)) {
        return htmlspecialchars($parent->nodeValue); // TODO: remove specialchars?
      }
    }
    return null;
  }

  /**
   * @param string|null $attName
   * @param string|null $eName
   * @return string|null
   */
  public function getAncestorValue ($attName = null, $eName = null) {
    $ancestor = $this->parentNode;
    while (!is_null($ancestor)) {
      if (!is_null($attName) && $ancestor->hasAttribute($attName)) {
        return $ancestor->getAttribute($attName);
      } elseif (is_null($attName) && strlen($ancestor->nodeValue)) {
        return htmlspecialchars($ancestor->nodeValue);
      }
      $ancestor = $ancestor->getPreviousElement($eName);
      if (is_null($ancestor)) {
        return null;
      }
    }
    return null;
  }

  /**
   * TODO return?
   * @param string|null $eName
   * @return DOMElement|DOMElementPlus|null
   */
  public function getPreviousElement ($eName = null) {
    $prevElement = $this->previousElement;
    if (is_null($prevElement)) {
      $prevElement = $this->parentNode;
    }
    while ($prevElement instanceof DOMElement) {
      if (is_null($eName) || $prevElement->nodeName == $eName) {
        return $prevElement;
      }
      if (!is_null($prevElement->previousElement)) {
        $prevElement = $prevElement->previousElement;
      } else {
        $prevElement = $prevElement->parentNode;
      }
    }
    return null;
  }

  /**
   * @param $name
   * @return DOMElementPlus|DOMElementPlus[]|null
   */
  public function __get ($name) {
    switch ($name) {
      case "nextElement":
        return $this->getNextSibElement();
        break;
      case "previousElement":
        return $this->getPrevSibElement();
        break;
      case "childElementsArray":
        return $this->getChildElementsArray();
        break;
      case "firstElement":
        return $this->getFirstElement();
        break;
      case "lastElement":
        return $this->getLastElement();
        break;
      default:
      /** @noinspection PhpVariableVariableInspection */
      return parent::$$name;
    }
  }

  /**
   * @return DOMElementPlus
   */
  private function getNextSibElement () {
    $nextSibling = $this->nextSibling;
    while (!is_null($nextSibling) && $nextSibling->nodeType != XML_ELEMENT_NODE) {
      $nextSibling = $nextSibling->nextSibling;
    }
    return $nextSibling;
  }

  /**
   * @return DOMElementPlus
   */
  private function getPrevSibElement () {
    $prevSibling = $this->previousSibling;
    while (!is_null($prevSibling) && $prevSibling->nodeType != XML_ELEMENT_NODE) {
      $prevSibling = $prevSibling->previousSibling;
    }
    return $prevSibling;
  }

  /**
   * @return DOMElementPlus[]
   */
  private function getChildElementsArray () {
    $elements = [];
    foreach ($this->childNodes as $node) {
      if ($node->nodeType != XML_ELEMENT_NODE) {
        continue;
      }
      $elements[] = $node;
    }
    return $elements;
  }

  /**
   * @return DOMElementPlus|null
   */
  private function getFirstElement () {
    foreach ($this->childNodes as $node) {
      if ($node->nodeType != XML_ELEMENT_NODE) {
        continue;
      }
      return $node;
    }
    return null;
  }

  /**
   * @return DOMElementPlus|null
   */
  private function getLastElement () {
    $childElements = $this->childElementsArray;
    if (!count($childElements)) {
      return null;
    }
    return $childElements[count($childElements) - 1];
  }

  /**
   * @param int $iter
   * @return string
   * @throws Exception
   */
  public function setUniqueId ($iter = 0) {
    $uniqueId = $this->getValidId();
    if ($iter != 0) {
      $uniqueId .= $iter;
    }
    try {
      if (is_null($this->ownerDocument->getElementById($uniqueId))) {
        $this->setAttribute("id", $uniqueId);
        return $uniqueId;
      }
    } catch (Exception $exc) {
    }
    return $this->setUniqueId(++$iter);
  }

  /**
   * @return string
   * @throws Exception
   */
  private function getValidId () {
    $validId = normalize($this->getAttribute("name"));
    if (is_valid_id($validId)) {
      return $validId;
    }
    $validId = normalize($this->getAttribute("short"));
    if (is_valid_id($validId)) {
      return $validId;
    }
    $validId = normalize($this->nodeValue);
    if (is_valid_id($validId)) {
      return $validId;
    }
    return $this->nodeName.".".substr(md5(microtime().rand()), 0, 3);
  }

}
