<?php

class ValidateForm extends Plugin implements SplObserver, ContentStrategyInterface {
  private $labels = array();
  const CSS_WARNING = "validateform-warning";
  const FORM_ID = "validateform-id";

  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 30);
  }

  public function update(SplSubject $subject) {
    if($subject->getStatus() != STATUS_PREINIT) return;
    $this->detachIfNotAttached("HtmlOutput");
    Cms::getOutputStrategy()->addCssFile($this->pluginDir.'/'.get_class($this).'.css');
  }

  public function getContent(HTMLPlus $content) {
    $xpath = new DOMXPath($content);
    foreach($xpath->query("//form") as $form) {
      if(!$form->hasClass("validable")) continue;
      if(!$form->hasAttribute("id")) {
        Logger::log(_("Validable form missing attribute id"));
        continue;
      }
      $securityCheck = true;
      if($form->hasClass("validateform-notime")) $securityCheck = false;
      $id = $form->getAttribute("id");
      $hInput = $content->createElement("input");
      $hInput->setAttribute("type", "hidden");
      $hInput->setAttribute("name", self::FORM_ID);
      $hInput->setAttribute("value", $id);
      $div = $content->createElement("div");
      $div->appendChild($hInput);
      $form->appendChild($div);
      $method = strtolower($form->getAttribute("method"));
      $request = $method == "post" ? $_POST : $_GET;
      if(empty($request)) continue;
      if(!isset($request[self::FORM_ID]) || $request[self::FORM_ID] != $id) continue;
      try {
        if($securityCheck) $this->securityCheck();
      } catch(Exception $e) {
        Cms::addMessage($e->getMessage(), Cms::MSG_ERROR);
        continue;
      }
      $this->getLabels($xpath, $form);
      Cms::setVariable($id, $this->verifyItems($xpath, $form, $request));
    }
    return $content;
  }

  private function getLabels(DOMXPath $xpath, DOMElementPlus $form) {
    foreach($xpath->query(".//label", $form) as $label) {
      if($label->hasAttribute("for")) {
        $id = $label->getAttribute("for");
      } else {
        $items = $xpath->query(".//input | .//textarea | .//select", $label);
        if(!$items->length) continue;
        $item = $items->item(0);
        if(!$item->hasAttribute("id")) $item->setUniqueId();
        $id = $item->getAttribute("id");
      }
      $this->labels[$id][] = $label->nodeValue;
    }
  }

  private function verifyItems(DOMXPath $xpath, DOMElementPlus $form, Array $request) {
    $isValid = true;
    $values = array();
    foreach($xpath->query(".//input | .//textarea | .//select", $form) as $item) {
      try {
        $name = normalize($item->getAttribute("name"), null, "", false);
        $value = isset($request[$name]) ? $request[$name] : null;
        $this->verifyItem($item, $value);
        $values[$name] = is_array($value) ? implode(", ", $value) : $value;
      } catch(Exception $e) {
        if(!$item->hasAttribute("id")) $item->setUniqueId();
        $id = $item->getAttribute("id");
        $error = $e->getMessage();
        if(isset($this->labels[$id][0])) $name = $this->labels[$id][0];
        if(isset($this->labels[$id][1])) $error = $this->labels[$id][1];
        Cms::addMessage(sprintf("<label for='%s'>%s</label>: %s", $id, $name, $error), Cms::MSG_ERROR);
        $item->parentNode->addClass(self::CSS_WARNING);
        $item->addClass(self::CSS_WARNING);
        $isValid = false;
      }
    }
    if(!$isValid) return null;
    return $values;
  }

  private function securityCheck() {
    $IP = getIP();
    $IPFile = str_replace(":", "-", $IP);
    $IPFilePath = USER_FOLDER."/".$this->pluginDir."/$IPFile";
    $bannedIPFilePath = USER_FOLDER."/".$this->pluginDir."/.$IPFile";
    if(is_file($bannedIPFilePath)) {
      throw new Exception(sprintf(_("Your IP adress %s is banned"), $IP));
    }
    if(is_file($IPFilePath)) {
      if(time() - filemtime($IPFilePath)  < 60*2) { // 2 min timeout
        throw new Exception(_("The form can not be sent in such quick succession, please try it again in a minute"));
      }
    }
    mkdir_plus(dirname($IPFilePath));
    if(!touch($IPFilePath)) throw new Exception(_("Unable to create security file"));
  }

  private function verifyItem(DOMElementPlus $e, $value) {
    $pattern = $e->getAttribute("pattern");
    $req = $e->hasAttribute("required");
    $id = $e->getAttribute("id");
    switch($e->nodeName) {
      case "textarea":
      $this->verifyText($value, $pattern, $req);
      break;
      case "input":
      switch($e->getAttribute("type")) {
        case "number":
        $this->verifyText($value, $pattern, $req);
        $min = $e->getAttribute("min");
        $max = $e->getAttribute("max");
        $this->verifyNumber($value, $min, $max);
        break;
        case "email":
        if(!strlen($pattern)) $pattern = EMAIL_PATTERN;
        case "text":
        case "search":
        $this->verifyText($value, $pattern, $req);
        break;
        case "checkbox":
        case "radio":
        if(!is_array($value)) $value = array($value);
        $this->verifyChecked(in_array($e->getAttribute("value"), $value), $req);
        break;
      }
      break;
      case "select":
      $this->verifyText($value, $pattern, $req);
      break;
    }
  }

  private function verifyNumber($value, $min, $max) {
    if(strlen($min) && (int)$value < (int)$min) throw new Exception(_("Item value is lower then allowed minimum"));
    if(strlen($max) && (int)$value > (int)$max) throw new Exception(_("Item value is greater then allowed maximum"));
  }

  private function verifyText($value, $pattern, $required) {
    if(is_null($value)) throw new Exception(_("Value missing"));
    if(!strlen(trim($value))) {
      if(!$required) return;
      throw new Exception(_("Item is required"));
    }
    if(!strlen($pattern)) return;
    $res = @preg_match("/^(?:$pattern)$/", $value);
    if($res === false) {
      Logger::log(_("Invalid item pattern"), Logger::LOGGER_WARNING);
      return;
    }
    if($res === 1) return;
    throw new Exception(_("Item value does not match required format"));
  }

  private function verifyChecked($checked, $required) {
    if(!$required || $checked) return;
    throw new Exception(_("Item must be checked"));
  }

}

?>
