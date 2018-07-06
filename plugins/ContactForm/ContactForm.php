<?php

namespace IGCMS\Plugins;

use DOMXPath;
use Exception;
use IGCMS\Core\Cms;
use IGCMS\Core\DOMDocumentPlus;
use IGCMS\Core\DOMElementPlus;
use IGCMS\Core\HTMLPlus;
use IGCMS\Core\HTMLPlusBuilder;
use IGCMS\Core\Logger;
use IGCMS\Core\ModifyContentStrategyInterface;
use IGCMS\Core\Plugin;
use IGCMS\Core\Plugins;
use PHPMailer;
use SplObserver;
use SplSubject;

/**
 * Class ContactForm
 * @package IGCMS\Plugins
 */
class ContactForm extends Plugin implements SplObserver, ModifyContentStrategyInterface {
  /**
   * @var string
   */
  const FORM_ITEMS_QUERY = "//input | //textarea | //select";
  /**
   * @var bool
   */
  const DEBUG = false;
  /**
   * @var DOMDocumentPlus
   */
  private $cfg;
  /**
   * @var array
   */
  private $vars = [];
  /**
   * @var array
   */
  private $formsElements = [];
  /**
   * @var array
   */
  private $formNames = [];
  /**
   * @var array
   */
  private $formGroupValues = [];
  /**
   * @var array
   */
  private $formIds;
  /**
   * @var array
   */
  private $formVars;
  /**
   * @var array
   */
  private $formValues;
  /**
   * @var array
   */
  private $formItems = [];
  /**
   * @var array
   */
  private $messages;
  /**
   * @var array
   */
  private $forms = [];
  /**
   * @var string|null
   */
  private $prefix = null;

  /**
   * ContactForm constructor.
   * @param Plugins|SplSubject $s
   */
  public function __construct (SplSubject $s) {
    parent::__construct($s);
    $s->setPriority($this, 60);
    $this->prefix = strtolower($this->className);
  }

  /**
   * @param Plugins|SplSubject $subject
   * @throws Exception
   */
  public function update (SplSubject $subject) {
    if (!Cms::isActive()) {
      $subject->detach($this);
      return;
    }
    if ($this->detachIfNotAttached("HtmlOutput")) {
      return;
    }
    if ($this->detachIfNotAttached("ValidateForm")) {
      return;
    }
    switch ($subject->getStatus()) {
      case STATUS_INIT:
        $this->initForm();
        break;
      case STATUS_PROCESS:
        $this->proceedForm();
        break;
    }
  }

  /**
   * @throws Exception
   */
  private function initForm () {
    $this->cfg = self::getXML();
    $this->createGlobalVars();
    foreach ($this->forms as $formId => $form) {
      $form->addClass("fillable");
      $form->addClass("validable");
      $form->addClass("editable");
      $formVar = $this->parseForm($form);
      $this->formsElements[normalize($this->className)."-$formId"] = $formVar;
      Cms::setVariable($formId, $formVar);
    }
  }

  private function createGlobalVars () {
    foreach ($this->cfg->documentElement->childElementsArray as $childElm) {
      try {
        switch ($childElm->nodeName) {
          case "var":
            $idAttr = $childElm->getRequiredAttribute("id");
            $this->vars[$idAttr] = $childElm->nodeValue;
            break;
          case "form":
            $idAttr = $childElm->getRequiredAttribute("id");
            $this->forms[$idAttr] = $childElm;
            break;
          case "message":
            $idAttr = $childElm->getRequiredAttribute("for");
            $this->messages[$idAttr] = $childElm->nodeValue;
            break;
        }
      } catch (Exception $exc) {
        Logger::user_warning(sprintf(_("Skipped element %s: %s"), $childElm->nodeName, $exc->getMessage()));
      }
    }
    if (self::DEBUG) {
      $this->vars["adminaddr"] = "debug@somedomain.cz";
    }
  }

  /**
   * @param DOMElementPlus $form
   * @return DOMDocumentPlus
   * @throws Exception
   */
  private function parseForm (DOMElementPlus $form) {
    $prefix = normalize($this->className);
    $doc = new DOMDocumentPlus();
    $var = $doc->appendChild($doc->createElement("var"));
    /** @var DOMElementPlus $htmlForm */
    $htmlForm = $var->appendChild($doc->importNode($form, true));
    $formId = $htmlForm->getAttribute("id");
    $htmlForm->removeAllAttributes(["id", "class", "action"]);
    $htmlForm->setAttribute("method", "post");
    if (!$htmlForm->hasAttribute("action")) {
      $htmlForm->setAttribute("action", HTMLPlusBuilder::getLinkToId(get_link()));
    }
    $htmlForm->setAttribute("id", "$prefix-$formId");
    $this->registerFormItems($htmlForm, "$prefix-$formId-");
    return $doc;
  }

  /**
   * @param DOMElementPlus $form
   * @param string $prefix
   * @throws Exception
   */
  private function registerFormItems (DOMElementPlus $form, $prefix) {
    $idInput = $form->ownerDocument->createElement("input");
    $idInput->setAttribute("name", $this->className);
    $idInput->setAttribute("type", "hidden");
    $idInput->setAttribute("value", $form->getAttribute("id"));
    $matchElm = null;
    $this->formItems = [];
    $this->formValues = [];
    $this->formNames = [$this->className];
    $this->formIds = [];
    $this->formGroupValues = [];
    $xpath = new DOMXPath($form->ownerDocument);
    /** @var DOMElementPlus $matchElm */
    foreach ($xpath->query(self::FORM_ITEMS_QUERY) as $matchElm) {
      if ($matchElm->nodeName == "textarea") {
        if (!$matchElm->hasAttribute("cols")) {
          $matchElm->setAttribute("cols", 40);
        }
        if (!$matchElm->hasAttribute("rows")) {
          $matchElm->setAttribute("rows", 7);
        }
      }
      $type = null;
      if ($matchElm->nodeName == "input") {
        try {
          $type = $matchElm->getRequiredAttribute("type");
        } catch (Exception $exc) {
          Logger::user_warning($exc->getMessage());
          continue;
        }
      }
      $this->formItems[] = $matchElm;
      $defId = strlen($matchElm->getAttribute("name")) ? normalize($matchElm->getAttribute("name")) : "item";
      $itemId = $this->processFormItem($this->formIds, $matchElm, "id", $prefix, $defId, false);
      $this->processFormItem($this->formNames, $matchElm, "name", "", $itemId, true);
      if (is_null(Cms::getLoggedUser()) || $type != "submit") {
        continue;
      }
      $matchElm->setAttribute("value", _("Show message"));
      $matchElm->setAttribute("title", _("Not sending form if logged user"));
    }
    $tmp = $matchElm->parentNode;
    while (!is_null($tmp) && $tmp->nodeName != "form") {
      if ($tmp->nodeName == "label") {
        $matchElm = $tmp;
        break;
      }
      $tmp = $tmp->parentNode;
    }
    if (is_null($matchElm)) {
      throw new Exception("Form has no input");
    }
    $matchElm->parentNode->appendChild($idInput);
    foreach ($xpath->query("//label") as $matchElm) {
      if ($matchElm->hasAttribute("for")) {
        $for = $prefix.$matchElm->getAttribute("for");
        $matchElm->setAttribute("for", $for);
        $this->formIds[$for][] = $matchElm;
        continue;
      }
      /** @var DOMElementPlus $field */
      foreach ($xpath->query("input | textarea | select", $matchElm) as $field) {
        $this->formIds[$field->getAttribute("id")][] = $matchElm;
      }
    }
    foreach ($this->formItems as $matchElm) {
      $this->completeFormItem($matchElm);
    }
  }
  /** @noinspection PhpTooManyParametersInspection */
  /**
   * @param array $register
   * @param DOMElementPlus $e
   * @param string $aName
   * @param string $prefix
   * @param string $default
   * @param bool $arraySupport
   * @return string
   * @throws Exception
   */
  private function processFormItem (Array &$register, DOMElementPlus $e, $aName, $prefix, $default, $arraySupport) {
    $value = normalize($e->getAttribute($aName), null, null, false); // remove "[]"" and stuff...
    if (!strlen($value)) {
      $value = $default;
    }
    $isCheckbox = $e->nodeName == "input" && $e->getAttribute("type") == "checkbox";
    $isRadio = $e->nodeName == "input" && $e->getAttribute("type") == "radio";
    if (array_key_exists($value, $register) && (!$arraySupport || !($isCheckbox || $isRadio))) {
      $index = 1;
      while (array_key_exists("$value$index", $register)) {
        $index++;
      }
      $value = "$value$index";
    }
    if ($arraySupport && $isCheckbox) {
      $e->setAttribute($aName, $prefix.$value."[]");
    } else {
      $e->setAttribute($aName, $prefix.$value);
    }
    $register[$value] = [];
    return $value;
  }

  /**
   * @param DOMElementPlus $item
   */
  private function completeFormItem (DOMElementPlus $item) {
    switch ($item->nodeName) {
      case "input":
        if (!in_array($item->getAttribute("type"), ["checkbox", "radio"])) {
          break;
        }
        $value = null;
        if (!empty($this->formIds[$item->getAttribute("id")])) {
          $value = trim($this->formIds[$item->getAttribute("id")][0]->nodeValue);
        }
        $this->setUniqueGroupValue($item, $value);
        break;
      case "select":
        foreach ($item->childElementsArray as $optionElm) {
          $this->setUniqueGroupValue($optionElm, $optionElm->nodeValue);
        }
    }
  }

  /**
   * @param DOMElementPlus $element
   * @param string $default
   */
  private function setUniqueGroupValue (DOMElementPlus $element, $default) {
    $name = $element->getAttribute("name");
    $value = $element->getAttribute("value");
    $iter = "";
    if (!strlen($value)) {
      $value = $default;
    }
    if (!isset($this->formGroupValues[$name])) {
      $this->formGroupValues[$name] = [];
    }
    if (is_null($value) || array_key_exists($value, $this->formGroupValues[$name])) {
      $iter = 1;
      while (array_key_exists($value.$iter, $this->formGroupValues[$name])) {
        $iter++;
      }
    }
    $element->setAttribute("value", $value.$iter);
    $this->formGroupValues[$name][$value.$iter] = null;
  }

  /**
   * @throws Exception
   */
  private function proceedForm () {
    $formToSend = null;
    $formIdToSend = null;
    foreach ($this->forms as $formId => $form) {
      $prefixedFormId = normalize($this->className)."-$formId";
      $htmlForm = $this->formsElements[$prefixedFormId]->documentElement->firstElement;
      $formValues = Cms::getVariableValue("validateform-$prefixedFormId");
      $formVars = $this->createFormVars($htmlForm);
      if (isset($_GET["cfok"]) && $_GET["cfok"] == $formId) {
        Logger::user_success($formVars["success"]);
      }
      if (is_null($formValues)) {
        continue;
      }
      foreach (["email", "name", "sendcopy"] as $name) {
        $formVars[$name] = isset($formValues[$name]) ? $formValues[$name] : "";
      }
      $this->formValues = $formValues;
      $this->formVars = $formVars;
      $this->formValues["form_id"] = $formId;
      $formToSend = $form;
      $formIdToSend = $formId;
    }

    if (is_null($formToSend)) {
      return;
    }
    try {
      foreach ($this->formValues as $name => $value) {
        $this->formValues[$name] = [
          "value" => $value,
          "cacheable" => false,
        ];
        if (is_null($value) || !strlen($value)) {
          $this->formValues[$name]["value"] = $this->formVars["nothing"];
        }
      }
      $variables = array_merge($this->formValues, Cms::getAllVariables());
      foreach ($this->formVars as $key => $var) {
        $this->formVars[$key] = replace_vars($var, $variables);
      }
      if (array_key_exists($formIdToSend, $this->messages) && strlen($this->messages[$formIdToSend])) {
        $msg = replace_vars($this->messages[$formIdToSend], $variables);
      } else {
        $msg = $this->createMessage();
      }
      $this->sendForm($formIdToSend, $msg);
      if (self::DEBUG) {
        var_dump($this->formVars);
        var_dump($this->formValues);
      }
    } catch (Exception $exc) {
      $message = sprintf(
        _("Unable to send form %s: %s"),
        "<a href='#".strtolower($this->className)."-".$formIdToSend."'>"
        .$formToSend->getAttribute("id")."</a>",
        $exc->getMessage()
      );
      Logger::user_error($message);
    }
  }

  /**
   * @param DOMElementPlus $form
   * @return array
   */
  private function createFormVars (DOMElementPlus $form) {
    $formVars = [];
    foreach ($this->vars as $name => $value) {
      $formVars[$name] = $value;
      if (!$form->hasAttribute($name)) {
        continue;
      }
      $formVars[$name] = $form->getAttribute($name);
      $form->removeAttribute($name);
    }
    return $formVars;
  }

  /**
   * @return string
   */
  private function createMessage () {
    $msg = [];
    foreach ($this->formValues as $key => $variable) {
      $value = $variable["value"];
      if (is_array($value)) {
        $value = implode(", ", $value);
      }
      $msg[] = "$key: $value";
    }
    return implode("\n", $msg);
  }

  /**
   * @param string $formIdToSend
   * @param string $msg
   * @throws Exception
   */
  private function sendForm ($formIdToSend, $msg) {
    if (!strlen($this->formVars["adminaddr"])) {
      throw new Exception(_("Missing admin address"));
    }
    if (!preg_match("/".EMAIL_PATTERN."/", $this->formVars["adminaddr"])) {
      throw new Exception(sprintf(_("Invalid admin email address: '%s'"), $this->formVars["adminaddr"]));
    }
    if (strlen($this->formVars["email"]) && !preg_match("/".EMAIL_PATTERN."/", $this->formVars["email"])) {
      throw new Exception(sprintf(_("Invalid client email address: '%s'"), $this->formVars["email"]));
    }
    $adminaddr = $this->formVars["adminaddr"];
    $adminname = $this->formVars["adminname"];
    $email = $this->formVars["email"];
    $name = $this->formVars["name"];
    $msg = trim($msg);
    $bcc = $this->formVars["bcc"];
    if (CMS_DEBUG) {
      $adminaddr = "pavelka.iix@gmail.com";
      $adminname = "Jiří Pavelka";
      $bcc = "pavel@petrzela.eu";
    }
    send_mail($adminaddr, $adminname, $email, $name, '', $msg, '', $bcc);
    if (!is_null(Cms::getLoggedUser())) {
      return;
    }
    if (strlen($this->formVars["sendcopy"])) {
      if (!strlen($this->formVars["email"])) {
        throw new Exception(_("Unable to send copy to empty client address"));
      }
      $msg = $this->formVars["copymsg"]."\n\n$msg";
      $bcc = "";
      send_mail($email, $name, $adminaddr, $adminname, $this->formVars["servername"], $msg, $this->formVars["subject"], $bcc);
    }
    redir_to(build_local_url(["path" => get_link(), "query" => "cfok=".$formIdToSend]));
  }

  /**
   * @param HTMLPlus $content
   */
  public function modifyContent (HTMLPlus $content) {
    $xpath = new DOMXPath($content);
    $forms = $xpath->query("//*[contains(@var, '$this->prefix-')]");
    if (!$forms->length) {
      return;
    }
    if (!strlen($this->vars["adminaddr"]) || !preg_match("/".EMAIL_PATTERN."/", $this->vars["adminaddr"])) {
      Logger::user_warning(_("Admin address is not set or invalid"));
    }
    /** @var DOMElementPlus $form */
    foreach ($forms as $form) {
      $varId = substr($form->getAttribute("var"), strlen($this->prefix) + 1);
      if (!array_key_exists($varId, $this->forms)) {
        Logger::user_warning(sprintf(_("Form id '%s' not found"), $varId));
        continue;
      }
      if (!array_key_exists($varId, $this->messages)) {
        Logger::user_warning(sprintf(_("Missing message for form id '%s'"), $varId));
        continue;
      }
    }
  }

}
