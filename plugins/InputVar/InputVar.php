<?php

#TODO: silent error support (@ sign)

class InputVar extends Plugin implements SplObserver {
  private $contentXPath;

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 1);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_INIT) return;
    $dom = $this->getDOMPlus();
    foreach($dom->documentElement->childElements as $e) {
      if(!$e->hasAttribute("id")) {
        new Logger(sprintf(_("Missing attribute id in element %s"), $e->nodeName), Logger::LOGGER_WARNING);
        continue;
      }
      $data = null;
      switch($e->nodeName) {
        case "var":
        if(!$e->hasAttribute("data")) {
          new Logger(sprintf(_("Element var id=%s missing attribute data"), $e->getAttribute("id")), Logger::LOGGER_WARNING);
          continue;
        }
        $data = $e->getAttribute("data");
        case "fn":
        $this->processRule($e, $data);
        break;
        default:
        new Logger(sprintf(_("Unknown element name %s"), $e->nodeName), Logger::LOGGER_WARNING);
      }
    }
  }

  private function setVar($var, $name, $value) {
    if($var == "fn") Cms::setFunction($name, $value);
    else Cms::setVariable($name, $value);
  }

  private function processRule(DOMElement $e, $data) {
    $data = $this->parse($data);
    if(!$e->hasAttribute("fn")) {
      $this->setVar($e->nodeName, $e->getAttribute("id"), $data);
      return;
    }
    $f = $e->getAttribute("fn");
    if(strpos($f, "-") === false) $f = get_class($this)."-$f";
    $result = Cms::getFunction($f);
    if(!is_null($data) && !is_null($result)) {
      try {
        $result = Cms::applyUserFn($f, $data);
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
          new Logger(_("Function translate missing attribute name"), Logger::LOGGER_WARNING);
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
    if(is_null($data)) Cms::setFunction($id, $fn);
    else Cms::setVariable($id, $fn($data));
  }

  private function parse($value) {
    if(is_null($value)) return null;
    $output = array();
    foreach(explode('\$', $value) as $s) {
      $r = array();
      preg_match_all('/@?\$('.VARIABLE_PATTERN.')/', $s, $match);
      foreach($match[1] as $k => $var) {
        $varVal = Cms::getVariable($var);
        if(is_null($varVal))
          $varVal = Cms::getVariable(get_class($this)."-$var");
        if(is_null($varVal)) {
          if(strpos($match[0][$k], "@") !== 0)
            new Logger(sprintf(_("Variable '%s' does not exist"), $var), Logger::LOGGER_WARNING);
          continue;
        }
        $r[$var] = $varVal;
      }
      $i = 0;
      foreach($r as $var => $varVal) {
        $s = str_replace($match[0][$i++], $varVal, $s);
      }
      $output[] = $s;
    }
    return implode('$', $output);
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
      new Logger(_("No data found for function translate"), Logger::LOGGER_WARNING);
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
      return "<a href='$href'".(is_null($title) ? "" : " title='$title'")
        .">".$value."</a>";
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