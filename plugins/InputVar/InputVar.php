<?php

#TODO: silent error support (@ sign)

class InputVar extends Plugin implements SplObserver {
  private $contentXPath;

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PROCESS) return;
    $dom = $this->getDOMPlus();
    $this->registerFnHash("hash", null, "crc32b");
    $this->registerFnStrftime("strftime", null);
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

  private function processRule(DOMElement $e, $data) {
    $value = $this->parse($data);
    if(!$e->hasAttribute("fn")) {
      Cms::setVariable($e->getAttribute("id"), $value);
      return;
    }
    $fn = Cms::getVariable($e->getAttribute("fn"));
    echo $e->getAttribute("fn");
    print_r(array_keys(Cms::getAllVariables()));
    var_dump($fn);
    if(!is_null($fn)) {
      if(!$fn instanceof Closure) {
        new Logger(sprintf(_("Variable %s is not a function"), $e->getAttribute("fn")), Logger::LOGGER_WARNING);
        return;
      }
      Cms::setVariable($e->getAttribute("id"), $fn($value));
      return;
    }
    switch($e->getAttribute("fn")) {
      case "hash":
      $algo = $e->hasAttribute("algo") ? $e->getAttribute("algo") : null;
      $this->registerFnHash($e->getAttribute("id"), $data, $this->parse($algo));
      break;
      case "strftime":
      $format = $e->hasAttribute("format") ? $e->getAttribute("format") : null;
      $this->registerFnStrftime($e->getAttribute("id"), $data, $this->parse($format));
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
      $this->registerFnReplace($e->getAttribute("id"), $data, $tr);
      break;
      case "sequence":
      $fn = array();
      foreach($e->childElements as $call) {
        if($call->nodeName != "call") continue;
        if(!$call->hasAttribute("fn")) {
          new Logger(_("Function sequence missing attribute fn"), Logger::LOGGER_WARNING);
          continue;
        }
        $fn[] = $call->getAttribute("fn");
      }
      $this->registerFnSequence($e->getAttribute("id"), $data, $fn);
      break;
      case "link":
      $href = "";
      $title = null;
      if($e->hasAttribute("href")) $href = $e->getAttribute("href");
      if($e->hasAttribute("title")) $title = $e->getAttribute("title");
      $this->registerFnLink($e->getAttribute("id"), $data, $this->parse($href), $this->parse($title));
      break;
      default:
      new Logger(sprintf(_("Unknown function name %s"), $e->getAttribute("fn")), Logger::LOGGER_WARNING);
    }
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
      foreach($r as $var => $varVal) {
        $s = str_replace($match[0][$k], $varVal, $s);
      }
      $output[] = $s;
    }
    return implode('$', $output);
  }

  private function registerFnHash($id, $data, $algo=null) {
    if(!in_array($algo, hash_algos())) $algo = "crc32b";
    $fn = function($value) use ($algo) {
      return hash($algo, $value);
    };
    Cms::setVariable($id, is_null($data) ? $fn : $fn($data));
  }

  private function registerFnStrftime($id, $data, $format=null) {
    if(is_null($format)) $format = "%m/%d/%Y";
    $format = $this->crossPlatformCompatibleFormat($format);
    $fn = function($value) use ($format) {
      $date = trim(strftime($format, strtotime($value)));
      return $date ? $date : $value;
    };
    Cms::setVariable($id, is_null($data) ? $fn : $fn($data));
  }

  private function registerFnReplace($id, $data, Array $replace) {
    if(empty($replace)) {
      new Logger(_("No data found for function translate"), Logger::LOGGER_WARNING);
      return;
    }
    $fn = function($value) use ($replace) {
      return str_replace(array_keys($replace), $replace, $value);
    };
    Cms::setVariable($id, is_null($data) ? $fn : $fn($data));
  }

  private function registerFnSequence($id, $data, Array $call) {
    if(empty($call)) {
      new Logger(_("No data found for function sequence"), Logger::LOGGER_WARNING);
      return;
    }
    $fn = function($value) use ($call) {
      foreach($call as $f) {
        $value = Cms::applyUserFn($f, $value);
      }
      return $value;
    };
    Cms::setVariable($id, is_null($data) ? $fn : $fn($data));
  }

  private function registerFnLink($id, $data, $href="", $title=null) {
    $fn = function($value) use ($href, $title) {
      return "<a href='$href'".(is_null($title) ? "" : " title='$title'")
        .">".$value."</a>";
     };
    Cms::setVariable($id, is_null($data) ? $fn : $fn($data));
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