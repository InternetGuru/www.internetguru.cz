<?php

namespace IGCMS\Plugins;

use DOMElement;
use DOMNode;
use DOMText;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\GetContentStrategyInterface;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class InputVar
 * @package IGCMS\Plugins
 */
class InputVar extends Plugin implements SplObserver, GetContentStrategyInterface {
  /**
   * @var string|null
   */
  private $userCfgPath = null;
  /**
   * @var DOMDocumentPlus|null
   */
  private $cfg = null;
  /**
   * @var string|null
   */
  private $formId = null;
  /**
   * @var string|null
   */
  private $passwd = null;
  /**
   * @var string
   */
  private $getOk = "ivok";
  /**
   * @var array
   */
  private $vars = [];

  /**
   * InputVar constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $this->userCfgPath = USER_FOLDER."/".$this->pluginDir."/".$this->className.".xml";
    $s->setPriority($this, 5);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update (SplSubject $subject) {
    try {
      if ($subject->getStatus() == STATUS_POSTPROCESS) {
        $this->processPost();
      }
      if (!in_array($subject->getStatus(), [STATUS_INIT, STATUS_PROCESS])) {
        return;
      }
      $this->cfg = $this->getXML();
      if ($subject->getStatus() == STATUS_INIT) {
        if (isset($_GET[$this->getOk])) {
          Logger::user_success(_("Changes successfully saved"));
        }
        $this->loadVars();
      }
      foreach ($this->cfg->documentElement->childElementsArray as $e) {
        if ($e->nodeName == "set") {
          continue;
        }
        if ($e->nodeName == "passwd") {
          continue;
        }
        try {
          $e->getRequiredAttribute("id"); // only check
        } catch (Exception $ex) {
          Logger::user_warning($ex->getMessage());
          continue;
        }
        switch ($e->nodeName) {
          case "var":
            if ($subject->getStatus() != STATUS_PROCESS) {
              continue;
            }
            $this->processRule($e);
          case "fn":
            if ($subject->getStatus() != STATUS_INIT) {
              continue;
            }
            $this->processRule($e);
            break;
          default:
            Logger::user_warning(sprintf(_("Unknown element name %s"), $e->nodeName));
        }
      }
    } catch (Exception $ex) {
      if ($ex->getCode() === 1) {
        Logger::user_error($ex->getMessage());
      } else {
        Logger::critical($ex->getMessage());
      }
    }
  }

  /**
   * @throws Exception
   */
  private function processPost () {
    if (!is_file($this->userCfgPath)) {
      return;
    }
    $req = Cms::getVariable("validateform-".$this->formId);
    if (is_null($req)) {
      return;
    }
    if (isset($req["passwd"]) && !hash_equals($this->passwd, crypt($req["passwd"], $this->passwd))) {
      throw new Exception(_("Incorrect password"), 1);
    }
    $var = null;
    foreach ($req as $k => $v) {
      if (isset($this->vars[$k])) {
        if ($this->vars[$k]->hasAttribute("var")) {
          $this->vars[$k]->setAttribute("var", normalize($this->className."-$v"));
        } else {
          $this->vars[$k]->nodeValue = $v;
        }
        $var = $this->vars[$k];
      }
    }
    if (@$var->ownerDocument->save($this->userCfgPath) === false) {
      throw new Exception(_("Unable to save user config"));
    }
    #if(!isset($_GET[DEBUG_PARAM]) || $_GET[DEBUG_PARAM] != DEBUG_ON)
    clearNginxCache();
    redirTo(buildLocalUrl(["path" => getCurLink(), "query" => $this->className."&".$this->getOk], true));
  }

  private function loadVars () {
    foreach ($this->cfg->documentElement->childElementsArray as $e) {
      if ($e->nodeName == "var") {
        $this->vars[$e->getRequiredAttribute("id")] = $e;
      }
      if ($e->nodeName == "passwd") {
        $this->passwd = $e->nodeValue;
      }
    }
  }

  /**
   * @param DOMElementPlus $element
   */
  private function processRule (DOMElementPlus $element) {
    $id = $element->getAttribute("id");
    $name = $element->nodeName;
    $attributes = [];
    foreach ($element->attributes as $attr) $attributes[$attr->nodeName] = $attr->nodeValue;
    $el = $element->processVariables(Cms::getAllVariables(), [], true); // pouze posledni node
    if (is_null($el)
      || (gettype($el) == "object" && (new \ReflectionClass($el))->getShortName() != "DOMElementPlus"
        && !$el->isSameNode($element))
    ) {
      if (is_null($el)) {
        $el = new DOMText("");
      }
      $var = $element->ownerDocument->createElement("var");
      foreach ($attributes as $aName => $aValue) {
        $var->setAttribute($aName, $aValue);
      }
      $var->appendChild($el);
      $el = $var;
    }
    if (!$el->hasAttribute("fn")) {
      $this->setVar($name, $id, $el);
      return;
    }
    $f = $el->getAttribute("fn");
    if (strpos($f, "-") === false) {
      $f = $this->className."-$f";
    }
    $result = Cms::getFunction($f);
    if ($el->hasAttribute("fn") && !is_null($result)) {
      try {
        $result = Cms::applyUserFn($f, $el);
      } catch (Exception $e) {
        Logger::user_warning(sprintf(_("Unable to apply function: %s"), $e->getMessage()));
        return;
      }
    }
    if (!is_null($result)) {
      $this->setVar($el->nodeName, $id, $result);
      return;
    }
    try {
      $fn = $this->register($el);
    } catch (Exception $e) {
      Logger::user_warning(sprintf(_("Unable to register function %s: %s"), $el->getAttribute("fn"), $e->getMessage()));
      return;
    }
    if ($el->nodeName == "fn") {
      Cms::setFunction($id, $fn);
    } else {
      Cms::setVariable($id, $fn($el));
    }
  }

  /**
   * @param string $var
   * @param string $name
   * @param mixed $value
   */
  private function setVar ($var, $name, $value) {
    if ($var == "fn") {
      Cms::setFunction($name, $value);
    } else {
      Cms::setVariable($name, $value);
    }
  }

  /**
   * @param DOMElementPlus $el
   * @return \Closure|null
   * @throws Exception
   */
  private function register (DOMElementPlus $el) {
    $fn = null;
    switch ($el->getAttribute("fn")) {
      case "hash":
        $algo = $el->hasAttribute("algo") ? $el->getAttribute("algo") : null;
        $fn = $this->createFnHash($this->parse($algo));
        break;
      case "strftime":
        $format = $el->hasAttribute("format") ? $el->getAttribute("format") : null;
        $fn = $this->createFnStrftime($format);
        break;
      case "sprintf":
        $fn = $this->createFnSprintf($el->nodeValue);
        break;
      case "pregreplace":
        $pattern = $el->hasAttribute("pattern") ? $el->getAttribute("pattern") : null;
        $replacement = $el->hasAttribute("replacement") ? $el->getAttribute("replacement") : null;
        $fn = $this->createFnPregReplace($pattern, $replacement);
        break;
      case "replace":
        $tr = [];
        foreach ($el->childElementsArray as $d) {
          if ($d->nodeName != "data") {
            continue;
          }
          try {
            $name = $d->getRequiredAttribute("name");
          } catch (Exception $e) {
            Logger::user_warning($e->getMessage());
            continue;
          }
          $tr[$name] = $this->parse($d->nodeValue);
        }
        $fn = $this->createFnReplace($tr);
        break;
      case "sequence":
        $seq = [];
        foreach ($el->childElementsArray as $call) {
          if ($call->nodeName != "call") {
            continue;
          }
          if (!strlen($call->nodeValue)) {
            Logger::user_warning(_("Element call missing content"));
            continue;
          }
          $seq[] = $call->nodeValue;
        }
        $fn = $this->createFnSequence($seq);
        break;
      default:
        throw new Exception(_("Unknown function name"));
    }
    return $fn;
  }

  /**
   * @param string|null $algo
   * @return \Closure
   */
  private function createFnHash ($algo = null) {
    if (!in_array($algo, hash_algos())) {
      $algo = "crc32b";
    }
    return function(DOMNode $node) use ($algo) {
      return hash($algo, $node->nodeValue);
    };
  }

  /**
   * @param string $value
   * @return string
   */
  private function parse ($value) {
    return replaceVariables($value, Cms::getAllVariables(), strtolower($this->className."-"));
  }

  /**
   * @param string|null $format
   * @return \Closure
   */
  private function createFnStrftime ($format = null) {
    if (is_null($format)) {
      $format = "%m/%d/%Y";
    }
    $format = $this->crossPlatformCompatibleFormat($format);
    return function(DOMNode $node) use ($format) {
      $value = $node->nodeValue;
      $date = trim(strftime($format, strtotime($value)));
      return $date ? $date : $value;
    };
  }

  /**
   * http://php.net/manual/en/function.strftime.php
   * @param string $format
   * @return mixed
   */
  private function crossPlatformCompatibleFormat ($format) {
    // Jan 1: results in: '%e%1%' (%%, e, %%, %e, %%)
    #$format = '%%e%%%e%%';

    // Check for Windows to find and replace the %e
    // modifier correctly
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
      $format = preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $format);
    }

    return $format;
  }

  /**
   * @param string $format
   * @return \Closure
   * @throws Exception
   */
  private function createFnSprintf ($format) {
    if (!strlen($format)) {
      throw new Exception(_("No content found"));
    }
    return function(DOMNode $node) use ($format) {
      $elements = [];
      foreach ($node->childNodes as $child) {
        if ($child->nodeType != XML_ELEMENT_NODE) {
          break;
        }
        $elements[] = $child->nodeValue;
      }
      if (empty($elements)) {
        $elements[] = $node->nodeValue;
      }
      $temp = @vsprintf($format, $elements);
      if ($temp === false) {
        return $format;
      }
      return $temp;
    };
  }

  /**
   * @param string $pattern
   * @param string $replacement
   * @return \Closure
   * @throws Exception
   */
  private function createFnPregReplace ($pattern, $replacement) {
    if (is_null($pattern)) {
      throw new Exception(_("No pattern found"));
    }
    if (is_null($replacement)) {
      throw new Exception(_("No replacement found"));
    }
    return function(DOMNode $node) use ($pattern, $replacement) {
      return preg_replace("/^(?:".$pattern.")$/", $replacement, $node->nodeValue);
    };
  }

  /**
   * @param array $replace
   * @return \Closure
   * @throws Exception
   */
  private function createFnReplace (Array $replace) {
    if (empty($replace)) {
      throw new Exception(_("No data found"));
    }
    return function(DOMNode $node) use ($replace) {
      return str_replace(array_keys($replace), $replace, $node->nodeValue);
    };
  }

  /**
   * @param array $call
   * @return \Closure
   * @throws Exception
   */
  private function createFnSequence (Array $call) {
    if (empty($call)) {
      throw new Exception(_("No data found"));
    }
    return function(DOMNode $node) use ($call) {
      foreach ($call as $f) {
        if (strpos($f, "-") === false) {
          $f = $this->className."-$f";
        }
        try {
          $node = new DOMElement("any", Cms::applyUserFn($f, $node));
        } catch (Exception $e) {
          Logger::user_warning(sprintf(_("Sequence call skipped: %s"), $e->getMessage()));
        }
      }
      return $node->nodeValue;
    };
  }

  /**
   * @return HTMLPlus|null
   */
  public function getContent () {
    if (!isset($_GET[$this->className])) {
      return null;
    }
    $newContent = $this->getHTMLPlus();
    /** @var DOMElementPlus $form */
    $form = $newContent->getElementsByTagName("form")->item(0);
    $this->formId = $form->getAttribute("id");
    $fieldset = $newContent->getElementsByTagName("fieldset")->item(0);
    /** @var DOMElementPlus $e */
    foreach ($this->cfg->getElementsByTagName("set") as $e) {
      try {
        $e->getRequiredAttribute("type"); // only check
      } catch (Exception $ex) {
        Logger::user_warning($ex->getMessage());
        continue;
      }
      $this->createFieldset($newContent, $fieldset, $e);
    }
    $fieldset->parentNode->removeChild($fieldset);
    $vars = [];
    if (is_null($this->passwd)) {
      $vars["nopasswd"] = "";
    }
    $vars["action"] = "?".$this->className;
    $newContent->processVariables($vars);
    return $newContent;
  }

  /**
   * @param HTMLPlus $content
   * @param DOMElementPlus $fieldset
   * @param DOMElementPlus $set
   */
  private function createFieldset (HTMLPlus $content, DOMElementPlus $fieldset, DOMElementPlus $set) {
    switch ($set->getAttribute("type")) {
      case "text":
      case "select":
        $this->createFs($content, $fieldset, $set, $set->getAttribute("type"));
        break;
      default:
        Logger::user_warning(sprintf(_("Unsupported attribute type value '%s'"), $set->getAttribute("type")));
    }
  }

  /**
   * @param DOMDocumentPlus $content
   * @param DOMElementPlus $fieldset
   * @param DOMElementPlus $set
   * @param string $type
   */
  private function createFs (DOMDocumentPlus $content, DOMElementPlus $fieldset, DOMElementPlus $set, $type) {
    $for = $set->getAttribute("for");
    foreach (explode(" ", $for) as $rule) {
      $inputVar = null;
      $list = $this->filterVars($rule);
      if (!count($list)) {
        continue;
      }
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($fieldset, true));
      switch ($type) {
        case "text":
          $inputVar = $this->createTextFs($list, $set);
          break;
        case "select":
          $inputVar = $this->createSelectFs($list, $set);
          break;
        default:
          // double check?
          return;
      }
      if (is_null($inputVar)) {
        Logger::user_warning(sprintf(_("Cannot create fieldset for %s"), $rule)); // never happend?
        continue;
      }
      $vars["group"] = strlen($set->nodeValue) ? $set->nodeValue : $rule;
      $vars["inputs"] = $inputVar;
      $doc->processVariables($vars);
      $fieldset->parentNode->insertBefore($content->importNode($doc->documentElement, true), $fieldset);
    }
  }

  /**
   * @param string $rule
   * @return array
   */
  private function filterVars ($rule) {
    $r = [];
    foreach ($this->vars as $v) {
      $id = $v->getAttribute("id");
      if (!@preg_match("/^".$rule."$/", $id)) {
        continue;
      }
      $r[] = $v;
    }
    return $r;
  }

  /**
   * TODO refactor: neopakovat kod ...
   * @param array $list
   * @param DOMElementPlus $set
   * @return DOMNode
   */
  private function createTextFs (Array $list, DOMElementPlus $set) {
    $inputDoc = new DOMDocumentPlus();
    $inputVar = $inputDoc->appendChild($inputDoc->createElement("var"));
    $dl = $inputVar->appendChild($inputDoc->createElement("dl"));
    foreach ($list as $v) {
      $id = normalize($this->className."-".$v->getAttribute("id"));
      $dt = $inputDoc->createElement("dt");
      $label = $inputDoc->createElement("label", $v->getAttribute("id"));
      $dt->appendChild($label);
      $label->setAttribute("for", $id);
      $dl->appendChild($dt);
      $dd = $inputDoc->createElement("dd");
      $text = $inputDoc->createElement("input");
      $dd->appendChild($text);
      $text->setAttribute("type", "text");
      $text->setAttribute("id", $id);
      $text->setAttribute("name", $v->getAttribute("id"));
      $text->setAttribute("value", $v->nodeValue);
      if ($set->hasAttribute("placeholder")) {
        $text->setAttribute("placeholder", $set->getAttribute("placeholder"));
      }
      if ($set->hasAttribute("pattern")) {
        $text->setAttribute("pattern", $set->getAttribute("pattern"));
      }
      $dd->appendChild($text);
      $dl->appendChild($dd);
    }
    return $inputVar;
  }

  /**
   * @param array $list
   * @param DOMElementPlus $set
   * @return DOMNode
   */
  private function createSelectFs (Array $list, DOMElementPlus $set) {
    $inputDoc = new DOMDocumentPlus();
    $inputVar = $inputDoc->appendChild($inputDoc->createElement("var"));
    $dl = $inputVar->appendChild($inputDoc->createElement("dl"));
    $dataListArray = [];
    foreach (explode(" ", $set->getAttribute("datalist")) as $d) {
      $dataListArray = array_merge($dataListArray, $this->filterVars($d));
    }
    foreach ($list as $v) {
      $id = normalize($this->className."-".$v->getAttribute("id"));
      try {
        $select = $this->createSelect($inputDoc, $dataListArray, $v->getAttribute("id"));
      } catch (Exception $e) {
        Logger::critical($e->getMessage());
        continue;
      }
      $dt = $inputDoc->createElement("dt");
      $label = $inputDoc->createElement("label", $v->nodeValue);
      $dt->appendChild($label);
      $label->setAttribute("for", $id);
      $dl->appendChild($dt);
      $dd = $inputDoc->createElement("dd");
      $dd->appendChild($select);
      $select->setAttribute("id", $id);
      $dl->appendChild($dd);
    }
    return $inputVar;
  }

  /**
   * @param DOMDocumentPlus $doc
   * @param array $vars
   * @param string $selectId
   * @return DOMElementPlus
   */
  private function createSelect (DOMDocumentPlus $doc, Array $vars, $selectId) {
    $select = $doc->createElement("select");
    $select->setAttribute("name", $selectId);
    $select->setAttribute("required", "required");
    /*if(is_null($this->vars[$selectId]->firstElement) ||
      !$this->vars[$selectId]->firstElement->hasAttribute("var")) {
      throw new Exception(sprintf(_("Variable %s missing inner element with attribute var"), $selectId));
    }*/
    $selected = substr($this->vars[$selectId]->getAttribute("var"), strlen($this->className) + 1);
    foreach ($vars as $v) {
      $id = $v->getAttribute("id");
      $value = strlen($v->nodeValue) ? $v->nodeValue : $id;
      $option = $doc->createElement("option", $value);
      $doc->appendChild($option);
      $option->setAttribute("value", $id);
      if ($id == $selected) {
        $option->setAttribute("selected", "selected");
      }
      $select->appendChild($option);
    }
    return $select;
  }

}

?>
