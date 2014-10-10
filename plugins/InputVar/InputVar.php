<?php

class InputVar extends Plugin implements SplObserver {
  private $contentXPath;

  public function update(SplSubject $subject) {
    if($subject->getStatus() != "process") return;
    $this->subject = $subject;
    $cf = $subject->getCms()->getContentFull();
    $dom = $this->getDOMPlus();
    $vars = $dom->getElementsByTagName("var");
    foreach($vars as $var) $this->parseVar($var);
  }

  private function parseVar(DOMElement $var) {
    // function
    if($var->hasAttribute("fn")) switch($var->getAttribute("fn")) {
      case "hash":
      $value = hash("crc32b", $this->parse($var->nodeValue));
      $this->subject->getCms()->setVariable($var->getAttribute("id"),$value);
      break;
      case "local_link":
      $href = getRoot();
      $title = null;
      if($var->hasAttribute("href")) $href .= $this->parse($var->getAttribute("href"));
      if($var->hasAttribute("title")) $title = $var->getAttribute("title");
      $value = "<a href='$href'" . (is_null($title) ? "" : " title='$title'")
      . ">" . $this->parse($var->nodeValue) . "</a>";
      $this->subject->getCms()->setVariable($var->getAttribute("id"),$value);
      break;
      case "date":
      $format = "n/j/Y";
      if($var->hasAttribute("format")) $format = $var->getAttribute("format");
      $time = false;
      if($var->hasAttribute("date")) $time = strtotime($this->parse($var->getAttribute("date")));
      if(!$time) $date = strftime($format);
      else $date = strftime($format,$time);
      if($date === false) {
        new Logger("Unrecognized date value or format","error");
        return;
      }
      $this->subject->getCms()->setVariable($var->getAttribute("id"),$date);
      break;
    } else {
      $value = $this->parse($var->nodeValue);
      $this->subject->getCms()->setVariable($var->getAttribute("id"),$value);
    }

  }

  private function parse($string) {
    $subStr = explode('\$', $string);
    $output = array();
    foreach($subStr as $s) {
      $r = array();
      preg_match_all('/\$((?:cms-)?[a-z]+)/',$s,$match);
      foreach($match[1] as $var) {
        $varVal = $this->subject->getCms()->getVariable($var);
        if(is_null($varVal)) {
          new Logger("Variable '$var' does not exist","warning");
          $output[] = $s;
          continue;
        }
        $r[$var] = $varVal;
      }
      foreach($r as $var => $varVal) {
        $s = str_replace('$'.$var,$varVal,$s);
      }
      $output[] = $s;
    }
    return implode('$',$output);
  }

}

?>