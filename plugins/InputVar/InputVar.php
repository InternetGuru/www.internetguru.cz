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
    foreach($dom->documentElement->childElements as $e) {
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

  private function processRule(DOMElement $e) {
    $e->processVariables(Cms::getAllVariables(), array(), true, $e);
    if(!$e->hasAttribute("fn")) {
      $this->setVar($e->nodeName, $e->getAttribute("id"), $e);
      return;
    }
    $f = $e->getAttribute("fn");
    if(strpos($f, "-") === false) $f = get_class($this)."-$f";
    $result = Cms::getFunction($f);
    if($e->hasAttribute("fn") && !is_null($result)) {
      try {
        $result = Cms::applyUserFn($f, $e->nodeValue);
      } catch(Exception $e) {
        new Logger(sprintf(_("Unable to apply function: %s"), $e->getMessage()), Logger::LOGGER_WARNING);
        return;
      }
    }
    if(!is_null($result)) {
      $this->setVar($e->nodeName, $e->getAttribute("id"), $result);
      return;
    }
    $id = $e->getAttribute("id");
    switch($e->getAttribute("fn")) {
      case "hash":
      $algo = $e->hasAttribute("algo") ? $e->getAttribute("algo") : null;
      $fn = $this->createFnHash($id, $this->parse($algo));
      break;
      case "strftime":
      $format = $e->hasAttribute("format") ? $e->getAttribute("format") : null;
      $fn = $this->createFnStrftime($id, $format);
      break;
      case "replace":
      $tr = array();
      foreach($e->childElements as $d) {
        if($d->nodeName != "data") continue;
        if(!$d->hasAttribute("name")) {
          new Logger(_("Function replace missing attribute name"), Logger::LOGGER_WARNING);
          continue;
        }
        $tr[$d->getAttribute("name")] = $this->parse($d->nodeValue);
      }
      $fn = $this->createFnReplace($id, $tr);
      break;
      case "sequence":
      $seq = array();
      foreach($e->childElements as $call) {
        if($call->nodeName != "call") continue;
        if(!$call->hasAttribute("fn")) {
          new Logger(_("Function sequence missing attribute fn"), Logger::LOGGER_WARNING);
          continue;
        }
        $seq[] = $call->getAttribute("fn");
      }
      $fn = $this->createFnSequence($id, $seq);
      break;
      case "link":
      $href = "";
      $title = null;
      if($e->hasAttribute("href")) $href = $e->getAttribute("href");
      if($e->hasAttribute("title")) $title = $e->getAttribute("title");
      $fn = $this->createFnLink($id, $this->parse($href), $this->parse($title));
      break;
      default:
      new Logger(sprintf(_("Unknown function name %s"), $e->getAttribute("fn")), Logger::LOGGER_WARNING);
      return;
    }
    if($e->nodeName == "fn") Cms::setFunction($id, $fn);
    else Cms::setVariable($id, $fn($e->nodeValue));
  }

  private function parse($value) {
    return replaceVariables($value, Cms::getAllVariables(), strtolower(get_class($this)."-"));
  }

  private function createFnHash($id, $algo=null) {
    if(!in_array($algo, hash_algos())) $algo = "crc32b";
    return function($value) use ($algo) {
      return hash($algo, $value);
    };
  }

  private function createFnStrftime($id, $format=null) {
    if(is_null($format)) $format = "%m/%d/%Y";
    $format = $this->crossPlatformCompatibleFormat($format);
    return function($value) use ($format) {
      $date = trim(strftime($format, strtotime($value)));
      return $date ? $date : $value;
    };
  }

  private function createFnReplace($id, Array $replace) {
    if(empty($replace)) {
      new Logger(_("No data found for function replace"), Logger::LOGGER_WARNING);
      return;
    }
    return function($value) use ($replace) {
      return str_replace(array_keys($replace), $replace, $value);
    };
  }

  private function createFnSequence($id, Array $call) {
    if(empty($call)) {
      new Logger(_("No data found for function sequence"), Logger::LOGGER_WARNING);
      return;
    }
    return function($value) use ($call) {
      foreach($call as $f) {
        if(strpos($f, "-") === false) $f = get_class($this)."-$f";
        try {
          $value = Cms::applyUserFn($f, $value);
        } catch(Exception $e) {
          new Logger(sprintf(_("Sequence call skipped: %s"), $e->getMessage()), Logger::LOGGER_WARNING);
        }
      }
      return $value;
    };
  }

  private function createFnLink($id, $href="", $title=null) {
    return function($value) use ($href, $title) {
      return "<a href='$href'".(is_null($title) ? "" : " title='$title'").">".$value."</a>";
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