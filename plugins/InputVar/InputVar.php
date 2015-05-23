<?php

#TODO: silent error support (@ sign)

class InputVar extends Plugin implements SplObserver, ContentStrategyInterface {
  private $userCfgPath = null;
  private $contentXPath;
  private $cfg = null;
  private $formId = null;
  private $vars = array();

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $this->userCfgPath = USER_FOLDER."/".$this->pluginDir."/".get_class($this).".xml";
    $s->setPriority($this, 5);
  }

  public function update(SplSubject $subject) {
    try {
      if($subject->getStatus() == STATUS_POSTPROCESS) $this->processPost();
      if(!in_array($subject->getStatus(), array(STATUS_INIT, STATUS_PROCESS))) return;
      if($subject->getStatus() == STATUS_INIT) $this->loadVars();
      $this->cfg = $this->getDOMPlus();
      foreach($this->cfg->documentElement->childElementsArray as $e) {
        if($e->nodeName == "edit") continue;
        if(!$e->hasAttribute("id")) {
          new Logger(sprintf(_("Missing attribute id in element %s"), $e->nodeName), Logger::LOGGER_WARNING);
          continue;
        }
        switch($e->nodeName) {
          case "var":
          if($subject->getStatus() != STATUS_PROCESS) continue;
          $this->processRule($e);
          case "fn":
          if($subject->getStatus() != STATUS_INIT) continue;
          $this->processRule($e);
          break;
          default:
          new Logger(sprintf(_("Unknown element name %s"), $e->nodeName), Logger::LOGGER_WARNING);
        }
      }
    } catch(Exception $ex) {
      new Logger($ex->getMessage(), Logger::LOGGER_ERROR);
    }
    #var_dump(Cms::getAllVariables());
  }

  private function loadVars() {
    if(!is_file(USER_FOLDER."/".$this->pluginDir."/".get_class($this).".xml")) return;
    $userCfg = new DOMDocumentPlus();
    if(!@$userCfg->load($this->userCfgPath))
      throw new Exception(sprintf(_("Unable to load content from user config")));
    foreach($userCfg->documentElement->childElementsArray as $e) {
      if($e->nodeName == "var") $this->vars[$e->getAttribute("id")] = $e;
    }

  }

  public function getContent(HTMLPlus $content) {
    if(!isset($_GET[get_class($this)])) return $content;
    $newContent = $this->getHTMLPlus();
    $this->formId = $newContent->getElementsByTagName("form")->item(0)->getAttribute("id");
    $fieldset = $newContent->getElementsByTagName("fieldset")->item(0);
    foreach($this->cfg->getElementsByTagName("edit") as $e) {
      $this->createDl($newContent, $fieldset, $e);
    }
    $fieldset->parentNode->removeChild($fieldset);
    $vars = array();
    $vars["action"] = "?".get_class($this);
    $newContent->processVariables($vars);
    return $newContent;
  }

  private function createDl(HTMLPlus $content, DOMElementPlus $fieldset, DOMElementPlus $edit) {
    $for = $edit->getAttribute("for");
    $dataList = $edit->getAttribute("datalist");
    foreach(explode(" ", $for) as $rule) {
      $list = $this->filterVars($rule);
      if(!count($list)) continue;
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($fieldset, true));
      $inputDoc = new DOMDocumentPlus();
      $inputVar = $inputDoc->appendChild($inputDoc->createElement("var"));
      $dl = $inputVar->appendChild($inputDoc->createElement("dl"));
      $dataListArray = array();
      foreach(explode(" ", $dataList) as $d) {
        $dataListArray = array_merge($dataListArray, $this->filterVars($d));
      }
      foreach($list as $v) {
        $id = normalize(get_class($this)."-".$v->getAttribute("id"));
        try {
          $select = $this->createSelect($inputDoc, $dataListArray, $v->getAttribute("id"));
        } catch(Exception $e) {
          new Logger($e->getMessage(), Logger::LOGGER_WARNING);
          continue;
        }
        $dt = $inputDoc->createElement("dt");
        $label = $dt->appendChild($inputDoc->createElement("label", $v->nodeValue));
        $label->setAttribute("for", $id);
        $dl->appendChild($dt);
        $dd = $inputDoc->createElement("dd");
        $dd->appendChild($select);
        $select->setAttribute("id", $id);
        $dl->appendChild($dd);
      }
      $vars["group"] = strlen($edit->nodeValue) ? $edit->nodeValue : $rule;
      $vars["inputs"] = $inputVar;
      $doc->processVariables($vars);
      $fieldset->parentNode->insertBefore($content->importNode($doc->documentElement, true), $fieldset);
    }
  }

  private function createSelect(DOMDocumentPlus $doc, Array $vars, $selectId) {
    $select = $doc->createElement("select");
    $select->setAttribute("name", $selectId);
    $select->setAttribute("required", "required");
    if(is_null($this->vars[$selectId]->firstElement) ||
      !$this->vars[$selectId]->firstElement->hasAttribute("var")) {
      throw new Exception(sprintf(_("Variable %s missing inner element with attribute var"), $selectId));
    }
    $selected = substr($this->vars[$selectId]->firstElement->getAttribute("var"), strlen(get_class($this))+1);
    foreach($vars as $v) {
      $id = $v->getAttribute("id");
      $value = strlen($v->nodeValue) ? $v->nodeValue : $id;
      $option = $doc->appendChild($doc->createElement("option", $value));
      $option->setAttribute("value", $id);
      if($id == $selected) $option->setAttribute("selected", "selected");
      $select->appendChild($option);
    }
    return $select;
  }

  private function filterVars($rule) {
    $r = array();
    foreach($this->vars as $v) {
      $id = $v->getAttribute("id");
      if(!@preg_match("/^".$rule."$/", $id)) continue;
      $r[] = $v;
    }
    return $r;
  }

  private function processPost() {
    if(!is_file(USER_FOLDER."/".$this->pluginDir."/".get_class($this).".xml")) return;
    $req = Cms::getVariable("validateform-".$this->formId);
    if(is_null($req)) return;
    $var = null;
    foreach($req as $k => $v) {
      if(isset($this->vars[$k])) {
        if(is_null($this->vars[$k]->firstElement)) continue;
        $var = $this->vars[$k];
        $this->vars[$k]->firstElement->setAttribute("var", normalize(get_class($this)."-$v"));
      }
    }
    if(@$var->ownerDocument->save($this->userCfgPath) === false)
      throw new Exception(_("Unabe to save user config"));
    if(!IS_LOCALHOST) clearNginxCache();
    Cms::addMessage(_("Changes successfully saved"), Cms::MSG_SUCCESS, true);
    redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => get_class($this)), true));
  }

  private function setVar($var, $name, $value) {
    if($var == "fn") Cms::setFunction($name, $value);
    else Cms::setVariable($name, $value);
  }

  private function processRule(DOMElement $el) {
    $el = $el->processVariables(Cms::getAllVariables(), array(), true);
    if(!$el->hasAttribute("fn")) {
      $this->setVar($el->nodeName, $el->getAttribute("id"), $el);
      return;
    }
    $f = $el->getAttribute("fn");
    if(strpos($f, "-") === false) $f = get_class($this)."-$f";
    $result = Cms::getFunction($f);
    if($el->hasAttribute("fn") && !is_null($result)) {
      try {
        $result = Cms::applyUserFn($f, $el);
      } catch(Exception $e) {
        new Logger(sprintf(_("Unable to apply function: %s"), $e->getMessage()), Logger::LOGGER_WARNING);
        return;
      }
    }
    if(!is_null($result)) {
      $this->setVar($el->nodeName, $el->getAttribute("id"), $result);
      return;
    }
    try {
      $fn = $this->register($el);
    } catch(Exception $e) {
      new Logger(sprintf(_("Unable to register function %s: %s"), $el->getAttribute("fn"), $e->getMessage()), Logger::LOGGER_WARNING);
      return;
    }
    $id = $el->getAttribute("id");
    if($el->nodeName == "fn") Cms::setFunction($id, $fn);
    else Cms::setVariable($id, $fn($el));
  }

  private function register(DOMElement $el) {
    $id = $el->getAttribute("id");
    $fn = null;
    switch($el->getAttribute("fn")) {
      case "hash":
      $algo = $el->hasAttribute("algo") ? $el->getAttribute("algo") : null;
      $fn = $this->createFnHash($id, $this->parse($algo));
      break;
      case "strftime":
      $format = $el->hasAttribute("format") ? $el->getAttribute("format") : null;
      $fn = $this->createFnStrftime($id, $format);
      break;
      case "sprintf":
      $fn = $this->createFnSprintf($id, $el->nodeValue);
      break;
      case "replace":
      $tr = array();
      foreach($el->childElementsArray as $d) {
        if($d->nodeName != "data") continue;
        if(!$d->hasAttribute("name")) {
          new Logger(_("Element data missing attribute name"), Logger::LOGGER_WARNING);
          continue;
        }
        $tr["~(?<!\pL)".$d->getAttribute("name")."(?!\pL)~u"] = $this->parse($d->nodeValue);
      }
      $fn = $this->createFnReplace($id, $tr);
      break;
      case "sequence":
      $seq = array();
      foreach($el->childElementsArray as $call) {
        if($call->nodeName != "call") continue;
        if(!strlen($call->nodeValue)) {
          new Logger(_("Element call missing content"), Logger::LOGGER_WARNING);
          continue;
        }
        $seq[] = $call->nodeValue;
      }
      $fn = $this->createFnSequence($id, $seq);
      break;
      default:
      throw new Exception(_("Unknown function name"));
    }
    return $fn;
  }

  private function parse($value) {
    return replaceVariables($value, Cms::getAllVariables(), strtolower(get_class($this)."-"));
  }

  private function createFnHash($id, $algo=null) {
    if(!in_array($algo, hash_algos())) $algo = "crc32b";
    return function(DOMNode $node) use ($algo) {
      return hash($algo, $node->nodeValue);
    };
  }

  private function createFnStrftime($id, $format=null) {
    if(is_null($format)) $format = "%m/%d/%Y";
    $format = $this->crossPlatformCompatibleFormat($format);
    return function(DOMNode $node) use ($format) {
      $value = $node->nodeValue;
      $date = trim(strftime($format, strtotime($value)));
      return $date ? $date : $value;
    };
  }

  private function createFnReplace($id, Array $replace) {
    if(empty($replace)) throw new Exception(_("No data found"));
    return function(DOMNode $node) use ($replace) {
      #return str_replace(array_keys($replace), $replace, $node->nodeValue);
      return preg_replace(array_keys($replace), $replace, $node->nodeValue);
    };
  }

  private function createFnSprintf($id, $format) {
    if(!strlen($format)) throw new Exception(_("No content found"));
    return function(DOMNode $node) use ($format) {
      $elements = array();
      foreach($node->childNodes as $child) {
        if($child->nodeType != XML_ELEMENT_NODE) break;
        $elements[] = $child->nodeValue;
      }
      if(empty($elements)) $elements[] = $node->nodeValue;
      $temp = @vsprintf($format, $elements);
      if($temp === false) return $format;
      return $temp;
    };
  }

  private function createFnSequence($id, Array $call) {
    if(empty($call)) throw new Exception(_("No data found"));
    return function(DOMNode $node) use ($call) {
      foreach($call as $f) {
        if(strpos($f, "-") === false) $f = get_class($this)."-$f";
        try {
          $node = new DOMElement("any", Cms::applyUserFn($f, $node));
        } catch(Exception $e) {
          new Logger(sprintf(_("Sequence call skipped: %s"), $e->getMessage()), Logger::LOGGER_WARNING);
        }
      }
      return $node->nodeValue;
    };
  }

  /**
   * http://php.net/manual/en/function.strftime.php
   */
  private function crossPlatformCompatibleFormat($format) {
    // Jan 1: results in: '%e%1%' (%%, e, %%, %e, %%)
    #$format = '%%e%%%e%%';

    // Check for Windows to find and replace the %e
    // modifier correctly
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
      $format = preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $format);
    }

    return $format;
  }

}

?>