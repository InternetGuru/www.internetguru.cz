<?php

#TODO: silent error support (@ sign)

class InputVar extends Plugin implements SplObserver {
  private $contentXPath;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
  }

  public function update(SplSubject $subject) {
    if(!in_array($subject->getStatus(), array(STATUS_INIT, STATUS_PROCESS))) return;
    $dom = $this->getDOMPlus();
    foreach($dom->documentElement->childElementsArray as $e) {
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
    #var_dump(Cms::getAllVariables());
  }

  private function setVar($var, $name, $value) {
    if($var == "fn") Cms::setFunction($name, $value);
    else Cms::setVariable($name, $value);
  }

  private function processRule(DOMElement $el) {
    $el->processVariables(Cms::getAllVariables(), array(), true, $el);
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
        $tr[$d->getAttribute("name")] = $this->parse($d->nodeValue);
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
      return str_replace(array_keys($replace), $replace, $node->nodeValue);
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