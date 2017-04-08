<?php

namespace IGCMS\Plugins;

use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\Logger;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use SplObserver;
use SplSubject;

/**
 * Class ValidateForm
 * @package IGCMS\Plugins
 */
class ValidateForm extends Plugin implements SplObserver, ModifyContentStrategyInterface {
  /**
   * @var array
   */
  private $labels = array();
  /**
   * @var string
   */
  const CSS_WARNING = "validateform-warning";
  /**
   * @var string
   */
  const FORM_ID = "validateform-id";
  /**
   * @var string
   */
  const FORM_HP = "validateform-hp";
  /**
   * @var int
   */
  const WAIT = 120;

  /**
   * @var int Default input max-length
   */
  const INPUT_MAX_LEN = 128;

  /**
   * @var int Default textarea max-length
   */
  const TEXTAREA_MAX_LEN = 512;

  /**
   * ValidateForm constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct(SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 30);
  }

  /**
   * @param Plugins|SplSubject $subject
   */
  public function update(SplSubject $subject) {
    if(!Cms::isActive()) {
      $subject->detach($this);
      return;
    }
    if($subject->getStatus() != STATUS_INIT) return;
    $this->detachIfNotAttached("HtmlOutput");
    foreach(Cms::getAllVariables() as $varId => $formDoc) {
      if(strpos($varId, "contactform-") !== 0) continue;
      $this->modifyFormVars($formDoc->documentElement->firstChild);
    }
    Cms::getOutputStrategy()->addCssFile($this->pluginDir.'/'.$this->className.'.css');
  }

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent(HTMLPlus $content) {
    foreach($content->getElementsByTagName("form") as $form) {
      $this->modifyFormVars($form);
    }
  }

  /**
   * @param DOMElementPlus $form
   */
  private function modifyFormVars(DOMElementPlus $form) {
    if(!$form->hasClass("validable")) return;
    try {
      $id = $form->getRequiredAttribute("id");
    } catch(Exception $e) {
      Logger::user_warning($e->getMessage());
      return;
    }
    $time = $this->getWaitTime($form);
    if($form->hasClass("validateform-notime")) $time = 0;

    $doc = $form->ownerDocument;
    $div = $doc->createElement("div");

    $input = $doc->createElement("input");
    $input->setAttribute("type", "email");
    $input->setAttribute("name", self::FORM_HP);
    $input->setAttribute("class", self::FORM_HP);
    $div->appendChild($input);

    $input = $doc->createElement("input");
    $input->setAttribute("type", "hidden");
    $input->setAttribute("name", self::FORM_ID);
    $input->setAttribute("value", $id);
    $div->appendChild($input);

    $form->appendChild($div);
    $method = strtolower($form->getAttribute("method"));
    $request = $method == "post" ? $_POST : $_GET;
    if(empty($request)) return;
    if(!isset($request[self::FORM_ID]) || $request[self::FORM_ID] != $id) return;
    if(!$this->hpCheck($request)) {
      Logger::info(_("Honeypot check failed"));
      return;
    }
    $this->getLabels($form);
    $items = $this->verifyItems($form, $request);
    try {
      if(!Cms::isSuperUser() && !is_null($items)) $this->ipCheck($time);
    } catch(Exception $e) {
      Cms::error($e->getMessage());
      return;
    }
    Cms::setVariable($id, $items);
  }

  /**
   * @param array $request
   * @return bool
   */
  private function hpCheck($request) {
    return isset($request[self::FORM_HP]) && !strlen($request[self::FORM_HP]);
  }

  /**
   * @param DOMElementPlus $form
   */
  private function getLabels(DOMElementPlus $form) {
    /** @var DOMElementPlus $label */
    foreach($form->getElementsByTagName("label") as $label) {
      if($label->hasAttribute("for")) {
        $id = $label->getAttribute("for");
      } else {
        $fields = array();
        $this->getFormFields($label, $fields);
        if(count($fields) != 1) {
          Logger::warning(sprintf(_("Invalid label '%s' with %s input fields"), $label->nodeValue, count($fields)));
          continue;
        }
        if(!$fields[0]->hasAttribute("id")) $fields[0]->setUniqueId();
        $id = $fields[0]->getAttribute("id");
      }
      $this->labels[$id][] = $label->nodeValue;
    }
  }

  /**
   * @param DOMElementPlus $e
   * @param array $fields
   */
  private function getFormFields(DOMElementPlus $e, Array &$fields) {
    foreach($e->childNodes as $child) {
      if($child->nodeType != XML_ELEMENT_NODE) continue;
      if(in_array($child->nodeName, array("input", "textarea", "select"))) $fields[] = $child;
      $this->getFormFields($child, $fields);
    }
  }

  /**
   * @param DOMElementPlus $form
   * @param array $request
   * @return array|null
   */
  private function verifyItems(DOMElementPlus $form, Array $request) {
    $isValid = true;
    $values = array();
    $fields = array();
    $this->getFormFields($form, $fields);
    foreach($fields as $field) {
      try {
        $name = normalize($field->getAttribute("name"), null, "", false);
        $value = isset($request[$name]) ? $request[$name] : null;
        $this->verifyItem($field, $value);
        $values[$name] = is_array($value) ? implode(", ", $value) : $value;
      } catch(Exception $e) {
        if(!$field->hasAttribute("id")) $field->setUniqueId();
        $id = $field->getAttribute("id");
        $error = $e->getMessage();
        if(isset($this->labels[$id][0])) $name = $this->labels[$id][0];
        if(isset($this->labels[$id][1])) $error = $this->labels[$id][1];
        Cms::error(sprintf("<label for='%s'>%s</label>: %s", $id, $name, $error));
        $field->parentNode->addClass(self::CSS_WARNING);
        $field->addClass(self::CSS_WARNING);
        $isValid = false;
      }
    }
    if(!$isValid) return null;
    return $values;
  }

  /**
   * @param DOMElementPlus $form
   * @return int
   */
  private function getWaitTime(DOMElementPlus $form) {
    $time = self::WAIT;
    foreach(explode(" ", $form->getAttribute("class")) as $class) {
      if(strpos($class, "validateform-") !== 0) continue;
      $time = (int)substr($class, strlen("validateform-"));
      break;
    }
    return $time;
  }

  /**
   * @param int $time
   * @throws Exception
   */
  private function ipCheck($time) {
    if($time == 0) return;
    $IP = getIP();
    $IPFile = str_replace(":", "-", $IP);
    $IPFilePath = USER_FOLDER."/".$this->pluginDir."/$IPFile";
    $bannedIPFilePath = USER_FOLDER."/".$this->pluginDir."/.$IPFile";
    if(is_file($bannedIPFilePath)) {
      throw new Exception(sprintf(_("Your IP address %s is banned"), $IP));
    }
    if(is_file($IPFilePath)) {
      if(time() - filemtime($IPFilePath) < $time) { // 2 min timeout
        $sec = $time - (time() - filemtime($IPFilePath));
        throw new Exception(sprintf(_("Please wait %s second before next post"), $sec));
      }
    }
    mkdir_plus(dirname($IPFilePath));
    if(!touch($IPFilePath)) throw new Exception(_("Unable to create security file"));
  }

  /**
   * @param DOMElementPlus $e
   * @param string $value
   * @throws Exception
   */
  private function verifyItem(DOMElementPlus $e, $value) {
    $pattern = $e->getAttribute("pattern");
    $req = $e->hasAttribute("required");
    switch($e->nodeName) {
      case "textarea":
      $this->verifyText($value, $pattern, $req, self::TEXTAREA_MAX_LEN);
      break;
      case "input":
      switch($e->getAttribute("type")) {
        case "number":
        $this->verifyText($value, $pattern, $req, self::INPUT_MAX_LEN);
        $min = $e->getAttribute("min");
        $max = $e->getAttribute("max");
        $this->verifyNumber($value, $min, $max);
        break;
        case "email":
        if(!strlen($pattern)) $pattern = EMAIL_PATTERN;
        case "text":
        case "search":
        $this->verifyText($value, $pattern, $req, self::INPUT_MAX_LEN);
        break;
        case "checkbox":
        case "radio":
        if(!is_array($value)) $value = array($value);
        $this->verifyChecked(in_array($e->getAttribute("value"), $value), $req);
        break;
      }
      break;
      case "select":
      try {
        $this->verifySelect($e, $value);
      } catch (Exception $ex) {
        if(!strlen($pattern)) throw $ex;
        $this->verifyText($value, $pattern, $req, self::INPUT_MAX_LEN);
      }
      break;
    }
  }

  /**
   * @param DOMElementPlus $select
   * @param string $value
   * @throws Exception
   */
  private function verifySelect(DOMElementPlus $select, $value) {
    $match = false;
    /** @var DOMElementPlus $option */
    foreach($select->getElementsByTagName("option") as $option) {
      $oVal = $option->hasAttribute("value") ? $option->getAttribute("value") : $option->nodeValue;
      if($oVal == $value) $match = true;
    }
    if(!$match) throw new Exception(_("Select value does not match any option"));
  }

  /**
   * @param mixed $value
   * @param int $min
   * @param int $max
   * @throws Exception
   */
  private function verifyNumber($value, $min, $max) {
    if(strlen($min) && (int)$value < (int)$min) throw new Exception(_("Item value is lower then allowed minimum"));
    if(strlen($max) && (int)$value > (int)$max) throw new Exception(_("Item value is greater then allowed maximum"));
  }

  /**
   * @param mixed $value
   * @param string $pattern
   * @param bool $required
   * @param string $maxlen
   * @throws Exception
   */
  private function verifyText($value, $pattern, $required, $maxlen) {
    if(is_null($value)) throw new Exception(_("Value missing"));
    $length = strlen(trim($value));
    if(!$length) {
      if(!$required) return;
      throw new Exception(_("Item is required"));
    }
    if($length > $maxlen) {
      throw new Exception(_("Maximum value length exceeded"));
    }
    if(!strlen($pattern)) return;
    $res = @preg_match("/^(?:$pattern)$/", $value);
    if($res === false) {
      Logger::user_warning(_("Invalid item pattern"));
      return;
    }
    if($res === 1) return;
    throw new Exception(_("Item value does not match required pattern"));
  }

  /**
   * @param bool $checked
   * @param bool $required
   * @throws Exception
   */
  private function verifyChecked($checked, $required) {
    if(!$required || $checked) return;
    throw new Exception(_("Item must be checked"));
  }

}

?>
