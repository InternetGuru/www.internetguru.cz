<?php

class InputVar extends Plugin implements SplObserver, ContentStrategyInterface {
  private $userCfgPath = null;
  private $contentXPath;
  private $cfg = null;
  private $formId = null;
  private $passwd = null;
  private $getOk = "ivok";
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
      if($subject->getStatus() == STATUS_INIT) {
        if(isset($_GET[$this->getOk])) Cms::addMessage(_("Changes successfully saved"), Cms::MSG_SUCCESS);
        $this->loadVars();
      }
      $this->cfg = $this->getDOMPlus();
      foreach($this->cfg->documentElement->childElementsArray as $e) {
        if($e->nodeName == "set") continue;
        if($e->nodeName == "passwd") continue;
        if(!$e->hasAttribute("id")) {
          Logger::log(sprintf(_("Missing attribute id in element %s"), $e->nodeName), Logger::LOGGER_WARNING);
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
          Logger::log(sprintf(_("Unknown element name %s"), $e->nodeName), Logger::LOGGER_WARNING);
        }
      }
    } catch(Exception $ex) {
      Cms::addMessage($ex->getMessage(), Cms::MSG_ERROR);
    }
  }

  private function loadVars() {
    if(!is_file(USER_FOLDER."/".$this->pluginDir."/".get_class($this).".xml")) return;
    $userCfg = new DOMDocumentPlus();
    if(!@$userCfg->load($this->userCfgPath))
      throw new Exception(sprintf(_("Unable to load content from user config")));
    foreach($userCfg->documentElement->childElementsArray as $e) {
      if($e->nodeName == "var") $this->vars[$e->getAttribute("id")] = $e;
      if(!IS_LOCALHOST && $e->nodeName == "passwd") $this->passwd = $e->nodeValue;
    }

  }

  public function getContent(HTMLPlus $content) {
    if(!isset($_GET[get_class($this)])) return $content;
    $newContent = $this->getHTMLPlus();
    $form = $newContent->getElementsByTagName("form")->item(0);
    $this->formId = $form->getAttribute("id");
    $fieldset = $newContent->getElementsByTagName("fieldset")->item(0);
    foreach($this->cfg->getElementsByTagName("set") as $e) {
      if(!$e->hasAttribute("type")) {
        Logger::log(_("Element set missing attribute type"), Logger::LOGGER_WARNING);
        continue;
      }
      $this->createFieldset($newContent, $fieldset, $e);
    }
    $fieldset->parentNode->removeChild($fieldset);
    $vars = array();
    if(is_null($this->passwd)) $vars["nopasswd"] = "";
    $vars["action"] = "?".get_class($this);
    $newContent->processVariables($vars);
    return $newContent;
  }

  private function createFieldset(HTMLPlus $content, DOMElementPlus $fieldset, DOMElementPlus $set) {
    switch($set->getAttribute("type")) {
      case "text":
      case "select":
      $this->createFs($content, $fieldset, $set, $set->getAttribute("type"));
      break;
      default:
      Logger::log(sprintf(_("Element set uknown type %s"), $set->getAttribute("type")), Logger::LOGGER_WARNING);
    }
  }

  private function createFs(DOMDocumentPlus $content, DOMElementPlus $fieldset, DOMElementPlus $set, $type) {
    $for = $set->getAttribute("for");
    foreach(explode(" ", $for) as $rule) {
      $inputVar = null;
      $list = $this->filterVars($rule);
      if(!count($list)) continue;
      $doc = new DOMDocumentPlus();
      $doc->appendChild($doc->importNode($fieldset, true));
      switch($type) {
        case "text":
        $inputVar = $this->createTextFs($list, $set, $rule);
        break;
        case "select":
        $inputVar = $this->createSelectFs($list, $set);
        break;
        default:
        // double check?
        return;
      }
      if(is_null($inputVar)) {
        Logger::log(sprintf(_("Cannot create fieldset for %s"), $rule), Logger::LOGGER_WARNING); // never happend?
        continue;
      }
      $vars["group"] = strlen($set->nodeValue) ? $set->nodeValue : $rule;
      $vars["inputs"] = $inputVar;
      $doc->processVariables($vars);
      $fieldset->parentNode->insertBefore($content->importNode($doc->documentElement, true), $fieldset);
    }
  }

  private function createSelectFs(Array $list, DOMElementPlus $set) {
    $inputDoc = new DOMDocumentPlus();
    $inputVar = $inputDoc->appendChild($inputDoc->createElement("var"));
    $dl = $inputVar->appendChild($inputDoc->createElement("dl"));
    $dataListArray = array();
    foreach(explode(" ", $set->getAttribute("datalist")) as $d) {
      $dataListArray = array_merge($dataListArray, $this->filterVars($d));
    }
    foreach($list as $v) {
      $id = normalize(get_class($this)."-".$v->getAttribute("id"));
      try {
        $select = $this->createSelect($inputDoc, $dataListArray, $v->getAttribute("id"));
      } catch(Exception $e) {
        Logger::log($e->getMessage(), Logger::LOGGER_WARNING);
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
    return $inputVar;
  }

  # todo refactor: neopakovat kod ...
  private function createTextFs(Array $list, DOMElementPlus $set, $rule) {
    $inputDoc = new DOMDocumentPlus();
    $inputVar = $inputDoc->appendChild($inputDoc->createElement("var"));
    $dl = $inputVar->appendChild($inputDoc->createElement("dl"));
    foreach($list as $v) {
      $id = normalize(get_class($this)."-".$v->getAttribute("id"));
      $dt = $inputDoc->createElement("dt");
      $label = $dt->appendChild($inputDoc->createElement("label", $v->getAttribute("id")));
      $label->setAttribute("for", $id);
      $dl->appendChild($dt);
      $dd = $inputDoc->createElement("dd");
      $text = $dd->appendChild($inputDoc->createElement("input"));
      $text->setAttribute("type", "text");
      $text->setAttribute("id", $id);
      $text->setAttribute("name", $v->getAttribute("id"));
      $text->setAttribute("value", $v->nodeValue);
      if($set->hasAttribute("placeholder"))
        $text->setAttribute("placeholder", $set->getAttribute("placeholder"));
      if($set->hasAttribute("pattern"))
        $text->setAttribute("pattern", $set->getAttribute("pattern"));
      $dd->appendChild($text);
      $dl->appendChild($dd);
    }
    return $inputVar;
  }

  private function createSelect(DOMDocumentPlus $doc, Array $vars, $selectId) {
    $select = $doc->createElement("select");
    $select->setAttribute("name", $selectId);
    $select->setAttribute("required", "required");
    /*if(is_null($this->vars[$selectId]->firstElement) ||
      !$this->vars[$selectId]->firstElement->hasAttribute("var")) {
      throw new Exception(sprintf(_("Variable %s missing inner element with attribute var"), $selectId));
    }*/
    $selected = substr($this->vars[$selectId]->getAttribute("var"), strlen(get_class($this))+1);
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
    if(isset($req["passwd"]) && !hash_equals($this->passwd, crypt($req["passwd"], $this->passwd))) {
      throw new Exception(_("Incorrect password"));
    }
    $var = null;
    foreach($req as $k => $v) {
      if(isset($this->vars[$k])) {
        if($this->vars[$k]->hasAttribute("var"))
          $this->vars[$k]->setAttribute("var", normalize(get_class($this)."-$v"));
        else
          $this->vars[$k]->nodeValue = $v;
        $var = $this->vars[$k];
      }
    }
    if(@$var->ownerDocument->save($this->userCfgPath) === false)
      throw new Exception(_("Unabe to save user config"));
    if(!IS_LOCALHOST) clearNginxCache();
    redirTo(buildLocalUrl(array("path" => getCurLink(), "query" => get_class($this)."&".$this->getOk), true));
  }

  private function setVar($var, $name, $value) {
    if($var == "fn") Cms::setFunction($name, $value);
    else Cms::setVariable($name, $value);
  }

  private function processRule(DOMElement $element) {
    $id = $element->getAttribute("id");
    $name = $element->nodeName;
    $attributes = array();
    foreach($element->attributes as $attr) $attributes[$attr->nodeName] = $attr->nodeValue;
    $el = $element->processVariables(Cms::getAllVariables(), array(), true); // pouze posledni node
    if(is_null($el) || (gettype($el) == "object" && get_class($el) != "DOMElementPlus" && !$el->isSameNode($element))) {
      if(is_null($el)) $el = new DOMText("");
      $var = $element->ownerDocument->createElement("var");
      foreach($attributes as $aName => $aValue) {
        $var->setAttribute($aName, $aValue);
      }
      $var->appendChild($el);
      $el = $var;
    }
    if(!$el->hasAttribute("fn")) {
      $this->setVar($name, $id, $el);
      return;
    }
    $f = $el->getAttribute("fn");
    if(strpos($f, "-") === false) $f = get_class($this)."-$f";
    $result = Cms::getFunction($f);
    if($el->hasAttribute("fn") && !is_null($result)) {
      try {
        $result = Cms::applyUserFn($f, $el);
      } catch(Exception $e) {
        Logger::log(sprintf(_("Unable to apply function: %s"), $e->getMessage()), Logger::LOGGER_WARNING);
        return;
      }
    }
    if(!is_null($result)) {
      $this->setVar($el->nodeName, $id, $result);
      return;
    }
    try {
      $fn = $this->register($el);
    } catch(Exception $e) {
      Logger::log(sprintf(_("Unable to register function %s: %s"), $el->getAttribute("fn"), $e->getMessage()), Logger::LOGGER_WARNING);
      return;
    }
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
      case "pregreplace":
      $pattern = $el->hasAttribute("pattern") ? $el->getAttribute("pattern") : null;
      $replacement = $el->hasAttribute("replacement") ? $el->getAttribute("replacement") : null;
      $fn = $this->createFnPregReplace($id, $pattern, $replacement);
      break;
      case "replace":
      $tr = array();
      foreach($el->childElementsArray as $d) {
        if($d->nodeName != "data") continue;
        if(!$d->hasAttribute("name")) {
          Logger::log(_("Element data missing attribute name"), Logger::LOGGER_WARNING);
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
          Logger::log(_("Element call missing content"), Logger::LOGGER_WARNING);
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

  private function createFnPregReplace($id, $pattern, $replacement) {
    if(is_null($pattern)) throw new Exception(_("No pattern found"));
    if(is_null($replacement)) throw new Exception(_("No replacement found"));
    return function(DOMNode $node) use ($pattern, $replacement) {
      return preg_replace("/^(?:".$pattern.")$/", $replacement, $node->nodeValue);
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
          Logger::log(sprintf(_("Sequence call skipped: %s"), $e->getMessage()), Logger::LOGGER_WARNING);
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